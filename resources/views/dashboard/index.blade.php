@php
    $roleLabels = [
        'project_manager' => 'مدير مشروع',
        'coordinator' => 'منسق',
        'department_manager' => 'مدير دائرة',
        'monitoring_director' => 'مدير الرقابة العامة',
        'monitor' => 'مراقب',
        'general_management' => 'الإدارة العامة',
        'admin' => 'أدمن النظام',
        'super_admin' => 'مدير النظام',
    ];
@endphp
<x-front-layout>
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
            @can('create', 'App\Models\Project')
                <a href="{{ route('dashboard.projects.create') }}" class="btn btn-success">
                    <i class="fa-solid fa-plus me-1"></i> مشروع جديد
                </a>
            @endcan
            @can('view', 'App\Models\Project')
                <a href="{{ route('dashboard.projects.index') }}" class="btn btn-outline-primary">المشاريع</a>
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

    @if ($monitoringStats)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">النشاطات الرقابية</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><strong>الإجمالي:</strong> {{ $monitoringStats['total'] }}</div>
                    <div class="col-md-4"><strong>قيد العمل:</strong> {{ $monitoringStats['in_progress'] }}</div>
                    <div class="col-md-4"><strong>بانتظار التأكيد:</strong> {{ $monitoringStats['pending_confirmation'] }}</div>
                </div>
            </div>
        </div>
    @endif

    @can('view', 'App\Models\Project')
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">يتطلب إجراءك</h5>
                <a href="{{ route('dashboard.projects.index') }}" class="btn btn-sm btn-label-secondary">كل المشاريع</a>
            </div>
            <div class="card-body p-0">
                @if ($actionProjects->isEmpty())
                    <div class="p-4 text-center text-muted">لا توجد مشاريع تتطلب إجراءك حالياً.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>المشروع</th>
                                    <th>الحالة</th>
                                    <th>الإجراء الحالي</th>
                                    <th></th>
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
                                        <td>{{ $project->project_name }}</td>
                                        <td>{{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}</td>
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
