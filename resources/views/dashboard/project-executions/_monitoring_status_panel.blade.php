<div class="card mb-4 monitoring-status-panel border-{{ $execution->workflow_status === 'pending_monitoring_confirmation' ? 'warning' : 'info' }}">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 bg-label-{{ $execution->workflow_status === 'pending_monitoring_confirmation' ? 'warning' : 'info' }}">
        <h5 class="mb-0">حالة المراقبة — {{ $execution->region_name }}</h5>
        @if ($execution->workflow_status === 'pending_monitoring_confirmation')
            <span class="badge bg-warning">بانتظار تأكيد المرور</span>
        @else
            <span class="badge bg-info">قيد التنفيذ</span>
        @endif
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6 col-lg-3">
                <div class="status-metric">
                    <div class="status-metric-label">المراقب المعيّن</div>
                    <div class="status-metric-value">{{ $execution->monitorPerson?->name ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="status-metric">
                    <div class="status-metric-label">تاريخ المراقبة</div>
                    <div class="status-metric-value">{{ $execution->monitoring_date?->format('Y-m-d') ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="status-metric">
                    <div class="status-metric-label">طريقة المراقبة</div>
                    <div class="status-metric-value">{{ $execution->monitoring_method ?: '—' }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="status-metric">
                    <div class="status-metric-label">مرحلة المراقبة</div>
                    <div class="status-metric-value">{{ $execution->monitoring_stage ?: '—' }}</div>
                </div>
            </div>
        </div>

        @if ($execution->workflow_status === 'pending_monitoring_confirmation')
            <div class="alert alert-warning py-2 mb-3">
                المراقب <strong>{{ $execution->monitorPerson?->name ?? '—' }}</strong> أنهى عمله وأرسل المسار — بانتظار تأكيد المرور من مدير الرقابة العامة.
            </div>
        @endif

        <div class="d-flex flex-wrap align-items-center gap-2">
            @if ($isAssignedMonitor ?? false)
                <a href="{{ route('dashboard.projects.executions.monitor-work', [$project, $execution]) }}" class="btn btn-outline-primary">
                    <i class="fa-solid fa-clipboard-check me-1"></i> شاشة عمل المراقب
                </a>
            @endif

            @if ($execution->workflow_status === 'pending_monitoring_confirmation' && ($canConfirmPassageExecution ?? false))
                <form action="{{ route('dashboard.projects.executions.confirm-passage', [$project, $execution]) }}" method="post" class="d-inline" data-confirm="تأكيد المرور على المسار وإغلاق دورة المراقبة؟" data-confirm-title="تأكيد المرور" data-confirm-variant="primary">
                    @csrf
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fa-solid fa-circle-check me-1"></i> تأكيد المرور — إتمام المسار
                    </button>
                </form>
            @endif

            @if ($canRejectExecution ?? false)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#executionRejectModal">
                    رفض المسار
                </button>
            @endif
        </div>
    </div>
</div>

@once
    @push('styles')
    <style>
        .monitoring-status-panel .status-metric {
            border: 1px solid rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            background: rgba(67, 89, 113, 0.02);
            height: 100%;
        }

        .monitoring-status-panel .status-metric-label {
            font-size: 0.75rem;
            color: rgba(67, 89, 113, 0.65);
            margin-bottom: 0.25rem;
        }

        .monitoring-status-panel .status-metric-value {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--bs-body-color);
        }
    </style>
    @endpush
@endonce
