@php
    $readinessStatusLabels = $readinessStatusLabels ?? [
        'stopped' => '🔴 يحتاج مراجعة (بند غير جاهز)',
        'partially_ready' => '🔶 جاهز جزئياً',
        'ready' => '✅ جاهز للتنفيذ',
    ];
@endphp

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

<div class="card mb-4 monitoring-status-panel border-{{ $project->workflow_status === 'pending_monitoring_confirmation' ? 'warning' : 'info' }}">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 bg-label-{{ $project->workflow_status === 'pending_monitoring_confirmation' ? 'warning' : 'info' }}">
        <h5 class="mb-0">حالة المراقبة</h5>
        @if ($project->workflow_status === 'pending_monitoring_confirmation')
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
                    <div class="status-metric-value">{{ $project->monitorPerson?->name ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="status-metric">
                    <div class="status-metric-label">تاريخ المراقبة</div>
                    <div class="status-metric-value">{{ $project->monitoring_date?->format('Y-m-d') ?? '—' }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="status-metric">
                    <div class="status-metric-label">طريقة المراقبة</div>
                    <div class="status-metric-value">{{ $project->monitoring_method ?: '—' }}</div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="status-metric">
                    <div class="status-metric-label">مرحلة المراقبة</div>
                    <div class="status-metric-value">{{ $project->monitoring_stage ?: '—' }}</div>
                </div>
            </div>
        </div>

        @if ($project->workflow_status === 'pending_monitoring_confirmation')
            <div class="alert alert-warning py-2 mb-3">
                المراقب <strong>{{ $project->monitorPerson?->name ?? '—' }}</strong> أنهى عمله وأرسل المشروع — بانتظار تأكيد المرور من مدير الرقابة العامة.
            </div>
        @elseif (($canViewMonitorData ?? false) && $project->readiness_status)
            <p class="text-muted small mb-3">
                تقييم الجاهزية: {{ $readinessStatusLabels[$project->readiness_status] ?? $project->readiness_status }}
            </p>
        @endif

        <div class="d-flex flex-wrap align-items-center gap-2">
            @if ($isAssignedMonitor ?? false)
                <a href="{{ route('dashboard.projects.monitor-work', $project) }}" class="btn btn-outline-primary">
                    <i class="fa-solid fa-clipboard-check me-1"></i> شاشة عمل المراقب
                </a>
            @endif

            @if ($project->workflow_status === 'pending_monitoring_confirmation' && ($canConfirmPassageThisProject ?? false))
                <form action="{{ route('dashboard.projects.confirm-passage', $project) }}" method="post" class="d-inline" data-confirm="تأكيد المرور على المشروع وإغلاق دورة المراقبة؟" data-confirm-title="تأكيد المرور" data-confirm-variant="primary">
                    @csrf
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fa-solid fa-circle-check me-1"></i> تأكيد المرور — إتمام المشروع
                    </button>
                </form>
            @endif

            @if ($canRejectThisProject ?? false)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#projectRejectModal">
                    رفض المشروع
                </button>
            @endif
        </div>
    </div>
</div>
