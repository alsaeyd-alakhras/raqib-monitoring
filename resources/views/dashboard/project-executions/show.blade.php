<x-front-layout>
    @if ($errors->has('coordinator'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ $errors->first('coordinator') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.projects.index') }}">المشاريع</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.projects.show', $project) }}">{{ $project->project_number }}</a></li>
                    <li class="breadcrumb-item active">{{ $execution->region_name }}</li>
                </ol>
            </nav>
            <h4 class="mb-1">{{ $execution->displayLabel() }}</h4>
            <p class="text-muted mb-0">
                <span class="badge bg-label-info">{{ $statusLabels[$execution->workflow_status] ?? $execution->workflow_status }}</span>
                · المنسق: <strong>{{ $execution->coordinatorDisplayName() }}</strong>
            </p>
        </div>
        <a href="{{ route('dashboard.projects.show', $project) }}" class="btn btn-label-secondary">رجوع للمشروع</a>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">ملخص المشروع</h5></div>
        <div class="card-body pt-3">
            @include('dashboard.projects._project_summary', [
                'compactLayout' => true,
                'executionPathMode' => true,
                'execution' => $execution,
                'showActions' => false,
                'showCoordinatorInSummary' => false,
                'canViewMonitorData' => $canViewMonitorData ?? false,
                'canManageCoordinatorColumn' => $canManageCoordinatorColumn ?? false,
                'executionRegionsForDisplay' => $executionRegionsForDisplay ?? null,
                'executionRegionsBeneficiariesTotal' => $executionRegionsBeneficiariesTotal ?? null,
            ])
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">سير العمل — المسار</h5></div>
        <div class="card-body">
            @include('dashboard.project-executions._workflow_stepper', [
                'execution' => $execution,
                'canViewMonitorData' => $canViewMonitorData ?? false,
            ])

            @include('dashboard.project-executions._rejection_history')

            @if ($execution->workflow_status === 'rejected' && ($canViewRejectionHistory ?? false))
                <div class="alert alert-danger mt-3">
                    <div><strong>رفض قاطع نهائي</strong></div>
                    <div><strong>سبب الرفض:</strong> {{ $execution->rejection_reason }}</div>
                    <div><strong>مسؤولية النقص:</strong> {{ \App\Models\Project::gapOwnerLabel($execution->gap_owner) }}</div>
                    <div><strong>رُفض بواسطة:</strong> {{ $execution->rejectedByUser?->name ?? '—' }}</div>
                    <div><strong>رُفض بتاريخ:</strong> {{ $execution->rejected_at?->format('Y-m-d H:i') }}</div>
                </div>
            @endif
        </div>
    </div>

    @if ($showWorkflowActions ?? false)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">إجراءات المسار</h5></div>
            <div class="card-body d-flex flex-wrap gap-2">
                @if ($execution->workflow_status === 'pending_section_manager' && $execution->approvableBySectionManager(auth()->user()?->person))
                    <div class="d-flex flex-wrap gap-2 mb-0">
                        <form action="{{ route('dashboard.projects.executions.approve-section', [$project, $execution]) }}" method="post" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">موافقة مدير القسم</button>
                        </form>
                        @if ($canRejectExecution ?? false)
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#executionRejectModal">
                                رفض المسار
                            </button>
                        @endif
                    </div>
                @endif

                @if ($execution->workflow_status === 'pending_dept_manager' && $execution->approvableByDepartmentManager(auth()->user()?->person))
                    <div class="d-flex flex-wrap gap-2 mb-0">
                        <form action="{{ route('dashboard.projects.executions.approve-department', [$project, $execution]) }}" method="post" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">موافقة مدير الدائرة</button>
                        </form>
                        @if ($canRejectExecution ?? false)
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#executionRejectModal">
                                رفض المسار
                            </button>
                        @endif
                    </div>
                @endif

                @if ($canViewMonitorData ?? false)
                    @if ($execution->workflow_status === 'monitoring_in_progress' && $execution->isAssignedMonitor(auth()->user()))
                        <a href="{{ route('dashboard.projects.executions.monitor-work', [$project, $execution]) }}" class="btn btn-primary">عمل المراقب</a>
                    @endif
                @endif
            </div>
        </div>
    @endif

    @if ($canManageMonitoringSetup ?? false)
        @include('dashboard.project-executions._monitoring_setup_panel')
    @endif

    @if (in_array($execution->workflow_status, ['monitoring_in_progress', 'pending_monitoring_confirmation'], true) && ($canViewMonitoringStatusPanel ?? false))
        @include('dashboard.project-executions._monitoring_status_panel')
    @endif

    @include('dashboard.project-executions._reject_modal')

    @if ($errors->has('rejection_reason') || $errors->has('gap_owner') || $errors->has('return_target'))
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('executionRejectModal');
                if (modalEl && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
        </script>
        @endpush
    @endif

    @if ($canViewMergedChecklist ?? false)
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">قائمة التحقق — المنسق والمراقب</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-label-primary">المنسق: <strong>{{ $execution->coordinator_readiness_pct !== null ? number_format($execution->coordinator_readiness_pct, 1) . '%' : '—' }}</strong></span>
                    <span class="badge bg-label-info">المراقب: <strong>{{ $execution->monitor_readiness_pct !== null ? number_format($execution->monitor_readiness_pct, 1) . '%' : '—' }}</strong></span>
                </div>
            </div>
            <div class="card-body">
                @include('dashboard.projects._checklist_merged_display', [
                    'groups' => $groups,
                    'values' => $values,
                    'valueLabels' => $valueLabels,
                    'readinessBreakdown' => $readinessBreakdown ?? null,
                ])
                @include('dashboard.projects._monitor_notes_display', ['execution' => $execution])
            </div>
        </div>
    @else
    @if ($canViewCoordinatorData ?? false)
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">قائمة التحقق — عمود المنسق</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if (! ($canManageCoordinatorColumn ?? false) && ($canFillClosureDocs ?? false))
                        <span class="badge bg-label-info">رفع مستندات الإغلاق</span>
                    @elseif (! ($canManageCoordinatorColumn ?? false))
                        <span class="badge bg-label-secondary">عرض فقط</span>
                    @endif
                    @if (in_array($execution->workflow_status, ['pending_coordinator', 'coordinator_filling'], true))
                        <span class="badge bg-label-warning">بانتظار تعبئة المنسق</span>
                    @endif
                    <span>نسبة الجاهزية: <strong class="checklist-overall-pct">{{ $execution->coordinator_readiness_pct !== null ? number_format($execution->coordinator_readiness_pct, 1) . '%' : '—' }}</strong></span>
                </div>
            </div>
            <div class="card-body">
                @if (($canManageCoordinatorColumn ?? false))
                    <form action="{{ route('dashboard.projects.executions.fill-coordinator', [$project, $execution]) }}" method="post" enctype="multipart/form-data" data-checklist-readiness>
                        @csrf
                        @include('dashboard.projects._implementation_mechanism_field', [
                            'implementationMechanism' => $execution->implementation_mechanism,
                            'fieldId' => 'execution_implementation_mechanism',
                        ])
                        @include('dashboard.projects._recipient_fields', [
                            'recipientName' => $execution->recipient_name,
                            'recipientPhone' => $execution->recipient_phone,
                        ])
                        @include('dashboard.projects._checklist_edit', [
                            'groups' => $groups,
                            'values' => $values,
                            'valueLabels' => $valueLabels,
                            'readinessBreakdown' => $readinessBreakdown ?? null,
                            'project' => $project,
                            'deleteAttachmentUrl' => $deleteAttachmentUrl ?? null,
                            'prefix' => 'checklist',
                            'valueField' => 'coordinator_value',
                            'projectExecutionId' => $execution->id,
                        ])
                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">حفظ وإرسال لمدير القسم</button>
                        </div>
                    </form>
                @else
                    @php
                        $closureFileEditMode = ($canFillClosureDocs ?? false);
                    @endphp
                    @if ($closureFileEditMode)
                        <form
                            action="{{ route('dashboard.projects.executions.fill-closure-docs', [$project, $execution]) }}"
                            method="post"
                            enctype="multipart/form-data"
                            data-closure-docs-form
                        >
                            @csrf
                            @include('dashboard.projects._checklist_display', [
                                'groups' => $groups,
                                'values' => $values,
                                'valueLabels' => $valueLabels,
                                'valueField' => 'coordinator_value',
                                'readinessBreakdown' => $readinessBreakdown ?? null,
                                'project' => $project,
                                'closureFileEditMode' => true,
                                'deleteAttachmentUrl' => $deleteAttachmentUrl ?? null,
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

    @if (($canViewMonitorData ?? false) && in_array($execution->workflow_status, ['monitoring_in_progress', 'pending_monitoring_confirmation', 'passage_complete'], true))
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
                ])
            </div>
        </div>
    @endif
    @endif

    @php
        $showCoordinatorChecklistUi = ($canManageCoordinatorColumn ?? false) || ($canFillClosureDocs ?? false);
    @endphp

    @if ($showCoordinatorChecklistUi)
        @include('dashboard.projects._checklist_attachment_delete_modal')
        @include('dashboard.projects._checklist_attachment_upload_modal')
    @endif

    @if ($showCoordinatorChecklistUi)
        @push('scripts')
            <script src="{{ asset('js/checklist-status-style.js') }}"></script>
            <script src="{{ asset('js/checklist-attachment-ui.js') }}"></script>
            <script src="{{ asset('js/checklist-readiness.js') }}"></script>
            <script src="{{ asset('js/checklist-person-required.js') }}"></script>
            <script src="{{ asset('js/checklist-closure-docs.js') }}"></script>
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
                    if (window.initChecklistClosureDocs) {
                        window.initChecklistClosureDocs(document);
                    }
                    if (window.initChecklistAttachmentUi) {
                        window.initChecklistAttachmentUi(document);
                    }
                    if (window.refreshChecklistReadiness) {
                        window.refreshChecklistReadiness(document);
                    }
                });
            </script>
        @endpush
    @endif

    @if (session('success') || session('warning') || session('danger') || $errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            </script>
        @endpush
    @endif
</x-front-layout>
