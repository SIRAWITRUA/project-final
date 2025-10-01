@extends('layouts.user-auth')


@section('content')
    <!-- ฟอร์มค้นหา -->
    <div class="card shadow-soft">
        <div class="card-body">
            <h5 class="card-title mb-2">ค้นหาเส้นทางรถ</h5>
            <p class="text-muted mb-4">เลือกจุดขึ้น-ลงรถ เพื่อค้นหาเส้นทางที่เหมาะสม</p>

            <form action="{{ route('reservation.search-trip-list') }}" method="GET">
                <div class="row gy-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">จุดขึ้นรถ</label>
                        <select class="form-select" name="origin">
                            <option value="">-- เลือกจุดขึ้นรถ --</option>
                            @isset($places)
                                @foreach ($places as $p)
                                    <option value="{{ $p->place_id }}"
                                        {{ (string) $originId === (string) $p->place_id ? 'selected' : '' }}>
                                        {{ $p->name }}
                                    </option>
                                @endforeach
                            @endisset
                        </select>
                    </div>

                    <div class="col-md-2 d-flex flex-column align-items-center justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="swapOriginDest">
                            <i class="ti ti-arrows-left-right me-1"></i>สลับ
                        </button>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">จุดลงรถ</label>
                        <select class="form-select" name="destination">
                            <option value="">-- เลือกจุดลงรถ --</option>
                            @isset($places)
                                @foreach ($places as $p)
                                    <option value="{{ $p->place_id }}"
                                        {{ (string) $destId === (string) $p->place_id ? 'selected' : '' }}>
                                        {{ $p->name }}
                                    </option>
                                @endforeach
                            @endisset
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">วันที่เดินรถ</label>
                        <input type="date" class="form-control" name="date" value="{{ $date ?? '' }}">
                    </div>

                    <div class="col-md-8 d-flex justify-content-end gap-2">
                        <a href="{{ route('reservation.search-trip-list') }}" class="btn btn-light">ดูเส้นทางทั้งหมด</a>
                        <button type="submit" class="btn btn-primary">ค้นหาเส้นทาง</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ผลลัพธ์การค้นหา -->
    <div class="mt-4">
        <h6 class="mb-3">
            @if (empty($originId) && empty($destId))
                รอบที่พร้อมจอง
            @else
                ผลการค้นหา
            @endif
        </h6>

        @if (!empty($results))
            @foreach ($results as $i => $r)
                <div class="card shadow-soft border mb-3">
                    <div class="card-body">
                        <!-- หัวการ์ด -->
                        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-2">
                            <h6 class="mb-0 route-header-title">
                                <span class="route-badge"><i class="ti ti-map-pin me-1"></i>เส้นทาง</span>
                                <span class="route-name">{{ $r['route_name'] ?? '#' . $r['route_id'] }}</span>
                            </h6>
                            <div class="d-flex align-items-center gap-2 ms-auto">
                                <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#routeDetail{{ $i }}" aria-expanded="false"
                                    aria-controls="routeDetail{{ $i }}">
                                    แสดงรายละเอียด
                                </button>
                                <a class="btn btn-primary btn-sm"
                                    href="{{ route('reservation.create', ['trip_id' => $r['trip_id'] ?? null, 'origin' => $originId, 'destination' => $destId]) }}">เลือกเส้นทางนี้</a>
                            </div>
                        </div>

                        <!-- แสดงลำดับจุดแวะ -->
                        <div class="route-flow-wrapper">
                            <p class="text-muted mb-0 route-flow" aria-label="เส้นทาง">
                                @foreach ($r['places'] as $idx => $name)
                                    <span class="route-chip">{{ $name }}</span>
                                    @if ($idx < count($r['places']) - 1)
                                        <span class="route-sep">→</span>
                                    @endif
                                @endforeach
                            </p>
                        </div>

                        <hr class="my-3">

                        <!-- เมตริกสรุป -->
                        <div class="row g-3 align-items-center">
                            <div class="col-6 col-sm-4 col-md-3">
                                <div class="depart-time-badge">
                                    <div class="depart-label">
                                        <i class="ti ti-clock-hour-9 me-1"></i>เวลาออก
                                    </div>
                                    <div class="depart-time">{{ $r['depart_time'] ?? '--:--' }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-2 col-md-2">
                                <div class="text-muted small">รอบที่</div>
                                <div class="fw-semibold">{{ $r['round_no'] ?? '—' }}</div>
                            </div>
                            <div class="col-6 col-sm-3 col-md-3">
                                <div class="text-muted small">ประเภทรถ</div>
                                <div class="fw-semibold">{{ $r['vehicle_text'] ?? '—' }}</div>
                            </div>
                            <div class="col-6 col-sm-3 col-md-4">
                                <div class="text-muted small">จำนวนที่นั่ง</div>
                                <div class="fw-semibold">
                                    @if (!empty($r['seats']))
                                        {{ $r['seats']['reserved'] }}/{{ $r['seats']['capacity'] }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- รายละเอียดย่อย -->
                        <div id="routeDetail{{ $i }}" class="collapse mt-3">
                            <div class="p-3 bg-light rounded-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="fw-semibold mb-2">สรุปข้อมูล</div>
                                        <ul class="list-unstyled mb-0 small">
                                            <li class="mb-1">เวลารวมโดยประมาณ: <strong>{{ $r['estimated_minutes'] ?? 0 }}
                                                    นาที</strong></li>
                                            <li class="mb-1">จุดขึ้นรถ: <strong>{{ $r['places'][0] ?? '-' }}</strong>
                                            </li>
                                            <li class="mb-1">จุดลงรถ:
                                                <strong>{{ $r['places'][count($r['places']) - 1] ?? '-' }}</strong>
                                            </li>
                                            <li class="mb-1">จำนวนจุดแวะ: <strong>{{ count($r['places']) }}</strong></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="fw-semibold mb-2">ลำดับจุดแวะ (ย่อ)</div>
                                        <p class="text-muted mb-0">
                                            @foreach ($r['places'] as $idx => $name)
                                                <span class="badge text-bg-light border">{{ $name }}</span>
                                                @if ($idx < count($r['places']) - 1)
                                                    <span class="text-muted">→</span>
                                                @endif
                                            @endforeach
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /รายละเอียดย่อย -->
                    </div>
                </div>
            @endforeach
        @else
            @if (empty($originId) && empty($destId))
                <div class="text-muted">ยังไม่มีข้อมูลเส้นทาง</div>
            @else
                <div class="text-muted">กรุณาเลือกจุดขึ้นรถและจุดลงรถ เพื่อค้นหาเส้นทาง</div>
            @endif
        @endif
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
            display: inline-block;
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
            white-space: nowrap;
        }

        .depart-time-badge .depart-time {
            font-size: 1.35rem;
            line-height: 1;
            font-weight: 800;
            color: #0f172a;
        }

        @media (max-width:576px) {
            .depart-time-badge {
                padding: .3rem .5rem;
                gap: .4rem;
            }

            .depart-time-badge .depart-label {
                font-size: .8rem;
            }

            .depart-time-badge .depart-time {
                font-size: 1.2rem;
            }

            .depart-time-badge .depart-label .ti {
                display: none;
            }
        }

        @media (min-width:768px) {
            .depart-time-badge {
                padding: .45rem .7rem;
            }

            .depart-time-badge .depart-time {
                font-size: 1.5rem;
            }
        }

        .card .btn {
            white-space: nowrap;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const originSel = document.querySelector('select[name="origin"]');
            const destSel = document.querySelector('select[name="destination"]');
            const swapBtn = document.getElementById('swapOriginDest');
            if (!originSel || !destSel) return;

            function syncDisable(source, target) {
                const val = source.value;
                Array.from(target.options).forEach(opt => {
                    if (opt.value !== '') opt.disabled = false;
                });
                if (val) {
                    const same = Array.from(target.options).find(o => o.value === val);
                    if (same) {
                        same.disabled = true;
                        if (target.value === val) target.value = '';
                    }
                }
            }

            syncDisable(originSel, destSel);
            syncDisable(destSel, originSel);
            originSel.addEventListener('change', () => syncDisable(originSel, destSel));
            destSel.addEventListener('change', () => syncDisable(destSel, originSel));

            if (swapBtn) {
                swapBtn.addEventListener('click', function() {
                    const o = originSel.value;
                    originSel.value = destSel.value;
                    destSel.value = o;
                    // re-apply disabling after swap
                    syncDisable(originSel, destSel);
                    syncDisable(destSel, originSel);
                });
            }
        });
    </script>
@endpush
