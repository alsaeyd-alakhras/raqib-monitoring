@php
    $valueLabels = $valueLabels ?? [
        'ready' => 'جاهز',
        'partial' => 'جزئي',
        'not_ready' => 'غير جاهز',
        'not_required' => 'غير مطلوب',
    ];
    $readinessStatusLabels = [
        'stopped' => '🔴 يحتاج مراجعة (بند غير جاهز)',
        'partially_ready' => '🔶 جاهز جزئياً',
        'ready' => '✅ جاهز للتنفيذ',
    ];
@endphp
<x-front-layout>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>يرجى تصحيح الأخطاء التالية:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($errors->has('monitor'))
        <div class="alert alert-danger">{{ $errors->first('monitor') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.projects.index') }}">المشاريع</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.projects.show', $project) }}">{{ $project->project_number }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.projects.executions.show', [$project, $execution]) }}">{{ $execution->region_name }}</a></li>
                    <li class="breadcrumb-item active">عمل المراقب</li>
                </ol>
            </nav>
            <h4 class="mb-0">عمل المراقب — {{ $execution->region_name }}</h4>
            <p class="text-muted mb-0">{{ $project->project_name }}</p>
        </div>
        <a href="{{ route('dashboard.projects.executions.show', [$project, $execution]) }}" class="btn btn-label-secondary">رجوع للمسار</a>
    </div>

    @include('dashboard.project-executions._workflow_stepper', [
        'execution' => $execution,
        'canViewMonitorData' => true,
    ])

    <div class="card mb-4 mt-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">ملخص المسار</h5>
            @if ($execution->workflow_status === 'passage_complete')
                <span class="badge bg-label-success">تم المرور</span>
            @elseif ($awaitingDirector)
                <span class="badge bg-label-warning">بانتظار مدير الرقابة</span>
            @else
                <span class="badge bg-label-info">قيد التعبئة</span>
            @endif
        </div>
        <div class="card-body pt-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">المنطقة</div>
                    <div class="fw-semibold">{{ $execution->region_name }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">المنسق</div>
                    <div class="fw-semibold">{{ $execution->coordinatorDisplayName() }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">المراقب</div>
                    <div class="fw-semibold">{{ $execution->monitorPerson?->name ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    @if (($canEditMonitorColumn ?? true) && ($isAssignedMonitor ?? true))
        {{-- حقول التقييم أولاً في HTML حتى لا تُقطع عند max_input_vars --}}
        <form action="{{ route('dashboard.projects.executions.fill-monitor', [$project, $execution]) }}" method="post">
            @csrf

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">بيانات النشاط المتبقية</h5>
                </div>
                <div class="card-body">
                    @include('dashboard.projects._activity_fields_editor', [
                        'activity' => $primaryActivity ?? null,
                        'people' => $people ?? collect(),
                        'activityTypes' => $activityTypes ?? [],
                    ])
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
                    <span>نسبة الجاهزية: <strong class="checklist-overall-pct">{{ $execution->monitor_readiness_pct !== null ? number_format($execution->monitor_readiness_pct, 1) . '%' : '—' }}</strong></span>
                </div>
                <div class="card-body">
                    @include('dashboard.projects._checklist_edit', [
                        'groups' => $groups,
                        'values' => $values,
                        'valueLabels' => $valueLabels,
                        'valueField' => 'monitor_value',
                        'readinessBreakdown' => $readinessBreakdown ?? null,
                        'prefix' => 'checklist',
                    ])

                    @include('dashboard.projects._monitor_notes_editor', ['execution' => $execution])
                </div>
            </div>

            <div class="mb-4">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i> حفظ التعديلات
                </button>
            </div>
        </form>
    @else
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">بيانات النشاط المتبقية</h5>
            </div>
            <div class="card-body">
                @include('dashboard.projects._activity_fields_display', [
                    'activity' => $primaryActivity ?? null,
                ])
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
                <span>نسبة الجاهزية: <strong>{{ $execution->monitor_readiness_pct !== null ? number_format($execution->monitor_readiness_pct, 1) . '%' : '—' }}</strong></span>
            </div>
            <div class="card-body">
                @include('dashboard.projects._checklist_display', [
                    'groups' => $groups,
                    'values' => $values,
                    'valueLabels' => $valueLabels,
                    'valueField' => 'monitor_value',
                    'readinessBreakdown' => $readinessBreakdown ?? null,
                ])
                @include('dashboard.projects._monitor_notes_display', ['execution' => $execution])
            </div>
        </div>
    @endif

    @if ($execution->workflow_status === 'passage_complete' || $awaitingDirector || ($canShowMonitorSubmitSection ?? false))
        <div class="card mb-4 border-{{ $awaitingDirector ? 'warning' : 'success' }}">
            <div class="card-header bg-label-{{ $awaitingDirector ? 'warning' : 'success' }}">
                <h5 class="mb-0">إرسال العمل لمدير الرقابة</h5>
            </div>
            <div class="card-body">
                @if ($execution->workflow_status === 'passage_complete')
                    <div class="alert alert-success mb-0">
                        <strong>تم المرور على هذا المسار.</strong> لا يلزم أي إجراء إضافي.
                    </div>
                @elseif ($awaitingDirector)
                    <div class="alert alert-warning mb-0">
                        <strong>تم الإرسال بنجاح.</strong> عملك وصل لمدير الرقابة العامة — بانتظار تأكيد المرور النهائي.
                    </div>
                @else
                    <p class="text-muted mb-3">
                        تم حفظ عملك. اضغط الزر أدناه لإرسال المسار لمدير الرقابة العامة.
                        <strong>الحفظ وحده لا يُرسل المسار — هذا الزر خطوة الإرسال الرسمية.</strong>
                    </p>
                    @if ($execution->readiness_status)
                        <p class="small mb-3">
                            <strong>تقييم الجاهزية (معلوماتي):</strong>
                            {{ $readinessStatusLabels[$execution->readiness_status] ?? $execution->readiness_status }}
                            — لا يمنع الإرسال.
                        </p>
                    @endif
                    @if ($primaryActivity ?? null)
                        <p class="small mb-3">
                            <strong>حالة التحقق (معلوماتي):</strong>
                            {{ $primaryActivity->verification_status }}
                            — لا يمنع الإرسال.
                        </p>
                    @endif
                    <form action="{{ route('dashboard.projects.executions.confirm-monitoring', [$project, $execution]) }}" method="post" data-confirm="إرسال عمل المراقب لمدير الرقابة العامة؟ لن تستطيع تعديل البيانات بعد الإرسال إلا بإرجاع المسار." data-confirm-title="تأكيد الإرسال" data-confirm-variant="primary">
                        @csrf
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fa-solid fa-paper-plane me-1"></i> إرسال لمدير الرقابة العامة
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @elseif (($canEditMonitorColumn ?? false) && ($isAssignedMonitor ?? true))
        <div class="alert alert-info mb-4">
            <strong>الخطوة التالية:</strong> احفظ التعديلات من الأعلى أولاً، ثم سيظهر خيار الإرسال لمدير الرقابة العامة في هذه الصفحة.
        </div>
    @endif

    @push('scripts')
        <script src="{{ asset('js/checklist-readiness.js') }}"></script>
        <script src="{{ asset('js/checklist-person-required.js') }}"></script>
    @endpush
</x-front-layout>
