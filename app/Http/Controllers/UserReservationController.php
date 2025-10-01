<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\MpTrip;
use App\Models\MpRoutePlace;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\CancelReservationRequest;

class UserReservationController extends Controller
{
    private const MAX_SEATS_PER_USER_PER_TRIP = 4; // จำกัดสูงสุด 4 ที่นั่งต่อรอบต่อผู้ใช้

    // ตรวจสอบว่ารอบพร้อมจองหรือไม่ ตามกติกาเวลา/สถานะ/ที่นั่ง
    private function isTripBookable(MpTrip $trip, Carbon $nowBkk, int $minLeadMinutes = 20): bool
    {
        if (($trip->status ?? 'scheduled') !== 'scheduled') {
            return false;
        }
        $capacity = (int) ($trip->capacity ?? 0);
        $reserved = (int) ($trip->reserved_seats ?? 0);
        if ($capacity <= $reserved) {
            return false;
        }
        $todayStr = $nowBkk->toDateString();
        if ($trip->service_date->toDateString() === $todayStr) {
            $cutoff = $nowBkk->copy()->addMinutes($minLeadMinutes)->format('H:i');
            return strcmp($trip->depart_time, $cutoff) >= 0;
        }
        // อนาคตจองได้, อดีตไม่ได้
        return strcmp($trip->service_date->toDateString(), $todayStr) > 0;
    }

    // แสดงข้อมูลก่อนสร้างการจอง (ใช้ตรวจสอบความพร้อม)
    // หมายเหตุ: ฟังก์ชัน preview/confirm ด้วยหน้าเว็บควรอยู่ใน view แยก หากต้องการ

    // แสดงฟอร์มสร้างการจองอย่างง่าย (รับ trip_id ผ่าน query)
    public function createForm(Request $request)
    {
        // รองรับทั้ง query และ old input (หลัง redirect back)
        $tripId = $request->query('trip_id') ?: $request->old('trip_id');
        $originId = $request->query('origin') ?: $request->old('origin_place_id');
        $destId = $request->query('destination') ?: $request->old('destination_place_id');

        $trip = null;
        $routePlaces = collect();
        if ($tripId) {
            $trip = MpTrip::with(['route', 'vehicle.type'])->find($tripId);
            if ($trip) {
                $routePlaces = MpRoutePlace::with('place')
                    ->where('route_id', $trip->route_id)
                    ->orderBy('sequence_no')
                    ->get();
            }
        }
        return view('reservation.create', [
            'trip' => $trip,
            'routePlaces' => $routePlaces,
            'originId' => $originId,
            'destId' => $destId,
        ]);
    }

    // บันทึกการจอง
    public function store(StoreReservationRequest $request)
    {
        $userId = Auth::id();
        $data = $request->validated();

        $requestedSeats = (int) ($data['seats'] ?? 1);
        if ($requestedSeats > self::MAX_SEATS_PER_USER_PER_TRIP) {
            $msg = 'ไม่อนุญาตให้จองเกิน ' . self::MAX_SEATS_PER_USER_PER_TRIP . ' ที่นั่งต่อรอบ';
            return back()->withErrors(['seats' => $msg])->withInput();
        }

        try {
            $result = DB::transaction(function () use ($data, $userId, $requestedSeats) {
                // ล็อกแถวของ trip เพื่ออัปเดตที่นั่งแบบอะตอมมิก
                $trip = MpTrip::where('trip_id', $data['trip_id'])->lockForUpdate()->firstOrFail();
                $nowBkk = Carbon::now('Asia/Bangkok');
                if (!$this->isTripBookable($trip, $nowBkk)) {
                    abort(422, 'รอบนี้ไม่พร้อมให้จอง');
                }

                // ตรวจไม่ให้จองซ้ำรอบเดียวกัน (active)
                $dupActive = DB::table('mp_reservations')
                    ->where('trip_id', $trip->trip_id)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->exists();
                if ($dupActive) {
                    abort(422, 'คุณมีการจองรอบนี้อยู่แล้ว');
                }

                // จำกัดตามผู้ใช้ต่อรอบ (สูงสุด 4)

                // ตรวจ origin/destination ถ้ามี ให้เป็นเส้นทางเดียวกันและลำดับถูกต้อง
                $originId = $data['origin_place_id'] ?? null;
                $destId = $data['destination_place_id'] ?? null;
                if ($originId && $destId) {
                    $rp1 = MpRoutePlace::where('route_id', $trip->route_id)->where('place_id', $originId)->first();
                    $rp2 = MpRoutePlace::where('route_id', $trip->route_id)->where('place_id', $destId)->first();
                    if (!$rp1 || !$rp2 || (int) $rp1->sequence_no >= (int) $rp2->sequence_no) {
                        abort(422, 'เส้นทางหรือจุดขึ้น–ลงไม่ถูกต้อง');
                    }
                }

                // ตรวจที่นั่งคงเหลือ
                $capacity = (int) ($trip->capacity ?? 0);
                $reserved = (int) ($trip->reserved_seats ?? 0);
                $left = $capacity - $reserved;
                if ($requestedSeats > $left) {
                    abort(422, 'ที่นั่งไม่เพียงพอ');
                }

                // กรณีมีรายการที่ยกเลิกไปแล้ว (cancelled) สำหรับ trip+user เดิม ให้ทำการ "คืนสถานะ" แทนการ insert เพื่อเลี่ยง unique key (trip_id, user_id)
                $existingAny = DB::table('mp_reservations')
                    ->where('trip_id', $trip->trip_id)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                $qrToken = Str::uuid()->toString();

                if ($existingAny && $existingAny->status === 'cancelled') {
                    // อัปเดตรายการเดิมให้กลับมาเป็น active พร้อมอัปเดตจุดขึ้น–ลง และเพิ่มที่นั่งกลับ
                    DB::table('mp_reservations')
                        ->where('reservation_id', $existingAny->reservation_id)
                        ->update([
                            'origin_place_id' => $originId,
                            'destination_place_id' => $destId,
                            'status' => 'active',
                            'qr_code' => $qrToken,
                            // หากมีคอลัมน์ seats_reserved ให้บันทึกจำนวนที่นั่งของการจองครั้งนี้
                            // ใน Oracle หากไม่มีคอลัมน์ดังกล่าว บรรทัดนี้จะไม่ทำงาน ซึ่งเราจะพึ่งการเพิ่ม reserved_seats ใน mp_trips
                            'seats_reserved' => $requestedSeats,
                            'updated_at' => now(),
                        ]);

                    // เพิ่มที่นั่งที่ถูกใช้กลับเข้าไป
                    MpTrip::where('trip_id', $trip->trip_id)
                        ->update(['reserved_seats' => DB::raw('reserved_seats + ' . (int) $requestedSeats)]);

                    return ['reservation_id' => $existingAny->reservation_id, 'qr_token' => $qrToken];
                }

                if ($existingAny && $existingAny->status !== 'cancelled') {
                    // completed หรือสถานะอื่น ๆ ที่ยังอยู่ในแถวเดียวกัน ทำให้ unique ขวางการ insert
                    abort(422, 'ไม่สามารถจองรอบนี้ซ้ำได้');
                }

                // สร้างการจองใหม่ + อัปเดตที่นั่ง
                // Oracle needs explicit returning column name when the PK isn't the default 'id'
                $reservationId = DB::table('mp_reservations')->insertGetId([
                    'trip_id' => $trip->trip_id,
                    'user_id' => $userId,
                    'origin_place_id' => $originId,
                    'destination_place_id' => $destId,
                    'status' => 'active',
                    'qr_code' => $qrToken,
                    // หากมีคอลัมน์ seats_reserved ให้บันทึกไว้ เพื่อรองรับการคืนที่นั่งตอนยกเลิก
                    'seats_reserved' => $requestedSeats,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'reservation_id');

                MpTrip::where('trip_id', $trip->trip_id)
                    ->update(['reserved_seats' => DB::raw('reserved_seats + ' . (int) $requestedSeats)]);

                return ['reservation_id' => $reservationId, 'qr_token' => $qrToken];
            });
        } catch (\Throwable $e) {
            $message = $e->getMessage() ?: 'ไม่สามารถทำรายการได้';
            return back()->withErrors(['reservation' => $message])->withInput();
        }

    return redirect()->route('reservation.history')->with('success', 'จองสำเร็จ')->with('reservation_success', $result);
    }

    // ยกเลิกการจอง และคืนที่นั่งแบบอะตอมมิก
    public function cancel(CancelReservationRequest $request)
    {
        $userId = Auth::id();
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $userId) {
                $res = DB::table('mp_reservations')
                    ->where('reservation_id', $data['reservation_id'])
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$res) {
                    abort(404, 'ไม่พบรายการจอง');
                }
                if (!in_array($res->status, ['active'])) {
                    abort(422, 'รายการนี้ไม่สามารถยกเลิกได้');
                }

                // ล็อก trip และคืนที่นั่ง
                $trip = MpTrip::where('trip_id', $res->trip_id)->lockForUpdate()->first();
                if ($trip) {
                    // ใช้จำนวนที่นั่งของรายการ ถ้ามีคอลัมน์ seats_reserved ในตาราง; มิฉะนั้นคืน 1 ที่นั่ง
                    $refundVal = (isset($res->seats_reserved) && is_numeric($res->seats_reserved)) ? (int) $res->seats_reserved : 1;
                    $refund = max(0, $refundVal);
                    $expr = 'CASE WHEN reserved_seats - ' . $refund . ' < 0 THEN 0 ELSE reserved_seats - ' . $refund . ' END';
                    MpTrip::where('trip_id', $trip->trip_id)
                        ->update(['reserved_seats' => DB::raw($expr)]);
                }

                DB::table('mp_reservations')
                    ->where('reservation_id', $res->reservation_id)
                    ->update([
                        'status' => 'cancelled',
                        'updated_at' => now(),
                    ]);
            });
        } catch (\Throwable $e) {
            $message = $e->getMessage() ?: 'ยกเลิกไม่สำเร็จ';
            return back()->withErrors(['cancel' => $message]);
        }

        return back()->with('success', 'ยกเลิกสำเร็จ')->with('reservation_canceled', true);
    }

    // รายการที่จะมาถึง
    public function myUpcoming(Request $request)
    {
        $userId = Auth::id();
        $nowBkk = Carbon::now('Asia/Bangkok');
        $today = $nowBkk->toDateString();
        $cutoff = $nowBkk->copy()->addMinutes(20)->format('H:i');

        $query = DB::table('mp_reservations as r')
            ->join('mp_trips as t', 't.trip_id', '=', 'r.trip_id')
            ->leftJoin('mp_routes as rt', 'rt.route_id', '=', 't.route_id')
            ->leftJoin('mp_places as po', 'po.place_id', '=', 'r.origin_place_id')
            ->leftJoin('mp_places as pd', 'pd.place_id', '=', 'r.destination_place_id')
            ->where('r.user_id', $userId)
            ->where('r.status', 'active')
            ->where('t.status', 'scheduled')
            ->where(function ($q) use ($today, $cutoff) {
                $q->whereDate('t.service_date', '>', $today)
                  ->orWhere(function ($q2) use ($today, $cutoff) {
                      $q2->whereDate('t.service_date', $today)
                         ->where('t.depart_time', '>=', $cutoff);
                  });
            })
            ->orderBy('t.service_date')
            ->orderBy('t.round_no')
            ->orderBy('t.depart_time')
            ->select('r.*', 't.service_date', 't.depart_time', 't.round_no', 'rt.name as route_name', 'po.name as origin_name', 'pd.name as dest_name');
        $rows = $query->paginate(10)->withQueryString();
        return view('reservation.upcoming', compact('rows'));
    }

    // ประวัติ (ที่ผ่านมา และที่ยกเลิก)
    public function myHistory(Request $request)
    {
        $userId = Auth::id();
        $nowBkk = Carbon::now('Asia/Bangkok');
        $today = $nowBkk->toDateString();
        $nowHHMM = $nowBkk->format('H:i');

        // Upcoming (cancelable) section: active reservations for scheduled trips, future or today with depart_time >= now+20m
        $cutoff = $nowBkk->copy()->addMinutes(20)->format('H:i');
        $upcomingQuery = DB::table('mp_reservations as r')
            ->join('mp_trips as t', 't.trip_id', '=', 'r.trip_id')
            ->leftJoin('mp_routes as rt', 'rt.route_id', '=', 't.route_id')
            ->leftJoin('mp_places as po', 'po.place_id', '=', 'r.origin_place_id')
            ->leftJoin('mp_places as pd', 'pd.place_id', '=', 'r.destination_place_id')
            ->where('r.user_id', $userId)
            ->where('r.status', 'active')
            ->where('t.status', 'scheduled')
            ->where(function ($q) use ($today, $cutoff) {
                $q->whereDate('t.service_date', '>', $today)
                  ->orWhere(function ($q2) use ($today, $cutoff) {
                      $q2->whereDate('t.service_date', $today)
                         ->where('t.depart_time', '>=', $cutoff);
                  });
            })
            ->orderBy('t.service_date')
            ->orderBy('t.round_no')
            ->orderBy('t.depart_time')
            ->select(
                'r.*', 't.service_date', 't.depart_time', 't.round_no', 't.status as trip_status',
                'rt.name as route_name', 'po.name as origin_name', 'pd.name as dest_name'
            );
        $upcomingRows = $upcomingQuery->paginate(10, ['*'], 'upcoming_page')->withQueryString();

        // History section: cancelled or completed, or trips in the past
        $historyQuery = DB::table('mp_reservations as r')
            ->join('mp_trips as t', 't.trip_id', '=', 'r.trip_id')
            ->leftJoin('mp_routes as rt', 'rt.route_id', '=', 't.route_id')
            ->leftJoin('mp_places as po', 'po.place_id', '=', 'r.origin_place_id')
            ->leftJoin('mp_places as pd', 'pd.place_id', '=', 'r.destination_place_id')
            ->where('r.user_id', $userId)
            ->where(function ($q) use ($today, $nowHHMM) {
                $q->whereIn('r.status', ['cancelled','completed'])
                  ->orWhere(function ($q2) use ($today, $nowHHMM) {
                      $q2->whereDate('t.service_date', '<', $today)
                         ->orWhere(function ($q3) use ($today, $nowHHMM) {
                             $q3->whereDate('t.service_date', $today)
                                ->where('t.depart_time', '<', $nowHHMM);
                         });
                  });
            })
            ->orderByDesc('t.service_date')
            ->orderByDesc('t.depart_time')
            ->select(
                'r.*', 't.service_date', 't.depart_time', 't.round_no', 't.status as trip_status',
                'rt.name as route_name', 'po.name as origin_name', 'pd.name as dest_name'
            );
        $historyRows = $historyQuery->paginate(10, ['*'], 'history_page')->withQueryString();

        return view('reservation.history', compact('upcomingRows', 'historyRows'));
    }
}
