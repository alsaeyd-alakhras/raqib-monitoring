@php
    $statusLabels = \App\Models\Project::workflowStatusLabels();
@endphp
<x-front-layout>
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="card-title mb-0">المشاريع</h5>
            <div class="d-flex align-items-center gap-2">
                @can('checklist_admin.manage')
                    <a class="btn btn-label-secondary" href="{{ route('dashboard.checklist-admin.index') }}">
                        إدارة قائمة التحقق
                    </a>
                @endcan
                @can('create', 'App\Models\Project')
                    <a class="btn btn-success" href="{{ route('dashboard.projects.create') }}">
                        <i class="fa-solid fa-plus"></i>
                    </a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive text-nowrap">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>اسم المشروع</th>
                            <th>المركز / الدائرة</th>
                            <th>مدير المشروع</th>
                            <th>المنسق</th>
                            <th>المراقب</th>
                            <th>جاهزية المنسق</th>
                            <th>جاهزية المراقب</th>
                            <th>حالة سير العمل</th>
                            <th>الإجراء الحالي</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($projects as $project)
                            @php $needsMyAction = ($currentPerson ?? null) && $project->needsActionFromPerson($currentPerson); @endphp
                            <tr class="{{ $needsMyAction ? 'table-warning' : '' }}">
                                <td>
                                    {{ $project->project_name }}
                                    @if ($needsMyAction)
                                        <span class="badge bg-warning text-dark ms-1">يتطلب إجراءك</span>
                                    @endif
                                </td>
                                <td>{{ $project->center?->name }} / {{ $project->department?->name }}</td>
                                <td>{{ $project->projectManager?->name ?? '-' }}</td>
                                <td>{{ $project->coordinatorDisplayName() }}</td>
                                <td>{{ $project->monitorPerson?->name ?? '-' }}</td>
                                <td>{{ $project->coordinator_readiness_pct !== null ? $project->coordinator_readiness_pct . '%' : '-' }}</td>
                                <td>{{ $project->monitor_readiness_pct !== null ? $project->monitor_readiness_pct . '%' : '-' }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $project->workflow_status === 'rejected' ? 'danger' : 'info' }}">
                                        {{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}
                                    </span>
                                </td>
                                <td class="small">{{ $project->currentActionLabel() }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @can('view', 'App\Models\Project')
                                            <a href="{{ route('dashboard.projects.show', $project) }}" class="btn btn-sm btn-outline-secondary">
                                                عرض
                                            </a>
                                        @endcan
                                        @can('update', 'App\Models\Project')
                                            <a href="{{ route('dashboard.projects.edit', $project) }}" class="btn btn-sm btn-outline-primary">
                                                تعديل
                                            </a>
                                        @endcan
                                        @can('delete', 'App\Models\Project')
                                            <form action="{{ route('dashboard.projects.destroy', $project) }}" method="post" onsubmit="return confirm('هل أنت متأكد من حذف هذا المشروع؟');">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    حذف
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4">لا توجد مشاريع مضافة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                {{ $projects->links() }}
            </div>
        </div>
    </div>
</x-front-layout>
