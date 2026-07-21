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
    @php
        $checklistValidationMessages = collect($errors->getMessages())
            ->filter(fn ($msgs, $key) => str_starts_with((string) $key, 'checklist.')
                || str_starts_with((string) $key, 'closure_docs.'))
            ->flatten()
            ->unique()
            ->values();
    @endphp
    @if ($checklistValidationMessages->isNotEmpty())
        <div id="checklist-validation-errors" class="d-none" aria-hidden="true">
            @foreach ($checklistValidationMessages as $message)
                <span data-checklist-error>{{ $message }}</span>
            @endforeach
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">{{ $project->project_name }}</h4>
            <p class="text-muted mb-0">
                <span class="badge bg-label-{{ match($project->workflow_status) {
                    'rejected' => 'danger',
                    'passage_complete' => 'success',
                    'pending_monitoring_confirmation' => 'warning',
                    default => 'info',
                } }}">{{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}</span>
                · {{ $project->project_number ?: '—' }}
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('view', 'App\Models\Project')
                <a href="{{ route('dashboard.projects.export-pdf', $project) }}" class="btn btn-outline-danger" target="_blank">
                    <i class="bx bx-file-blank"></i> تصدير PDF
                </a>
            @endcan
            <a href="{{ route('dashboard.projects.index') }}" class="btn btn-label-secondary">رجوع</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">ملخص المشروع</h5>
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
            @include('dashboard.projects._project_summary', ['compactLayout' => true])
        </div>
    </div>

    {{-- سير العمل: دورة الاعتماد الإدارية --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">سير العمل</h5></div>
        <div class="card-body">
            @include('dashboard.projects._workflow_stepper')

            @include('dashboard.projects._rejection_history')

            <div class="workflow-actions-panel mt-4">
            @if ($project->workflow_status === 'pending_project_manager')
                <div class="alert alert-info py-2 mb-3">
                    المشروع بانتظار مراجعة
                    <strong>{{ $project->projectManager?->name }}</strong>
                    وإرساله لمدير القسم.
                </div>
            @endif

            @if ($project->workflow_status === 'pending_section_manager')
                <div class="alert alert-info py-2 mb-3">
                    المشروع بانتظار موافقة
                    <strong>{{ $approverSectionManagerLabel }}</strong>
                    — مدير قسم المشروع التنظيمي.
                </div>
            @endif

            @if ($project->workflow_status === 'pending_dept_manager')
                <div class="alert alert-info py-2 mb-3">
                    المشروع بانتظار موافقة
                    <strong>{{ $approverDepartmentManagerLabel }}</strong>
                    @if ($projectManagerDepartmentName)
                        — مدير دائرة «{{ $projectManagerDepartmentName }}» (دائرة مدير المشروع، وليست بالضرورة دائرة المشروع التنظيمية أعلاه).
                    @endif
                </div>
            @endif

            @if ($project->workflow_status === 'pending_secretariat')
                <div class="alert alert-info py-2 mb-3">
                    @if ($project->hasCompletedSecretariatPhase())
                        تصحيح رقم ومرفق التخصيص — سكرتاريا الدائرة. بعد الحفظ يُرسل المشروع مباشرة لمدير القسم.
                    @elseif ($project->isSelfCoordinator())
                        المشروع بانتظار سكرتاريا الدائرة لتعبئة رقم التخصيص ومرفق التخصيص.
                        يجب على مدير المشروع/المنسق إكمال قائمة التحقق قبل أن تُرسل السكرتاريا لمدير القسم.
                    @else
                        المشروع بانتظار سكرتاريا الدائرة لتعبئة رقم التخصيص ومرفق التخصيص قبل إرساله للمنسق.
                    @endif
                </div>
            @endif

            @if (in_array($project->workflow_status, ['pending_coordinator', 'coordinator_filling'], true) && $project->hasCoordinatorAssignment())
                <div class="alert alert-info py-2 mb-3">
                    @if ($project->workflow_status === 'pending_coordinator')
                        المشروع بانتظار بدء تعبئة المنسق
                    @else
                        المشروع قيد تعبئة المنسق
                    @endif
                    <strong>{{ $project->coordinatorDisplayName() }}</strong>
                    @if ($project->isSelfCoordinator())
                        <span class="badge bg-label-info">مدير المشروع / منسق</span>
                    @elseif ($project->coordinator_external_name)
                        <span class="badge bg-label-secondary">منسق خارجي</span>
                    @endif
                    — بعد اكتمال التعبئة يُرسل المشروع مباشرة لمدير القسم.
                </div>
            @endif

            @if ($project->workflow_status === 'draft' && ($canSubmitToSecretariat ?? false))
                <form action="{{ route('dashboard.projects.submit-to-secretariat', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">إرسال لسكرتاريا الدائرة</button>
                </form>
            @elseif ($project->workflow_status === 'draft' && ($canSubmitToCoordinatorFromDraft ?? false))
                <form action="{{ route('dashboard.projects.submit-to-coordinator', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">إرسال للمنسق</button>
                </form>
            @elseif ($project->workflow_status === 'draft' && $canUpdate && $project->isSelfCoordinator() && ! $project->hasCompletedSecretariatPhase())
                <div class="alert alert-secondary py-2 mb-0">
                    أكمل تعبئة قائمة المنسق واحفظها، ثم يظهر زر الإرسال لسكرتاريا الدائرة.
                </div>
            @elseif ($project->workflow_status === 'draft' && $canUpdate && $project->isSelfCoordinator() && $project->hasCompletedSecretariatPhase() && ! ($canSubmitToSectionManager ?? false))
                <div class="alert alert-secondary py-2 mb-0">
                    تم تعبئة التخصيص سابقاً — راجع قائمة المنسق واحفظها، ثم أرسل لمدير القسم.
                </div>
            @endif

            @if ($project->workflow_status === 'pending_secretariat' && auth()->user()?->can('fill_secretariat', 'App\Models\Project') && ($canShowSecretariatForm ?? true))
                @include('dashboard.projects._secretariat_form')
            @endif

            @if ($canSubmitToProjectManager ?? false)
                <form action="{{ route('dashboard.projects.submit-to-project-manager', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">إرسال لمدير القسم</button>
                </form>
            @endif

            @if ($canSubmitToSectionManager ?? false)
                <form action="{{ route('dashboard.projects.submit-to-section-manager', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">إرسال لمدير القسم</button>
                </form>
            @elseif (in_array($project->workflow_status, ['pending_coordinator', 'coordinator_filling']) && ($canManageCoordinatorColumn ?? false))
                <div class="alert alert-secondary py-2 mb-0">
                    قبل الإرسال لمدير القسم يجب حفظ تعبئة المنسق أولاً (من المنسق نفسه أو نيابةً عنه إن وُجدت).
                </div>
            @endif

            @if ($project->workflow_status === 'pending_section_manager' && ($canApproveSection ?? false))
                <div class="d-flex flex-wrap gap-2 mb-0">
                    <form action="{{ route('dashboard.projects.approve-section', $project) }}" method="post" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">موافقة وإرسال لمدير الدائرة</button>
                    </form>
                    @if ($canRejectThisProject ?? false)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#projectRejectModal">
                            رفض المشروع
                        </button>
                    @endif
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

            @if ($project->workflow_status === 'passage_complete')
                <div class="alert alert-success mb-0">
                    <strong>تم المرور على المشروع بنجاح.</strong> دورة المراقبة مكتملة ولا يلزم أي إجراء إضافي.
                    @if ($project->primaryMonitoringActivity?->passage_completed_at)
                        <span class="d-block small mt-1">تاريخ التأكيد: {{ $project->primaryMonitoringActivity->passage_completed_at->format('Y-m-d H:i') }}</span>
                    @endif
                </div>
            @endif

            @if ($project->workflow_status === 'rejected' && ($canViewRejectionHistory ?? false))
                <div class="alert alert-danger">
                    <div><strong>رفض قاطع نهائي</strong></div>
                    <div><strong>سبب الرفض:</strong> {{ $project->rejection_reason }}</div>
                    <div><strong>مسؤولية النقص:</strong> {{ \App\Models\Project::gapOwnerLabel($project->gap_owner) }}</div>
                    <div><strong>رُفض بواسطة:</strong> {{ $project->rejectedByUser?->name ?? '-' }}</div>
                    <div><strong>رُفض بتاريخ:</strong> {{ $project->rejected_at }}</div>
                </div>
            @endif

            </div>{{-- /.workflow-actions-panel --}}
        </div>
    </div>

    @if ($project->workflow_status === 'pending_monitoring_manager' && (($canSetMonitoringInfo ?? false) || ($canAssignMonitor ?? false)))
        @include('dashboard.projects._monitoring_setup_panel')
    @endif

    @if (in_array($project->workflow_status, ['monitoring_in_progress', 'pending_monitoring_confirmation'], true) && ($canViewMonitoringStatusPanel ?? false))
        @include('dashboard.projects._monitoring_status_panel', [
            'readinessStatusLabels' => $readinessStatusLabels,
        ])
    @endif

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

    {{-- قائمة التحقق — دمج المنسق والمراقب لمدير الرقابة --}}
    @if ($canViewMergedChecklist ?? false)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">قائمة التحقق — المنسق والمراقب</h5>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-label-primary">المنسق: <strong>{{ $project->coordinator_readiness_pct !== null ? $project->coordinator_readiness_pct . '%' : '—' }}</strong></span>
                <span class="badge bg-label-info">المراقب: <strong>{{ $project->monitor_readiness_pct !== null ? $project->monitor_readiness_pct . '%' : '—' }}</strong></span>
            </div>
        </div>
        <div class="card-body">
            @include('dashboard.projects._checklist_merged_display', [
                'groups' => $groups,
                'values' => $values,
                'valueLabels' => $valueLabels,
                'readinessBreakdown' => $readinessBreakdown ?? null,
            ])
            @include('dashboard.projects._monitor_notes_display', ['project' => $project])
        </div>
    </div>
    @else
    {{-- قائمة التحقق — عمود المنسق --}}
    @if ($canViewCoordinatorData)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">قائمة التحقق — عمود المنسق</h5>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if (! ($canManageCoordinatorColumn ?? false))
                    <span class="badge bg-label-secondary">عرض فقط</span>
                @endif
                @if ($project->coordinator_filled_by)
                    <span class="badge bg-label-warning">عُبّئ نيابةً: {{ $project->coordinatorFilledByUser?->name }}</span>
                @elseif ($coordinatorFillActorLabel ?? null)
                    <span class="badge bg-label-info">عُبّئ بواسطة: {{ $coordinatorFillActorLabel }}</span>
                @endif
                <span>نسبة الجاهزية: <strong class="checklist-overall-pct">{{ $project->coordinator_readiness_pct !== null ? $project->coordinator_readiness_pct . '%' : '—' }}</strong></span>
            </div>
        </div>
        <div class="card-body">
            @if ((
                    in_array($project->workflow_status, ['pending_coordinator', 'coordinator_filling'])
                    || ($showCoordinatorFillOnDraft ?? false)
                ) && ($canManageCoordinatorColumn ?? false))
                <form action="{{ route('dashboard.projects.fill-coordinator', $project) }}" method="post" enctype="multipart/form-data">
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
                                'readinessBreakdown' => $readinessBreakdown ?? null,
                                'project' => $project,
                            ])
                            <button type="submit" class="btn btn-primary">حفظ عمود المنسق</button>
                        </div>
                    @else
                        @include('dashboard.projects._checklist_edit', [
                            'groups' => $groups,
                            'values' => $values,
                            'valueLabels' => $valueLabels,
                            'readinessBreakdown' => $readinessBreakdown ?? null,
                            'project' => $project,
                        ])
                        <button type="submit" class="btn btn-primary">حفظ عمود المنسق</button>
                    @endif
                </form>
            @else
                @php
                    $closureFileEditMode = ($canFillClosureDocs ?? false);
                @endphp
                @if ($closureFileEditMode)
                    <form
                        action="{{ route('dashboard.projects.fill-closure-docs', $project) }}"
                        method="post"
                        enctype="multipart/form-data"
                        data-closure-docs-form
                    >
                        @csrf
                        @if ($requiresFillOnBehalfConfirm ?? false)
                            <input type="hidden" name="fill_on_behalf" value="1">
                        @endif
                        @include('dashboard.projects._checklist_display', [
                            'groups' => $groups,
                            'values' => $values,
                            'valueLabels' => $valueLabels,
                            'valueField' => 'coordinator_value',
                            'readinessBreakdown' => $readinessBreakdown ?? null,
                            'project' => $project,
                            'closureFileEditMode' => true,
                        ])
                        @if ($project->planned_end_date)
                            <div class="form-text mb-3 mt-2">
                                تاريخ نهاية التنفيذ المخطط: <strong>{{ $project->planned_end_date->format('Y-m-d') }}</strong>
                                — الرفع بعد هذا التاريخ يُخصم من نسبة الجاهزية (معامل {{ ($closureLateScore ?? 0.5) * 100 }}%).
                            </div>
                        @endif
                        <button type="submit" class="btn btn-primary">حفظ مستندات الإغلاق</button>
                    </form>
                @else
                    @include('dashboard.projects._checklist_display', [
                        'groups' => $groups,
                        'values' => $values,
                        'valueLabels' => $valueLabels,
                        'valueField' => 'coordinator_value',
                        'readinessBreakdown' => $readinessBreakdown ?? null,
                        'project' => $project,
                    ])
                @endif
            @endif
        </div>
    </div>
    @endif

    {{-- قائمة التحقق — عمود المراقب (عرض فقط هنا، التعديل من شاشة المراقب المعزولة) --}}
    @if ($canViewMonitorData ?? false)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if (! ($isAssignedMonitor ?? false))
                    <span class="badge bg-label-secondary">عرض فقط — التعديل من شاشة المراقب</span>
                @endif
                <span>نسبة الجاهزية: <strong class="checklist-overall-pct">{{ $project->monitor_readiness_pct !== null ? $project->monitor_readiness_pct . '%' : '—' }}</strong></span>
            </div>
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
    @endif
    @endif

    @php
        $coordinatorChecklistEditable = (
            in_array($project->workflow_status, ['pending_coordinator', 'coordinator_filling'])
            || ($showCoordinatorFillOnDraft ?? false)
        ) && ($canManageCoordinatorColumn ?? false);
        $showAttachmentDeleteModal = ($canManageCoordinatorColumn ?? false)
            || $coordinatorChecklistEditable
            || ($canFillClosureDocs ?? false);
    @endphp

    @if ($showAttachmentDeleteModal)
        @include('dashboard.projects._checklist_attachment_delete_modal')
        @include('dashboard.projects._checklist_attachment_upload_modal')
    @endif

    @push('scripts')
        <script src="{{ asset('js/checklist-status-style.js') }}"></script>
        <script src="{{ asset('js/checklist-attachment-ui.js') }}"></script>
        <script src="{{ asset('js/checklist-readiness.js') }}"></script>
        <script src="{{ asset('js/checklist-person-required.js') }}"></script>
        <script src="{{ asset('js/checklist-closure-docs.js') }}"></script>
        <script>
            function initCoordinatorChecklistUi(root) {
                const scope = root || document;

                if (window.initChecklistReadiness) {
                    window.initChecklistReadiness(scope);
                }
                if (window.initChecklistStatusStyle) {
                    window.initChecklistStatusStyle(scope);
                }
                if (window.initChecklistPersonRequired) {
                    window.initChecklistPersonRequired(scope);
                }
                if (window.initChecklistClosureDocs) {
                    window.initChecklistClosureDocs(scope);
                }
                if (window.initChecklistAttachmentUi) {
                    window.initChecklistAttachmentUi(scope);
                }
                if (window.refreshChecklistReadiness) {
                    window.refreshChecklistReadiness(scope);
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                initCoordinatorChecklistUi(document);

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

                    if (enabled) {
                        initCoordinatorChecklistUi(panel);
                    }
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
