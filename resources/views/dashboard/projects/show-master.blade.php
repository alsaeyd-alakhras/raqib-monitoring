<x-front-layout>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">{{ $project->project_name }}</h4>
            <p class="text-muted mb-0">
                <span class="badge bg-label-{{ match($project->workflow_status) {
                    'passage_complete' => 'success',
                    'executions_in_progress' => 'info',
                    default => 'secondary',
                } }}">{{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}</span>
                · {{ $project->project_number ?: '—' }}
                · مسارات: {{ $executionSummary['label'] }}
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('dashboard.projects.index') }}" class="btn btn-label-secondary">رجوع</a>
            @if ($canEditProjectForm ?? false)
                <a href="{{ route('dashboard.projects.edit', $project) }}" class="btn btn-primary">تعديل</a>
            @endif
        </div>
    </div>

    @if ($project->workflow_status === 'draft')
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">إجراءات المسودة</h5></div>
            <div class="card-body">
                @include('dashboard.projects._draft_workflow_actions', [
                    'project' => $project,
                    'canSubmitHandedToProjectManager' => $canSubmitHandedToProjectManager ?? false,
                    'canSubmitAndStartExecutions' => $canSubmitAndStartExecutions ?? false,
                    'canSubmitToCoordinatorFromDraft' => false,
                ])
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">ملخص المشروع الأساسي</h5></div>
        <div class="card-body pt-3">
            @include('dashboard.projects._project_summary', [
                'compactLayout' => true,
                'canViewMonitorData' => $canViewMonitorData ?? false,
                'canViewCoordinatorData' => $canViewCoordinatorData ?? false,
                'showCoordinatorInSummary' => $showCoordinatorInSummary ?? false,
                'projectManagerDepartmentName' => $projectManagerDepartmentName ?? null,
                'approverDepartmentManager' => $approverDepartmentManager ?? null,
                'approverDepartmentManagerLabel' => $approverDepartmentManagerLabel ?? null,
                'executionRegionsForDisplay' => $executionRegionsForDisplay ?? null,
                'executionRegionsBeneficiariesTotal' => $executionRegionsBeneficiariesTotal ?? null,
            ])
        </div>
    </div>

    @include('dashboard.projects._pm_fields_panel', ['project' => $project])

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">مسارات التنفيذ (المناطق)</h5>
            @if ($canManageRegions ?? false)
                <form action="{{ route('dashboard.projects.sync-regions', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">مزامنة المناطق</button>
                </form>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المنطقة</th>
                            <th>المستفيدون</th>
                            @if (! in_array(auth()->user()?->person?->role, ['coordinator'], true))
                                <th>المنسق</th>
                            @endif
                            @if ($canViewMonitorData ?? false)
                                <th>المراقب</th>
                            @endif
                            <th>جاهزية المنسق</th>
                            @if ($canViewMonitorData ?? false)
                                <th>جاهزية المراقب</th>
                            @endif
                            <th>الحالة</th>
                            <th class="text-end text-nowrap">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($project->executions->where('is_active', true) as $execution)
                            @php
                                $execLabels = \App\Models\ProjectExecution::workflowStatusLabels();
                                $canFollowExecution = $execution->isVisibleToUser(auth()->user())
                                    || auth()->user()?->canOverseeExecutions();
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <strong>{{ $execution->region_name }}</strong>
                                    @if ($execution->region_execution_site)
                                        <br><small class="text-muted">{{ $execution->region_execution_site }}</small>
                                    @endif
                                </td>
                                <td>{{ $execution->region_beneficiaries !== null ? number_format($execution->region_beneficiaries) : '—' }}</td>
                                @if (! in_array(auth()->user()?->person?->role, ['coordinator'], true))
                                    <td>{{ $execution->coordinatorDisplayName() }}</td>
                                @endif
                                @if ($canViewMonitorData ?? false)
                                    <td>{{ $execution->monitorPerson?->name ?? '—' }}</td>
                                @endif
                                <td>{{ $execution->coordinator_readiness_pct !== null ? number_format($execution->coordinator_readiness_pct, 1) . '%' : '—' }}</td>
                                @if ($canViewMonitorData ?? false)
                                    <td>{{ $execution->monitor_readiness_pct !== null ? number_format($execution->monitor_readiness_pct, 1) . '%' : '—' }}</td>
                                @endif
                                <td>
                                    <span class="badge bg-label-{{ match($execution->workflow_status) {
                                        'passage_complete' => 'success',
                                        'rejected' => 'danger',
                                        'pending_monitoring_confirmation' => 'warning',
                                        default => 'info',
                                    } }}">{{ $execLabels[$execution->workflow_status] ?? $execution->workflow_status }}</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($canFollowExecution)
                                        <a href="{{ route('dashboard.projects.executions.show', [$project, $execution]) }}" class="btn btn-sm btn-primary">متابعة</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ (($canViewMonitorData ?? false) ? 9 : 7) - (in_array(auth()->user()?->person?->role, ['coordinator'], true) ? 1 : 0) }}" class="text-center text-muted py-4">لا توجد مسارات بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-front-layout>
