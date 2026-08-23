@if ($project->workflow_status === 'draft' && $project->wasHandedToProjectManager())
    <div class="alert alert-info py-2 mb-3">
        المشروع بانتظار مراجعة
        <strong>{{ $project->projectManager?->name }}</strong>
        — أُدخل بواسطة السكرتاريا. أكمل التخصيص إن لزم ثم ابدأ مسارات التنفيذ.
    </div>
@elseif ($project->workflow_status === 'draft' && ! $project->hasCompletedSecretariatPhase())
    <div class="alert alert-info py-2 mb-3">
        أكمل <strong>رقم التخصيص</strong> و<strong>مرفق التخصيص</strong> من صفحة تعديل المشروع ثم اختر مسار الإرسال.
    </div>
@endif

<div class="d-flex flex-wrap gap-2">
    @if ($canSubmitHandedToProjectManager ?? false)
        <form action="{{ route('dashboard.projects.submit-handed-to-pm', $project) }}" method="post" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">إرسال لمدير المشروع</button>
        </form>
    @endif

    @if ($canSubmitAndStartExecutions ?? false)
        <form action="{{ route('dashboard.projects.submit-and-start-executions', $project) }}" method="post" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">
                {{ ($canSubmitHandedToProjectManager ?? false) ? 'بدء مسارات التنفيذ (للمنسقين)' : 'بدء مسارات التنفيذ' }}
            </button>
        </form>
    @elseif ($project->workflow_status === 'draft' && ($canSubmitToCoordinatorFromDraft ?? false))
        <form action="{{ route('dashboard.projects.submit-to-coordinator', $project) }}" method="post" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">إرسال للمنسق</button>
        </form>
    @endif
</div>
