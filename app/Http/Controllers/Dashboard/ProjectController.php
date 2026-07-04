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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    private const STATUSES = [
        'draft', 'pending_coordinator', 'coordinator_filling',
        'pending_dept_manager', 'pending_monitoring_manager',
        'monitoring_in_progress', 'pending_monitoring_confirmation',
        'passage_complete', 'rejected',
    ];

    public function index(): View
    {
        $this->authorize('view', Project::class);

        $query = Project::with(['center', 'department', 'projectManager.department', 'coordinator', 'monitorPerson'])
            ->orderBy('created_at', 'desc');
        $query = $this->applyVisibilityScope($query);

        $projects = $query->paginate(15);

        return view('dashboard.projects.index', [
            'projects' => $projects,
            'currentPerson' => auth()->user()?->person,
        ]);
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

        $project->load(['center', 'department', 'section', 'funder', 'projectManager.department', 'monitorPerson', 'primaryMonitoringActivity', 'rejectedByUser', 'coordinatorFilledByUser']);
        $project->syncMonitoringWorkflowState();
        $project->refresh();

        if (! $this->canViewCoordinatorData()) {
            $project->unsetRelation('coordinator');
            $project->makeHidden(['coordinator_id', 'coordinator_readiness_pct']);
        } else {
            $project->load('coordinator');
        }

        $groups = $this->activeChecklistGroups();
        $values = $project->checklistValues()->get()->keyBy('checklist_item_id');
        $monitors = Person::withRole('monitor')->orderBy('name')->get();

        return view('dashboard.projects.show', $this->showViewData($project, $groups, $values, $monitors));
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

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', Project::class);
        $this->ensureProjectVisible($project);

        $project->delete();

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

        $nextStatus = 'pending_coordinator';

        // تخطّي تلقائي: مدير المشروع منسقاً، أو منسق خارجي بلا حساب
        if ($project->isSelfCoordinator() || filled($project->coordinator_external_name)) {
            $nextStatus = 'coordinator_filling';
        }

        $project->update([
            'workflow_status' => $nextStatus,
            'coordinator_submitted_at' => now(),
            'coordinator_submitted_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم إرسال المشروع للمنسق.');
    }

    public function fillCoordinator(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('fill_coordinator', Project::class);
        $this->guardCoordinatorFillStatus($project);
        $this->authorizeCoordinatorFill($project);
        $this->validateCoordinatorFillOnBehalf($request, $project);

        $this->saveChecklistValues($request, $project, 'coordinator_value');

        $this->recordCoordinatorFilledBy($request, $project);

        if (in_array($project->workflow_status, ['pending_coordinator', 'draft'], true)) {
            $project->update(['workflow_status' => 'coordinator_filling']);
        }

        $project->recalculateReadiness();

        return back()->with('success', 'تم حفظ عمود المنسق.');
    }

    public function submitToDeptManager(Project $project): RedirectResponse
    {
        $this->authorize('fill_coordinator', Project::class);
        $this->guardStatus($project, ['coordinator_filling']);
        $this->authorizeCoordinatorFill($project);

        $project->update([
            'workflow_status' => 'pending_dept_manager',
            'updated_by' => auth()->id(),
        ]);

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

        // عزل بيانات المنسق على مستوى الاستعلام: لا يُحمَّل coordinator_id ولا coordinator_value إطلاقاً
        $project->loadMissing(['center', 'department', 'section', 'funder', 'primaryMonitoringActivity']);
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

        return view('dashboard.projects.monitor-work', compact(
            'project',
            'groups',
            'values',
            'canSubmitToDirector',
            'awaitingDirector',
            'canEditMonitorColumn',
        ));
    }

    public function fillMonitor(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('fill_monitor', Project::class);
        $this->guardStatus($project, ['monitoring_in_progress']);
        $this->authorizeMonitorFill($project);

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

        return redirect()
            ->route('dashboard.projects.monitor-work', $project)
            ->with('success', 'تم حفظ عمود المراقب.');
    }

    public function confirmMonitoring(Project $project): RedirectResponse
    {
        $this->authorize('fill_monitor', Project::class);
        $this->guardStatus($project, ['monitoring_in_progress']);
        $this->authorizeMonitorFill($project);

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

        return redirect()
            ->route('dashboard.projects.monitor-work', $project)
            ->with('success', 'تم إرسال عمل المراقب لمدير الرقابة العامة — بانتظار تأكيد المرور.');
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
        $allowedGapOwners = array_keys(Project::gapOwnerOptionsForRejector($user?->person, (bool) $user?->super_admin));

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
            'gap_owner' => ['required', 'string', Rule::in($allowedGapOwners)],
        ]);

        $project->update($validated + [
            'workflow_status' => 'rejected',
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم رفض المشروع.');
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

        $validated = $request->validate([
            'checklist' => ['array'],
            'checklist.*.value' => ['nullable', 'in:ready,partial,not_ready,not_required'],
            'checklist.*.person_name' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated['checklist'] as $itemId => $data) {
            if (empty($data['value']) && empty($data['person_name'])) {
                continue;
            }

            $attributes = ['project_id' => $project->id, 'checklist_item_id' => $itemId];
            $payload = [$column => $data['value'] ?? null];

            if (array_key_exists('person_name', $data)) {
                $payload['person_name'] = $data['person_name'];
            }

            ProjectChecklistValue::updateOrCreate($attributes, $payload);
        }
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
                old('fill_on_behalf')
                || ($project && $project->exists && $project->coordinator_filled_by)
            ));

        return [
            'centers' => Center::orderBy('name')->get(),
            'funders' => Funder::orderBy('name')->get(),
            'projectManagers' => Person::withRole('project_manager')->orderBy('name')->get(),
            'coordinators' => Person::withRole('coordinator')->orderBy('name')->get(),
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
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        return $user->can('fill_coordinator', Project::class)
            || $user->can('update', Project::class)
            || $user->can('create', Project::class);
    }

    private function shouldProcessCoordinatorChecklistInForm(Request $request): bool
    {
        $mode = $request->input('coordinator_mode');

        if ($mode === 'self' || $mode === 'external') {
            return true;
        }

        if ($mode === 'person') {
            return $request->boolean('fill_on_behalf');
        }

        return false;
    }

    private function saveCoordinatorChecklistFromForm(Request $request, Project $project): void
    {
        if (! $this->canFillCoordinatorInForm() || ! $this->shouldProcessCoordinatorChecklistInForm($request)) {
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

        return [
            'project' => $project,
            'groups' => $groups,
            'values' => $values,
            'monitors' => $monitors,
            'canViewCoordinatorData' => $this->canViewCoordinatorData(),
            'canSetMonitoringInfo' => auth()->user()?->can('set_monitoring_info', MonitoringActivity::class),
            'canAssignMonitor' => auth()->user()?->can('assign_monitor', MonitoringActivity::class),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
            'showCoordinatorFillOnDraft' => $this->canFillCoordinatorOnDraft($project),
            'requiresFillOnBehalfConfirm' => $isProjectManager
                && ! $isAssignedCoordinator
                && ! $project->isSelfCoordinator()
                && $project->hasCoordinatorAssignment(),
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

    private function canViewCoordinatorData(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        return $user->can('fill_coordinator', Project::class)
            || $user->can('approve_department', Project::class)
            || $user->can('update', Project::class)
            || $user->can('reject', Project::class);
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
        $user = auth()->user();

        if ($user?->super_admin) {
            return;
        }

        $personId = $user?->person?->id;

        $allowed = $personId && (
            (int) $personId === (int) $project->coordinator_id
            || (int) $personId === (int) $project->project_manager_id
        );

        abort_if(! $allowed, 403, 'غير مصرّح لك بتعبئة عمود المنسق لهذا المشروع.');
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
            'project_type' => ['nullable', 'string', Rule::in($projectTypes)],
            'funder_id' => ['nullable', 'exists:funders,id'],
            'procurement_rep' => ['nullable', 'string', 'max:255'],
            'project_manager_id' => ['required', 'exists:people,id'],
            'coordinator_mode' => ['required', 'in:self,person,external'],
            'coordinator_id' => ['nullable', 'exists:people,id'],
            'coordinator_external_name' => ['nullable', 'string', 'max:255'],
            'center_id' => ['nullable', 'exists:centers,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'location' => ['nullable', 'string'],
            'target_beneficiaries' => ['nullable', 'integer', 'min:0'],
            'execution_zones' => ['nullable', 'integer', 'min:0'],
            'estimated_duration' => ['nullable', 'string', 'max:255'],
            'allocated_budget' => ['nullable', 'numeric', 'min:0'],
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

        if ($currentPerson?->role === 'project_manager') {
            unset($rules['project_manager_id']);
        }

        $validator = Validator::make($request->all(), $rules, [
            'project_number_seq.required' => 'رقم المشروع مطلوب.',
            'project_number_seq.integer' => 'رقم المشروع يجب أن يكون عدداً صحيحاً.',
            'project_number_seq.min' => 'رقم المشروع يجب أن يكون 1 على الأقل.',
        ]);

        $validator->after(function ($validator) use ($request, $currentPerson, $project) {
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
