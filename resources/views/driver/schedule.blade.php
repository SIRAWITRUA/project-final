@extends('layouts.employee-auth')

@section('content')
    <div class="container my-4">
        @php($mode = $mode ?? 'list')

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($mode === 'list')
            <h3 class="fw-bold mb-3">ตารางงานคนขับ</h3>

            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label class="form-label">เลือกวัน</label>
                    <input type="date" class="form-control" name="date" value="{{ $date ?? '' }}" />
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">แสดง</button>
                </div>
            </form>

            @forelse ($trips as $trip)
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-2">
                            <h6 class="mb-0 route-header-title">
                                <span class="route-badge"><i class="bi bi-geo-alt-fill me-1"></i> เส้นทาง</span>
                                <span class="route-name">{{ $trip->route_name ?? ('Route #' . $trip->route_id) }}</span>
                            </h6>
                            <div class="d-flex gap-2">
                                @if ($trip->status === 'scheduled')
                                    <form method="POST" action="{{ route('driver.schedule.start', $trip->trip_id) }}">
                                        @csrf
                                        <button class="btn btn-success btn-sm">เริ่มงาน</button>
                                    </form>
                                @elseif ($trip->status === 'ongoing')
                                    <a class="btn btn-warning btn-sm"
                                       href="{{ route('driver.schedule.show', $trip->trip_id) }}">ไปยังรอบนี้</a>
                                @elseif ($trip->status === 'completed')
                                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('driver.schedule.close.form', $trip->trip_id) }}">ดูสรุป</a>
                                @else
                                    <span class="badge bg-light text-dark">-</span>
                                @endif
                            </div>
                        </div>

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
                                <div class="fw-semibold">{{ ($trip->vehicle_type ?? '-') . ' (' . ($trip->license_plate ?? '-') . ')' }}</div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-2">
                                <div class="text-muted small">จำนวน</div>
                                <div class="fw-semibold">ขึ้นแล้ว {{ $trip->stats->boarded ?? 0 }} | ยังไม่ขึ้น {{ $trip->stats->remaining ?? 0 }} | ว่าง {{ $trip->stats->free ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-muted text-center py-4">ไม่มีรอบงาน</div>
            @endforelse
        @elseif ($mode === 'running')
            <h3 class="fw-bold mb-3">ระหว่างวิ่ง</h3>

            <div class="row g-3">
                <div class="col-12 col-lg-7">
                    <div class="card shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <div class="depart-time-badge mb-2">
                                        <div class="depart-label"><i class="bi bi-clock"></i> เวลาออก</div>
                                        <div class="depart-time">{{ $trip->depart_time }}</div>
                                    </div>
                                    <div class="fw-bold">{{ $trip->route_name ?? ('Route #' . $trip->route_id) }}</div>
                                    <div class="text-muted small">รอบที่ {{ $trip->round_no ?? '-' }} | {{ ($trip->vehicle_type ?? '-') . ' (' . ($trip->license_plate ?? '-') . ')' }}</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#scanModal">สแกน QR Code</button>
                                    <a class="btn btn-danger" href="{{ route('driver.schedule.close.form', $trip->trip_id) }}">ปิดงาน</a>
                                </div>
                            </div>
                            <div class="text-muted">ขึ้นแล้ว {{ $stats->boarded ?? 0 }} | ยังไม่ขึ้น {{ $stats->remaining ?? 0 }} | ว่าง {{ $stats->free ?? 0 }}</div>
                        </div>
                    </div>

                    <!-- บอร์ดยอดสรุปผู้โดยสาร -->
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body d-flex flex-column align-items-start">
                                    <div class="text-muted small mb-1"><i class="bi bi-list-ul me-1"></i>ทั้งหมด</div>
                                    <div class="fs-4 fw-bold text-secondary">{{ $boardSummary->total ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body d-flex flex-column align-items-start">
                                    <div class="text-muted small mb-1"><i class="bi bi-check-circle-fill text-success me-1"></i>ขึ้นแล้ว</div>
                                    <div class="fs-4 fw-bold text-success">{{ $boardSummary->boarded ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body d-flex flex-column align-items-start">
                                    <div class="text-muted small mb-1"><i class="bi bi-clock-fill text-warning me-1"></i>ยังไม่ขึ้น</div>
                                    <div class="fs-4 fw-bold text-warning">{{ $boardSummary->not_boarded ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body d-flex flex-column align-items-start">
                                    <div class="text-muted small mb-1"><i class="bi bi-x-circle-fill text-danger me-1"></i>ยกเลิก</div>
                                    <div class="fs-4 fw-bold text-danger">{{ $boardSummary->cancelled ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="fw-bold mb-2">ขึ้นแล้ว</div>
                                    <div class="display-6">{{ $stats->boarded ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="fw-bold mb-2">ยังไม่ขึ้น</div>
                                    <div class="display-6">{{ $stats->remaining ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="fw-bold mb-2">ว่าง</div>
                                    <div class="display-6">{{ $stats->free ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="fw-bold mb-2">จุดจอดตามลำดับ</div>
                            <ol class="timeline-list">
                                @foreach ($places as $p)
                                    <li class="timeline-item">{{ $p->name }}</li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal สแกน QR -->
            <div class="modal fade" id="scanModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">สแกน QR Code</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="qr-reader" class="mb-3"></div>

                            <form method="POST" action="{{ route('driver.schedule.scan', $trip->trip_id) }}" id="scanForm">
                                @csrf
                                <input type="hidden" name="code" id="scanCode" />
                                <div class="mb-2">
                                    <label class="form-label">กรอกรหัสด้วยตนเอง</label>
                                    <input type="text" class="form-control" name="code_text" id="scanCodeText" placeholder="ระบุรหัส" />
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary">ยืนยัน</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($mode === 'close')
            <h3 class="fw-bold mb-3">สรุปก่อนปิดงาน</h3>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="mb-1 fw-bold">{{ $trip->route_name ?? ('Route #' . $trip->route_id) }}</div>
                    <div class="text-muted small mb-2">รอบที่ {{ $trip->round_no ?? '-' }} | เวลา {{ $trip->depart_time }} | {{ ($trip->vehicle_type ?? '-') . ' (' . ($trip->license_plate ?? '-') . ')' }}</div>
                    <div>ขึ้นแล้ว {{ $summary->boarded ?? 0 }} | ยังไม่ขึ้น {{ $summary->pending ?? 0 }} | ว่าง {{ $summary->free ?? 0 }}</div>
                </div>
            </div>

            @if (($summary->pending ?? 0) > 0)
                <div class="alert alert-warning">ยังมีผู้โดยสารที่ยังไม่สแกน แต่สามารถปิดงานได้</div>
            @endif

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="fw-bold mb-2">รายชื่อ - ขึ้นแล้ว</div>
                            <ul class="list-group list-group-flush">
                                @forelse ($reservationsBoarded as $r)
                                    <li class="list-group-item">{{ $r->first_name }} {{ $r->last_name }} ({{ $r->seats_reserved }})</li>
                                @empty
                                    <li class="list-group-item text-muted">-</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="fw-bold mb-2">รายชื่อ - ยังไม่ขึ้น</div>
                            <ul class="list-group list-group-flush">
                                @forelse ($reservationsPending as $r)
                                    <li class="list-group-item">{{ $r->first_name }} {{ $r->last_name }} ({{ $r->seats_reserved }})</li>
                                @empty
                                    <li class="list-group-item text-muted">-</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- บอร์ดยอดสรุป (แสดงซ้ำในหน้า close ด้วย) -->
            <div class="row g-3 mt-3 mb-2">
                <div class="col-6 col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column align-items-start">
                            <div class="text-muted small mb-1"><i class="bi bi-list-ul me-1"></i>ทั้งหมด</div>
                            <div class="fs-4 fw-bold text-secondary">{{ $boardSummary->total ?? 0 }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column align-items-start">
                            <div class="text-muted small mb-1"><i class="bi bi-check-circle-fill text-success me-1"></i>ขึ้นแล้ว</div>
                            <div class="fs-4 fw-bold text-success">{{ $boardSummary->boarded ?? 0 }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column align-items-start">
                            <div class="text-muted small mb-1"><i class="bi bi-clock-fill text-warning me-1"></i>ยังไม่ขึ้น</div>
                            <div class="fs-4 fw-bold text-warning">{{ $boardSummary->not_boarded ?? 0 }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column align-items-start">
                            <div class="text-muted small mb-1"><i class="bi bi-x-circle-fill text-danger me-1"></i>ยกเลิก</div>
                            <div class="fs-4 fw-bold text-danger">{{ $boardSummary->cancelled ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @if (empty($viewOnly))
                <form method="POST" action="{{ route('driver.schedule.close', $trip->trip_id) }}" class="mt-2">
                    @csrf
                    <button class="btn btn-danger">ยืนยันปิดงาน</button>
                    <a href="{{ route('driver.schedule.show', $trip->trip_id) }}" class="btn btn-secondary ms-2">ย้อนกลับ</a>
                </form>
            @else
                <a href="{{ route('driver.schedule') }}" class="btn btn-secondary mt-2">กลับไปตารางงาน</a>
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

        .timeline-list {
            margin: 0;
            padding-left: 1.2rem;
        }
        .timeline-item {
            margin-bottom: .5rem;
        }
    </style>
@endpush

@push('scripts')
    @if (($mode ?? '') === 'running')
        <script src="https://unpkg.com/html5-qrcode"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('scanModal');
                if (!modal) return;
                let html5QrcodeScanner; let started = false;

                modal.addEventListener('shown.bs.modal', async () => {
                    if (started) return;
                    const el = document.getElementById('qr-reader');
                    if (!el) return;
                    html5QrcodeScanner = new Html5Qrcode('qr-reader');
                    const config = { fps: 10, qrbox: 200 };

                    const onDecoded = (decodedText) => {
                        const inputHidden = document.getElementById('scanCode');
                        inputHidden.value = decodedText;
                        document.getElementById('scanForm').submit();
                        const bsModal = bootstrap.Modal.getInstance(modal);
                        bsModal?.hide();
                    };

                    // Try to start with the environment-facing (back) camera first
                    try {
                        await html5QrcodeScanner.start({ facingMode: 'environment' }, config, onDecoded);
                        started = true;
                        return;
                    } catch (e) {
                        // Fallback to picking a camera that looks like back/rear or the first available
                        try {
                            const cameras = await Html5Qrcode.getCameras();
                            let camId;
                            if (cameras && cameras.length) {
                                const prefer = cameras.find(c => /back|rear|environment/i.test(c.label || '')) || cameras[0];
                                camId = prefer?.id;
                            }
                            await html5QrcodeScanner.start(camId, config, onDecoded);
                            started = true;
                        } catch (e2) {
                            // Give up silently; UI remains usable for manual input
                        }
                    }
                });

                modal.addEventListener('hidden.bs.modal', () => {
                    if (html5QrcodeScanner) {
                        html5QrcodeScanner.stop().catch(() => {}).then(() => {
                            html5QrcodeScanner.clear();
                        });
                    }
                });
            });
        </script>
    @endif
@endpush
