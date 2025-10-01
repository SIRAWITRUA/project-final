<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverScheduleController extends Controller
{
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
}
