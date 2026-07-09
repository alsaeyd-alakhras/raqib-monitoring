<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">بيانات النشاط</h5></div>
    <div class="card-body">
        @include('reports.monitoring-activities.partials._activity_summary', [
            'activity' => $activity,
            'sourceTypes' => $sourceTypes,
            'workflowStatusLabels' => $workflowStatusLabels,
            'compactLayout' => true,
        ])
    </div>
</div>

@if ($linkedProject)
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">المشروع المرتبط — {{ $linkedProject->project_number }} {{ $linkedProject->project_name }}</h5></div>
        <div class="card-body">
            @include('dashboard.projects._project_summary', [
                'project' => $linkedProject,
                'canViewCoordinatorData' => $canViewCoordinatorData,
                'canViewMonitorData' => $canViewMonitorData,
                'showCoordinatorInSummary' => true,
                'approverDepartmentManagerLabel' => $linkedProject->approverDepartmentManagerLabel(),
                'projectManagerDepartmentName' => $linkedProject->projectManagerDepartmentName(),
                'coordinatorFillActorLabel' => $linkedProject->coordinatorFilledByLabel(),
                'compactLayout' => true,
            ])
        </div>
    </div>

    @if ($canViewMonitorData && ($linkedProject->monitor_notes || $linkedProject->monitor_recommendations))
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">ملاحظات وتوصيات المراقب على المشروع</h5></div>
            <div class="card-body">
                @include('reports.monitoring-activities.partials._monitor_notes_report', [
                    'linkedProject' => $linkedProject,
                    'canConvertToSecondary' => $canConvertToSecondary ?? false,
                ])
            </div>
        </div>
    @endif
@endif

@if ($activity->activity_role === 'primary' && $activity->source_type === 'project')
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">الأنشطة التابعة للمشروع</h5></div>
        <div class="card-body">
            @include('reports.monitoring-activities.partials._secondary_activities', [
                'secondaryActivities' => $secondaryActivities ?? collect(),
            ])
        </div>
    </div>
@endif
