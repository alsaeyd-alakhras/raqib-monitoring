@php
    $actionActivities = $monitorHome['actionActivities'] ?? collect();
    $returnedActivities = $monitorHome['returnedActivities'] ?? collect();
    $myCreatedActivities = $monitorHome['myCreatedActivities'] ?? collect();
    $pendingDirectorActivities = $monitorHome['pendingDirectorActivities'] ?? collect();
    $homeActionProjects = $monitorHome['actionProjects'] ?? collect();
    $homeActionExecutions = $monitorHome['actionExecutions'] ?? collect();
    $myExecutions = $monitorHome['myExecutions'] ?? collect();
    $myProjects = $monitorHome['myProjects'] ?? collect();
    $sourceTypeLabels = \App\Models\MonitoringActivity::sourceTypeLabels();
    $workflowLabels = \App\Models\MonitoringActivity::workflowStatusLabels();

    $statusBadgeClass = fn (?string $status) => match ($status) {
        'passage_complete', 'completed' => 'success',
        'rejected' => 'danger',
        'pending_monitoring_manager' => 'warning',
        'pending_monitoring_confirmation', 'pending_confirmation' => 'primary',
        'monitoring_in_progress', 'in_progress' => 'info',
        default => 'secondary',
    };

    $activityActionUrl = fn (\App\Models\MonitoringActivity $activity) => $activity->isExternal()
        ? route('dashboard.external-activities.edit', $activity)
        : route('dashboard.monitoring-activities.edit', $activity);

    $hasImmediateAction = $actionActivities->isNotEmpty()
        || $returnedActivities->isNotEmpty()
        || $homeActionProjects->isNotEmpty()
        || $homeActionExecutions->isNotEmpty();
@endphp

<div class="card mb-4 shadow-sm border-primary border-1">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-1">النشاطات الرقابية</h5>
            <p class="text-muted mb-0 small">أنشطتك الخارجية والتابعة — تعبئة، متابعة، وإرسال لمدير الرقابة.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-primary">
                <i class="fa-solid fa-list-check me-1"></i> كل النشاطات
            </a>
            @can('create_external', 'App\Models\MonitoringActivity')
                <a href="{{ route('dashboard.external-activities.create') }}" class="btn btn-success">
                    <i class="fa-solid fa-plus me-1"></i> نشاط خارجي
                </a>
            @endcan
        </div>
    </div>
</div>

@if ($hasImmediateAction)
    <div class="card mb-4 border-warning border-2 shadow-lg enhanced-card raqib-home-dt-card">
        <div class="card-header bg-label-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fa-solid fa-bell me-2"></i>يتطلب إجراءك الآن</h5>
            <a href="{{ route('dashboard.monitoring-activities.index', ['needs_my_action' => 1]) }}" class="btn btn-sm btn-warning">أنشطتي — عرض الكل</a>
        </div>
        <div class="enhanced-card-body">
            <div class="raqib-home-table-container">
                <table class="table home-dt table-striped table-hover mb-0 w-100">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th>الوصف</th>
                            <th>الحالة</th>
                            <th>ملاحظة</th>
                            <th class="text-end no-sort">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($returnedActivities as $activity)
                            <tr class="table-warning">
                                <td><span class="badge bg-label-info">نشاط خارجي</span></td>
                                <td>
                                    <strong>{{ $activity->reference_code }}</strong>
                                    <br><span class="text-muted small">{{ $activity->subject ?: '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-label-danger">مرجع للتعديل</span>
                                </td>
                                <td class="small text-danger">{{ \Illuminate\Support\Str::limit($activity->rejection_reason, 80) ?: '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ $activityActionUrl($activity) }}" class="btn btn-sm btn-warning">تعديل</a>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($actionActivities->reject(fn ($a) => $returnedActivities->pluck('id')->contains($a->id)) as $activity)
                            <tr>
                                <td>
                                    <span class="badge bg-label-{{ $activity->source_type === 'external' ? 'info' : 'secondary' }}">
                                        {{ $sourceTypeLabels[$activity->source_type] ?? $activity->source_type }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $activity->reference_code }}</strong>
                                    <br><span class="text-muted small">{{ $activity->subject ?: '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-label-info">{{ $workflowLabels[$activity->workflow_status] ?? $activity->workflow_status }}</span>
                                </td>
                                <td class="small text-muted">بانتظار تعبئتك</td>
                                <td class="text-end">
                                    <a href="{{ $activityActionUrl($activity) }}" class="btn btn-sm btn-primary">متابعة</a>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($homeActionExecutions as $execution)
                            <tr>
                                <td><span class="badge bg-label-primary">مسار</span></td>
                                <td>
                                    <strong>{{ $execution->project?->project_name ?: '—' }}</strong>
                                    — {{ $execution->region_name }}
                                    <br><span class="text-muted small">{{ $execution->project?->project_number ?: '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-label-{{ $statusBadgeClass($execution->workflow_status) }}">
                                        {{ $executionStatusLabels[$execution->workflow_status] ?? $execution->workflow_status }}
                                    </span>
                                </td>
                                <td class="small text-muted">تعبئة قائمة المراقب</td>
                                <td class="text-end">
                                    @if ($execution->project)
                                        <a href="{{ route('dashboard.projects.executions.monitor-work', [$execution->project, $execution]) }}" class="btn btn-sm btn-warning">تعبئة</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($homeActionProjects as $project)
                            <tr>
                                <td><span class="badge bg-label-secondary">مشروع</span></td>
                                <td>
                                    <strong>{{ $project->project_number ?: '—' }}</strong>
                                    <br><span class="text-muted small">{{ $project->project_name }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-label-{{ $statusBadgeClass($project->workflow_status) }}">
                                        {{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}
                                    </span>
                                </td>
                                <td class="small text-muted">تعبئة قائمة المراقب</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.projects.monitor-work', $project) }}" class="btn btn-sm btn-warning">تعبئة</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@if ($myCreatedActivities->isNotEmpty())
    <div class="card mb-4 shadow-lg enhanced-card raqib-home-dt-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fa-solid fa-pen-to-square me-2"></i>أنشطة أضفتها</h5>
            @can('create_external', 'App\Models\MonitoringActivity')
                <a href="{{ route('dashboard.external-activities.create') }}" class="btn btn-sm btn-label-primary">إضافة نشاط خارجي</a>
            @endcan
        </div>
        <div class="enhanced-card-body">
            <div class="raqib-home-table-container">
                <table class="table home-dt table-striped table-hover mb-0 w-100">
                    <thead>
                        <tr>
                            <th>الرمز</th>
                            <th>الموضوع</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                            <th class="text-end no-sort">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($myCreatedActivities as $activity)
                            <tr>
                                <td><strong>{{ $activity->reference_code }}</strong></td>
                                <td>{{ $activity->subject ?: '—' }}</td>
                                <td class="small">{{ $activity->activity_date?->format('Y-m-d') ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $statusBadgeClass($activity->workflow_status) }}">
                                        {{ $workflowLabels[$activity->workflow_status] ?? $activity->workflow_status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if ($activity->canMonitorEditExternal(auth()->user()))
                                        <a href="{{ route('dashboard.external-activities.edit', $activity) }}" class="btn btn-sm btn-outline-primary">تعديل</a>
                                    @else
                                        <a href="{{ route('dashboard.monitoring-activities.show', $activity) }}" class="btn btn-sm btn-outline-secondary">عرض</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@if ($pendingDirectorActivities->isNotEmpty())
    <div class="card mb-4 shadow-lg enhanced-card raqib-home-dt-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fa-solid fa-hourglass-half me-2"></i>بانتظار اعتماد مدير الرقابة</h5>
            <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-sm btn-label-secondary">كل النشاطات</a>
        </div>
        <div class="enhanced-card-body">
            <div class="raqib-home-table-container">
                <table class="table home-dt table-striped table-hover mb-0 w-100">
                    <thead>
                        <tr>
                            <th>الرمز</th>
                            <th>الموضوع</th>
                            <th>تاريخ الإرسال</th>
                            <th>الحالة</th>
                            <th class="text-end no-sort">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingDirectorActivities as $activity)
                            <tr class="table-info">
                                <td><strong>{{ $activity->reference_code }}</strong></td>
                                <td>{{ $activity->subject ?: '—' }}</td>
                                <td class="small">{{ $activity->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-label-warning">{{ $workflowLabels[$activity->workflow_status] ?? $activity->workflow_status }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.monitoring-activities.show', $activity) }}" class="btn btn-sm btn-outline-primary">عرض</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

<div class="row g-4 mb-4">
    @if ($myExecutions->isNotEmpty())
        <div class="col-lg-7">
            <div class="card h-100 shadow-lg enhanced-card raqib-home-dt-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0"><i class="fa-solid fa-route me-2"></i>مساراتي</h5>
                    @can('view', 'App\Models\ProjectExecution')
                        <a href="{{ route('dashboard.project-executions.index') }}" class="btn btn-sm btn-label-primary">كل المسارات</a>
                    @endcan
                </div>
                <div class="enhanced-card-body">
                    <div class="raqib-home-table-container">
                        <table class="table home-dt table-striped table-hover mb-0 w-100">
                            <thead>
                                <tr>
                                    <th>المشروع</th>
                                    <th>المنطقة</th>
                                    <th>الجاهزية</th>
                                    <th>الحالة</th>
                                    <th class="text-end no-sort">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($myExecutions as $execution)
                                    <tr>
                                        <td>
                                            <strong>{{ $execution->project?->project_number ?: '—' }}</strong>
                                            <br><span class="text-muted small">{{ $execution->project?->project_name }}</span>
                                        </td>
                                        <td>{{ $execution->region_name }}</td>
                                        <td>{{ $execution->coordinator_readiness_pct !== null ? number_format($execution->coordinator_readiness_pct, 1) . '%' : '—' }}</td>
                                        <td>
                                            <span class="badge bg-label-{{ $statusBadgeClass($execution->workflow_status) }}">
                                                {{ $executionStatusLabels[$execution->workflow_status] ?? $execution->workflow_status }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @if ($execution->project)
                                                <a href="{{ route('dashboard.projects.executions.show', [$execution->project, $execution]) }}" class="btn btn-sm btn-outline-primary">عرض</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($myProjects->isNotEmpty())
        <div class="{{ $myExecutions->isNotEmpty() ? 'col-lg-5' : 'col-12' }}">
            <div class="card h-100 shadow-lg enhanced-card raqib-home-dt-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0"><i class="fa-solid fa-diagram-project me-2"></i>مشاريعي</h5>
                    <a href="{{ route('dashboard.projects.index') }}" class="btn btn-sm btn-label-secondary">كل المشاريع</a>
                </div>
                <div class="enhanced-card-body">
                    <div class="raqib-home-table-container">
                        <table class="table home-dt table-striped table-hover mb-0 w-100">
                            <thead>
                                <tr>
                                    <th>المشروع</th>
                                    <th>تاريخ بدء التنفيذ</th>
                                    <th>الحالة</th>
                                    <th class="text-end no-sort">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($myProjects as $project)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $project->project_name }}</div>
                                            <div class="small text-muted">{{ $project->project_number ?: '—' }}</div>
                                        </td>
                                        <td class="small">{{ $project->execution_start_date?->format('Y-m-d') ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-label-{{ $statusBadgeClass($project->workflow_status) }}">
                                                {{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @if ($project->needsActionFromPerson($person))
                                                <a href="{{ route('dashboard.projects.monitor-work', $project) }}" class="btn btn-sm btn-warning">تعبئة</a>
                                            @else
                                                <a href="{{ route('dashboard.projects.show', $project) }}" class="btn btn-sm btn-outline-primary">عرض</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
