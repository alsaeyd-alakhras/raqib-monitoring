<x-front-layout>
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="card-title mb-0">النشاطات الرقابية</h5>
            <div class="d-flex align-items-center">
                @can('create', 'App\Models\MonitoringActivity')
                    <a class="btn btn-success" href="{{ route('dashboard.monitoring-activities.create') }}">
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
                        @php
                            $sourceTypeLabels = [
                                'project' => 'مشروع',
                                'external' => 'خارجي',
                                'meeting' => 'محضر اجتماع',
                            ];
                        @endphp
                        @forelse ($activities as $activity)
                            <tr>
                                <td>{{ $activity->reference_code }}</td>
                                <td>{{ $sourceTypeLabels[$activity->source_type] ?? $activity->source_type }}</td>
                                <td>{{ $activity->activity_type ?: '-' }}</td>
                                <td>{{ $activity->center?->name }} / {{ $activity->department?->name }}</td>
                                <td>{{ $activity->monitorPerson?->name ?? '-' }}</td>
                                <td>{{ $activity->kpi_value ?? '-' }}</td>
                                <td>{{ $activity->kpi_rating ?: '-' }}</td>
                                <td>{{ $activity->workflow_status }}</td>
                                <td>
                                    @if ($activity->is_verified)
                                        <span class="badge bg-label-success">{{ $activity->verification_status }}</span>
                                    @else
                                        <span class="badge bg-label-warning">{{ $activity->verification_status }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @can('update', 'App\Models\MonitoringActivity')
                                            <a href="{{ route('dashboard.monitoring-activities.edit', $activity) }}" class="btn btn-sm btn-outline-primary">
                                                تعديل
                                            </a>
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
