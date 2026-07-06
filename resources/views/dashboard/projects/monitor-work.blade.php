@php
    $valueLabels = [
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
    @if ($errors->has('monitor'))
        <div class="alert alert-danger">{{ $errors->first('monitor') }}</div>
    @endif

    @include('dashboard.projects._workflow_stepper')

    <div class="card mb-4 mt-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">عمل المراقب — {{ $project->project_name }}</h5>
            @if ($project->workflow_status === 'passage_complete')
                <span class="badge bg-label-success">تم المرور</span>
            @elseif ($awaitingDirector)
                <span class="badge bg-label-warning">بانتظار مدير الرقابة</span>
            @else
                <span class="badge bg-label-info">قيد التعبئة</span>
            @endif
        </div>
        <div class="card-body pt-3">
            @include('dashboard.projects._project_summary', [
                'compactLayout' => true,
                'showActions' => false,
                'canViewMonitorData' => true,
            ])
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
        </div>
        <div class="card-body">
            @if (($canEditMonitorColumn ?? true) && ($isAssignedMonitor ?? true))
                <form action="{{ route('dashboard.projects.fill-monitor', $project) }}" method="post">
                    @csrf
                    @include('dashboard.projects._checklist_edit', [
                        'groups' => $groups,
                        'values' => $values,
                        'valueLabels' => $valueLabels,
                        'valueField' => 'monitor_value',
                    ])

                    @include('dashboard.projects._monitor_notes_editor', ['project' => $project])

                    <button type="submit" class="btn btn-outline-primary mt-3">
                        <i class="fa-solid fa-floppy-disk me-1"></i> حفظ التعديلات
                    </button>
                </form>
            @else
                @include('dashboard.projects._checklist_display', [
                    'groups' => $groups,
                    'values' => $values,
                    'valueLabels' => $valueLabels,
                    'valueField' => 'monitor_value',
                ])
                @include('dashboard.projects._monitor_notes_display', ['project' => $project])
            @endif
        </div>
    </div>

    {{-- خطوة الإرسال — تظهر بعد حفظ عمل المراقب أولاً --}}
    @if ($project->workflow_status === 'passage_complete' || $awaitingDirector || ($canShowMonitorSubmitSection ?? false))
        <div class="card mb-4 border-{{ $awaitingDirector ? 'warning' : 'success' }}">
            <div class="card-header bg-label-{{ $awaitingDirector ? 'warning' : 'success' }}">
                <h5 class="mb-0">إرسال العمل لمدير الرقابة</h5>
            </div>
            <div class="card-body">
                @if ($project->workflow_status === 'passage_complete')
                    <div class="alert alert-success mb-0">
                        <strong>تم المرور على هذا المشروع.</strong> لا يلزم أي إجراء إضافي.
                    </div>
                @elseif ($awaitingDirector)
                    <div class="alert alert-warning mb-0">
                        <strong>تم الإرسال بنجاح.</strong> عملك وصل لمدير الرقابة العامة — بانتظار تأكيد المرور النهائي.
                    </div>
                @else
                    <p class="text-muted mb-3">
                        تم حفظ عملك. اضغط الزر أدناه لإرسال المشروع لمدير الرقابة العامة.
                        <strong>الحفظ وحده لا يُرسل المشروع — هذا الزر خطوة الإرسال الرسمية.</strong>
                    </p>
                    @if ($project->readiness_status)
                        <p class="small mb-3">
                            <strong>تقييم الجاهزية (معلوماتي):</strong>
                            {{ $readinessStatusLabels[$project->readiness_status] ?? $project->readiness_status }}
                            — لا يمنع الإرسال.
                        </p>
                    @endif
                    <form action="{{ route('dashboard.projects.confirm-monitoring', $project) }}" method="post" data-confirm="إرسال عمل المراقب لمدير الرقابة العامة؟ لن تستطيع تعديل البيانات بعد الإرسال إلا بإرجاع المشروع." data-confirm-title="تأكيد الإرسال" data-confirm-variant="primary">
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
            <strong>الخطوة التالية:</strong> احفظ قائمة التحقق والملاحظات من الأعلى أولاً، ثم سيظهر خيار الإرسال لمدير الرقابة العامة في هذه الصفحة.
        </div>
    @endif
</x-front-layout>
