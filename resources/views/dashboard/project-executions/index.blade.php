<x-front-layout>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">مسارات التنفيذ</h4>
            <p class="text-muted mb-0">متابعة مسارات المشاريع متعددة المناطق</p>
        </div>
        <a href="{{ route('dashboard.projects.index') }}" class="btn btn-label-secondary">المشاريع</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>المشروع</th>
                        <th>المنطقة</th>
                        <th>المنسق</th>
                        <th>المراقب</th>
                        <th>الجاهزية</th>
                        <th>الحالة</th>
                        <th class="text-end">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($executions as $execution)
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
                            <td class="small">{{ $execution->coordinatorDisplayName() }}</td>
                            <td class="small">{{ $execution->monitorPerson?->name ?? '—' }}</td>
                            <td>{{ $execution->coordinator_readiness_pct !== null ? number_format($execution->coordinator_readiness_pct, 1) . '%' : '—' }}</td>
                            <td>
                                <span class="badge bg-label-{{ match($execution->workflow_status) {
                                    'passage_complete' => 'success',
                                    'rejected' => 'danger',
                                    'pending_monitoring_manager' => 'warning',
                                    'pending_monitoring_confirmation' => 'primary',
                                    'monitoring_in_progress' => 'info',
                                    default => 'secondary',
                                } }}">
                                    {{ $statusLabels[$execution->workflow_status] ?? $execution->workflow_status }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('dashboard.projects.executions.show', [$execution->project, $execution]) }}" class="btn btn-sm btn-primary">متابعة</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">لا توجد مسارات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            @include('dashboard.partials._per_page_select', ['defaultPerPage' => 15])
            {{ $executions->links() }}
        </div>
    </div>
</x-front-layout>
