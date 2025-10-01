@extends('layouts.driver-schedule')

@section('content')
    <div class="container my-4">
        <h3 class="fw-bold mb-4">ตารางงานคนขับ</h3>

        @forelse ($trips as $trip)
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- หัวการ์ด -->
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-2">
                        <h6 class="mb-0 route-header-title">
                            <span class="route-badge"><i class="bi bi-geo-alt-fill me-1"></i> เส้นทาง</span>
                            <span class="route-name">{{ $trip->route_name ?? 'Route #' . $trip->route_id }}</span>
                        </h6>
                        <button class="btn btn-success btn-sm">รับงาน</button>
                    </div>

                    <!-- เส้นทาง bubble: places[] -->
                    <div class="route-flow-wrapper">
                        <p class="route-flow">
                            @if (!empty($trip->places))
                                @foreach ($trip->places as $idx => $p)
                                    <span class="route-chip">{{ $p }}</span>
                                    @if ($idx < count($trip->places) - 1)
                                        <span class="route-sep">→</span>
                                    @endif
                                @endforeach
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </p>
                    </div>

                    <hr class="my-3">

                    <!-- รายละเอียดที่ผู้ใช้ต้องการ: เวลาออก (ชั่วโมง:นาที), รอบที่, ประเภทรถ(ทะเบียนรถ), จำนวนผู้โดยสาร -->
                    <div class="row g-3 align-items-center">
                        <div class="col-6 col-sm-4 col-md-3">
                            <div class="depart-time-badge">
                                <div class="depart-label"><i class="bi bi-clock"></i> เวลาออก</div>
                                <div class="depart-time">{{ $trip->depart_time }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 col-md-3">
                            <div class="text-muted small">รอบที่</div>
                            <div class="fw-semibold">{{ $trip->round_no ?? '-' }}</div>
                        </div>

                        <div class="col-12 col-sm-5 col-md-4">
                            <div class="text-muted small">ประเภทรถ (ทะเบียน)</div>
                            <div class="fw-semibold">
                                {{ ($trip->vehicle_type ?? '-') . ' (' . ($trip->license_plate ?? '-') . ')' }}</div>
                        </div>
                        <div class="col-12 col-sm-3 col-md-2">
                            <div class="text-muted small">ผู้โดยสาร</div>
                            <div class="fw-semibold">{{ $trip->passengers ?? ($trip->reserved_seats ?? 0) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-muted text-center py-4">ไม่มีรอบงาน</div>
        @endforelse
    </div>
@endsection

@push('styles')
    <style>
        .route-flow-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .route-flow {
            white-space: nowrap;
        }

        .route-chip {
            display: inline-block;
            padding: .375rem .75rem;
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 999px;
            background: #fff;
            margin: 0 .25rem;
            font-weight: 500;
        }

        .route-sep {
            margin: 0 .25rem;
            color: #6c757d;
        }

        .route-header-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-weight: 800;
        }

        .route-badge {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .25rem .5rem;
            border-radius: 999px;
            background: rgba(22, 100, 163, .08);
            color: #1682a3;
            border: 1px solid rgba(22, 78, 163, .18);
            font-size: .9rem;
            font-weight: 700;
        }

        .route-name {
            font-size: 1.1rem;
            font-weight: 800;
            color: #0f172a;
        }

        .depart-time-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .35rem .6rem;
            border-radius: 999px;
            border: 1px solid rgba(22, 163, 74, .15);
            background: rgba(22, 163, 74, .06);
        }

        .depart-time-badge .depart-label {
            color: #16a34a;
            font-weight: 700;
            font-size: .85rem;
        }

        .depart-time-badge .depart-time {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
        }
    </style>
@endpush
