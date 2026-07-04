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

    <div class="card mb-4">
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
        <div class="card-body">
            <div class="row">
                <div class="col-md-3"><strong>المركز/الدائرة/القسم:</strong> {{ $project->center?->name }} / {{ $project->department?->name }} / {{ $project->section?->name }}</div>
                <div class="col-md-3"><strong>الممول:</strong> {{ $project->funder?->name ?? '-' }}</div>
                <div class="col-md-3"><strong>المستفيدون المستهدفون:</strong> {{ $project->target_beneficiaries ?? '-' }}</div>
                <div class="col-md-3"><strong>الميزانية المرصودة:</strong> {{ $project->allocated_budget ?? '-' }}</div>
                <div class="col-md-6"><strong>الموقع:</strong> {{ $project->location ?? '-' }}</div>
                <div class="col-md-3"><strong>عدد مناطق التنفيذ:</strong> {{ $project->execution_zones ?? '-' }}</div>
                <div class="col-md-3"><strong>المدة المقدّرة:</strong> {{ $project->estimated_duration ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
        </div>
        <div class="card-body">
            @if ($canEditMonitorColumn ?? true)
                <form action="{{ route('dashboard.projects.fill-monitor', $project) }}" method="post">
                    @csrf
                    @include('dashboard.projects._checklist_edit', [
                        'groups' => $groups,
                        'values' => $values,
                        'valueLabels' => $valueLabels,
                        'valueField' => 'monitor_value',
                    ])

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">ملاحظات المراقب (سطر لكل ملاحظة)</label>
                            <textarea name="monitor_notes_text" class="form-control" rows="4">{{ implode("\n", $project->monitor_notes ?? []) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">توصيات المراقب (سطر لكل توصية)</label>
                            <textarea name="monitor_recommendations_text" class="form-control" rows="4">{{ implode("\n", $project->monitor_recommendations ?? []) }}</textarea>
                        </div>
                    </div>

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
                @if ($project->monitor_notes)
                    <div class="mt-3"><strong>ملاحظات المراقب:</strong>
                        <ul class="mb-0">@foreach ($project->monitor_notes as $note)<li>{{ $note }}</li>@endforeach</ul>
                    </div>
                @endif
                @if ($project->monitor_recommendations)
                    <div class="mt-2"><strong>توصيات المراقب:</strong>
                        <ul class="mb-0">@foreach ($project->monitor_recommendations as $rec)<li>{{ $rec }}</li>@endforeach</ul>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- خطوة الإرسال — منفصلة عن الحفظ --}}
    <div class="card mb-4 border-{{ $awaitingDirector ? 'warning' : ($canSubmitToDirector ? 'success' : 'secondary') }}">
        <div class="card-header bg-label-{{ $awaitingDirector ? 'warning' : ($canSubmitToDirector ? 'success' : 'secondary') }}">
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
            @elseif ($canSubmitToDirector)
                <p class="text-muted mb-3">
                    بعد حفظ قائمة التحقق والملاحظات، اضغط الزر أدناه لإرسال عملك لمدير الرقابة العامة.
                    <strong>الحفظ وحده لا يُرسل المشروع.</strong>
                </p>
                @if ($project->readiness_status)
                    <p class="small mb-3">
                        <strong>تقييم الجاهزية (معلوماتي):</strong>
                        {{ $readinessStatusLabels[$project->readiness_status] ?? $project->readiness_status }}
                        — لا يمنع الإرسال.
                    </p>
                @endif
                <form action="{{ route('dashboard.projects.confirm-monitoring', $project) }}" method="post" onsubmit="return confirm('إرسال عمل المراقب لمدير الرقابة العامة؟ لن تستطيع تعديل البيانات بعد الإرسال إلا بإرجاع المشروع.');">
                    @csrf
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fa-solid fa-paper-plane me-1"></i> إرسال لمدير الرقابة العامة
                    </button>
                </form>
            @else
                <div class="alert alert-secondary mb-0">
                    لا يمكن الإرسال حالياً — تحقق من حالة المشروع أو تواصل مع مدير الرقابة.
                </div>
            @endif
        </div>
    </div>
</x-front-layout>
