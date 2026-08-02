@php
    $pipelineExecutions = $monitoringDirectorHome['pipelineExecutions'] ?? collect();
    $activeSingleProjects = $monitoringDirectorHome['activeSingleProjects'] ?? collect();
    $executionTrackProjects = $monitoringDirectorHome['executionTrackProjects'] ?? collect();
    $pendingApprovalActivities = $monitoringDirectorHome['pendingApprovalActivities'] ?? collect();
    $pendingApprovalCount = $monitoringDirectorHome['pendingApprovalCount'] ?? 0;
    $sourceTypeLabels = \App\Models\MonitoringActivity::sourceTypeLabels();

    $statusBadgeClass = fn (?string $status) => match ($status) {
        'passage_complete', 'completed' => 'success',
        'rejected' => 'danger',
        'pending_monitoring_manager' => 'warning',
        'pending_monitoring_confirmation', 'pending_confirmation' => 'primary',
        'monitoring_in_progress', 'in_progress' => 'info',
        default => 'secondary',
    };

    $sourceBadgeClass = fn (?string $type) => match ($type) {
        'external' => 'info',
        'project_execution' => 'warning',
        'meeting' => 'secondary',
        default => 'primary',
    };
@endphp

@if ($pendingApprovalActivities->isNotEmpty())
    <div class="card mb-4 border-warning border-2">
        <div class="card-header bg-label-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">
                <i class="fa-solid fa-clipboard-check me-2"></i>
                أنشطة خارجية بانتظار اعتمادك
                <span class="badge bg-warning text-dark ms-2">{{ $pendingApprovalCount }}</span>
            </h5>
            <a href="{{ route('dashboard.monitoring-activities.index', ['pending_my_approval' => 1]) }}" class="btn btn-sm btn-warning">عرض الكل</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>الرمز</th>
                            <th>المصدر</th>
                            <th>الموضوع</th>
                            <th>المراقب</th>
                            <th>تاريخ الإرسال</th>
                            <th>الحالة</th>
                            <th class="text-end">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingApprovalActivities as $activity)
                            <tr class="table-warning">
                                <td><strong>{{ $activity->reference_code }}</strong></td>
                                <td>
                                    <span class="badge bg-label-{{ $sourceBadgeClass($activity->source_type) }}">
                                        {{ $sourceTypeLabels[$activity->source_type] ?? $activity->source_type }}
                                    </span>
                                </td>
                                <td>{{ $activity->subject ?: '—' }}</td>
                                <td class="small">{{ $activity->monitorPerson?->name ?? '—' }}</td>
                                <td class="small">{{ $activity->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-label-warning">{{ $activity->workflow_status_label }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.monitoring-activities.show', $activity) }}" class="btn btn-sm btn-warning">مراجعة / اعتماد</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@if (($actionExecutions ?? collect())->isNotEmpty() || ($actionProjects ?? collect())->isNotEmpty())
    <div class="card mb-4 border-warning border-2">
        <div class="card-header bg-label-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fa-solid fa-bell me-2"></i>يتطلب إجراءك الآن — مشاريع ومسارات</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>النوع</th>
                            <th>الوصف</th>
                            <th>المنسق / المسؤول</th>
                            <th>المراقب</th>
                            <th>الحالة</th>
                            <th class="text-end">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($actionExecutions as $execution)
                            <tr>
                                <td><span class="badge bg-label-primary">مسار</span></td>
                                <td>
                                    <strong>{{ $execution->project?->project_name ?: '—' }}</strong>
                                    — {{ $execution->region_name }}
                                    <br><span class="text-muted small">{{ $execution->project?->project_number ?: '—' }}</span>
                                </td>
                                <td class="small">{{ $execution->coordinatorDisplayName() }}</td>
                                <td class="small">{{ $execution->monitorPerson?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $statusBadgeClass($execution->workflow_status) }}">
                                        {{ $executionStatusLabels[$execution->workflow_status] ?? $execution->workflow_status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.projects.executions.show', [$execution->project, $execution]) }}" class="btn btn-sm btn-warning">متابعة</a>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($actionProjects as $project)
                            <tr>
                                <td><span class="badge bg-label-secondary">مشروع</span></td>
                                <td>
                                    <strong>{{ $project->project_number ?: '—' }}</strong>
                                    <br><span class="text-muted small">{{ $project->project_name }}</span>
                                </td>
                                <td class="small">{{ $project->coordinator?->name ?? '—' }}</td>
                                <td class="small">{{ $project->monitorPerson?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $statusBadgeClass($project->workflow_status) }}">
                                        {{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.projects.show', $project) }}" class="btn btn-sm btn-warning">متابعة</a>
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
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="fa-solid fa-route me-2"></i>مسارات التنفيذ</h5>
                <a href="{{ route('dashboard.project-executions.index') }}" class="btn btn-sm btn-label-primary">كل المسارات</a>
            </div>
            <div class="card-body p-0">
                @if ($pipelineExecutions->isEmpty())
                    <div class="p-4 text-center text-muted">لا توجد مسارات تنفيذ حالياً.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>المشروع</th>
                                    <th>المنطقة</th>
                                    <th>المنسق</th>
                                    <th>المراقب</th>
                                    <th>الحالة</th>
                                    <th class="text-end">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pipelineExecutions->take(15) as $execution)
                                    <tr>
                                        <td>
                                            <strong>{{ $execution->project?->project_name ?: '—' }}</strong>
                                            <br><span class="text-muted small">{{ $execution->project?->project_number ?: '—' }}</span>
                                        </td>
                                        <td>{{ $execution->region_name }}</td>
                                        <td class="small">{{ $execution->coordinatorDisplayName() }}</td>
                                        <td class="small">{{ $execution->monitorPerson?->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-label-{{ $statusBadgeClass($execution->workflow_status) }}">
                                                {{ $executionStatusLabels[$execution->workflow_status] ?? $execution->workflow_status }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('dashboard.projects.executions.show', [$execution->project, $execution]) }}" class="btn btn-sm btn-primary">متابعة</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="fa-solid fa-diagram-project me-2"></i>المشاريع النشطة</h5>
                <a href="{{ route('dashboard.projects.index') }}" class="btn btn-sm btn-label-secondary">كل المشاريع</a>
            </div>
            <div class="card-body p-0">
                @if ($activeSingleProjects->isEmpty() && $executionTrackProjects->isEmpty())
                    <div class="p-4 text-center text-muted">لا توجد مشاريع نشطة في الرقابة.</div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($activeSingleProjects as $project)
                            <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ $project->project_name }}</div>
                                    <div class="small text-muted">
                                        {{ $project->project_number ?: '—' }}
                                        · مدير المشروع: {{ $project->projectManager?->name ?? '—' }}
                                    </div>
                                    <span class="badge bg-label-{{ $statusBadgeClass($project->workflow_status) }} mt-1">
                                        {{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}
                                    </span>
                                </div>
                                <a href="{{ route('dashboard.projects.show', $project) }}" class="btn btn-sm btn-outline-primary shrink-0">متابعة</a>
                            </div>
                        @endforeach
                        @foreach ($executionTrackProjects as $project)
                            <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ $project->project_name }}</div>
                                    <div class="small text-muted">
                                        {{ $project->project_number ?: '—' }}
                                        · مدير المشروع: {{ $project->projectManager?->name ?? '—' }}
                                        · {{ $project->active_executions_count ?? 0 }} مسار
                                        @if (($project->pipeline_executions_count ?? 0) > 0)
                                            · {{ $project->pipeline_executions_count }} في الرقابة
                                        @endif
                                    </div>
                                    <span class="badge bg-label-info mt-1">مسارات متعددة</span>
                                </div>
                                <a href="{{ route('dashboard.projects.show', $project) }}" class="btn btn-sm btn-outline-primary shrink-0">متابعة</a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
