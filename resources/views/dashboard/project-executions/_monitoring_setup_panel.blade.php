@if ($canManageMonitoringSetup ?? false)
<div class="card mb-4 monitoring-setup-panel">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">إعداد المراقبة — {{ $execution->region_name }}</h5>
        <span class="badge bg-label-primary">مدير الرقابة العامة</span>
    </div>
    <div class="card-body">
        <form action="{{ route('dashboard.projects.executions.assign-monitor', [$project, $execution]) }}" method="post">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label" for="monitoring_method">طريقة المراقبة <span class="text-danger">*</span></label>
                    <select name="monitoring_method" id="monitoring_method" class="form-select @error('monitoring_method') is-invalid @enderror" required>
                        <option value="">إختر القيمة</option>
                        @foreach ($monitoringMethods as $method)
                            <option value="{{ $method }}" @selected(old('monitoring_method', $execution->monitoring_method) === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                    @error('monitoring_method')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label" for="monitoring_stage">مرحلة المراقبة <span class="text-danger">*</span></label>
                    <select name="monitoring_stage" id="monitoring_stage" class="form-select @error('monitoring_stage') is-invalid @enderror" required>
                        <option value="">إختر القيمة</option>
                        @foreach ($monitoringStages as $stage)
                            <option value="{{ $stage }}" @selected(old('monitoring_stage', $execution->monitoring_stage) === $stage)>{{ $stage }}</option>
                        @endforeach
                    </select>
                    @error('monitoring_stage')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label" for="monitor_person_id">المراقب <span class="text-danger">*</span></label>
                    <select name="monitor_person_id" id="monitor_person_id" class="form-select select2-searchable @error('monitor_person_id') is-invalid @enderror" required>
                        <option value="">إختر القيمة</option>
                        @foreach ($monitors as $person)
                            <option value="{{ $person->id }}" @selected(old('monitor_person_id', $execution->monitor_person_id) == $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                    @error('monitor_person_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label" for="monitoring_date">تاريخ المراقبة <span class="text-danger">*</span></label>
                    <input
                        type="date"
                        name="monitoring_date"
                        id="monitoring_date"
                        class="form-control @error('monitoring_date') is-invalid @enderror"
                        value="{{ old('monitoring_date', $defaultMonitoringDate ?? $execution->monitoring_date?->format('Y-m-d')) }}"
                        required
                    >
                    @error('monitoring_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-user-check me-1"></i> حفظ وبدء المراقبة
                    </button>
                </div>
            </div>
        </form>

        @if ($canRejectExecution ?? false)
            <div class="border-top pt-3 mt-3">
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#executionRejectModal">
                    رفض المسار
                </button>
            </div>
        @endif
    </div>
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('css/searchable-select.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/vendor/libs/select2/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/searchable-select.js') }}"></script>
    @endpush
@endonce
@endif
