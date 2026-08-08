@php
    $roleLabels = [
        'project_manager' => 'مدير مشروع',
        'coordinator' => 'منسق',
        'department_manager' => 'مدير دائرة',
        'section_manager' => 'مدير قسم',
        'monitoring_director' => 'مدير الرقابة العامة',
        'monitor' => 'مراقب',
        'general_management' => 'الإدارة العامة',
        'admin' => 'أدمن النظام',
        'super_admin' => 'مدير النظام',
    ];
    $showCoordinatorColumn = ! in_array($role, ['coordinator'], true);
    $showMonitorColumn = in_array($role, ['monitor', 'monitoring_director', 'general_management', 'admin', 'super_admin'], true)
        || auth()->user()?->super_admin;
    $isProjectSecretariat = $role === 'project_secretariat';
@endphp
<x-front-layout>
    @include('dashboard.partials._home_datatable_assets')

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">مرحباً، {{ auth()->user()?->name }}</h4>
            <p class="text-muted mb-0">
                {{ $roleLabels[$role] ?? 'مستخدم' }}
                @if ($stats['label'] ?? null)
                    — {{ $stats['label'] }}
                @endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if ($role === 'monitor')
                <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-primary">
                    <i class="fa-solid fa-list-check me-1"></i> النشاطات الرقابية
                </a>
                @can('create_external', 'App\Models\MonitoringActivity')
                    <a href="{{ route('dashboard.external-activities.create') }}" class="btn btn-success">
                        <i class="fa-solid fa-globe me-1"></i> نشاط خارجي جديد
                    </a>
                @endcan
            @endif
            @can('create', 'App\Models\Project')
                <a href="{{ route('dashboard.projects.create') }}" class="btn btn-success">
                    <i class="fa-solid fa-plus me-1"></i> مشروع جديد
                </a>
            @endcan
            @can('view', 'App\Models\ProjectExecution')
                <a href="{{ route('dashboard.project-executions.index') }}" class="btn btn-outline-primary">مسارات التنفيذ</a>
            @endcan
            @can('view', 'App\Models\Project')
                <a href="{{ route('dashboard.projects.index') }}" class="btn btn-outline-secondary">المشاريع</a>
            @endcan
            @can('view', 'App\Models\MonitoringActivity')
                <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-outline-secondary">النشاطات الرقابية</a>
            @endcan
        </div>
    </div>

    @if (! empty($stats['cards']))
        <div class="row g-3 mb-4">
            @foreach ($stats['cards'] as $card)
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1">{{ $card['title'] }}</div>
                            <div class="fs-3 fw-semibold text-{{ $card['class'] }}">{{ $card['value'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if (($isMonitoringDirector ?? false) && ($monitoringDirectorHome ?? null))
        @include('dashboard._monitoring_director_home')
    @endif

    @if ($role === 'monitor' && ($monitorHome ?? null))
        @include('dashboard._monitor_home')
    @endif

    @can('view', 'App\Models\ProjectExecution')
        @if (($usesExecutionDashboard ?? false) && ! ($isMonitoringDirector ?? false) && $role !== 'monitor')
            <div class="card mb-4 shadow-lg enhanced-card raqib-home-dt-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">يتطلب إجراءك — المسارات</h5>
                    <a href="{{ route('dashboard.project-executions.index') }}" class="btn btn-sm btn-label-secondary">كل المسارات</a>
                </div>
                <div class="enhanced-card-body">
                    @if (($actionExecutions ?? collect())->isEmpty())
                        <div class="p-4 text-center text-muted">لا توجد مسارات تتطلب إجراءك حالياً.</div>
                    @else
                        <div class="raqib-home-table-container">
                            <table class="table home-dt table-striped table-hover mb-0 w-100">
                                <thead>
                                    <tr>
                                        <th>المشروع</th>
                                        <th>المنطقة</th>
                                        @if ($showCoordinatorColumn)
                                            <th>المنسق</th>
                                        @endif
                                        <th>الجاهزية</th>
                                        <th>تاريخ بدء التنفيذ</th>
                                        <th>الحالة</th>
                                        <th class="no-sort"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($actionExecutions as $execution)
                                        <tr>
                                            <td>
                                                <strong>{{ $execution->project?->project_number ?: '—' }}</strong>
                                                <br><span class="text-muted small">{{ $execution->project?->project_name }}</span>
                                            </td>
                                            <td>
                                                {{ $execution->region_name }}
                                                @if ($execution->region_execution_site)
                                                    <br><small class="text-muted">{{ $execution->region_execution_site }}</small>
                                                @endif
                                            </td>
                                            @if ($showCoordinatorColumn)
                                                <td class="small">{{ $execution->coordinatorDisplayName() }}</td>
                                            @endif
                                            <td>
                                                {{ $execution->coordinator_readiness_pct !== null ? number_format($execution->coordinator_readiness_pct, 1) . '%' : '—' }}
                                            </td>
                                            <td class="small">{{ $execution->project?->execution_start_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td>
                                                <span class="badge bg-label-{{ match($execution->workflow_status) {
                                                    'passage_complete' => 'success',
                                                    'rejected' => 'danger',
                                                    'pending_coordinator', 'coordinator_filling' => 'warning',
                                                    default => 'info',
                                                } }}">
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

            @if (($visibleExecutions ?? collect())->isNotEmpty())
                <div class="card mb-4 shadow-lg enhanced-card raqib-home-dt-card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">كل مساراتي</h5>
                        <a href="{{ route('dashboard.project-executions.index') }}" class="btn btn-sm btn-label-secondary">عرض الكل</a>
                    </div>
                    <div class="enhanced-card-body">
                        <div class="raqib-home-table-container">
                            <table class="table home-dt table-striped table-hover mb-0 w-100">
                                <thead>
                                    <tr>
                                        <th>المشروع</th>
                                        <th>المنطقة</th>
                                        @if ($showCoordinatorColumn)
                                            <th>المنسق</th>
                                        @endif
                                        <th>الجاهزية</th>
                                        <th>تاريخ بدء التنفيذ</th>
                                        <th>الحالة</th>
                                        <th class="no-sort"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($visibleExecutions as $execution)
                                        <tr>
                                            <td>
                                                <strong>{{ $execution->project?->project_number ?: '—' }}</strong>
                                                <br><span class="text-muted small">{{ $execution->project?->project_name }}</span>
                                            </td>
                                            <td>{{ $execution->region_name }}</td>
                                            @if ($showCoordinatorColumn)
                                                <td class="small">{{ $execution->coordinatorDisplayName() }}</td>
                                            @endif
                                            <td>{{ $execution->coordinator_readiness_pct !== null ? number_format($execution->coordinator_readiness_pct, 1) . '%' : '—' }}</td>
                                            <td class="small">{{ $execution->project?->execution_start_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td>
                                                <span class="badge bg-label-info">{{ $executionStatusLabels[$execution->workflow_status] ?? $execution->workflow_status }}</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('dashboard.projects.executions.show', [$execution->project, $execution]) }}" class="btn btn-sm btn-outline-primary">عرض</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    @endcan

    @if ($monitoringStats && $role !== 'monitor')
        <div class="card mb-4 {{ ($isMonitoringDirector ?? false) && ($monitoringStats['pending_confirmation'] ?? 0) > 0 ? 'border-warning border-2' : '' }}">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">النشاطات الرقابية</h5>
                @if (($isMonitoringDirector ?? false) && ($monitoringStats['pending_confirmation'] ?? 0) > 0)
                    <a href="{{ route('dashboard.monitoring-activities.index', ['pending_my_approval' => 1]) }}" class="btn btn-sm btn-warning">
                        {{ $monitoringStats['pending_confirmation'] }} خارجي بانتظار اعتمادك
                    </a>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><strong>الإجمالي:</strong> {{ $monitoringStats['total'] }}</div>
                    <div class="col-md-4"><strong>قيد العمل:</strong> {{ $monitoringStats['in_progress'] }}</div>
                    <div class="col-md-4">
                        <strong>{{ ($isMonitoringDirector ?? false) ? 'خارجي بانتظار الاعتماد:' : 'بانتظار الاعتماد:' }}</strong>
                        <span class="{{ ($monitoringStats['pending_confirmation'] ?? 0) > 0 ? 'text-warning fw-semibold' : '' }}">
                            {{ $monitoringStats['pending_confirmation'] }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @can('view', 'App\Models\Project')
        @if ($role !== 'monitor' && ! ($isMonitoringDirector ?? false) && (! ($usesExecutionDashboard ?? false) || ($actionProjects ?? collect())->isNotEmpty()))
            <div class="card mb-4 shadow-lg enhanced-card raqib-home-dt-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">يتطلب إجراءك — المشاريع</h5>
                    <a href="{{ route('dashboard.projects.index') }}" class="btn btn-sm btn-label-secondary">كل المشاريع</a>
                </div>
                <div class="enhanced-card-body">
                    @if (($actionProjects ?? collect())->isEmpty())
                        <div class="p-4 text-center text-muted">لا توجد مشاريع تتطلب إجراءك حالياً.</div>
                    @else
                        <div class="raqib-home-table-container">
                            <table class="table home-dt table-striped table-hover mb-0 w-100">
                                <thead>
                                    <tr>
                                        <th>المشروع</th>
                                        @if ($isProjectSecretariat)
                                            <th>مدير المشروع</th>
                                            <th>موازنة المشروع</th>
                                        @endif
                                        <th>الحالة</th>
                                        <th>تاريخ بدء التنفيذ</th>
                                        <th>الإجراء الحالي</th>
                                        <th class="no-sort text-end">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($actionProjects as $project)
                                        @php
                                            $actionUrl = match ($person?->role) {
                                                'monitor' => route('dashboard.projects.monitor-work', $project),
                                                default => route('dashboard.projects.show', $project),
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $project->project_name }}</strong>
                                                @if ($project->project_number)
                                                    <br><span class="text-muted small">{{ $project->project_number }}</span>
                                                @endif
                                            </td>
                                            @if ($isProjectSecretariat)
                                                <td class="small">{{ $project->projectManager?->name ?? '—' }}</td>
                                                <td class="small">
                                                    @if ($project->project_budget !== null)
                                                        {{ number_format((float) $project->project_budget, 2) }}
                                                        @if ($project->currency?->code)
                                                            <span class="text-muted">{{ $project->currency->code }}</span>
                                                        @endif
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            @endif
                                            <td>{{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}</td>
                                            <td class="small">{{ $project->execution_start_date?->format('Y-m-d') ?? '—' }}</td>
                                            <td class="small">{{ $project->currentActionLabel() }}</td>
                                            <td class="text-end">
                                                <a href="{{ $actionUrl }}" class="btn btn-sm btn-primary">متابعة</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    @endcan

    @if (in_array($role, ['admin', 'super_admin'], true))
        <div class="card">
            <div class="card-header"><h5 class="mb-0">روابط الإدارة</h5></div>
            <div class="card-body d-flex flex-wrap gap-2">
                @can('view', 'App\Models\User')
                    <a href="{{ route('dashboard.users.index') }}" class="btn btn-outline-primary btn-sm">المستخدمون</a>
                @endcan
                @can('view', 'App\Models\Person')
                    <a href="{{ route('dashboard.people.index') }}" class="btn btn-outline-primary btn-sm">الأشخاص</a>
                @endcan
                @can('view', 'App\Models\Constant')
                    <a href="{{ route('dashboard.constants.index') }}" class="btn btn-outline-primary btn-sm">ثوابت النظام</a>
                @endcan
                @can('checklist_admin.manage')
                    <a href="{{ route('dashboard.checklist-admin.index') }}" class="btn btn-outline-primary btn-sm">قائمة التحقق</a>
                @endcan
            </div>
        </div>
    @endif
</x-front-layout>
