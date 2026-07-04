<x-front-layout>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">النشاط الرقابي {{ $activity->reference_code }}</h4>
            <p class="text-muted mb-0">{{ $sourceTypes[$activity->source_type] ?? $activity->source_type }} — {{ $activity->workflow_status_label }}</p>
        </div>
        <div class="d-flex gap-2">
            @can('update', 'App\Models\MonitoringActivity')
                <a href="{{ route('dashboard.monitoring-activities.edit', $activity) }}" class="btn btn-outline-primary">تعديل</a>
            @endcan
            @if ($linkedProject)
                <a href="{{ route('dashboard.projects.show', $linkedProject) }}" class="btn btn-outline-secondary">عرض المشروع</a>
            @endif
            <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-label-secondary">رجوع</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">بيانات النشاط</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><strong>الرمز:</strong> {{ $activity->reference_code }}</div>
                <div class="col-md-3"><strong>المراقب:</strong> {{ $activity->monitorPerson?->name ?? '—' }}</div>
                <div class="col-md-3"><strong>التاريخ:</strong> {{ $activity->activity_date?->format('Y-m-d') ?? '—' }}</div>
                <div class="col-md-3"><strong>KPI:</strong> {{ $activity->kpi_value ?? '—' }} {{ $activity->kpi_rating ? "({$activity->kpi_rating})" : '' }}</div>
                <div class="col-md-4"><strong>المركز/الدائرة/القسم:</strong> {{ $activity->center?->name ?? '—' }} / {{ $activity->department?->name ?? '—' }} / {{ $activity->section?->name ?? '—' }}</div>
                <div class="col-md-4"><strong>طريقة/مرحلة المراقبة:</strong> {{ $activity->monitoring_method ?? '—' }} / {{ $activity->monitoring_stage ?? '—' }}</div>
                <div class="col-md-4"><strong>التحقق:</strong> {{ $activity->verification_status }}</div>
                <div class="col-md-6"><strong>الموضوع:</strong> {{ $activity->subject ?: '—' }}</div>
                <div class="col-md-6"><strong>ملاحظة النشاط:</strong> {{ $activity->notes ?: '—' }}</div>
            </div>
        </div>
    </div>

    @if ($linkedProject)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">المشروع المرتبط — {{ $linkedProject->project_number }} {{ $linkedProject->project_name }}</h5></div>
            <div class="card-body">
                @include('dashboard.projects._project_summary', [
                    'project' => $linkedProject,
                    'canViewCoordinatorData' => $canViewCoordinatorData,
                    'canViewMonitorData' => $canViewMonitorData,
                    'approverDepartmentManagerLabel' => $linkedProject->approverDepartmentManagerLabel(),
                    'projectManagerDepartmentName' => $linkedProject->projectManagerDepartmentName(),
                    'coordinatorFillActorLabel' => $linkedProject->coordinatorFilledByLabel(),
                ])

                @if ($canViewMonitorData && ($linkedProject->monitor_notes || $linkedProject->monitor_recommendations))
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="mb-2">ملاحظات/توصيات المراقب على المشروع</h6>
                        <p class="small text-muted">هذه من قائمة تحقق المشروع — وليست «ملاحظة النشاط» أعلاه.</p>
                        @if ($linkedProject->monitor_notes)
                            <div><strong>ملاحظات:</strong>
                                <ul class="mb-2">@foreach ($linkedProject->monitor_notes as $note)<li>{{ $note }}</li>@endforeach</ul>
                            </div>
                        @endif
                        @if ($linkedProject->monitor_recommendations)
                            <div><strong>توصيات:</strong>
                                <ul class="mb-0">@foreach ($linkedProject->monitor_recommendations as $rec)<li>{{ $rec }}</li>@endforeach</ul>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-front-layout>
