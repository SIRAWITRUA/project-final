@extends('layouts.user-auth')

@section('content')
    <div class="container py-3">
        <h5 class="mb-3">ประวัติการจอง</h5>
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        {{-- Upcoming (Cancelable) --}}
        <h6 class="mb-2">กำลังจะมาถึง</h6>
        @if (($upcomingRows->count() ?? 0) === 0)
            <div class="text-muted mb-3">ไม่มีรายการที่จะมาถึง</div>
        @else
            <div class="list-group mb-3">
                @foreach ($upcomingRows as $r)
                    @php $modalId = 'resvDetailUpcoming'.$r->reservation_id; @endphp
                    <div class="list-group-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <img alt="QR" width="72" height="72" class="border rounded"
                                    src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($r->qr_code ?? '') }}" />
                            </div>
                            <div class="col">
                                <div class="fw-semibold">{{ $r->route_name ?? '—' }}</div>
                                <div class="text-muted small">{{ \Carbon\Carbon::parse($r->service_date)->format('Y-m-d') }}
                                    {{ $r->depart_time }}</div>
                                <div class="text-muted small">จาก: {{ $r->origin_name ?? '—' }} → ถึง:
                                    {{ $r->dest_name ?? '—' }}</div>
                                <div class="text-muted small">ที่นั่ง: {{ (int)($r->seats_reserved ?? 1) }}</div>
                            </div>
                            <div class="col-auto d-flex align-items-center gap-2">
                                <span class="badge bg-success">ยกเลิกได้</span>
                                <form action="{{ route('reservation.cancel') }}" method="POST"
                                    onsubmit="return confirm('ยืนยันยกเลิก?')">
                                    @csrf
                                    <input type="hidden" name="reservation_id" value="{{ $r->reservation_id }}">
                                    <button class="btn btn-outline-danger btn-sm" type="submit">ยกเลิก</button>
                                </form>
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#{{ $modalId }}">รายละเอียด</button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title">รายละเอียดการจอง #{{ $r->reservation_id }}</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="d-flex gap-3">
                                        <img alt="QR" width="140" height="140" class="border rounded"
                                            src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($r->qr_code ?? '') }}" />
                                        <div class="small">
                                            <div><span class="text-muted">เส้นทาง:</span>
                                                <strong>{{ $r->route_name ?? '—' }}</strong></div>
                                            <div><span class="text-muted">วันที่-เวลาออก:</span>
                                                <strong>{{ \Carbon\Carbon::parse($r->service_date)->format('Y-m-d') }}
                                                    {{ $r->depart_time }}</strong></div>
                                            <div><span class="text-muted">รอบที่:</span>
                                                <strong>{{ $r->round_no ?? '—' }}</strong></div>
                                            <div><span class="text-muted">จาก:</span>
                                                <strong>{{ $r->origin_name ?? '—' }}</strong></div>
                                            <div><span class="text-muted">ถึง:</span>
                                                <strong>{{ $r->dest_name ?? '—' }}</strong></div>
                                            <div><span class="text-muted">จำนวนที่นั่ง:</span>
                                                <strong>{{ (int)($r->seats_reserved ?? 1) }}</strong></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ปิด</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2">{{ $upcomingRows->links() }}</div>
        @endif

        {{-- History --}}
        <h6 class="mb-2 mt-4">ประวัติ</h6>
        @if (($historyRows->count() ?? 0) === 0)
            <div class="text-muted">ยังไม่มีประวัติ</div>
        @else
            <div class="list-group">
                @foreach ($historyRows as $r)
                    @php $modalId = 'resvDetailHistory'.$r->reservation_id; @endphp
                    <div class="list-group-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <img alt="QR" width="72" height="72" class="border rounded"
                                    src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($r->qr_code ?? '') }}" />
                            </div>
                            <div class="col">
                                <div class="fw-semibold">{{ $r->route_name ?? '—' }}</div>
                                <div class="text-muted small">
                                    {{ \Carbon\Carbon::parse($r->service_date)->format('Y-m-d') }} {{ $r->depart_time }}
                                </div>
                                <div class="text-muted small">จาก: {{ $r->origin_name ?? '—' }} → ถึง:
                                    {{ $r->dest_name ?? '—' }}</div>
                                <div class="text-muted small">ที่นั่ง: {{ (int)($r->seats_reserved ?? 1) }}</div>
                            </div>
                            <div class="col-auto d-flex align-items-center gap-2">
                                @php
                                    $statusLabel =
                                        $r->status === 'cancelled'
                                            ? 'ยกเลิกแล้ว'
                                            : ($r->status === 'completed'
                                                ? 'เสร็จสิ้น'
                                                : 'หมดเวลา');
                                    $statusClass =
                                        $r->status === 'cancelled'
                                            ? 'bg-danger'
                                            : ($r->status === 'completed'
                                                ? 'bg-success'
                                                : 'bg-secondary');
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#{{ $modalId }}">รายละเอียด</button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title">รายละเอียดการจอง #{{ $r->reservation_id }}</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="d-flex gap-3">
                                        <img alt="QR" width="140" height="140" class="border rounded"
                                            src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($r->qr_code ?? '') }}" />
                                        <div class="small">
                                            <div><span class="text-muted">เส้นทาง:</span>
                                                <strong>{{ $r->route_name ?? '—' }}</strong></div>
                                            <div><span class="text-muted">วันที่-เวลาออก:</span>
                                                <strong>{{ \Carbon\Carbon::parse($r->service_date)->format('Y-m-d') }}
                                                    {{ $r->depart_time }}</strong></div>
                                            <div><span class="text-muted">รอบที่:</span>
                                                <strong>{{ $r->round_no ?? '—' }}</strong></div>
                                            <div><span class="text-muted">จาก:</span>
                                                <strong>{{ $r->origin_name ?? '—' }}</strong></div>
                                            <div><span class="text-muted">ถึง:</span>
                                                <strong>{{ $r->dest_name ?? '—' }}</strong></div>
                                            <div><span class="text-muted">สถานะ:</span>
                                                <strong>{{ $r->status }}</strong></div>
                                            <div><span class="text-muted">จำนวนที่นั่ง:</span>
                                                <strong>{{ (int)($r->seats_reserved ?? 1) }}</strong></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ปิด</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2">{{ $historyRows->links() }}</div>
        @endif
    </div>
@endsection
