@php
    $canConvertToSecondary = $linkedProject && auth()->user()?->can('assign_monitor', 'App\Models\MonitoringActivity');
@endphp
<x-front-layout>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">النشاط الرقابي {{ $activity->reference_code }}</h4>
            <p class="text-muted mb-0">{{ $sourceTypes[$activity->source_type] ?? $activity->source_type }} — {{ $activity->workflow_status_label }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('dashboard.monitoring-activities.export-pdf', $activity) }}" class="btn btn-outline-danger" target="_blank">
                <i class="bx bx-file-blank"></i> تصدير PDF
            </a>
            <a href="{{ route('dashboard.monitoring-activities.export-excel', $activity) }}" class="btn btn-outline-success">
                <i class="bx bx-spreadsheet"></i> تصدير Excel
            </a>
            @can('update', 'App\Models\MonitoringActivity')
                <a href="{{ route('dashboard.monitoring-activities.edit', $activity) }}" class="btn btn-outline-primary">تعديل</a>
            @endcan
            @if ($canMonitorSubmit ?? false)
                <form action="{{ route('dashboard.monitoring-activities.submit-to-director', $activity) }}" method="post" class="d-inline" onsubmit="return confirm('إرسال النشاط لمدير الرقابة؟');">
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
