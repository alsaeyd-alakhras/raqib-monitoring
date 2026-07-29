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
                'showCoordinatorInSummary' => true,
                'canViewCoordinatorData' => false,
                'canViewMonitorData' => true,
            ])
        </div>
    </div>

    @if (($canEditMonitorColumn ?? true) && ($isAssignedMonitor ?? true))
        <form
            action="{{ route('dashboard.projects.fill-monitor', $project) }}"
            method="post"
            data-confirm="حفظ وإرسال عمل المراقب لمدير الرقابة العامة؟ لن تستطيع تعديل البيانات بعد الإرسال إلا بإرجاع المشروع."
            data-confirm-title="تأكيد الحفظ والإرسال"
            data-confirm-variant="primary"
        >
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
                        'textareaRows' => 1,
                    ])
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
                    <span>نسبة الجاهزية: <strong class="checklist-overall-pct">{{ $project->monitor_readiness_pct !== null ? $project->monitor_readiness_pct . '%' : '—' }}</strong></span>
                </div>
                <div class="card-body">
                    @include('dashboard.projects._checklist_edit', [
                        'groups' => $groups,
                        'values' => $values,
                        'valueLabels' => $valueLabels,
                        'valueField' => 'monitor_value',
                        'readinessBreakdown' => $readinessBreakdown ?? null,
                        'inputClass' => 'monitor-checklist-input',
                    ])

                    @include('dashboard.projects._monitor_notes_editor', ['project' => $project])
                </div>
            </div>

            <div class="mb-4">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fa-solid fa-paper-plane me-1"></i> حفظ وإرسال لمدير الرقابة العامة
                </button>
            </div>
        </form>
    @else
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
                <span>نسبة الجاهزية: <strong>{{ $project->monitor_readiness_pct !== null ? $project->monitor_readiness_pct . '%' : '—' }}</strong></span>
            </div>
            <div class="card-body">
                @include('dashboard.projects._checklist_display', [
                    'groups' => $groups,
                    'values' => $values,
                    'valueLabels' => $valueLabels,
                    'valueField' => 'monitor_value',
                    'readinessBreakdown' => $readinessBreakdown ?? null,
                ])
                @include('dashboard.projects._monitor_notes_display', ['project' => $project])
            </div>
        </div>

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
    @endif

    @if ($project->workflow_status === 'passage_complete' || $awaitingDirector)
        <div class="card mb-4 border-{{ $awaitingDirector ? 'warning' : 'success' }}">
            <div class="card-header bg-label-{{ $awaitingDirector ? 'warning' : 'success' }}">
                <h5 class="mb-0">إرسال العمل لمدير الرقابة</h5>
            </div>
            <div class="card-body">
                @if ($project->workflow_status === 'passage_complete')
                    <div class="alert alert-success mb-0">
                        <strong>تم المرور على هذا المشروع.</strong> لا يلزم أي إجراء إضافي.
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        <strong>تم الإرسال بنجاح.</strong> عملك وصل لمدير الرقابة العامة — بانتظار تأكيد المرور النهائي.
                    </div>
                @endif
            </div>
        </div>
    @endif

    @push('scripts')
        <script src="{{ asset('js/checklist-status-style.js') }}"></script>
        <script src="{{ asset('js/checklist-readiness.js') }}"></script>
        <script src="{{ asset('js/checklist-person-required.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.initChecklistReadiness) {
                    window.initChecklistReadiness(document);
                }
                if (window.initChecklistStatusStyle) {
                    window.initChecklistStatusStyle(document);
                }
                if (window.initChecklistPersonRequired) {
                    window.initChecklistPersonRequired(document);
                }
                if (window.refreshChecklistReadiness) {
                    window.refreshChecklistReadiness(document);
                }
            });
        </script>
    @endpush
</x-front-layout>
