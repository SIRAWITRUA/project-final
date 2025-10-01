@extends('layouts.user-auth')

@section('content')
<div class="container py-3">
  <h5 class="mb-3">จองรอบรถ</h5>
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('reservation.store') }}" method="POST" class="card p-3">
    @csrf
    @if(!empty($trip))
    <div class="mb-3">
      <div class="card border-0 shadow-sm p-3">
        <div class="row g-3 align-items-center small">
          <div class="col-12 col-md-4">
            <div class="text-muted">เส้นทาง</div>
            <div class="fw-semibold d-flex align-items-center gap-1"><i class="ti ti-map-pin text-muted"></i>{{ $trip->route->name ?? ('#'.$trip->route_id) }}</div>
          </div>
          <div class="col-6 col-md-4">
            <div class="text-muted">วันที่-เวลาออก</div>
            <div class="fw-semibold d-flex align-items-center gap-1"><i class="ti ti-clock text-muted"></i>{{ optional($trip->service_date)->format('Y-m-d') }} {{ $trip->depart_time }}</div>
          </div>
          <div class="col-6 col-md-2">
            <div class="text-muted">รอบที่</div>
            <div class="fw-semibold d-flex align-items-center gap-1"><i class="ti ti-list-numbers text-muted"></i>{{ $trip->round_no ?? '—' }}</div>
          </div>
          <div class="col-6 col-md-2">
            <div class="text-muted">ที่นั่งคงเหลือ</div>
            <div class="fw-semibold d-flex align-items-center gap-1">
              <i class="ti ti-armchair text-muted"></i>
              @php $left = max(0, (int)($trip->capacity ?? 0) - (int)($trip->reserved_seats ?? 0)); @endphp
              {{ $left }}
            </div>
          </div>
          <div class="col-12">
            <div class="text-muted">รถ</div>
            <div class="fw-semibold d-flex align-items-center gap-1">
              <i class="ti ti-bus text-muted"></i>
              @php
                $typeName = $trip->vehicle->type->name ?? null;
                $plate = $trip->vehicle->license_plate ?? null;
              @endphp
              {{ $typeName ? ($typeName . ($plate ? ' ('.$plate.')' : '')) : ($plate ? '(' . $plate . ')' : '—') }}
            </div>
          </div>
        </div>
      </div>
      <input type="hidden" name="trip_id" value="{{ $trip->trip_id }}">
    </div>
    @endif
    @if(!empty($trip))
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">จุดขึ้น</label>
  <select class="form-select" name="origin_place_id" required>
          <option value="">-- เลือกจุดขึ้น --</option>
          @if(!empty($routePlaces) && $routePlaces->count())
            @foreach($routePlaces as $rp)
              <option value="{{ $rp->place_id }}" {{ (string)old('origin_place_id', $originId ?? '') === (string)$rp->place_id ? 'selected' : '' }}>
                {{ $rp->place->name ?? ('#'.$rp->place_id) }}
              </option>
            @endforeach
          @else
            <option value="" disabled>ไม่มีข้อมูลจุดในเส้นทางนี้</option>
          @endif
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">จุดลง</label>
  <select class="form-select" name="destination_place_id" required>
          <option value="">-- เลือกจุดลง --</option>
          @if(!empty($routePlaces) && $routePlaces->count())
            @foreach($routePlaces as $rp)
              <option value="{{ $rp->place_id }}" {{ (string)old('destination_place_id', $destId ?? '') === (string)$rp->place_id ? 'selected' : '' }}>
                {{ $rp->place->name ?? ('#'.$rp->place_id) }}
              </option>
            @endforeach
          @else
            <option value="" disabled>ไม่มีข้อมูลจุดในเส้นทางนี้</option>
          @endif
        </select>
      </div>
    </div>
    @else
      <div class="alert alert-warning">กรุณาระบุ Trip ID ที่ถูกต้องเพื่อเลือกจุดขึ้น–ลง</div>
    @endif
    <div class="mt-3">
      <label class="form-label">จำนวนที่นั่ง</label>
      <input type="number" class="form-control" name="seats" min="1" max="4" step="1" value="{{ old('seats', 1) }}">
      <div class="form-text">จำกัดสูงสุด 4 ที่นั่งต่อรอบ</div>
    </div>
    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-light" type="button" onclick="window.history.back()">ย้อนกลับ</button>
      <button class="btn btn-primary" type="submit">ยืนยันการจอง</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const originSel = document.querySelector('select[name="origin_place_id"]');
    const destSel = document.querySelector('select[name="destination_place_id"]');

    if (!originSel || !destSel) return;

    function syncDisable(source, target) {
      const val = source.value;
      // Enable all first (except placeholder with empty value)
      Array.from(target.options).forEach(opt => {
        if (opt.value !== '') opt.disabled = false;
      });
      if (val) {
        const same = Array.from(target.options).find(o => o.value === val);
        if (same) {
          same.disabled = true;
          // If currently selected equals disabled one, reset to placeholder
          if (target.value === val) {
            target.value = '';
          }
        }
      }
    }

    // Initial sync and event listeners
    syncDisable(originSel, destSel);
    syncDisable(destSel, originSel);

    originSel.addEventListener('change', () => {
      syncDisable(originSel, destSel);
    });
    destSel.addEventListener('change', () => {
      syncDisable(destSel, originSel);
    });
  });
</script>
@endpush
