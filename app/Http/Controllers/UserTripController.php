<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MpPlace;
use App\Models\MpRoute;
use App\Models\MpRoutePlace;
use App\Models\MpTrip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserTripController extends Controller
{
    public function searchTripListPage()
    {
        $request = request();

        // สถานที่ทั้งหมดสำหรับ dropdown
        $places = MpPlace::orderBy('name')->get(['place_id', 'name']);

        $originId = $request->query('origin');
        $destId = $request->query('destination');
        $dateParam = $request->query('date'); // YYYY-MM-DD

        // โซนเวลาเอเชีย/กรุงเทพ
        $nowBkk = Carbon::now('Asia/Bangkok');
        $date = $dateParam ?: $nowBkk->toDateString();
        $isToday = $date === $nowBkk->toDateString();
        $cutoffTime = $isToday ? $nowBkk->copy()->addMinutes(20)->format('H:i') : null; // HH:MM

        $results = [];

        // เงื่อนไขพื้นฐานของ trips ตามวัน/สถานะ/ที่นั่งเหลือ และ cutoff สำหรับวันนี้
        $baseQuery = MpTrip::query()
            ->with(['vehicle.type', 'route'])
            ->whereRaw("TRUNC(mp_trips.service_date) = TO_DATE(?, 'YYYY-MM-DD')", [$date])
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->whereRaw('mp_trips.capacity > NVL(mp_trips.reserved_seats, 0)');

        if ($cutoffTime) {
            $baseQuery->where('mp_trips.depart_time', '>=', $cutoffTime);
        }

        if ($originId && $destId && $originId != $destId) {
            // โหมดค้นหา O->D: join route_places เป็น rp1,rp2 และบังคับลำดับ
            $query = (clone $baseQuery)
                ->select('mp_trips.*', 'rp1.sequence_no as origin_seq', 'rp2.sequence_no as dest_seq')
                ->join('mp_route_places as rp1', function ($join) use ($originId) {
                    $join->on('rp1.route_id', '=', 'mp_trips.route_id')
                         ->where('rp1.place_id', '=', $originId);
                })
                ->join('mp_route_places as rp2', function ($join) use ($destId) {
                    $join->on('rp2.route_id', '=', 'mp_trips.route_id')
                         ->where('rp2.place_id', '=', $destId);
                })
                ->whereColumn('rp1.sequence_no', '<', 'rp2.sequence_no');

            // เรียงตามรอบ แล้วเวลาออก (ถ้ามีคอลัมน์ round_no)
            if (Schema::hasColumn('mp_trips', 'round_no')) {
                $query->orderBy('mp_trips.round_no');
            }
            $trips = $query->orderBy('mp_trips.depart_time')->get();

            foreach ($trips as $trip) {
                $originSeq = (int) ($trip->origin_seq ?? 0);
                $destSeq = (int) ($trip->dest_seq ?? 0);

                // ดึงรายชื่อจุดแวะระหว่าง origin..destination
                $stops = MpRoutePlace::with('place')
                    ->where('route_id', $trip->route_id)
                    ->whereBetween('sequence_no', [$originSeq, $destSeq])
                    ->orderBy('sequence_no')
                    ->get();

                $stopNames = $stops->map(fn($rp) => $rp->place?->name ?? '');

                // เวลารวมโดยประมาณ: รวม duration ของช่วง (origin_seq, dest_seq]
                $estimatedMinutes = MpRoutePlace::where('route_id', $trip->route_id)
                    ->where('sequence_no', '>', $originSeq)
                    ->where('sequence_no', '<=', $destSeq)
                    ->sum('duration_min');

                // สร้าง vehicle text
                $vehicleText = null;
                $typeName = $trip->vehicle?->type?->name ?? null;
                $plate = $trip->vehicle?->license_plate ?? null;
                if ($typeName && $plate) {
                    $vehicleText = sprintf('%s (%s)', $typeName, $plate);
                } elseif ($plate) {
                    $vehicleText = sprintf('(%s)', $plate);
                }

                $results[] = [
                    'trip_id' => $trip->trip_id,
                    'route_id' => $trip->route_id,
                    'route_name' => $trip->route?->name,
                    'places' => $stopNames->values()->all(),
                    'estimated_minutes' => (int) $estimatedMinutes,
                    'depart_time' => $trip->depart_time,
                    'status' => $trip->status,
                    'vehicle_text' => $vehicleText,
                    'round_no' => $trip->round_no,
                    'seats' => [
                        'reserved' => (int) ($trip->reserved_seats ?? 0),
                        'capacity' => (int) ($trip->capacity ?? 0),
                        'left' => max(0, (int)($trip->capacity ?? 0) - (int)($trip->reserved_seats ?? 0)),
                    ],
                ];
            }
        } else {
            // โหมด "แสดงทั้งหมด": แสดงเฉพาะทริปที่พร้อมจอง (วันนี้ที่ยังไม่ถึงเวลา +20 นาที และอนาคต)
            $todayStr = $nowBkk->toDateString();
            $allQuery = MpTrip::query()
                ->with(['vehicle.type', 'route'])
                ->whereIn('status', ['scheduled', 'ongoing'])
                ->whereRaw("TRUNC(mp_trips.service_date) >= TO_DATE(?, 'YYYY-MM-DD')", [$todayStr]);

            if (Schema::hasColumn('mp_trips', 'round_no')) {
                $allQuery->orderBy('mp_trips.service_date')
                         ->orderBy('mp_trips.round_no');
            } else {
                $allQuery->orderBy('mp_trips.service_date');
            }
            $trips = $allQuery->orderBy('mp_trips.depart_time')->get();

            foreach ($trips as $trip) {
                // ดึงทุกจุดของเส้นทางตามลำดับ
                $stops = MpRoutePlace::with('place')
                    ->where('route_id', $trip->route_id)
                    ->orderBy('sequence_no')
                    ->get();

                $stopNames = $stops->map(fn($rp) => $rp->place?->name ?? '');

                // เวลารวมทั้งเส้นทาง (ใช้ของทริปถ้ามี snapshot), fallback = รวมจาก route_places
                $estimatedMinutes = $trip->estimated_minutes ?? MpRoutePlace::where('route_id', $trip->route_id)
                    ->sum('duration_min');

                // สร้าง vehicle text
                $vehicleText = null;
                $typeName = $trip->vehicle?->type?->name ?? null;
                $plate = $trip->vehicle?->license_plate ?? null;
                if ($typeName && $plate) {
                    $vehicleText = sprintf('%s (%s)', $typeName, $plate);
                } elseif ($plate) {
                    $vehicleText = sprintf('(%s)', $plate);
                }

                $results[] = [
                    'trip_id' => $trip->trip_id,
                    'route_id' => $trip->route_id,
                    'route_name' => $trip->route?->name,
                    'places' => $stopNames->values()->all(),
                    'estimated_minutes' => (int) $estimatedMinutes,
                    'depart_time' => $trip->depart_time,
                    'status' => $trip->status,
                    'vehicle_text' => $vehicleText,
                    'round_no' => $trip->round_no,
                    'seats' => [
                        'reserved' => (int) ($trip->reserved_seats ?? 0),
                        'capacity' => (int) ($trip->capacity ?? 0),
                        'left' => max(0, (int)($trip->capacity ?? 0) - (int)($trip->reserved_seats ?? 0)),
                    ],
                ];
            }
        }

        return view('reservation.search-trip-list', [
            'places' => $places,
            'results' => $results,
            'originId' => $originId,
            'destId' => $destId,
            'date' => $date,
        ]);
    }
}
