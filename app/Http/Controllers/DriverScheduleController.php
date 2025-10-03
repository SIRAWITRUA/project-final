<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DriverScheduleController extends Controller
{
    /**
     * ตารางงานรายวันของคนขับ (โหมด list)
     */
    public function index(Request $request)
    {
        // วันเป้าหมาย ตามโซนเวลา
        $tz = 'Asia/Bangkok';
        $date = $request->query('date');
        if (!$date) {
            $date = Carbon::now($tz)->toDateString();
        }

        $employeeId = Auth::guard('employee')->id();

        // โหลดข้อมูลรอบงานของผู้ใช้ตามวัน พร้อมข้อมูลแสดงผลหลัก
        $trips = DB::connection('oracle')
            ->table('mp_trips as t')
            ->join('mp_routes as r', 'r.route_id', '=', 't.route_id')
            ->join('mp_vehicles as v', 'v.vehicle_id', '=', 't.vehicle_id')
            ->join('mp_vehicle_types as vt', 'vt.vehicle_type_id', '=', 'v.vehicle_type_id')
            ->where('t.service_date', '=', $date)
            ->where('t.driver_id', '=', $employeeId)
            ->select(
                't.trip_id',
                't.route_id',
                't.depart_time',
                't.service_date',
                't.round_no',
                't.status',
                't.reserved_seats',
                't.capacity',
                'r.name as route_name',
                'v.license_plate',
                'vt.name as vehicle_type'
            )
            ->orderBy('t.depart_time')
            ->get();

        // โหลดจุดจอดทั้งหมดของเส้นทางที่ต้องใช้ครั้งเดียว
        $routeIds = $trips->pluck('route_id')->unique()->values()->all();
        $placesByRoute = [];
        if (!empty($routeIds)) {
            $routePlacesRows = DB::connection('oracle')
                ->table('mp_route_places as rp')
                ->join('mp_places as p', 'p.place_id', '=', 'rp.place_id')
                ->whereIn('rp.route_id', $routeIds)
                ->select('rp.route_id', 'rp.sequence_no', 'p.name as place_name')
                ->orderBy('rp.route_id')
                ->orderBy('rp.sequence_no')
                ->get();
            foreach ($routePlacesRows as $row) {
                $placesByRoute[$row->route_id][] = $row->place_name;
            }
        }

        // รวมสรุปผู้โดยสารแบบกลุ่มเดียวเพื่อเลี่ยง N+1
        $tripIds = $trips->pluck('trip_id')->values()->all();
        $resvAgg = [];
        if (!empty($tripIds)) {
            $rows = DB::connection('oracle')
                ->table('mp_reservations')
                ->select(
                    'trip_id',
                    DB::raw("SUM(CASE WHEN status = 'completed' THEN seats_reserved ELSE 0 END) as boarded"),
                    DB::raw("SUM(CASE WHEN status = 'active' THEN seats_reserved ELSE 0 END) as active_reserved")
                )
                ->whereIn('trip_id', $tripIds)
                ->groupBy('trip_id')
                ->get();
            foreach ($rows as $row) {
                $resvAgg[$row->trip_id] = [
                    'boarded' => (int) $row->boarded,
                    'active_reserved' => (int) $row->active_reserved,
                ];
            }
        }

        // enrich
        $trips = $trips->map(function ($t) use ($placesByRoute, $resvAgg) {
            $t->places = $placesByRoute[$t->route_id] ?? [];
            $boarded = $resvAgg[$t->trip_id]['boarded'] ?? 0;
            $activeReserved = $resvAgg[$t->trip_id]['active_reserved'] ?? 0;
            $reservedTotal = (int) ($t->reserved_seats ?? ($boarded + $activeReserved));
            $t->stats = (object) [
                'boarded' => $boarded,
                'remaining' => max(0, $reservedTotal - $boarded),
                'free' => max(0, (int) $t->capacity - $reservedTotal),
            ];
            return $t;
        });

        $mode = 'list';
        return view('driver.schedule', compact('trips', 'mode', 'date'));
    }

    public function schedule()
    {
        // Join trips -> routes -> route_places -> places -> vehicles (+ vehicle_types)
        $baseTrips = DB::connection('oracle')
            ->table('mp_trips as t')
            ->join('mp_routes as r', 'r.route_id', '=', 't.route_id')
            ->join('mp_vehicles as v', 'v.vehicle_id', '=', 't.vehicle_id')
            ->join('mp_vehicle_types as vt', 'vt.vehicle_type_id', '=', 'v.vehicle_type_id')
            ->select(
                't.trip_id',
                't.route_id',
                't.depart_time',
                't.service_date',
                't.reserved_seats',
                't.capacity',
                'r.name as route_name',
                'v.license_plate',
                'vt.name as vehicle_type'
            )
            ->orderBy('t.service_date')
            ->orderBy('t.depart_time')
            ->get();

        // Preload places per route (ordered by sequence)
        $routePlacesRows = DB::connection('oracle')
            ->table('mp_route_places as rp')
            ->join('mp_places as p', 'p.place_id', '=', 'rp.place_id')
            ->select('rp.route_id', 'rp.sequence_no', 'p.name as place_name')
            ->orderBy('rp.route_id')
            ->orderBy('rp.sequence_no')
            ->get();

        $placesByRoute = [];
        foreach ($routePlacesRows as $row) {
            $placesByRoute[$row->route_id][] = $row->place_name;
        }

        // คำนวณลำดับรอบ (รอบที่ 1,2,...) ต่อ route+service_date โดยเรียงตามเวลาออก
        $roundByTripId = [];
        $grouped = [];
        foreach ($baseTrips as $row) {
            $key = $row->route_id . '|' . (string) $row->service_date;
            $grouped[$key][] = $row;
        }
        foreach ($grouped as $key => $rows) {
            // เรียงตาม depart_time (HH:MM)
            usort($rows, function ($a, $b) {
                return strcmp((string) $a->depart_time, (string) $b->depart_time);
            });
            foreach ($rows as $i => $row) {
                $roundByTripId[$row->trip_id] = $i + 1;
            }
        }

        // Enrich base trips
        $trips = $baseTrips->map(function ($t) use ($placesByRoute, $roundByTripId) {
            $t->places = $placesByRoute[$t->route_id] ?? [];
            $t->seats_text = ((int) ($t->reserved_seats ?? 0)) . '/' . ((int) ($t->capacity ?? 0));
            $t->passengers = (int) ($t->reserved_seats ?? 0);
            $t->round_no = $roundByTripId[$t->trip_id] ?? null;
            return $t;
        });

        return view('driver.schedule', compact('trips'));
    }

    /**
     * เริ่มงานของรอบที่ระบุ
     */
    public function start($tripId)
    {
        $employeeId = Auth::guard('employee')->id();

        return DB::connection('oracle')->transaction(function () use ($tripId, $employeeId) {
            // ล็อกข้อมูลก่อนเปลี่ยนแปลง
            $trip = DB::connection('oracle')
                ->table('mp_trips')
                ->where('trip_id', $tripId)
                ->lockForUpdate()
                ->first();

            if (!$trip) {
                return redirect()->back()->with('error', 'ไม่พบรอบงาน');
            }
            if ((int) $trip->driver_id !== (int) $employeeId) {
                return redirect()->back()->with('error', 'ไม่สามารถเริ่มงานของรอบนี้');
            }
            if ($trip->status !== 'scheduled') {
                return redirect()->back()->with('error', 'รอบนี้ไม่อยู่ในสถานะที่เริ่มได้');
            }

            DB::connection('oracle')
                ->table('mp_trips')
                ->where('trip_id', $tripId)
                ->update([
                    'status' => 'ongoing',
                    'updated_at' => Carbon::now('Asia/Bangkok'),
                ]);

            return redirect()
                ->route('driver.schedule.show', ['trip' => $tripId])
                ->with('success', 'เริ่มงานสำเร็จ');
        });
    }

    /**
     * หน้าระหว่างวิ่ง (โหมด running)
     */
    public function show($tripId)
    {
        $employeeId = Auth::guard('employee')->id();

        $trip = DB::connection('oracle')
            ->table('mp_trips as t')
            ->join('mp_routes as r', 'r.route_id', '=', 't.route_id')
            ->join('mp_vehicles as v', 'v.vehicle_id', '=', 't.vehicle_id')
            ->join('mp_vehicle_types as vt', 'vt.vehicle_type_id', '=', 'v.vehicle_type_id')
            ->where('t.trip_id', '=', $tripId)
            ->where('t.driver_id', '=', $employeeId)
            ->select(
                't.trip_id',
                't.route_id',
                't.depart_time',
                't.service_date',
                't.round_no',
                't.status',
                't.reserved_seats',
                't.capacity',
                'r.name as route_name',
                'v.license_plate',
                'vt.name as vehicle_type'
            )
            ->first();

        if (!$trip) {
            return redirect()->route('driver.schedule')->with('error', 'ไม่พบรอบงาน');
        }

        // จุดจอด
        $placesRows = DB::connection('oracle')
            ->table('mp_route_places as rp')
            ->join('mp_places as p', 'p.place_id', '=', 'rp.place_id')
            ->where('rp.route_id', '=', $trip->route_id)
            ->select('rp.sequence_no', 'p.place_id', 'p.name as place_name')
            ->orderBy('rp.sequence_no')
            ->get();
        $places = $placesRows->map(fn($x) => (object) ['place_id' => $x->place_id, 'name' => $x->place_name])->all();

        // สรุปจำนวน
        $agg = DB::connection('oracle')
            ->table('mp_reservations')
            ->select(
                DB::raw("SUM(CASE WHEN status = 'completed' THEN seats_reserved ELSE 0 END) as boarded"),
                DB::raw("SUM(CASE WHEN status = 'active' THEN seats_reserved ELSE 0 END) as active_reserved")
            )
            ->where('trip_id', '=', $trip->trip_id)
            ->first();
        $boarded = (int) ($agg->boarded ?? 0);
        $activeReserved = (int) ($agg->active_reserved ?? 0);
        $reservedTotal = (int) ($trip->reserved_seats ?? ($boarded + $activeReserved));
        $stats = (object) [
            'boarded' => $boarded,
            'remaining' => max(0, $reservedTotal - $boarded),
            'free' => max(0, (int) $trip->capacity - $reservedTotal),
        ];

        // สรุปบอร์ดยอดรวมของ reservation (นับจำนวนรายการ ไม่ใช่จำนวนที่นั่ง)
        $resvRows = DB::connection('oracle')
            ->table('mp_reservations')
            ->select('reservation_id', 'status')
            ->where('trip_id', '=', $trip->trip_id)
            ->get();
        $boardSummary = (object) [
            'total' => (int) $resvRows->where('status', 'active')->count(),
            'boarded' => (int) $resvRows->where('status', 'completed')->count(),
            'not_boarded' => (int) $resvRows->where('status', 'active')->count(), // active แต่ยังไม่สแกน
            'cancelled' => (int) $resvRows->where('status', 'cancelled')->count(),
        ];

        $mode = 'running';
        return view('driver.schedule', [
            'trip' => $trip,
            'places' => $places,
            'stats' => $stats,
            'boardSummary' => $boardSummary,
            'mode' => $mode,
        ]);
    }

    /**
     * เช็คอินด้วยโค้ด (โหมด running)
     */
    public function scan(Request $request, $tripId)
    {
        $code = trim((string) ($request->input('code') ?? $request->input('code_text')));
        if ($code === '') {
            return redirect()->back()->with('error', 'กรุณาระบุรหัส');
        }

        // รองรับรูปแบบโค้ดที่เป็น URL/JSON และมีพารามิเตอร์ code
        $rawCode = $code;
        // JSON payload {"code":"..."}
        try {
            $json = json_decode($rawCode, true);
            if (is_array($json) && isset($json['code']) && is_string($json['code'])) {
                $code = trim($json['code']);
            }
        } catch (\Throwable $e) {
        }
        // URL payload with ?code=XYZ or last path segment
        if (preg_match('/^https?:\/\//i', $rawCode)) {
            $parts = parse_url($rawCode);
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $qs);
                if (!empty($qs['code']) && is_string($qs['code'])) {
                    $code = trim($qs['code']);
                }
            }
            if ($code === $rawCode && !empty($parts['path'])) {
                $segments = array_values(array_filter(explode('/', $parts['path'])));
                if (!empty($segments)) {
                    $code = trim(end($segments));
                }
            }
        }

        $employeeId = Auth::guard('employee')->id();

        try {
            return DB::connection('oracle')->transaction(function () use ($tripId, $employeeId, $code) {
                // ล็อกรอบ
                $trip = DB::connection('oracle')
                    ->table('mp_trips')
                    ->where('trip_id', $tripId)
                    ->lockForUpdate()
                    ->first();

                if (!$trip) {
                    return redirect()->back()->with('error', 'ไม่พบรอบงาน');
                }
                if ((int) $trip->driver_id !== (int) $employeeId) {
                    return redirect()->back()->with('error', 'ไม่สามารถเช็คอินในรอบนี้');
                }
                if ($trip->status !== 'ongoing') {
                    return redirect()->back()->with('error', 'รอบนี้ไม่ได้อยู่ระหว่างวิ่ง');
                }

                // หาเรคคอร์ดการจองจากโค้ด (รองรับทั้งฟิลด์รหัสและรหัสตัวเลขของรายการ)
                $reservationQuery = DB::connection('oracle')
                    ->table('mp_reservations')
                    ->where('trip_id', '=', $tripId)
                    ->where(function ($q) use ($code) {
                        $codeTrim = trim($code);
                        if ($codeTrim !== '' && ctype_digit($codeTrim)) {
                            $q->orWhere('reservation_id', '=', (int) $codeTrim);
                        }
                        // เปรียบเทียบแบบตัดช่องว่างหัวท้าย ป้องกัน mismatch
                        $q->orWhereRaw('TRIM(qr_code) = TRIM(?)', [$codeTrim]);
                    })
                    ->lockForUpdate();

                $resv = $reservationQuery->first();
                if (!$resv) {
                    return redirect()->back()->with('error', 'ไม่พบข้อมูลการจองของรอบนี้');
                }
                if ($resv->status === 'cancelled') {
                    return redirect()->back()->with('error', 'รายการนี้ถูกใช้ไปแล้วหรือยกเลิก');
                }
                if ($resv->status === 'completed') {
                    // ทำให้ idempotent: ถ้าสแกนซ้ำถือว่าสำเร็จเหมือนเดิม
                    return redirect()->back()->with('success', 'เช็คอินสำเร็จ');
                }

                // ตรวจสอบจุดขึ้น-ลงให้อยู่บนเส้นทางและทิศทางถูกต้อง
                if (!is_null($resv->origin_place_id) && !is_null($resv->destination_place_id)) {
                    $rp = DB::connection('oracle')
                        ->table('mp_route_places')
                        ->where('route_id', '=', $trip->route_id)
                        ->whereIn('place_id', [$resv->origin_place_id, $resv->destination_place_id])
                        ->select('place_id', 'sequence_no')
                        ->get();
                    $seq = [];
                    foreach ($rp as $r) {
                        $seq[$r->place_id] = (int) $r->sequence_no;
                    }
                    if (!isset($seq[$resv->origin_place_id]) || !isset($seq[$resv->destination_place_id]) || $seq[$resv->origin_place_id] >= $seq[$resv->destination_place_id]) {
                        return redirect()->back()->with('error', 'ข้อมูลจุดขึ้น-ลงไม่ตรงกับรอบ');
                    }
                }

                // อัปเดตสถานะเป็นสำเร็จ
                DB::connection('oracle')
                    ->table('mp_reservations')
                    ->where('reservation_id', '=', $resv->reservation_id)
                    ->update([
                        'status' => 'completed',
                        'updated_at' => Carbon::now('Asia/Bangkok'),
                    ]);

                return redirect()->back()->with('success', 'เช็คอินสำเร็จ');
            });
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'ไม่สามารถเช็คอินได้');
        }
    }

    /**
     * แบบฟอร์มสรุปก่อนปิดงาน (โหมด close)
     */
    public function closeForm($tripId)
    {
        $employeeId = Auth::guard('employee')->id();

        $trip = DB::connection('oracle')
            ->table('mp_trips as t')
            ->join('mp_routes as r', 'r.route_id', '=', 't.route_id')
            ->join('mp_vehicles as v', 'v.vehicle_id', '=', 't.vehicle_id')
            ->join('mp_vehicle_types as vt', 'vt.vehicle_type_id', '=', 'v.vehicle_type_id')
            ->where('t.trip_id', '=', $tripId)
            ->where('t.driver_id', '=', $employeeId)
            ->select(
                't.trip_id', 't.route_id', 't.depart_time', 't.service_date', 't.round_no', 't.status', 't.reserved_seats', 't.capacity',
                'r.name as route_name', 'v.license_plate', 'vt.name as vehicle_type'
            )
            ->first();

        if (!$trip) {
            return redirect()->route('driver.schedule')->with('error', 'ไม่พบรอบงาน');
        }

        // รายการผู้โดยสาร
        $reservations = DB::connection('oracle')
            ->table('mp_reservations as rs')
            ->join('mp_users as u', 'u.user_id', '=', 'rs.user_id')
            ->where('rs.trip_id', '=', $trip->trip_id)
            ->select('rs.reservation_id', 'rs.status', 'rs.seats_reserved', 'u.first_name', 'u.last_name')
            ->orderBy('rs.reservation_id')
            ->get();

        $boarded = $reservations->where('status', 'completed');
        $pending = $reservations->where('status', 'active');
        $boardedCount = (int) $boarded->sum('seats_reserved');
        $pendingCount = (int) $pending->sum('seats_reserved');
        $free = max(0, (int) $trip->capacity - (int) $trip->reserved_seats);

        // บอร์ดยอดรวม (จำนวนรายการตามสถานะ)
        $boardSummary = (object) [
            'total' => (int) $reservations->where('status', 'active')->count(),
            'boarded' => (int) $reservations->where('status', 'completed')->count(),
            'not_boarded' => (int) $reservations->where('status', 'active')->count(),
            'cancelled' => (int) $reservations->where('status', 'cancelled')->count(),
        ];

        $viewOnly = ($trip->status === 'completed');

        $mode = 'close';
        return view('driver.schedule', [
            'trip' => $trip,
            'reservationsBoarded' => $boarded,
            'reservationsPending' => $pending,
            'summary' => (object) [
                'boarded' => $boardedCount,
                'pending' => $pendingCount,
                'free' => $free,
            ],
            'boardSummary' => $boardSummary,
            'viewOnly' => $viewOnly,
            'mode' => $mode,
        ]);
    }

    /**
     * ยืนยันปิดงาน
     */
    public function close(Request $request, $tripId)
    {
        $employeeId = Auth::guard('employee')->id();

        try {
            return DB::connection('oracle')->transaction(function () use ($tripId, $employeeId) {
                $trip = DB::connection('oracle')
                    ->table('mp_trips')
                    ->where('trip_id', $tripId)
                    ->lockForUpdate()
                    ->first();

                if (!$trip) {
                    return redirect()->route('driver.schedule')->with('error', 'ไม่พบรอบงาน');
                }
                if ((int) $trip->driver_id !== (int) $employeeId) {
                    return redirect()->route('driver.schedule')->with('error', 'ไม่สามารถปิดงานของรอบนี้');
                }

                // บันทึกสรุปแบบสั้นไว้ในบันทึก (ถ้ามีฟิลด์)
                $agg = DB::connection('oracle')
                    ->table('mp_reservations')
                    ->select(
                        DB::raw("SUM(CASE WHEN status = 'completed' THEN seats_reserved ELSE 0 END) as boarded"),
                        DB::raw("SUM(CASE WHEN status = 'active' THEN seats_reserved ELSE 0 END) as remaining")
                    )
                    ->where('trip_id', '=', $tripId)
                    ->first();
                $note = 'summary: boarded=' . ((int) ($agg->boarded ?? 0)) . ', remaining=' . ((int) ($agg->remaining ?? 0));

                DB::connection('oracle')
                    ->table('mp_trips')
                    ->where('trip_id', $tripId)
                    ->update([
                        'status' => 'completed',
                        'notes' => $note,
                        'updated_at' => Carbon::now('Asia/Bangkok'),
                    ]);

                return redirect()->route('driver.schedule')->with('success', 'ยืนยันปิดงานสำเร็จ');
            });
        } catch (\Throwable $e) {
            return redirect()->route('driver.schedule')->with('error', 'ไม่สามารถปิดงานได้');
        }
    }
}
