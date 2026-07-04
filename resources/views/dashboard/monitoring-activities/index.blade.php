<x-front-layout>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="card-title mb-0">النشاطات الرقابية</h5>
            <div class="d-flex align-items-center gap-2">
                @can('create', 'App\Models\MonitoringActivity')
                    <a class="btn btn-success" href="{{ route('dashboard.monitoring-activities.create') }}">
                        <i class="fa-solid fa-plus"></i>
                    </a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 mb-4">
                <div class="col-md-2">
                    <label class="form-label">نوع المصدر</label>
                    <select name="source_type" class="form-select">
                        <option value="">الكل</option>
                        @foreach ($sourceTypes as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['source_type'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">حالة سير العمل</label>
                    <select name="workflow_status" class="form-select">
                        <option value="">الكل</option>
                        @foreach ($workflowStatusLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['workflow_status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">المراقب</label>
                    <select name="monitor_person_id" class="form-select">
                        <option value="">الكل</option>
                        @foreach ($people as $person)
                            <option value="{{ $person->id }}" @selected((string) ($filters['monitor_person_id'] ?? '') === (string) $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">تصفية</button>
                    <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-label-secondary">إعادة</a>
                </div>
            </form>

            <div class="table-responsive text-nowrap">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>الرمز</th>
                            <th>المصدر</th>
                            <th>النوع</th>
                            <th>المركز / الدائرة</th>
                            <th>المراقب</th>
                            <th>KPI</th>
                            <th>التصنيف</th>
                            <th>حالة سير العمل</th>
                            <th>التحقق</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activities as $activity)
                            <tr>
                                <td>{{ $activity->reference_code }}</td>
                                <td>{{ $sourceTypes[$activity->source_type] ?? $activity->source_type }}</td>
                                <td>{{ $activity->activity_type ?: '-' }}</td>
                                <td>{{ $activity->center?->name ?? '-' }} / {{ $activity->department?->name ?? '-' }}</td>
                                <td>{{ $activity->monitorPerson?->name ?? '-' }}</td>
                                <td>{{ $activity->kpi_value ?? '-' }}</td>
                                <td>{{ $activity->kpi_rating ?: '-' }}</td>
                                <td>{{ $activity->workflow_status_label }}</td>
                                <td>
                                    @if ($activity->is_verified)
                                        <span class="badge bg-label-success">{{ $activity->verification_status }}</span>
                                    @else
                                        <span class="badge bg-label-warning">{{ $activity->verification_status }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        @can('view', 'App\Models\MonitoringActivity')
                                            <a href="{{ route('dashboard.monitoring-activities.show', $activity) }}" class="btn btn-sm btn-outline-secondary">
                                                عرض
                                            </a>
                                        @endcan
                                        @can('update', 'App\Models\MonitoringActivity')
                                            <a href="{{ route('dashboard.monitoring-activities.edit', $activity) }}" class="btn btn-sm btn-outline-primary">
                                                تعديل
                                            </a>
                                        @endcan
                                        @can('confirm_completion', 'App\Models\MonitoringActivity')
                                            @if (! $activity->is_passage_complete)
                                                <form action="{{ route('dashboard.monitoring-activities.confirm-passage', $activity) }}" method="post" onsubmit="return confirm('تأكيد اكتمال المرور على هذا النشاط؟');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success">تأكيد المرور</button>
                                                </form>
                                            @endif
                                        @endcan
                                        @can('delete', 'App\Models\MonitoringActivity')
                                            <form action="{{ route('dashboard.monitoring-activities.destroy', $activity) }}" method="post" onsubmit="return confirm('هل أنت متأكد من حذف هذا النشاط؟');">
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
                                <td colspan="10" class="text-center py-4">لا توجد نشاطات مضافة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                {{ $activities->links() }}
            </div>
        </div>
    </div>
</x-front-layout>
