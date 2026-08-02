@php
    $canConvertToSecondary = $linkedProject && auth()->user()?->can('assign_monitor', 'App\Models\MonitoringActivity');
    $isExternal = $activity->isExternal();
    $submitRoute = $isExternal
        ? route('dashboard.external-activities.submit', $activity)
        : route('dashboard.monitoring-activities.submit-to-director', $activity);
@endphp
<x-front-layout>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">النشاط الرقابي {{ $activity->reference_code }}</h4>
            <p class="text-muted mb-0">
                {{ $sourceTypes[$activity->source_type] ?? $activity->source_type }}
                —
                <span class="badge bg-label-{{ match($activity->workflow_status) {
                    'rejected' => 'danger',
                    'completed' => 'success',
                    'pending_confirmation' => 'warning',
                    'in_progress' => 'info',
                    default => 'secondary',
                } }}">{{ $activity->workflow_status_label }}</span>
            </p>
            @if ($activity->submitted_at)
                <p class="text-muted small mb-0">أُرسل بتاريخ: {{ $activity->submitted_at->format('Y-m-d H:i') }}
                    @if ($activity->submittedByUser)
                        — {{ $activity->submittedByUser->name }}
                    @endif
                </p>
            @endif
            @if ($activity->passage_completed_at)
                <p class="text-muted small mb-0">اعتُمد بتاريخ: {{ $activity->passage_completed_at->format('Y-m-d H:i') }}</p>
            @endif
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('dashboard.monitoring-activities.export-pdf', $activity) }}" class="btn btn-outline-danger" target="_blank">
                <i class="bx bx-file-blank"></i> تصدير PDF
            </a>
            <a href="{{ route('dashboard.monitoring-activities.export-excel', $activity) }}" class="btn btn-outline-success">
                <i class="bx bx-spreadsheet"></i> تصدير Excel
            </a>
            @if ($canEditExternal ?? false)
                <a href="{{ $externalEditUrl }}" class="btn btn-outline-primary">تعديل</a>
            @elseif (! $isExternal)
                @can('update', 'App\Models\MonitoringActivity')
                    <a href="{{ route('dashboard.monitoring-activities.edit', $activity) }}" class="btn btn-outline-primary">تعديل</a>
                @endcan
            @endif
            @if ($canMonitorSubmit ?? false)
                <form action="{{ $submitRoute }}" method="post" class="d-inline" data-confirm="إرسال النشاط لمدير الرقابة؟" data-confirm-title="تأكيد الإرسال" data-confirm-variant="primary">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-send"></i> إرسال لمدير الرقابة
                    </button>
                </form>
            @endif
            @if ($linkedProject)
                <a href="{{ route('dashboard.projects.show', $linkedProject) }}" class="btn btn-outline-secondary">عرض المشروع</a>
            @endif
            <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-label-secondary">رجوع</a>
        </div>
    </div>

    @if ($activity->rejection_reason)
        <div class="alert alert-{{ $activity->workflow_status === 'rejected' ? 'danger' : 'warning' }} mb-4">
            <div><strong>{{ $activity->workflow_status === 'rejected' ? 'سبب الرفض:' : 'سبب الإرجاع:' }}</strong> {{ $activity->rejection_reason }}</div>
            <div><strong>مسؤولية النقص:</strong> {{ match($activity->gap_owner) {
                'monitor' => 'المراقب',
                'coordinator' => 'المنسق',
                'dept_manager' => 'مدير الدائرة',
                default => $activity->gap_owner ?? '—',
            } }}</div>
            <div><strong>بواسطة:</strong> {{ $activity->rejectedByUser?->name ?? '-' }}</div>
            <div><strong>بتاريخ:</strong> {{ $activity->rejected_at?->format('Y-m-d H:i') ?? '-' }}</div>
        </div>
    @endif

    @include('dashboard.monitoring-activities._external_review_actions')

    @include('reports.monitoring-activities.show-content', [
        'activity' => $activity,
        'linkedProject' => $linkedProject,
        'sourceTypes' => $sourceTypes,
        'workflowStatusLabels' => $workflowStatusLabels,
        'canViewCoordinatorData' => $canViewCoordinatorData,
        'canViewMonitorData' => $canViewMonitorData,
        'canConvertToSecondary' => $canConvertToSecondary,
        'secondaryActivities' => $secondaryActivities ?? collect(),
    ])
</x-front-layout>
