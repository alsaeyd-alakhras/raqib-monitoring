@if (($secondaryActivities ?? collect())->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>الرمز</th>
                    <th>الموضوع</th>
                    <th>المراقب</th>
                    <th>حالة سير العمل</th>
                    <th>KPI</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($secondaryActivities as $secondary)
                    <tr>
                        <td>{{ $secondary->reference_code }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($secondary->subject ?: '—', 60) }}</td>
                        <td>{{ $secondary->monitorPerson?->name ?? '—' }}</td>
                        <td>{{ $secondary->workflow_status_label }}</td>
                        <td>{{ $secondary->kpi_value ?? '—' }}</td>
                        <td>
                            <a href="{{ route('dashboard.monitoring-activities.show', $secondary) }}" class="btn btn-sm btn-outline-secondary">
                                عرض
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-muted mb-0">لا توجد أنشطة تابعة مرتبطة بهذا المشروع حالياً.</p>
@endif
