<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\ChecklistGroup;
use App\Models\Constant;
use App\Models\Department;
use App\Models\Funder;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectChecklistValue;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Yajra\DataTables\Facades\DataTables;

class ProjectController extends Controller
{
    private const STATUSES = [
        'draft', 'pending_coordinator', 'coordinator_filling',
        'pending_project_manager', 'pending_dept_manager', 'pending_monitoring_manager',
        'monitoring_in_progress', 'pending_monitoring_confirmation',
        'passage_complete', 'rejected',
    ];

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('view', Project::class);

        $query = Project::with(['center', 'department', 'projectManager', 'coordinator', 'monitorPerson', 'funder', 'primaryMonitoringActivity']);
        $query = $this->applyVisibilityScope($query);

        if ($request->ajax()) {
            if ($request->from_date) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->to_date) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
            if ($request->column_filters) {
                $this->applyColumnFilters($query, $request->column_filters);
            }
            $this->applySort($query, $request->sort_column, $request->sort_direction);

            $workflowLabels = Project::workflowStatusLabels();
            $currentPerson = auth()->user()?->person;

            $rows = $query->get()->map(function (Project $project) use ($workflowLabels, $currentPerson) {
                $needsMyAction = $currentPerson && $project->needsActionFromPerson($currentPerson);

                return [
                    'id' => $project->id,
                    'project_number' => $project->project_number ?? '-',
                    'project_name' => $project->project_name,
                    'project_name_display' => $needsMyAction
                        ? $project->project_name . ' ⚡'
                        : $project->project_name,
                    'project_type' => $project->project_type ?? '-',
                    'org_label' => trim(($project->center?->name ?? '-') . ' / ' . ($project->department?->name ?? '-')),
                    'project_manager_name' => $project->projectManager?->name ?? '-',
                    'coordinator_name' => $project->coordinatorDisplayName(),
                    'coordinator_readiness_pct' => $project->coordinator_readiness_pct !== null
                        ? number_format((float) $project->coordinator_readiness_pct, 1) . '%'
                        : '-',
                    'monitor_name' => $project->monitorPerson?->name ?? '-',
                    'monitor_readiness_pct' => $project->monitor_readiness_pct !== null
                        ? number_format((float) $project->monitor_readiness_pct, 1) . '%'
                        : '-',
                    'funder_name' => $project->funder?->name ?? '-',
                    'workflow_status_label' => $workflowLabels[$project->workflow_status] ?? $project->workflow_status,
                    'current_action_label' => $project->currentActionLabel(),
                    'needs_my_action' => $needsMyAction,
                ];
            })->values();

            return DataTables::of($rows)
                ->addIndexColumn()
                ->make(true);
        }

        return view('dashboard.projects.index', [
            'canViewCoordinatorColumnInList' => $this->userCanViewCoordinatorColumnInList(),
            'canViewMonitorColumnInList' => $this->userCanViewMonitorColumnInList(),
        ]);
    }

    public function getFilterOptions(Request $request, string $column): JsonResponse
    {
        $this->authorize('view', Project::class);

        $query = Project::with(['center', 'department', 'projectManager', 'coordinator', 'monitorPerson', 'funder']);
        $query = $this->applyVisibilityScope($query);

        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        if ($request->active_filters) {
            $this->applyColumnFilters($query, $request->active_filters);
        }

        $rows = $query->get();
        $workflowLabels = Project::workflowStatusLabels();

        $options = match ($column) {
            'project_number' => $rows->pluck('project_number')->filter()->unique()->values()->toArray(),
            'project_name' => $rows->pluck('project_name')->filter()->unique()->values()->toArray(),
            'project_type' => $rows->pluck('project_type')->filter()->unique()->values()->toArray(),
            'org_label' => $rows->map(fn ($p) => trim(($p->center?->name ?? '-') . ' / ' . ($p->department?->name ?? '-')))->unique()->values()->toArray(),
            'project_manager_name' => $rows->pluck('projectManager.name')->filter()->unique()->values()->toArray(),
            'coordinator_name' => $rows->map(fn ($p) => $p->coordinatorDisplayName())->unique()->values()->toArray(),
            'coordinator_readiness_pct' => $rows->map(fn ($p) => $p->coordinator_readiness_pct !== null ? number_format((float) $p->coordinator_readiness_pct, 1) . '%' : null)->filter()->unique()->values()->toArray(),
            'monitor_name' => $rows->pluck('monitorPerson.name')->filter()->unique()->values()->toArray(),
            'monitor_readiness_pct' => $rows->map(fn ($p) => $p->monitor_readiness_pct !== null ? number_format((float) $p->monitor_readiness_pct, 1) . '%' : null)->filter()->unique()->values()->toArray(),
            'funder_name' => $rows->pluck('funder.name')->filter()->unique()->values()->toArray(),
            'workflow_status_label' => $rows->map(fn ($p) => $workflowLabels[$p->workflow_status] ?? $p->workflow_status)->unique()->values()->toArray(),
            'current_action_label' => $rows->map(fn ($p) => $p->currentActionLabel())->unique()->values()->toArray(),
            'created_at' => $rows->pluck('created_at')->filter()->map(fn ($d) => $d->format('Y-m-d'))->unique()->values()->toArray(),
            default => [],
        };

        return response()->json($options);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('dashboard.projects.create', $this->formData(new Project()) + ['project' => new Project()]);
    }

    public function checkProjectNumber(Request $request)
    {
        $this->authorize('view', Project::class);

        if ($request->filled('project_number_seq')) {
            $number = Project::formatFromSequence((int) $request->query('project_number_seq'));
        } else {
            $number = Project::normalizeProjectNumber((string) $request->query('project_number', ''));
        }
        $exceptId = $request->query('except_id');

        if ($number === '' || $number === 'P-0') {
            return response()->json([
                'valid' => false,
                'available' => false,
                'message' => 'أدخل رقم المشروع.',
            ]);
        }

        if (! Project::isValidProjectNumberFormat($number)) {
            return response()->json([
                'valid' => false,
                'available' => false,
                'normalized' => $number,
                'message' => 'أدخل رقماً صحيحاً فقط (بدون P-).',
            ]);
        }

        $exceptProjectId = filled($exceptId) ? (int) $exceptId : null;
        $available = Project::isProjectNumberAvailable($number, $exceptProjectId);
        $suggested = Project::generateProjectNumber();

        return response()->json([
            'valid' => true,
            'available' => $available,
            'normalized' => $number,
            'sequence' => Project::sequenceFromProjectNumber($number),
            'message' => $available ? 'الرقم متاح.' : 'رقم المشروع مستخدم مسبقاً.',
            'suggested' => $available ? null : $suggested,
            'suggested_sequence' => $available ? null : Project::sequenceFromProjectNumber($suggested),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $validated = $this->validateProject($request);
        $validated['project_number'] = $this->resolveProjectNumberFromValidated($validated);
        unset($validated['project_number_seq']);
        $validated['workflow_status'] = 'draft';
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $project = Project::create($validated);

        $this->saveCoordinatorChecklistFromForm($request, $project->fresh());

        return redirect()
            ->route('dashboard.projects.show', $project)
            ->with('success', 'تم إنشاء المشروع بنجاح.');
    }

    public function show(Project $project): View|RedirectResponse
    {
        $this->authorize('view', Project::class);
        $this->ensureProjectVisible($project);

        if ($this->shouldRedirectMonitorToWork($project)) {
            return redirect()->route('dashboard.projects.monitor-work', $project);
        }

        $project->load(['center', 'department', 'section', 'funder', 'procurementRep', 'projectManager.department', 'monitorPerson', 'primaryMonitoringActivity', 'rejectedByUser', 'coordinatorFilledByUser', 'rejections.rejectedByUser', 'rejections.returnTargetPerson']);
        $project->syncMonitoringWorkflowState();
        $project->refresh();

        if (! $this->canViewCoordinatorData($project)) {
            $project->unsetRelation('coordinator');
            $project->makeHidden(['coordinator_id', 'coordinator_readiness_pct']);
        } else {
            $project->load('coordinator');
        }

        if (! $this->canViewMonitorData($project)) {
            $project->unsetRelation('monitorPerson');
            $project->makeHidden([
                'monitor_person_id',
                'monitor_readiness_pct',
                'monitor_notes',
                'monitor_recommendations',
                'monitoring_date',
                'monitoring_method',
                'monitoring_stage',
            ]);
        }

        $groups = $this->activeChecklistGroups();
        $values = $project->checklistValues()->get()->keyBy('checklist_item_id');
        $monitors = Person::withRole('monitor')->orderBy('name')->get();

        return view('dashboard.projects.show', $this->showViewData($project, $groups, $values, $monitors));
    }

    public function exportPdf(Project $project)
    {
        $this->authorize('view', Project::class);
        $this->ensureProjectVisible($project);

        $pdf = PDF::loadView(
            'reports.projects.pdf',
            $this->buildPdfReportData($project),
            [],
            config('pdf')
        );

        $filename = 'تقرير جاهزية المشروع ' . ($project->project_number ?? $project->id) . '.pdf';

        return $pdf->stream($filename);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', Project::class);
        $this->ensureProjectVisible($project);

        return view('dashboard.projects.edit', $this->formData($project) + ['project' => $project]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', Project::class);
        $this->ensureProjectVisible($project);

        $previousCoordinatorId = $project->coordinator_id;
        $previousExternalName = $project->coordinator_external_name;

        $validated = $this->validateProject($request, $project);
        $validated['project_number'] = $this->resolveProjectNumberFromValidated($validated, $project);
        unset($validated['project_number_seq']);
        $validated['updated_by'] = auth()->id();

        $project->update($validated);

        $this->clearCoordinatorChecklistIfChanged(
            $project,
            $previousCoordinatorId,
            $previousExternalName
        );

        $project->refresh();
        $this->saveCoordinatorChecklistFromForm($request, $project);

        return redirect()
            ->route('dashboard.projects.show', $project)
            ->with('success', 'تم تحديث المشروع بنجاح.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', Project::class);
        $this->ensureProjectVisible($project);

        $project->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => 'تم حذف المشروع بنجاح.']);
        }

        return redirect()
            ->route('dashboard.projects.index')
            ->with('success', 'تم حذف المشروع بنجاح.');
    }

    /* ===================== Workflow: السلسلة الأولى ===================== */

    public function submitToCoordinator(Project $project): RedirectResponse
    {
        $this->authorize('update', Project::class);
        $this->guardStatus($project, ['draft']);

        if (! $project->hasCoordinatorAssignment()) {
            return back()->withErrors(['coordinator_mode' => 'يجب تحديد المنسق (من النظام، خارجي، أو أنت كمنسق) قبل الإرسال.']);
        }

        $project->update([
            'workflow_status' => 'pending_coordinator',
            'coordinator_submitted_at' => now(),
            'coordinator_submitted_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->clearProjectReturnNotice($project);

        return back()->with('success', 'تم إرسال المشروع للمنسق.');
    }

    public function fillCoordinator(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('fill_coordinator', Project::class);
        $this->authorizeCoordinatorFill($project);
        $this->guardCoordinatorFillStatus($project);
        $this->validateCoordinatorFillOnBehalf($request, $project);

        $this->saveChecklistValues($request, $project, 'coordinator_value');

        $this->recordCoordinatorFilledBy($request, $project);

        if (in_array($project->workflow_status, ['pending_coordinator', 'draft'], true)) {
            $project->update(['workflow_status' => 'coordinator_filling']);
        }

        $project->recalculateReadiness();

        return back()->with('success', 'تم حفظ عمود المنسق.');
    }

    public function submitToProjectManager(Project $project): RedirectResponse
    {
        $this->authorize('fill_coordinator', Project::class);
        $this->guardStatus($project, ['coordinator_filling']);
        $this->authorizeCoordinatorFill($project);
        $project->loadMissing('checklistValues');

        if (! $this->coordinatorChecklistReadyForSubmission($project)) {
            return back()->withErrors([
                'coordinator' => 'لا يمكن الإرسال لمدير المشروع قبل اكتمال تعبئة جميع بنود قائمة المنسق.',
            ]);
        }

        $project->update([
            'workflow_status' => 'pending_project_manager',
            'updated_by' => auth()->id(),
        ]);
        $this->clearProjectReturnNotice($project);

        return back()->with('success', 'تم إرسال المشروع لمدير المشروع.');
    }

    public function submitToDeptManager(Project $project): RedirectResponse
    {
        $this->authorize('update', Project::class);
        $this->guardStatus($project, ['pending_project_manager']);
        $this->authorizeProjectManagerReview($project);
        $project->loadMissing('checklistValues');

        if (! $this->coordinatorChecklistReadyForSubmission($project)) {
            return back()->withErrors([
                'coordinator' => 'لا يمكن الإرسال لمدير الدائرة قبل اكتمال تعبئة المنسق.',
            ]);
        }

        $project->update([
            'workflow_status' => 'pending_dept_manager',
            'updated_by' => auth()->id(),
        ]);
        $this->clearProjectReturnNotice($project);

        return back()->with('success', 'تم إرسال المشروع لمدير الدائرة.');
    }

    public function approveDepartment(Project $project): RedirectResponse
    {
        $this->authorize('approve_department', Project::class);
        $this->guardStatus($project, ['pending_dept_manager']);
        $this->authorizeDepartmentApproval($project);

        $project->update([
            'workflow_status' => 'pending_monitoring_manager',
            'dept_manager_approved_at' => now(),
            'dept_manager_approved_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->clearProjectReturnNotice($project);

        return back()->with('success', 'تمت الموافقة، أُرسل المشروع لمدير الرقابة العامة.');
    }

    public function setMonitoringInfo(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('set_monitoring_info', MonitoringActivity::class);
        $this->guardStatus($project, ['pending_monitoring_manager']);

        $validated = $request->validate([
            'monitoring_method' => ['nullable', 'string'],
            'monitoring_stage' => ['nullable', 'string'],
        ]);

        $validated['updated_by'] = auth()->id();

        if (! $project->monitoring_manager_received_at) {
            $validated['monitoring_manager_received_at'] = now();
            $validated['monitoring_manager_received_by'] = auth()->id();
        }

        $project->update($validated);

        return back()->with('success', 'تم حفظ طريقة/مرحلة المراقبة.');
    }

    public function assignMonitor(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('assign_monitor', MonitoringActivity::class);
        $this->guardStatus($project, ['pending_monitoring_manager']);

        if (! $project->center_id || ! $project->department_id) {
            return back()->withErrors([
                'center_id' => 'يجب تحديد المركز والدائرة في بيانات المشروع قبل تعيين المراقب.',
            ]);
        }

        $validated = $request->validate([
            'monitor_person_id' => [
                'required',
                Rule::exists('people', 'id')->where('role', 'monitor'),
            ],
            'monitoring_date' => ['nullable', 'date'],
        ]);

        $project->update($validated + [
            'monitoring_manager_received_at' => $project->monitoring_manager_received_at ?? now(),
            'monitoring_manager_received_by' => $project->monitoring_manager_received_by ?? auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $activity = MonitoringActivity::create([
            'reference_code' => $this->generateReferenceCode(),
            'source_type' => 'project',
            'source_id' => $project->id,
            'activity_role' => 'primary',
            'center_id' => $project->center_id,
            'department_id' => $project->department_id,
            'section_id' => $project->section_id,
            'monitor_person_id' => $project->monitor_person_id,
            'funder_id' => $project->funder_id,
            'monitoring_method' => $project->monitoring_method,
            'monitoring_stage' => $project->monitoring_stage,
            'subject' => $project->project_name,
            'field_problem' => false,
            'workflow_status' => 'in_progress',
            'is_passage_complete' => false,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $project->update([
            'primary_monitoring_activity_id' => $activity->id,
            'workflow_status' => 'monitoring_in_progress',
        ]);

        return back()->with('success', 'تم تعيين المراقب وبدء المراقبة.');
    }

    /* ===================== شاشة المراقب المعزولة ===================== */

    public function monitorWork(Project $project): View
    {
        $this->authorize('fill_monitor', Project::class);
        $this->guardStatus($project, ['monitoring_in_progress', 'pending_monitoring_confirmation', 'passage_complete']);
        $this->authorizeMonitorFill($project);

        // عزل بيانات المنسق على مستوى الاستعلام: لا يُحمَّل coordinator_value إطلاقاً
        $project->loadMissing([
            'center', 'department', 'section', 'funder', 'procurementRep',
            'projectManager.department', 'coordinator', 'primaryMonitoringActivity',
        ]);
        $project->syncMonitoringWorkflowState();
        $project->refresh();

        $groups = $this->activeChecklistGroups();
        $values = $project->checklistValues()
            ->select(['id', 'project_id', 'checklist_item_id', 'monitor_value', 'person_name'])
            ->get()
            ->keyBy('checklist_item_id');

        $canSubmitToDirector = $project->canMonitorSubmitToDirector();
        $awaitingDirector = $project->awaitingMonitoringDirectorConfirmation();
        $canEditMonitorColumn = $project->workflow_status === 'monitoring_in_progress';
        $canShowMonitorSubmitSection = $this->isMonitorSubmitUnlocked($project)
            && $canSubmitToDirector
            && $project->isAssignedMonitor(auth()->user());

        return view('dashboard.projects.monitor-work', compact(
            'project',
            'groups',
            'values',
            'canSubmitToDirector',
            'awaitingDirector',
            'canEditMonitorColumn',
            'canShowMonitorSubmitSection',
        ) + [
            'isAssignedMonitor' => $project->isAssignedMonitor(auth()->user()),
            'showCoordinatorInSummary' => true,
            'canViewCoordinatorData' => false,
            'canViewMonitorData' => true,
            'approverDepartmentManager' => $project->approverDepartmentManager(),
            'approverDepartmentManagerLabel' => $project->approverDepartmentManagerLabel(),
            'projectManagerDepartmentName' => $project->projectManagerDepartmentName(),
            'readinessBreakdown' => $project->readinessBreakdown(),
        ]);
    }

    public function fillMonitor(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('fill_monitor', Project::class);
        $this->authorizeMonitorFill($project);
        $this->guardStatus($project, ['monitoring_in_progress']);

        $this->saveChecklistValues($request, $project, 'monitor_value');

        $validated = $request->validate([
            'monitor_notes_text' => ['nullable', 'string'],
            'monitor_recommendations_text' => ['nullable', 'string'],
        ]);

        $project->update([
            'monitor_notes' => $this->linesToArray($validated['monitor_notes_text'] ?? ''),
            'monitor_recommendations' => $this->linesToArray($validated['monitor_recommendations_text'] ?? ''),
            'updated_by' => auth()->id(),
        ]);

        $project->recalculateReadiness();
        $this->unlockMonitorSubmit($project);

        return redirect()
            ->route('dashboard.projects.monitor-work', $project)
            ->with('success', 'تم حفظ عمل المراقب — يمكنك الآن الإرسال لمدير الرقابة العامة من الأسفل.');
    }

    public function confirmMonitoring(Project $project): RedirectResponse
    {
        $this->authorize('fill_monitor', Project::class);
        $this->guardStatus($project, ['monitoring_in_progress']);
        $this->authorizeMonitorFill($project);

        if (! $this->isMonitorSubmitUnlocked($project) || ! $this->monitorChecklistReadyForSubmission($project)) {
            return redirect()
                ->route('dashboard.projects.monitor-work', $project)
                ->withErrors(['monitor' => 'يجب حفظ وتعبئة جميع بنود قائمة التحقق قبل الإرسال لمدير الرقابة.']);
        }

        $activity = $project->primaryMonitoringActivity;

        if (! $activity) {
            return back()->withErrors(['monitor' => 'لا يوجد نشاط رقابي أساسي مرتبط بهذا المشروع.']);
        }

        if ($activity->workflow_status !== 'in_progress') {
            abort(422, 'حالة النشاط الحالية لا تسمح بتأكيد إنهاء المراقبة.');
        }

        $activity->update([
            'workflow_status' => 'pending_confirmation',
            'updated_by' => auth()->id(),
        ]);

        $project->update([
            'workflow_status' => 'pending_monitoring_confirmation',
            'updated_by' => auth()->id(),
        ]);

        $this->lockMonitorSubmit($project);

        return redirect()
            ->route('dashboard.projects.monitor-work', $project)
            ->with('success', 'تم إرسال عمل المراقب لمدير الرقابة العامة — بانتظار تأكيد المرور.');
    }

    private function monitorSubmitSessionKey(Project $project): string
    {
        return 'monitor_submit_unlocked.'.$project->id.'.'.auth()->id();
    }

    private function isMonitorSubmitUnlocked(Project $project): bool
    {
        return (bool) session($this->monitorSubmitSessionKey($project), false);
    }

    private function unlockMonitorSubmit(Project $project): void
    {
        session()->put($this->monitorSubmitSessionKey($project), true);
    }

    private function lockMonitorSubmit(Project $project): void
    {
        session()->forget($this->monitorSubmitSessionKey($project));
    }

    public function confirmPassage(Project $project): RedirectResponse
    {
        $this->authorize('confirm_completion', MonitoringActivity::class);
        $this->ensureProjectVisible($project);
        $project->syncMonitoringWorkflowState();
        $project->refresh();

        if (! in_array($project->workflow_status, ['pending_monitoring_confirmation', 'monitoring_in_progress'], true)) {
            abort(422, 'حالة المشروع الحالية لا تسمح بتأكيد المرور.');
        }

        $activity = $project->primaryMonitoringActivity;

        if (! $activity) {
            return back()->withErrors(['monitor' => 'لا يوجد نشاط رقابي أساسي مرتبط بهذا المشروع.']);
        }

        if (! in_array($activity->workflow_status, ['pending_confirmation', 'in_progress'], true)) {
            abort(422, 'حالة النشاط الحالية لا تسمح بتأكيد اكتمال المرور.');
        }

        $project->completePassage((int) auth()->id());

        return back()->with('success', 'تم تأكيد المرور — المشروع مكتمل.');
    }

    /* ===================== الرفض وإعادة التوجيه ===================== */

    public function reject(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('reject', Project::class);
        $this->ensureProjectVisible($project);
        $this->authorizeProjectReject($project);

        $user = auth()->user();
        $allowedReturnTargets = array_keys(Project::returnTargetOptionsForRejector($user?->person, (bool) $user?->super_admin));
        $allowedGapOwners = array_keys(Project::gapOwnerOptionsForRejector($user?->person, (bool) $user?->super_admin));

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
            'gap_owner' => ['required', 'string', Rule::in($allowedGapOwners)],
            'return_target' => ['required', 'string', Rule::in($allowedReturnTargets)],
        ]);

        $returnTarget = $validated['return_target'];
        $nextStatus = Project::workflowStatusForReturnTarget($returnTarget);
        $statusBefore = $project->workflow_status;

        if ($nextStatus === null) {
            abort(422, 'خيار الإرجاع غير صالح.');
        }

        $payload = [
            'rejection_reason' => $validated['rejection_reason'],
            'gap_owner' => $validated['gap_owner'],
            'workflow_status' => $nextStatus,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'updated_by' => auth()->id(),
        ];

        if ($returnTarget !== 'reject_final') {
            $payload['return_target'] = $returnTarget;
        } else {
            $payload['return_target'] = null;
        }

        $project->update($payload);

        $project->rejections()->create([
            'rejection_reason' => $validated['rejection_reason'],
            'gap_owner' => $validated['gap_owner'],
            'return_target' => $returnTarget === 'reject_final' ? null : $returnTarget,
            'return_target_person_id' => $project->fresh()->personIdForReturnTarget(
                $returnTarget === 'reject_final' ? null : $returnTarget
            ),
            'workflow_status_before' => $statusBefore,
            'workflow_status_after' => $nextStatus,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
        ]);

        $message = $returnTarget === 'reject_final'
            ? 'تم رفض المشروع نهائياً.'
            : 'تم إرجاع المشروع للجهة المحددة مع تسجيل سبب الرفض.';

        return back()->with('success', $message);
    }

    public function reroute(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('reject', Project::class);

        $validated = $request->validate([
            'workflow_status' => ['required', 'in:' . implode(',', self::STATUSES)],
        ]);

        $project->update($validated + ['updated_by' => auth()->id()]);

        return back()->with('success', 'تم تحديث حالة المشروع.');
    }

    /* ===================== Helpers ===================== */

    private function guardStatus(Project $project, array $allowed): void
    {
        if (! in_array($project->workflow_status, $allowed, true)) {
            abort(422, 'حالة المشروع الحالية لا تسمح بهذا الإجراء.');
        }
    }

    private function clearProjectReturnNotice(Project $project): void
    {
        if ($project->hasPendingReturnNotice()) {
            $project->clearReturnNotice();
        }
    }

    private function activeChecklistGroups()
    {
        return ChecklistGroup::where('is_active', true)
            ->orderBy('order')
            ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('order')])
            ->get();
    }

    private function saveChecklistValues(Request $request, Project $project, string $column): void
    {
        if (! $request->has('checklist') || ! is_array($request->input('checklist'))) {
            return;
        }

        $activeItemIds = $this->activeChecklistItemIds();
        $rules = ['checklist' => ['required', 'array']];

        foreach ($activeItemIds as $itemId) {
            $rules["checklist.{$itemId}.value"] = ['required', 'in:ready,partial,not_ready,not_required'];
            $rules["checklist.{$itemId}.person_name"] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules, [
            'checklist.*.value.required' => 'يجب تحديد حالة كل بند في قائمة التحقق.',
        ]);

        foreach ($activeItemIds as $itemId) {
            $data = $validated['checklist'][$itemId] ?? null;

            if (! is_array($data)) {
                continue;
            }

            $attributes = ['project_id' => $project->id, 'checklist_item_id' => $itemId];
            $payload = [$column => $data['value']];

            if (array_key_exists('person_name', $data)) {
                $payload['person_name'] = $data['person_name'];
            }

            ProjectChecklistValue::updateOrCreate($attributes, $payload);
        }
    }

    /** @return list<int> */
    private function activeChecklistItemIds(): array
    {
        return \App\Models\ChecklistItem::query()
            ->where('is_active', true)
            ->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->orderBy('group_id')
            ->orderBy('order')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function linesToArray(string $text): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $text)))));
    }

    private function generateReferenceCode(): string
    {
        $lastNumber = MonitoringActivity::where('reference_code', 'like', 'MP-%')
            ->selectRaw('MAX(CAST(SUBSTR(reference_code, 4) AS UNSIGNED)) as max_num')
            ->value('max_num');

        return 'MP-' . (((int) $lastNumber) + 1);
    }

    private function formData(?Project $project = null): array
    {
        $currentPerson = auth()->user()?->person;
        $lockProjectManager = $currentPerson?->role === 'project_manager';
        $coordinatorMode = $project && $project->exists
            ? $project->coordinatorMode()
            : old('coordinator_mode', 'person');

        $showCoordinatorChecklistInitially = in_array($coordinatorMode, ['self', 'external'], true)
            || ($coordinatorMode === 'person' && (
                (old('fill_on_behalf') && ! $this->coordinatorIdHasUser((int) old('coordinator_id', 0)))
                || ($project && $project->exists && $project->coordinator_filled_by && ! $project->coordinatorHasUserAccount())
            ));

        return [
            'centers' => Center::orderBy('name')->get(),
            'funders' => Funder::orderBy('name')->get(),
            'projectManagers' => Person::withRole('project_manager')->orderBy('name')->get(),
            'people' => Person::orderBy('name')->get(),
            'coordinators' => Person::withRole('coordinator')->orderBy('name')->get(),
            'coordinatorUserMap' => Person::withRole('coordinator')->get()->mapWithKeys(
                fn (Person $person) => [(string) $person->id => (bool) $person->user_id]
            )->all(),
            'projectTypes' => $this->constantOptions('project_types'),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
            'currentPerson' => $currentPerson,
            'lockProjectManager' => $lockProjectManager,
            'nextProjectNumber' => Project::generateProjectNumber(),
            'nextProjectNumberSeq' => Project::sequenceFromProjectNumber(Project::generateProjectNumber()),
            'coordinatorMode' => $coordinatorMode,
            'checkProjectNumberUrl' => route('dashboard.projects.check-project-number'),
            'exceptProjectId' => ($project && $project->exists) ? $project->id : null,
            'canFillCoordinatorInForm' => $this->canFillCoordinatorInForm(),
            'canEditProjectBasicData' => auth()->user()?->can('update', Project::class),
            'canEditCoordinatorChecklistInForm' => $this->canEditCoordinatorChecklistInForm(),
            'lockTeamFieldsForMonitoringDirector' => auth()->user()?->person?->role === 'monitoring_director',
            'checklistGroups' => $this->activeChecklistGroups(),
            'checklistValues' => ($project && $project->exists)
                ? $project->checklistValues()->get()->keyBy('checklist_item_id')
                : collect(),
            'valueLabels' => [
                'ready' => 'جاهز',
                'partial' => 'جزئي',
                'not_ready' => 'غير جاهز',
                'not_required' => 'غير مطلوب',
            ],
            'showCoordinatorChecklistInitially' => $showCoordinatorChecklistInitially,
            'selectedCenterId' => old('center_id', $project->center_id ?? ''),
            'selectedDepartmentId' => old('department_id', $project->department_id ?? ''),
            'selectedSectionId' => old('section_id', $project->section_id ?? ''),
            'departmentsByCenterUrl' => route('dashboard.departments.by-center', ['center' => '__ID__']),
            'sectionsByDepartmentUrl' => route('dashboard.sections.by-department', ['department' => '__ID__']),
        ];
    }

    private function canFillCoordinatorInForm(): bool
    {
        return $this->canEditCoordinatorChecklistInForm();
    }

    private function canEditCoordinatorChecklistInForm(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        if ($user->person?->role === 'monitoring_director') {
            return false;
        }

        return $user->can('fill_coordinator', Project::class);
    }

    private function shouldProcessCoordinatorChecklistInForm(Request $request): bool
    {
        $mode = $request->input('coordinator_mode');

        if ($mode === 'self' || $mode === 'external') {
            return true;
        }

        if ($mode === 'person') {
            if ($request->boolean('fill_on_behalf')) {
                $coordinatorId = (int) $request->input('coordinator_id', 0);
                if ($coordinatorId && Person::where('id', $coordinatorId)->whereNotNull('user_id')->exists()) {
                    return false;
                }

                return true;
            }

            return false;
        }

        return false;
    }

    private function saveCoordinatorChecklistFromForm(Request $request, Project $project): void
    {
        if (! $this->canEditCoordinatorChecklistInForm() || ! $this->shouldProcessCoordinatorChecklistInForm($request)) {
            return;
        }

        if (! $request->has('checklist')) {
            return;
        }

        $this->validateCoordinatorFillOnBehalf($request, $project);
        $this->saveChecklistValues($request, $project, 'coordinator_value');
        $this->recordCoordinatorFilledBy($request, $project);
        $project->recalculateReadiness();

        if ($project->workflow_status === 'draft') {
            $project->update(['workflow_status' => 'coordinator_filling']);
        }
    }

    private function showViewData(Project $project, $groups, $values, $monitors): array
    {
        $personId = auth()->user()?->person?->id;
        $isProjectManager = $personId && (int) $personId === (int) $project->project_manager_id;
        $isAssignedCoordinator = $personId && (int) $personId === (int) $project->coordinator_id;
        $canManageCoordinatorColumn = $this->currentUserCanManageCoordinatorColumn($project);

        return [
            'project' => $project,
            'groups' => $groups,
            'values' => $values,
            'monitors' => $monitors,
            'canViewCoordinatorData' => $this->canViewCoordinatorData($project),
            'canViewMonitorData' => $this->canViewMonitorData($project),
            'isAssignedMonitor' => $project->isAssignedMonitor(auth()->user()),
            'canSetMonitoringInfo' => auth()->user()?->can('set_monitoring_info', MonitoringActivity::class),
            'canAssignMonitor' => auth()->user()?->can('assign_monitor', MonitoringActivity::class),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
            'showCoordinatorFillOnDraft' => $this->canFillCoordinatorOnDraft($project),
            'canSubmitToProjectManager' => $this->canSubmitToProjectManager($project),
            'canSubmitToDeptManager' => $this->canSubmitToDeptManager($project),
            'canManageCoordinatorColumn' => $canManageCoordinatorColumn,
            'requiresFillOnBehalfConfirm' => $isProjectManager
                && ! $isAssignedCoordinator
                && ! $project->isSelfCoordinator()
                && ! $project->coordinatorHasUserAccount()
                && $project->hasCoordinatorAssignment()
                && $canManageCoordinatorColumn,
            'readinessBreakdown' => $project->readinessBreakdown(),
            'coordinatorFillActorLabel' => $project->coordinatorFilledByLabel(),
            'canApproveThisProject' => auth()->user()?->can('approve_department', Project::class)
                && $project->workflow_status === 'pending_dept_manager'
                && $project->approvableByDepartmentManager(auth()->user()?->person),
            'approverDepartmentManager' => $project->approverDepartmentManager(),
            'approverDepartmentManagerLabel' => $project->approverDepartmentManagerLabel(),
            'projectManagerDepartmentName' => $project->projectManagerDepartmentName(),
            'canRejectThisProject' => $this->canRejectProject($project),
            'canConfirmPassageThisProject' => auth()->user()?->can('confirm_completion', MonitoringActivity::class)
                && $project->awaitingMonitoringDirectorConfirmation(),
            'gapOwnerOptions' => Project::gapOwnerOptionsForRejector(
                auth()->user()?->person,
                (bool) auth()->user()?->super_admin
            ),
            'returnTargetOptions' => Project::returnTargetOptionsForRejector(
                auth()->user()?->person,
                (bool) auth()->user()?->super_admin
            ),
            'canViewRejectionHistory' => $project->canUserViewRejectionHistory(auth()->user()),
            'canViewMonitoringStatusPanel' => $project->canViewMonitoringStatusPanel(auth()->user()),
        ];
    }

    /** @return array<string, mixed> */
    private function buildPdfReportData(Project $project): array
    {
        $project->load([
            'center', 'department', 'section', 'funder',
            'projectManager.department', 'coordinator', 'monitorPerson',
            'primaryMonitoringActivity',
        ]);

        $groups = $this->activeChecklistGroups();
        $values = $project->checklistValues()->get()->keyBy('checklist_item_id');

        return [
            'project' => $project,
            'groups' => $groups,
            'values' => $values,
            'canViewCoordinatorData' => $this->canViewCoordinatorData($project),
            'canViewMonitorData' => $this->canViewMonitorData($project),
            'workflowStatusLabels' => Project::workflowStatusLabels(),
            'valueLabels' => [
                'ready' => 'جاهز',
                'partial' => 'جزئي',
                'not_ready' => 'غير جاهز',
                'not_required' => 'غير مطلوب',
            ],
            'readinessStatusLabels' => [
                'stopped' => 'يحتاج مراجعة — بند غير جاهز',
                'partially_ready' => 'جاهز جزئياً',
                'ready' => 'جاهز للتنفيذ — موصى بالمتابعة',
            ],
            'readinessBreakdown' => $project->readinessBreakdown(),
        ];
    }

    private function shouldRedirectMonitorToWork(Project $project): bool
    {
        if ($project->workflow_status !== 'monitoring_in_progress') {
            return false;
        }

        $user = auth()->user();
        if (! $user || $user->super_admin) {
            return false;
        }

        if (! $user->can('fill_monitor', Project::class)) {
            return false;
        }

        if ($user->can('fill_coordinator', Project::class) || $user->can('update', Project::class)) {
            return false;
        }

        return true;
    }

    private function canViewCoordinatorData(?Project $project = null): bool
    {
        $user = auth()->user();
        if (! $user || ! $project) {
            return false;
        }

        return $project->showsCoordinatorDataTo($user);
    }

    private function canViewMonitorData(?Project $project = null): bool
    {
        $user = auth()->user();
        if (! $user || ! $project) {
            return false;
        }

        return $project->showsMonitorDataTo($user);
    }

    private function constantOptions(string $key): array
    {
        $value = Constant::where('key', $key)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }

    private function authorizeDepartmentApproval(Project $project): void
    {
        $user = auth()->user();

        if ($user?->super_admin) {
            return;
        }

        $project->loadMissing('projectManager');
        $managerDepartmentId = $project->projectManager?->department_id;

        if (! $managerDepartmentId) {
            abort(422, 'مدير المشروع بلا دائرة، لا يمكن توجيه الاعتماد.');
        }

        $person = $user?->person;

        abort_if(! $person || $person->role !== 'department_manager', 403);
        abort_if((int) $person->department_id !== (int) $managerDepartmentId, 403);
    }

    private function authorizeCoordinatorFill(Project $project): void
    {
        $allowed = $this->currentUserCanManageCoordinatorColumn($project);

        abort_if(! $allowed, 403, 'غير مصرّح لك بتعبئة عمود المنسق لهذا المشروع.');
    }

    private function canSubmitToProjectManager(Project $project): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->can('fill_coordinator', Project::class)) {
            return false;
        }

        if ($project->workflow_status !== 'coordinator_filling') {
            return false;
        }

        if (! $this->isCoordinatorFillActor($project, $user)) {
            return false;
        }

        return $this->coordinatorChecklistReadyForSubmission($project);
    }

    private function canSubmitToDeptManager(Project $project): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($project->workflow_status !== 'pending_project_manager') {
            return false;
        }

        if ($user->super_admin) {
            return $this->coordinatorChecklistReadyForSubmission($project);
        }

        $personId = $user->person?->id;

        if (! $personId || (int) $personId !== (int) $project->project_manager_id) {
            return false;
        }

        return $user->can('update', Project::class)
            && $this->coordinatorChecklistReadyForSubmission($project);
    }

    private function authorizeProjectManagerReview(Project $project): void
    {
        $user = auth()->user();

        if ($user?->super_admin) {
            return;
        }

        $personId = $user?->person?->id;

        abort_if(
            ! $personId || (int) $personId !== (int) $project->project_manager_id,
            403,
            'غير مصرّح لك بإرسال المشروع لمدير الدائرة.'
        );
    }

    private function isCoordinatorFillActor(Project $project, $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        $personId = $user->person?->id;

        return (bool) ($personId && (
            (int) $personId === (int) $project->coordinator_id
            || (int) $personId === (int) $project->project_manager_id
        ));
    }

    private function currentUserCanManageCoordinatorColumn(Project $project, $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        if ($user->person?->role === 'monitoring_director') {
            return false;
        }

        $personId = $user->person?->id;
        if (! $personId) {
            return false;
        }

        $isAssignedCoordinator = (int) $personId === (int) $project->coordinator_id;
        if ($isAssignedCoordinator) {
            return true;
        }

        $isProjectManager = (int) $personId === (int) $project->project_manager_id;
        if (! $isProjectManager) {
            return false;
        }

        if ($project->isSelfCoordinator()) {
            return true;
        }

        if ($project->coordinatorHasUserAccount()) {
            return false;
        }

        // إذا المنسق الرسمي عبّى بنفسه (بدون نيابة)، يتحول مدير المشروع لعرض فقط.
        if ($project->coordinator_readiness_pct !== null && (int) ($project->coordinator_filled_by ?? 0) !== (int) $user->id) {
            return false;
        }

        return true;
    }

    private function coordinatorChecklistReadyForSubmission(Project $project): bool
    {
        $activeItemIds = $this->activeChecklistItemIds();

        if ($activeItemIds === []) {
            return true;
        }

        $filledCount = $project->checklistValues()
            ->whereIn('checklist_item_id', $activeItemIds)
            ->whereNotNull('coordinator_value')
            ->where('coordinator_value', '!=', '')
            ->count();

        return $filledCount === count($activeItemIds);
    }

    private function monitorChecklistReadyForSubmission(Project $project): bool
    {
        $activeItemIds = $this->activeChecklistItemIds();

        if ($activeItemIds === []) {
            return true;
        }

        $filledCount = $project->checklistValues()
            ->whereIn('checklist_item_id', $activeItemIds)
            ->whereNotNull('monitor_value')
            ->where('monitor_value', '!=', '')
            ->count();

        return $filledCount === count($activeItemIds);
    }

    private function coordinatorIdHasUser(int $coordinatorId): bool
    {
        if ($coordinatorId <= 0) {
            return false;
        }

        return Person::where('id', $coordinatorId)->whereNotNull('user_id')->exists();
    }

    private function authorizeMonitorFill(Project $project): void
    {
        $user = auth()->user();

        if ($user?->super_admin) {
            return;
        }

        $personId = $user?->person?->id;

        abort_if(
            ! $personId || (int) $personId !== (int) $project->monitor_person_id,
            403,
            'غير مصرّح لك بتعبئة عمود المراقب لهذا المشروع.'
        );
    }

    private function validationRules(?int $projectId = null): array
    {
        $projectTypes = $this->constantOptions('project_types');

        $rules = [
            'project_name' => ['required', 'string', 'max:255'],
            'project_type' => ['required', 'string', Rule::in($projectTypes)],
            'funder_id' => ['required', 'exists:funders,id'],
            'procurement_rep_id' => ['required', 'exists:people,id'],
            'project_manager_id' => ['required', 'exists:people,id'],
            'coordinator_mode' => ['required', 'in:self,person,external'],
            'coordinator_id' => ['nullable', 'exists:people,id'],
            'coordinator_external_name' => ['nullable', 'string', 'max:255'],
            'center_id' => ['required', 'exists:centers,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'planned_start_date' => ['required', 'date'],
            'planned_end_date' => ['required', 'date', 'after_or_equal:planned_start_date'],
            'location' => ['required', 'string'],
            'target_beneficiaries' => ['required', 'integer', 'min:0'],
            'execution_zones' => ['required', 'integer', 'min:0'],
            'estimated_duration' => ['required', 'string', 'max:255'],
            'allocated_budget' => ['required', 'numeric', 'min:0'],
        ];

        $rules['project_number_seq'] = ['required', 'integer', 'min:1'];

        return $rules;
    }

    private function resolveProjectNumberFromValidated(array $validated, ?Project $project = null): string
    {
        if (isset($validated['project_number_seq'])) {
            return Project::formatFromSequence((int) $validated['project_number_seq']);
        }

        return $project?->project_number ?? Project::generateProjectNumber();
    }

    private function validateProject(Request $request, ?Project $project = null): array
    {
        $rules = $this->validationRules($project?->id);
        $currentPerson = auth()->user()?->person;
        $isMonitoringDirector = $currentPerson?->role === 'monitoring_director';

        if ($currentPerson?->role === 'project_manager') {
            unset($rules['project_manager_id']);
        }

        if ($isMonitoringDirector && $project) {
            unset(
                $rules['project_manager_id'],
                $rules['coordinator_mode'],
                $rules['coordinator_id'],
                $rules['coordinator_external_name']
            );
        }

        $validator = Validator::make($request->all(), $rules, [
            'project_number_seq.required' => 'رقم المشروع مطلوب.',
            'project_number_seq.integer' => 'رقم المشروع يجب أن يكون عدداً صحيحاً.',
            'project_number_seq.min' => 'رقم المشروع يجب أن يكون 1 على الأقل.',
        ]);

        $validator->after(function ($validator) use ($request, $currentPerson, $project, $isMonitoringDirector) {
            if ($isMonitoringDirector && $project) {
                return;
            }

            $mode = $request->input('coordinator_mode');
            $managerId = $currentPerson?->role === 'project_manager'
                ? $currentPerson->id
                : $request->input('project_manager_id');

            if ($mode === 'self') {
                if (! $managerId) {
                    $validator->errors()->add('coordinator_mode', 'يجب تحديد مدير المشروع قبل اختيار «أنا المنسق».');
                }
            } elseif ($mode === 'person') {
                if (! $request->filled('coordinator_id')) {
                    $validator->errors()->add('coordinator_id', 'اختر منسقاً من القائمة.');
                } else {
                    $isCoordinator = Person::where('id', $request->input('coordinator_id'))
                        ->where('role', 'coordinator')
                        ->exists();
                    if (! $isCoordinator) {
                        $validator->errors()->add('coordinator_id', 'الشخص المختار ليس منسقاً.');
                    }
                }
            } elseif ($mode === 'external') {
                if (! filled(trim((string) $request->input('coordinator_external_name')))) {
                    $validator->errors()->add('coordinator_external_name', 'أدخل اسم المنسق الخارجي.');
                }
            }

            if ($request->filled('project_number_seq')) {
                $fullNumber = Project::formatFromSequence((int) $request->input('project_number_seq'));
                $exceptId = $project?->id;

                if (! Project::isProjectNumberAvailable($fullNumber, $exceptId)) {
                    $validator->errors()->add(
                        'project_number_seq',
                        'رقم المشروع مستخدم مسبقاً، اختر رقماً آخر.'
                    );
                }
            }

            if ($request->filled('center_id') && $request->filled('department_id')) {
                $department = Department::find($request->input('department_id'));

                if (! $department || (int) $department->center_id !== (int) $request->input('center_id')) {
                    $validator->errors()->add('department_id', 'الدائرة المختارة لا تتبع المركز المحدد.');
                }
            }

            if ($request->filled('department_id') && $request->filled('section_id')) {
                $section = Section::find($request->input('section_id'));

                if (! $section || (int) $section->department_id !== (int) $request->input('department_id')) {
                    $validator->errors()->add('section_id', 'القسم المختار لا يتبع الدائرة المحددة.');
                }
            }
        });

        $validated = $validator->validate();

        if ($isMonitoringDirector && $project) {
            $validated['project_manager_id'] = $project->project_manager_id;
            $validated['coordinator_id'] = $project->coordinator_id;
            $validated['coordinator_external_name'] = $project->coordinator_external_name;

            return $validated;
        }

        $validated['project_manager_id'] = $this->resolveProjectManagerId($request, $validated);
        $validated = $this->normalizeCoordinatorInput($validated);

        return $validated;
    }

    private function resolveProjectManagerId(Request $request, array $validated): int
    {
        $person = auth()->user()?->person;

        if ($person?->role === 'project_manager') {
            return (int) $person->id;
        }

        return (int) $validated['project_manager_id'];
    }

    private function normalizeCoordinatorInput(array $validated): array
    {
        $mode = $validated['coordinator_mode'] ?? null;
        unset($validated['coordinator_mode']);

        return match ($mode) {
            'self' => array_merge($validated, [
                'coordinator_id' => $validated['project_manager_id'],
                'coordinator_external_name' => null,
            ]),
            'person' => array_merge($validated, [
                'coordinator_external_name' => null,
            ]),
            'external' => array_merge($validated, [
                'coordinator_id' => null,
                'coordinator_external_name' => trim((string) ($validated['coordinator_external_name'] ?? '')),
            ]),
            default => $validated,
        };
    }

    private function clearCoordinatorChecklistIfChanged(
        Project $project,
        ?int $previousCoordinatorId,
        ?string $previousExternalName
    ): void {
        $coordinatorChanged = (int) ($previousCoordinatorId ?? 0) !== (int) ($project->coordinator_id ?? 0);
        $externalChanged = (string) ($previousExternalName ?? '') !== (string) ($project->coordinator_external_name ?? '');

        if (! $coordinatorChanged && ! $externalChanged) {
            return;
        }

        ProjectChecklistValue::where('project_id', $project->id)
            ->where(function ($query) {
                $query->whereNotNull('coordinator_value')
                    ->orWhereNotNull('person_name');
            })
            ->update([
                'coordinator_value' => null,
                'person_name' => null,
            ]);

        $project->update([
            'coordinator_readiness_pct' => null,
            'coordinator_filled_by' => null,
        ]);
    }

    private function applyColumnFilters($query, array $columnFilters): void
    {
        foreach ($columnFilters as $fieldName => $values) {
            if (empty($values)) {
                continue;
            }

            if ($fieldName === 'created_at' && is_array($values)) {
                if (isset($values['from'])) {
                    $query->whereDate('created_at', '>=', $values['from']);
                }
                if (isset($values['to'])) {
                    $query->whereDate('created_at', '<=', $values['to']);
                }

                continue;
            }

            $filteredValues = array_values(array_filter((array) $values, fn ($v) => ! in_array($v, ['الكل', 'all', 'All'], true)));

            if ($filteredValues === []) {
                continue;
            }

            switch ($fieldName) {
                case 'org_label':
                    $query->where(function ($q) use ($filteredValues) {
                        foreach ($filteredValues as $value) {
                            $parts = array_map('trim', explode('/', (string) $value, 2));
                            $center = $parts[0] ?? null;
                            $department = $parts[1] ?? null;
                            $q->orWhere(function ($sub) use ($center, $department) {
                                if ($center && $center !== '-') {
                                    $sub->whereHas('center', fn ($c) => $c->where('name', $center));
                                }
                                if ($department && $department !== '-') {
                                    $sub->whereHas('department', fn ($d) => $d->where('name', $department));
                                }
                            });
                        }
                    });
                    break;
                case 'project_manager_name':
                    $query->whereHas('projectManager', fn ($q) => $q->whereIn('name', $filteredValues));
                    break;
                case 'coordinator_name':
                    $query->where(function ($q) use ($filteredValues) {
                        $q->whereHas('coordinator', fn ($c) => $c->whereIn('name', $filteredValues))
                            ->orWhereIn('coordinator_external_name', $filteredValues);
                    });
                    break;
                case 'monitor_name':
                    $query->whereHas('monitorPerson', fn ($q) => $q->whereIn('name', $filteredValues));
                    break;
                case 'funder_name':
                    $query->whereHas('funder', fn ($q) => $q->whereIn('name', $filteredValues));
                    break;
                case 'workflow_status_label':
                    $statusMap = array_flip(Project::workflowStatusLabels());
                    $keys = array_values(array_filter(array_map(fn ($v) => $statusMap[$v] ?? null, $filteredValues)));
                    if ($keys !== []) {
                        $query->whereIn('workflow_status', $keys);
                    }
                    break;
                case 'coordinator_readiness_pct':
                    $nums = array_map(fn ($v) => (float) str_replace('%', '', $v), $filteredValues);
                    $query->whereIn('coordinator_readiness_pct', $nums);
                    break;
                case 'monitor_readiness_pct':
                    $nums = array_map(fn ($v) => (float) str_replace('%', '', $v), $filteredValues);
                    $query->whereIn('monitor_readiness_pct', $nums);
                    break;
                default:
                    $query->whereIn($fieldName, $filteredValues);
                    break;
            }
        }
    }

    private function applySort($query, ?string $sortColumn, ?string $sortDirection): void
    {
        $dir = in_array(strtolower((string) $sortDirection), ['asc', 'desc'], true)
            ? strtolower($sortDirection)
            : null;

        if (empty($sortColumn) || $dir === null) {
            $query->orderBy('projects.created_at', 'desc');

            return;
        }

        $baseTable = 'projects';

        switch ($sortColumn) {
            case 'project_number':
                $query->orderBy("{$baseTable}.project_number", $dir);
                break;
            case 'project_name':
                $query->orderBy("{$baseTable}.project_name", $dir);
                break;
            case 'project_type':
                $query->orderBy("{$baseTable}.project_type", $dir);
                break;
            case 'coordinator_readiness_pct':
                $query->orderBy("{$baseTable}.coordinator_readiness_pct", $dir);
                break;
            case 'monitor_readiness_pct':
                $query->orderBy("{$baseTable}.monitor_readiness_pct", $dir);
                break;
            case 'workflow_status_label':
                $query->orderBy("{$baseTable}.workflow_status", $dir);
                break;
            case 'created_at':
                $query->orderBy("{$baseTable}.created_at", $dir);
                break;
            case 'project_manager_name':
                $query->leftJoin('people as pm_people', "{$baseTable}.project_manager_id", '=', 'pm_people.id')
                    ->select("{$baseTable}.*")
                    ->orderBy('pm_people.name', $dir);
                break;
            case 'coordinator_name':
                $query->leftJoin('people as coord_people', "{$baseTable}.coordinator_id", '=', 'coord_people.id')
                    ->select("{$baseTable}.*")
                    ->orderBy('coord_people.name', $dir);
                break;
            case 'monitor_name':
                $query->leftJoin('people as mon_people', "{$baseTable}.monitor_person_id", '=', 'mon_people.id')
                    ->select("{$baseTable}.*")
                    ->orderBy('mon_people.name', $dir);
                break;
            case 'funder_name':
                $query->leftJoin('funders', "{$baseTable}.funder_id", '=', 'funders.id')
                    ->select("{$baseTable}.*")
                    ->orderBy('funders.name', $dir);
                break;
            default:
                $query->orderBy("{$baseTable}.created_at", 'desc');
                break;
        }
    }

    private function applyVisibilityScope($query)
    {
        return $query->visibleToUser(auth()->user());
    }

    private function ensureProjectVisible(Project $project): void
    {
        if (auth()->user()?->super_admin) {
            return;
        }

        $project->loadMissing('projectManager');

        abort_unless($project->isVisibleToUser(auth()->user()), 403);
    }

    private function canRejectProject(Project $project): bool
    {
        $user = auth()->user();

        if (! $user?->can('reject', Project::class)) {
            return false;
        }

        if ($project->workflow_status === 'rejected') {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        $person = $user->person;

        if (! $person) {
            return false;
        }

        return match ($person->role) {
            'department_manager' => $project->workflow_status === 'pending_dept_manager'
                && $project->approvableByDepartmentManager($person),
            'monitoring_director' => in_array($project->workflow_status, [
                'pending_monitoring_manager',
                'monitoring_in_progress',
                'pending_monitoring_confirmation',
            ], true),
            default => ! in_array($project->workflow_status, ['monitoring_in_progress', 'pending_monitoring_confirmation'], true),
        };
    }

    private function authorizeProjectReject(Project $project): void
    {
        abort_unless($this->canRejectProject($project), 403, 'غير مصرّح لك برفض المشروع في هذه المرحلة.');
    }

    private function guardCoordinatorFillStatus(Project $project): void
    {
        $allowed = ['pending_coordinator', 'coordinator_filling'];

        if ($this->canFillCoordinatorOnDraft($project)) {
            $allowed[] = 'draft';
        }

        $this->guardStatus($project, $allowed);
    }

    private function canFillCoordinatorOnDraft(Project $project): bool
    {
        if ($project->workflow_status !== 'draft') {
            return false;
        }

        if (! auth()->user()?->can('fill_coordinator', Project::class)) {
            return false;
        }

        return $project->isSelfCoordinator() || filled($project->coordinator_external_name);
    }

    private function userCanViewCoordinatorColumnInList(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->super_admin) {
            return true;
        }

        return $user->person?->role !== 'monitor';
    }

    private function userCanViewMonitorColumnInList(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->super_admin) {
            return true;
        }

        return in_array($user->person?->role, ['monitoring_director', 'general_management'], true);
    }

    private function validateCoordinatorFillOnBehalf(Request $request, Project $project): void
    {
        $user = auth()->user();
        $personId = $user?->person?->id;

        if ($user?->super_admin || $project->isSelfCoordinator()) {
            return;
        }

        $isPmFillingOnBehalf = $personId
            && (int) $personId === (int) $project->project_manager_id
            && ! $project->isSelfCoordinator()
            && (int) $personId !== (int) ($project->coordinator_id ?? 0);

        if ($isPmFillingOnBehalf && $project->coordinatorHasUserAccount()) {
            abort(422, 'لا يمكن التعبئة نيابةً عن منسق له حساب في النظام.');
        }

        if ($isPmFillingOnBehalf && ! $request->boolean('fill_on_behalf') && ! filled($project->coordinator_external_name)) {
            abort(422, 'يجب تأكيد التعبئة نيابةً عن المنسق.');
        }
    }

    private function recordCoordinatorFilledBy(Request $request, Project $project): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $personId = $user->person?->id;

        if ($personId && (int) $personId === (int) $project->coordinator_id) {
            $project->update(['coordinator_filled_by' => null]);

            return;
        }

        $isPm = $personId && (int) $personId === (int) $project->project_manager_id;
        $confirmedOnBehalf = $request->boolean('fill_on_behalf');
        $externalCoordinator = filled($project->coordinator_external_name);

        if (! $project->isSelfCoordinator() && ($isPm || $user->super_admin) && ($confirmedOnBehalf || $externalCoordinator)) {
            $project->update(['coordinator_filled_by' => $user->id]);
        }
    }
}
