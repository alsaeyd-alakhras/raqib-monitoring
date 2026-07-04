@php
    $statusLabels = \App\Models\Project::workflowStatusLabels();
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
    $canFillCoordinator = auth()->user()?->can('fill_coordinator', 'App\Models\Project');
    $canApproveDept = auth()->user()?->can('approve_department', 'App\Models\Project');
    $canReject = auth()->user()?->can('reject', 'App\Models\Project');
    $canUpdate = auth()->user()?->can('update', 'App\Models\Project');
@endphp
<x-front-layout>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $project->project_name }}</h5>
            <span class="badge bg-label-{{ match($project->workflow_status) {
                'rejected' => 'danger',
                'passage_complete' => 'success',
                'pending_monitoring_confirmation' => 'warning',
                default => 'info',
            } }} fs-6">
                {{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}
            </span>
        </div>
        <div class="card-body pt-3">
            @if ($project->hasPendingReturnNotice())
                <div class="alert alert-warning py-2 mb-3">
                    <strong>أُرجِع المشروع للمراجعة.</strong>
                    @if ($project->return_target)
                        الإجراء: {{ \App\Models\Project::returnTargetLabel($project->return_target) }} —
                    @endif
                    السبب: {{ $project->rejection_reason }}
                    @if ($project->rejectedByUser)
                        <span class="d-block small mt-1">بواسطة: {{ $project->rejectedByUser->name }} — {{ $project->rejected_at?->format('Y-m-d H:i') }}</span>
                    @endif
                </div>
            @endif
            @include('dashboard.projects._project_summary')
        </div>
    </div>

    {{-- سير العمل: دورة الاعتماد الإدارية --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">سير العمل</h5></div>
        <div class="card-body">
            @if ($project->workflow_status === 'pending_dept_manager')
                <div class="alert alert-info py-2 mb-3">
                    المشروع بانتظار موافقة
                    <strong>{{ $approverDepartmentManagerLabel }}</strong>
                    @if ($projectManagerDepartmentName)
                        — مدير دائرة «{{ $projectManagerDepartmentName }}» (دائرة مدير المشروع، وليست بالضرورة دائرة المشروع التنظيمية أعلاه).
                    @endif
                </div>
            @endif

            @if ($project->workflow_status === 'draft' && $canUpdate)
                <form action="{{ route('dashboard.projects.submit-to-coordinator', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">إرسال للمنسق</button>
                </form>
            @endif

            @if ($canSubmitToDeptManager ?? false)
                <form action="{{ route('dashboard.projects.submit-to-dept-manager', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">إرسال لمدير الدائرة</button>
                </form>
            @elseif (in_array($project->workflow_status, ['pending_coordinator', 'coordinator_filling']) && ($canManageCoordinatorColumn ?? false))
                <div class="alert alert-secondary py-2 mb-0">
                    قبل الإرسال لمدير الدائرة يجب حفظ تعبئة المنسق أولاً (من المنسق نفسه أو نيابةً عنه).
                </div>
            @endif

            @if ($project->workflow_status === 'pending_dept_manager' && ($canApproveThisProject ?? false))
                <div class="d-flex flex-wrap gap-2 mb-0">
                    <form action="{{ route('dashboard.projects.approve-department', $project) }}" method="post" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">موافقة وإرسال لمدير الرقابة العامة</button>
                    </form>
                    @if ($canRejectThisProject ?? false)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#projectRejectModal">
                            رفض المشروع
                        </button>
                    @endif
                </div>
            @endif

            @if ($project->workflow_status === 'pending_monitoring_manager' && ($canSetMonitoringInfo || $canAssignMonitor))
                @if ($canSetMonitoringInfo)
                <form action="{{ route('dashboard.projects.set-monitoring-info', $project) }}" method="post" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label">طريقة المراقبة</label>
                        <select name="monitoring_method" class="form-select">
                            <option value="">إختر القيمة</option>
                            @foreach ($monitoringMethods as $method)
                                <option value="{{ $method }}" @selected($project->monitoring_method === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">مرحلة المراقبة</label>
                        <select name="monitoring_stage" class="form-select">
                            <option value="">إختر القيمة</option>
                            @foreach ($monitoringStages as $stage)
                                <option value="{{ $stage }}" @selected($project->monitoring_stage === $stage)>{{ $stage }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary">حفظ طريقة/مرحلة المراقبة</button>
                    </div>
                </form>
                @endif

                @if ($canAssignMonitor)
                <form action="{{ route('dashboard.projects.assign-monitor', $project) }}" method="post" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">تعيين المراقب</label>
                        <select name="monitor_person_id" class="form-select" required>
                            <option value="">إختر القيمة</option>
                            @foreach ($monitors as $person)
                                <option value="{{ $person->id }}" @selected($project->monitor_person_id == $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاريخ المراقبة</label>
                        <input type="date" name="monitoring_date" class="form-control" value="{{ $project->monitoring_date?->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-success">تعيين وبدء المراقبة</button>
                    </div>
                </form>
                @endif
            @endif

            @if ($project->workflow_status === 'pending_monitoring_manager' && ($canRejectThisProject ?? false))
                <div class="mt-3">
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#projectRejectModal">
                        رفض المشروع
                    </button>
                </div>
            @endif

            @if ($project->workflow_status === 'monitoring_in_progress')
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if ($isAssignedMonitor ?? false)
                        <a href="{{ route('dashboard.projects.monitor-work', $project) }}" class="btn btn-outline-primary">شاشة عمل المراقب</a>
                    @endif
                    @if (($canViewMonitorData ?? false) && $project->readiness_status)
                        <span class="text-muted small">تقييم الجاهزية: {{ $readinessStatusLabels[$project->readiness_status] ?? '-' }}</span>
                    @endif
                    @if ($canRejectThisProject ?? false)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#projectRejectModal">
                            رفض المشروع
                        </button>
                    @endif
                </div>
            @endif

            @if ($project->workflow_status === 'pending_monitoring_confirmation')
                <div class="alert alert-warning py-2 mb-3">
                    المراقب <strong>{{ $project->monitorPerson?->name ?? '—' }}</strong> أنهى عمله وأرسل المشروع — بانتظار تأكيد المرور من مدير الرقابة العامة.
                </div>
                @if ($canConfirmPassageThisProject ?? false)
                    <form action="{{ route('dashboard.projects.confirm-passage', $project) }}" method="post" class="d-inline" onsubmit="return confirm('تأكيد المرور على المشروع وإغلاق دورة المراقبة؟');">
                        @csrf
                        <button type="submit" class="btn btn-success btn-lg">تأكيد المرور — إتمام المشروع</button>
                    </form>
                @endif
                @if ($canRejectThisProject ?? false)
                    <button type="button" class="btn btn-outline-danger ms-2" data-bs-toggle="modal" data-bs-target="#projectRejectModal">
                        رفض المشروع
                    </button>
                @endif
            @endif

            @if ($project->workflow_status === 'passage_complete')
                <div class="alert alert-success mb-0">
                    <strong>تم المرور على المشروع بنجاح.</strong> دورة المراقبة مكتملة ولا يلزم أي إجراء إضافي.
                    @if ($project->primaryMonitoringActivity?->passage_completed_at)
                        <span class="d-block small mt-1">تاريخ التأكيد: {{ $project->primaryMonitoringActivity->passage_completed_at->format('Y-m-d H:i') }}</span>
                    @endif
                </div>
            @endif

            @if ($project->workflow_status === 'rejected')
                <div class="alert alert-danger">
                    <div><strong>رفض قاطع نهائي</strong></div>
                    <div><strong>سبب الرفض:</strong> {{ $project->rejection_reason }}</div>
                    <div><strong>مسؤولية النقص:</strong> {{ \App\Models\Project::gapOwnerLabel($project->gap_owner) }}</div>
                    <div><strong>رُفض بواسطة:</strong> {{ $project->rejectedByUser?->name ?? '-' }}</div>
                    <div><strong>رُفض بتاريخ:</strong> {{ $project->rejected_at }}</div>
                </div>
            @endif
        </div>
    </div>

    @include('dashboard.projects._reject_modal')

    @if ($errors->has('rejection_reason') || $errors->has('gap_owner') || $errors->has('return_target'))
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('projectRejectModal');
                if (modalEl && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
        </script>
        @endpush
    @endif

    {{-- قائمة التحقق — عمود المنسق --}}
    @if ($canViewCoordinatorData)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">قائمة التحقق — عمود المنسق</h5>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if ($project->coordinator_filled_by)
                    <span class="badge bg-label-warning">عُبّئ نيابةً: {{ $project->coordinatorFilledByUser?->name }}</span>
                @elseif ($coordinatorFillActorLabel ?? null)
                    <span class="badge bg-label-info">عُبّئ بواسطة: {{ $coordinatorFillActorLabel }}</span>
                @endif
                <span>نسبة الجاهزية: {{ $project->coordinator_readiness_pct !== null ? $project->coordinator_readiness_pct . '%' : '-' }}</span>
            </div>
        </div>
        <div class="card-body">
            @if ((
                    in_array($project->workflow_status, ['pending_coordinator', 'coordinator_filling'])
                    || ($showCoordinatorFillOnDraft ?? false)
                ) && ($canManageCoordinatorColumn ?? false))
                <form action="{{ route('dashboard.projects.fill-coordinator', $project) }}" method="post">
                    @csrf
                    @if ($requiresFillOnBehalfConfirm ?? false)
                        <div class="mb-3">
                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm"
                                id="toggle-coordinator-on-behalf-fill"
                                aria-expanded="{{ old('fill_on_behalf') ? 'true' : 'false' }}"
                                aria-controls="coordinator-on-behalf-fill-panel"
                            >
                                أعبّئ قائمة التحقق نيابةً عن المنسق
                            </button>
                            <div class="form-text mt-2">لن تظهر حقول التعبئة إلا بعد الضغط على الزر وتأكيد النيابة.</div>
                        </div>
                        <div id="coordinator-on-behalf-fill-panel" class="{{ old('fill_on_behalf') ? '' : 'd-none' }}">
                            <div class="alert alert-warning py-2 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fill_on_behalf" id="fill-on-behalf" value="1" @checked(old('fill_on_behalf')) required>
                                    <label class="form-check-label" for="fill-on-behalf">
                                        أؤكّد أنني أعبّئ قائمة التحقق نيابةً عن المنسق:
                                        <strong>{{ $project->coordinatorDisplayName() }}</strong>
                                    </label>
                                </div>
                                <div class="form-text mb-0">سيُسجَّل اسمك كمُعبّئ نيابةً عن المنسق في بيانات المشروع.</div>
                            </div>
                            @include('dashboard.projects._checklist_edit', [
                                'groups' => $groups,
                                'values' => $values,
                                'valueLabels' => $valueLabels,
                            ])
                            <button type="submit" class="btn btn-primary">حفظ عمود المنسق</button>
                        </div>
                    @else
                        @include('dashboard.projects._checklist_edit', [
                            'groups' => $groups,
                            'values' => $values,
                            'valueLabels' => $valueLabels,
                        ])
                        <button type="submit" class="btn btn-primary">حفظ عمود المنسق</button>
                    @endif
                </form>
            @else
                @include('dashboard.projects._checklist_display', [
                    'groups' => $groups,
                    'values' => $values,
                    'valueLabels' => $valueLabels,
                    'valueField' => 'coordinator_value',
                ])
            @endif
        </div>
    </div>
    @endif

    {{-- قائمة التحقق — عمود المراقب (عرض فقط هنا، التعديل من شاشة المراقب المعزولة) --}}
    @if ($canViewMonitorData ?? false)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
            <span>نسبة الجاهزية: {{ $project->monitor_readiness_pct !== null ? $project->monitor_readiness_pct . '%' : '-' }}</span>
        </div>
        <div class="card-body">
            @include('dashboard.projects._checklist_display', [
                'groups' => $groups,
                'values' => $values,
                'valueLabels' => $valueLabels,
                'valueField' => 'monitor_value',
            ])
            @if ($project->monitor_notes)
                <div><strong>ملاحظات المراقب:</strong>
                    <ul>@foreach ($project->monitor_notes as $note)<li>{{ $note }}</li>@endforeach</ul>
                </div>
            @endif
            @if ($project->monitor_recommendations)
                <div><strong>توصيات المراقب:</strong>
                    <ul>@foreach ($project->monitor_recommendations as $rec)<li>{{ $rec }}</li>@endforeach</ul>
                </div>
            @endif
        </div>
    </div>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const toggleBtn = document.getElementById('toggle-coordinator-on-behalf-fill');
                const panel = document.getElementById('coordinator-on-behalf-fill-panel');
                const checkbox = document.getElementById('fill-on-behalf');

                if (!toggleBtn || !panel) {
                    return;
                }

                const syncOnBehalfUi = function (enabled) {
                    panel.classList.toggle('d-none', !enabled);
                    toggleBtn.setAttribute('aria-expanded', enabled ? 'true' : 'false');
                    toggleBtn.textContent = enabled
                        ? 'إخفاء تعبئة النيابة'
                        : 'أعبّئ قائمة التحقق نيابةً عن المنسق';
                };

                syncOnBehalfUi(Boolean(checkbox?.checked));

                toggleBtn.addEventListener('click', function () {
                    const enableOnBehalf = panel.classList.contains('d-none');

                    if (checkbox) {
                        checkbox.checked = enableOnBehalf;
                    }

                    syncOnBehalfUi(enableOnBehalf);
                });

                checkbox?.addEventListener('change', function () {
                    syncOnBehalfUi(Boolean(checkbox.checked));
                });
            });
        </script>
    @endpush
</x-front-layout>
