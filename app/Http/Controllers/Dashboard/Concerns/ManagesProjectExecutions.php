<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectChecklistValue;
use App\Models\ProjectExecution;
use App\Services\Projects\ProjectAggregateStatusService;
use App\Services\Projects\ProjectExecutionSpawner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

trait ManagesProjectExecutions
{
    use ResolvesPerPage;
    public function executionsIndex(Request $request): View
    {
        $this->authorize('view', ProjectExecution::class);

        $query = ProjectExecution::with(['project', 'coordinator', 'monitorPerson', 'primaryMonitoringActivity'])
            ->where('is_active', true)
            ->visibleToUser(auth()->user());

        $executions = $query->orderByDesc('updated_at')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();
        $statusLabels = ProjectExecution::workflowStatusLabels();

        return view('dashboard.project-executions.index', compact('executions', 'statusLabels'));
    }

    public function showExecution(Project $project, ProjectExecution $execution): View|RedirectResponse
    {
        $this->authorize('view', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->ensureExecutionVisible($execution);

        if ($this->shouldRedirectMonitorToExecutionWork($execution)) {
            return redirect()->route('dashboard.projects.executions.monitor-work', [$project, $execution]);
        }

        $execution->load([
            'coordinator', 'monitorPerson', 'primaryMonitoringActivity.passageCompletedByUser',
            'project.projectManager.department', 'rejectedByUser',
            'rejections.rejectedByUser', 'rejections.returnTargetPerson',
        ]);
        $execution->syncMonitoringWorkflowState();
        $execution->refresh();

        $project->load([
            'center', 'department', 'section', 'funder', 'currency', 'projectManager.department', 'procurementRep',
            'executions' => fn ($q) => $q->where('is_active', true),
        ]);

        $groups = $this->activeChecklistGroups();
        $values = $execution->checklistValues()->get()->keyBy('checklist_item_id');
        $monitors = Person::withRole('monitor')->orderBy('name')->get();
        $statusLabels = ProjectExecution::workflowStatusLabels();
        $user = auth()->user();
        $executionRegionsForDisplay = $this->executionRegionsForViewer($project, $user);

        return view('dashboard.project-executions.show', compact(
            'project',
            'execution',
            'groups',
            'values',
            'monitors',
            'statusLabels',
            'executionRegionsForDisplay',
        ) + $this->executionShowViewData($project, $execution, $groups, $values, $monitors, $executionRegionsForDisplay));
    }

    public function monitorWorkExecution(Project $project, ProjectExecution $execution): View
    {
        $this->authorize('fill_monitor', ProjectExecution::class);
        $this->guardExecutionStatus($execution, ['monitoring_in_progress', 'pending_monitoring_confirmation', 'passage_complete']);
        $this->authorizeMonitorFillExecution($execution);

        $execution->loadMissing([
            'project.center', 'project.department', 'project.section', 'project.funder',
            'project.projectManager.department', 'coordinator', 'primaryMonitoringActivity',
        ]);
        $execution->syncMonitoringWorkflowState();
        $execution->refresh();
        $execution->primaryMonitoringActivity?->refresh();

        $groups = $this->activeChecklistGroups();
        $values = $execution->checklistValues()
            ->select(['id', 'project_id', 'project_execution_id', 'checklist_item_id', 'monitor_value', 'person_name'])
            ->get()
            ->keyBy('checklist_item_id');

        return view('dashboard.project-executions.monitor-work', [
            'project' => $project,
            'execution' => $execution,
            'groups' => $groups,
            'values' => $values,
            'valueLabels' => [
                'ready' => 'جاهز',
                'partial' => 'جزئي',
                'not_ready' => 'غير جاهز',
                'not_required' => 'غير مطلوب',
            ],
            'readinessBreakdown' => $execution->readinessBreakdown(),
            'canSubmitToDirector' => $execution->canMonitorSubmitToDirector(),
            'awaitingDirector' => $execution->awaitingMonitoringDirectorConfirmation(),
            'canEditMonitorColumn' => $execution->workflow_status === 'monitoring_in_progress',
            'isAssignedMonitor' => $execution->isAssignedMonitor(auth()->user()),
            'primaryActivity' => $execution->primaryMonitoringActivity,
            'people' => Person::orderBy('name')->get(),
            'activityTypes' => $this->constantOptions('activity_types'),
        ]);
    }

    public function fillCoordinatorExecution(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->authorizeCoordinatorFillExecutionAbility($execution);
        $this->authorizeCoordinatorFillExecution($execution);
        $this->guardExecutionStatus($execution, ['pending_coordinator', 'coordinator_filling']);

        $validated = $request->validate([
            'implementation_mechanism' => ['nullable', 'string'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
        ], [
            'recipient_phone.regex' => 'رقم الجوال يجب أن يحتوي أرقاماً فقط.',
        ]);

        $this->saveExecutionChecklistValues($request, $project, $execution, 'coordinator_value');
        $this->recordExecutionCoordinatorFilledAt($execution);

        $execution->update([
            'implementation_mechanism' => $validated['implementation_mechanism'] ?? null,
            'recipient_name' => $validated['recipient_name'] ?? null,
            'recipient_phone' => $validated['recipient_phone'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        $execution->recalculateReadiness();
        $execution->loadMissing('checklistValues');

        if (! $this->executionCoordinatorChecklistReady($execution)) {
            if ($execution->workflow_status === 'coordinator_filling') {
                $execution->update(['workflow_status' => 'pending_coordinator', 'updated_by' => auth()->id()]);
            }

            return back()->withErrors([
                'coordinator' => 'تم حفظ التعبئة — أكمل جميع بنود قائمة المنسق قبل الإرسال لمدير القسم.',
            ]);
        }

        $execution->update([
            'workflow_status' => 'pending_section_manager',
            'submitted_to_section_manager_at' => now(),
            'submitted_to_section_manager_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم الحفظ والإرسال لمدير القسم.');
    }

    public function fillClosureDocsExecution(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('fill_coordinator', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->ensureExecutionVisible($execution);
        $this->authorizeCoordinatorFillExecution($execution);
        $this->guardExecutionClosureDocsFillStatus($execution);

        $this->saveExecutionClosureDocs($request, $project, $execution);
        $this->recordExecutionCoordinatorFilledAt($execution);

        $execution->recalculateReadiness();

        return back()->with('success', 'تم حفظ مستندات الإغلاق.');
    }

    public function submitToSectionManagerExecution(Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->authorizeCoordinatorFillExecutionAbility($execution);
        $this->guardExecutionStatus($execution, ['pending_coordinator', 'coordinator_filling']);
        $this->authorizeCoordinatorFillExecution($execution);

        $execution->loadMissing('checklistValues');

        if (! $this->executionCoordinatorChecklistReady($execution)) {
            return back()->withErrors([
                'coordinator' => 'لا يمكن الإرسال لمدير القسم قبل اكتمال تعبئة جميع بنود قائمة المنسق.',
            ]);
        }

        $execution->update([
            'workflow_status' => 'pending_section_manager',
            'submitted_to_section_manager_at' => now(),
            'submitted_to_section_manager_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم إرسال المسار لمدير القسم.');
    }

    public function approveSectionExecution(Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('approve_section', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->guardExecutionStatus($execution, ['pending_section_manager']);
        $this->authorizeSectionApprovalExecution($execution);

        $execution->update([
            'workflow_status' => 'pending_dept_manager',
            'section_manager_approved_at' => now(),
            'section_manager_approved_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'تمت الموافقة، أُرسل المسار لمدير الدائرة.');
    }

    public function approveDepartmentExecution(Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('approve_department', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->guardExecutionStatus($execution, ['pending_dept_manager']);
        $this->authorizeDepartmentApprovalExecution($execution);

        $execution->update([
            'workflow_status' => 'pending_monitoring_manager',
            'dept_manager_approved_at' => now(),
            'dept_manager_approved_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'تمت الموافقة، أُرسل المسار لمدير الرقابة العامة.');
    }

    public function setMonitoringInfoExecution(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('set_monitoring_info', MonitoringActivity::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->guardExecutionStatus($execution, ['pending_monitoring_manager']);

        $validated = $request->validate([
            'monitoring_method' => ['nullable', 'string'],
            'monitoring_stage' => ['nullable', 'string'],
        ]);

        $payload = $validated + ['updated_by' => auth()->id()];

        if (! $execution->monitoring_manager_received_at) {
            $payload['monitoring_manager_received_at'] = now();
            $payload['monitoring_manager_received_by'] = auth()->id();
        }

        $execution->update($payload);

        return back()->with('success', 'تم حفظ طريقة/مرحلة المراقبة.');
    }

    public function assignMonitorExecution(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('assign_monitor', MonitoringActivity::class);
        abort_unless(auth()->user()?->super_admin || auth()->user()?->isMonitoringDirector(), 403);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->guardExecutionStatus($execution, ['pending_monitoring_manager']);

        if (! $project->center_id || ! $project->department_id) {
            return back()->withErrors([
                'center_id' => 'يجب تحديد المركز والدائرة في بيانات المشروع قبل تعيين المراقب.',
            ]);
        }

        $validated = $this->validateAssignMonitorPayload($request);

        $execution->update($validated + [
            'monitoring_manager_received_at' => $execution->monitoring_manager_received_at ?? now(),
            'monitoring_manager_received_by' => $execution->monitoring_manager_received_by ?? auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $activity = MonitoringActivity::create([
            'reference_code' => $this->generateReferenceCode(),
            'source_type' => 'project_execution',
            'source_id' => $execution->id,
            'project_execution_id' => $execution->id,
            'activity_role' => 'primary',
            'center_id' => $project->center_id,
            'department_id' => $project->department_id,
            'section_id' => $project->section_id,
            'monitor_person_id' => $execution->monitor_person_id,
            'funder_id' => $project->funder_id,
            'monitoring_method' => $execution->monitoring_method,
            'monitoring_stage' => $execution->monitoring_stage,
            'subject' => $project->project_name . ' — ' . $execution->region_name,
            'field_problem' => false,
            'workflow_status' => 'in_progress',
            'is_passage_complete' => false,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $execution->update([
            'primary_monitoring_activity_id' => $activity->id,
            'workflow_status' => 'monitoring_in_progress',
        ]);

        return back()->with('success', 'تم تعيين المراقب وبدء المراقبة.');
    }

    public function fillMonitorExecution(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('fill_monitor', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->authorizeMonitorFillExecution($execution);
        $this->guardExecutionStatus($execution, ['monitoring_in_progress']);

        $this->mergeNormalizedActivityTime($request);

        $validated = $request->validate([
            'monitor_notes_text' => ['nullable', 'string'],
            'monitor_negative_notes_text' => ['nullable', 'string'],
            'monitor_recommendations_text' => ['nullable', 'string'],
        ] + $this->monitorActivityFieldRules(), $this->monitorActivityValidationMessages());

        // احفظ التقييم أولاً — لا يتأثر بفشل checklist لاحقاً
        $this->saveExecutionPrimaryActivityFields($execution, $validated);

        if ($request->has('checklist') && is_array($request->input('checklist'))) {
            $this->saveExecutionChecklistValues($request, $project, $execution, 'monitor_value');
        }

        $execution->update([
            'monitor_notes' => $this->linesToArray($validated['monitor_notes_text'] ?? ''),
            'monitor_negative_notes' => $this->linesToArray($validated['monitor_negative_notes_text'] ?? ''),
            'monitor_recommendations' => $this->linesToArray($validated['monitor_recommendations_text'] ?? ''),
            'updated_by' => auth()->id(),
        ]);

        $execution->recalculateReadiness();
        $this->saveExecutionPrimaryActivityFields($execution, $validated);
        $execution->loadMissing('checklistValues', 'primaryMonitoringActivity');

        if (! $this->executionMonitorChecklistReady($execution)) {
            return redirect()
                ->route('dashboard.projects.executions.monitor-work', [$project, $execution])
                ->withErrors([
                    'monitor' => 'تم حفظ التعبئة — أكمل جميع بنود قائمة التحقق قبل الإرسال لمدير الرقابة.',
                ]);
        }

        return $this->finalizeExecutionMonitorSubmission($project, $execution);
    }

    /** @deprecated استخدم fillMonitorExecution — يُبقى للتوافق مع روابط قديمة */
    public function saveMonitorActivityExecution(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        return $this->fillMonitorExecution($request, $project, $execution);
    }

    /** @deprecated يُبقى للتوافق — الإرسال يتم عبر fillMonitorExecution */
    public function confirmMonitoringExecution(Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('fill_monitor', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->guardExecutionStatus($execution, ['monitoring_in_progress']);
        $this->authorizeMonitorFillExecution($execution);

        $execution->loadMissing('checklistValues', 'primaryMonitoringActivity');

        if (! $this->executionMonitorChecklistReady($execution)) {
            return redirect()
                ->route('dashboard.projects.executions.monitor-work', [$project, $execution])
                ->withErrors(['monitor' => 'يجب تعبئة جميع بنود قائمة التحقق قبل الإرسال.']);
        }

        return $this->finalizeExecutionMonitorSubmission($project, $execution);
    }

    protected function finalizeExecutionMonitorSubmission(Project $project, ProjectExecution $execution): RedirectResponse
    {
        $activity = $execution->primaryMonitoringActivity;

        if (! $activity || $activity->workflow_status !== 'in_progress') {
            return redirect()
                ->route('dashboard.projects.executions.monitor-work', [$project, $execution])
                ->withErrors(['monitor' => 'لا يوجد نشاط رقابي أساسي مرتبط بهذا المسار.']);
        }

        $activity->update(['workflow_status' => 'pending_confirmation', 'updated_by' => auth()->id()]);
        $execution->update([
            'workflow_status' => 'pending_monitoring_confirmation',
            'monitor_submitted_at' => now(),
            'monitor_submitted_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('dashboard.projects.executions.monitor-work', [$project, $execution])
            ->with('success', 'تم الحفظ والإرسال لمدير الرقابة العامة.');
    }

    protected function finalizeProjectMonitorSubmission(Project $project): RedirectResponse
    {
        $activity = $project->primaryMonitoringActivity;

        if (! $activity || $activity->workflow_status !== 'in_progress') {
            return redirect()
                ->route('dashboard.projects.monitor-work', $project)
                ->withErrors(['monitor' => 'لا يوجد نشاط رقابي أساسي مرتبط بهذا المشروع.']);
        }

        $activity->update(['workflow_status' => 'pending_confirmation', 'updated_by' => auth()->id()]);
        $project->update([
            'workflow_status' => 'pending_monitoring_confirmation',
            'monitor_submitted_at' => now(),
            'monitor_submitted_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('dashboard.projects.monitor-work', $project)
            ->with('success', 'تم الحفظ والإرسال لمدير الرقابة العامة.');
    }

    public function confirmPassageExecution(Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('confirm_completion', MonitoringActivity::class);
        abort_unless(auth()->user()?->super_admin || auth()->user()?->isMonitoringDirector(), 403);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->ensureExecutionVisible($execution);

        $execution->syncMonitoringWorkflowState();
        $execution->refresh();

        if (! in_array($execution->workflow_status, ['pending_monitoring_confirmation', 'monitoring_in_progress'], true)) {
            abort(422, 'حالة المسار لا تسمح بتأكيد المرور.');
        }

        $activity = $execution->primaryMonitoringActivity;

        if (! $activity || ! in_array($activity->workflow_status, ['pending_confirmation', 'in_progress'], true)) {
            abort(422, 'حالة النشاط لا تسمح بتأكيد المرور.');
        }

        $execution->completePassage((int) auth()->id());
        app(ProjectAggregateStatusService::class)->refresh($project);

        return back()->with('success', 'تم تأكيد المرور — المسار مكتمل.');
    }

    public function rejectExecution(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('reject', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->ensureExecutionVisible($execution);
        $this->authorizeRejectExecution($execution);

        $user = auth()->user();
        $allowedReturnTargets = array_keys(ProjectExecution::returnTargetOptionsForRejector($user?->person, (bool) $user?->super_admin));
        $allowedGapOwners = array_keys(ProjectExecution::gapOwnerOptionsForRejector($user?->person, (bool) $user?->super_admin));

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
            'gap_owner' => ['required', 'string', Rule::in($allowedGapOwners)],
            'return_target' => ['required', 'string', Rule::in($allowedReturnTargets)],
        ]);

        $nextStatus = ProjectExecution::workflowStatusForReturnTarget($validated['return_target']);

        if ($nextStatus === null) {
            abort(422, 'خيار الإرجاع غير صالح.');
        }

        $statusBefore = $execution->workflow_status;

        $execution->update([
            'rejection_reason' => $validated['rejection_reason'],
            'gap_owner' => $validated['gap_owner'],
            'workflow_status' => $nextStatus,
            'return_target' => $validated['return_target'] === 'reject_final' ? null : $validated['return_target'],
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        $execution->rejections()->create([
            'rejection_reason' => $validated['rejection_reason'],
            'gap_owner' => $validated['gap_owner'],
            'return_target' => $validated['return_target'] === 'reject_final' ? null : $validated['return_target'],
            'return_target_person_id' => $execution->fresh()->personIdForReturnTarget(
                $validated['return_target'] === 'reject_final' ? null : $validated['return_target']
            ),
            'workflow_status_before' => $statusBefore,
            'workflow_status_after' => $nextStatus,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
        ]);

        app(ProjectAggregateStatusService::class)->refresh($project);

        return back()->with('success', 'تم رفض المسار.');
    }

    public function rerouteExecution(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('reject', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->ensureExecutionVisible($execution);

        abort_unless(auth()->user()?->super_admin || auth()->user()?->person?->role === 'admin', 403);

        $validated = $request->validate([
            'workflow_status' => ['required', 'string', Rule::in(array_keys(ProjectExecution::workflowStatusLabels()))],
        ]);

        $workflowStatus = $validated['workflow_status'] === 'coordinator_filling'
            ? 'pending_coordinator'
            : $validated['workflow_status'];

        $execution->update(['workflow_status' => $workflowStatus, 'updated_by' => auth()->id()]);
        app(ProjectAggregateStatusService::class)->refresh($project);

        return back()->with('success', 'تم تحديث حالة المسار.');
    }

    public function syncRegions(Project $project): RedirectResponse
    {
        $this->authorize('update', Project::class);
        $this->ensureProjectVisible($project);

        if (! $project->usesExecutionTracks()) {
            return back()->withErrors(['regions' => 'المشروع لم يبدأ مسارات التنفيذ بعد.']);
        }

        if (! $this->canManageProjectRegions($project)) {
            abort(403);
        }

        app(ProjectExecutionSpawner::class)->syncFromRegions($project, (int) auth()->id());
        app(ProjectAggregateStatusService::class)->refresh($project);

        return back()->with('success', 'تمت مزامنة مسارات التنفيذ مع المناطق.');
    }

    public function updateExecutionCoordinator(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('update', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);

        if (! $this->canManageProjectRegions($project)) {
            abort(403);
        }

        $validated = $request->validate([
            'coordinator_mode' => ['required', 'in:person,self,external'],
            'coordinator_id' => ['nullable', 'exists:people,id'],
            'coordinator_external_name' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = ['updated_by' => auth()->id()];

        if ($validated['coordinator_mode'] === 'self') {
            $payload['coordinator_id'] = $project->project_manager_id;
            $payload['coordinator_external_name'] = null;
        } elseif ($validated['coordinator_mode'] === 'external') {
            $payload['coordinator_id'] = null;
            $payload['coordinator_external_name'] = $validated['coordinator_external_name'];
        } else {
            $payload['coordinator_id'] = $validated['coordinator_id'];
            $payload['coordinator_external_name'] = null;
        }

        $execution->update($payload);

        return back()->with('success', 'تم تحديث المنسق.');
    }

    private function ensureExecutionBelongsToProject(Project $project, ProjectExecution $execution): void
    {
        if ((int) $execution->project_id !== (int) $project->id) {
            abort(404);
        }
    }

    private function ensureExecutionVisible(ProjectExecution $execution): void
    {
        if (! $execution->isVisibleToUser(auth()->user())) {
            abort(403);
        }
    }

    private function guardExecutionStatus(ProjectExecution $execution, array $allowed): void
    {
        if (! in_array($execution->workflow_status, $allowed, true)) {
            abort(422, 'حالة المسار الحالية لا تسمح بهذا الإجراء.');
        }
    }

    private function shouldRedirectMonitorToExecutionWork(ProjectExecution $execution): bool
    {
        $user = auth()->user();

        return $user?->person?->role === 'monitor'
            && $execution->isAssignedMonitor($user)
            && in_array($execution->workflow_status, ['monitoring_in_progress', 'pending_monitoring_confirmation'], true);
    }

    private function canManageProjectRegions(Project $project): bool
    {
        $user = auth()->user();

        if ($user?->super_admin) {
            return true;
        }

        $person = $user?->person;

        if (! $person) {
            return false;
        }

        return in_array($person->role, ['monitoring_director', 'admin'], true)
            || ($person->role === 'project_manager' && (int) $project->project_manager_id === (int) $person->id);
    }

    /** @return list<array{name: string, beneficiaries: int|null, execution_site: string|null}> */
    private function executionRegionsForViewer(Project $project, $user): array
    {
        $role = $user?->person?->role;

        if ($project->usesExecutionTracks() && in_array($role, ['coordinator', 'monitor'], true)) {
            return $project->executions->map(fn (ProjectExecution $execution) => [
                'name' => $execution->region_name,
                'beneficiaries' => $execution->region_beneficiaries,
                'execution_site' => $execution->region_execution_site,
            ])->values()->all();
        }

        return $project->executionRegionsForDisplay();
    }

    /** @param  list<array{name: string, beneficiaries: int|null, execution_site: string|null}>  $regions */
    private function executionRegionsBeneficiariesTotalForViewer(Project $project, $user, array $regions): ?int
    {
        $role = $user?->person?->role;

        if ($project->usesExecutionTracks() && in_array($role, ['coordinator', 'monitor'], true)) {
            $hasAny = false;
            $total = 0;

            foreach ($regions as $region) {
                if ($region['beneficiaries'] === null) {
                    continue;
                }

                $hasAny = true;
                $total += (int) $region['beneficiaries'];
            }

            return $hasAny ? $total : null;
        }

        return $project->executionRegionsBeneficiariesTotal();
    }

    private function authorizeCoordinatorFillExecutionAbility(ProjectExecution $execution): void
    {
        $user = auth()->user();

        if ($user?->super_admin) {
            return;
        }

        if ($user?->can('fill_coordinator', ProjectExecution::class)) {
            return;
        }

        $execution->loadMissing('project');
        $personId = $user?->person?->id;

        if ($execution->isSelfCoordinator()
            && $personId
            && (int) $personId === (int) $execution->project?->project_manager_id
            && in_array($execution->workflow_status, ['pending_coordinator', 'coordinator_filling'], true)) {
            return;
        }

        abort(403);
    }

    private function authorizeCoordinatorFillExecution(ProjectExecution $execution): void
    {
        $user = auth()->user();

        if ($user?->can('fill_coordinator', ProjectExecution::class)) {
            if ($execution->isSelfCoordinator()) {
                $execution->loadMissing('project');

                if ((int) $execution->project?->project_manager_id !== (int) $user?->person?->id && ! $user?->super_admin) {
                    abort(403);
                }
            } elseif ((int) $execution->coordinator_id !== (int) $user?->person?->id && ! $user?->super_admin) {
                abort(403);
            }
        }
    }

    private function authorizeSectionApprovalExecution(ProjectExecution $execution): void
    {
        if (! $execution->approvableBySectionManager(auth()->user()?->person) && ! auth()->user()?->super_admin) {
            abort(403);
        }
    }

    private function authorizeDepartmentApprovalExecution(ProjectExecution $execution): void
    {
        if (! $execution->approvableByDepartmentManager(auth()->user()?->person) && ! auth()->user()?->super_admin) {
            abort(403);
        }
    }

    private function authorizeMonitorFillExecution(ProjectExecution $execution): void
    {
        if (! $execution->isAssignedMonitor(auth()->user()) && ! auth()->user()?->super_admin) {
            abort(403);
        }
    }

    private function executionMonitorSubmitSessionKey(ProjectExecution $execution): string
    {
        return 'execution_monitor_submit_unlocked.' . $execution->id . '.' . auth()->id();
    }

    private function isExecutionMonitorSubmitUnlocked(ProjectExecution $execution): bool
    {
        return (bool) session($this->executionMonitorSubmitSessionKey($execution), false);
    }

    private function unlockExecutionMonitorSubmit(ProjectExecution $execution): void
    {
        session()->put($this->executionMonitorSubmitSessionKey($execution), true);
    }

    private function lockExecutionMonitorSubmit(ProjectExecution $execution): void
    {
        session()->forget($this->executionMonitorSubmitSessionKey($execution));
    }

    private function recordExecutionCoordinatorFilledAt(ProjectExecution $execution): void
    {
        if (! $execution->coordinator_filled_at) {
            $execution->update(['coordinator_filled_at' => now(), 'coordinator_filled_by' => auth()->id()]);
        }
    }

    private function saveExecutionChecklistValues(Request $request, Project $project, ProjectExecution $execution, string $column): void
    {
        if (! $request->has('checklist') || ! is_array($request->input('checklist'))) {
            return;
        }

        $activeItemIds = $this->activeChecklistItemIds();
        $personFieldItemIds = $this->activeChecklistPersonFieldItemIds();
        $fileFieldItemIds = $this->activeChecklistFileFieldItemIds();
        $rules = ['checklist' => ['required', 'array']];
        $submittedChecklist = $request->input('checklist', []);
        $itemIdsToValidate = $column === 'coordinator_value'
            ? array_values(array_intersect(
                $activeItemIds,
                array_map('intval', array_keys(is_array($submittedChecklist) ? $submittedChecklist : []))
            ))
            : $activeItemIds;

        foreach ($itemIdsToValidate as $itemId) {
            $isClosureDocItem = in_array($itemId, $fileFieldItemIds, true);
            $allowedValues = ($isClosureDocItem && in_array($column, ['coordinator_value', 'monitor_value'], true))
                ? 'ready,not_ready'
                : 'ready,partial,not_ready,not_required';
            $rules["checklist.{$itemId}.value"] = ['required', 'in:' . $allowedValues];
            $rules["checklist.{$itemId}.person_name"] = ['nullable', 'string', 'max:255'];

            if ($column === 'coordinator_value' && in_array($itemId, $fileFieldItemIds, true)) {
                $rules = array_merge($rules, $this->coordinatorLinkOnlyAttachmentRules('checklist', $itemId));
            }
        }

        $existingValues = ProjectChecklistValue::query()
            ->where('project_id', $project->id)
            ->where('project_execution_id', $execution->id)
            ->whereIn('checklist_item_id', $fileFieldItemIds)
            ->get()
            ->keyBy('checklist_item_id');

        $validator = Validator::make($request->all(), $rules, [
            'checklist.*.value.required' => 'يجب تحديد حالة كل بند في قائمة التحقق.',
        ]);

        $validator->after(function ($validator) use ($request, $personFieldItemIds, $fileFieldItemIds, $existingValues, $column) {
            if ($column === 'coordinator_value') {
                $this->rejectCoordinatorFileUploads($validator, $request, 'checklist', $fileFieldItemIds);
            }

            foreach ($personFieldItemIds as $itemId) {
                $value = $request->input("checklist.{$itemId}.value");
                $personName = trim((string) $request->input("checklist.{$itemId}.person_name", ''));

                if ($value === 'ready' && $personName === '') {
                    $validator->errors()->add(
                        "checklist.{$itemId}.person_name",
                        'اسم الشخص مطلوب عند اختيار جاهز.'
                    );
                }

                if ($value === 'partial' && $personName === '' && ! in_array($itemId, $fileFieldItemIds, true)) {
                    $validator->errors()->add(
                        "checklist.{$itemId}.person_name",
                        'اسم الشخص مطلوب عند اختيار جاهز أو جزئي.'
                    );
                }
            }

            foreach ($fileFieldItemIds as $itemId) {
                if ($column !== 'coordinator_value' || $request->input("checklist.{$itemId}.value") !== 'ready') {
                    continue;
                }

                $hasNewFile = false;
                $hasAttachment = $this->closureAttachmentProvided(
                    $request,
                    'checklist',
                    $itemId,
                    $existingValues->get($itemId),
                    $hasNewFile
                );

                if (! $hasAttachment) {
                    $validator->errors()->add(
                        "checklist.{$itemId}.attachment",
                        'المرفق مطلوب عند اختيار جاهز.'
                    );
                }
            }
        });

        $validated = $validator->validate();

        foreach ($itemIdsToValidate as $itemId) {
            $data = $validated['checklist'][$itemId] ?? null;

            if (! is_array($data)) {
                continue;
            }

            $attributes = [
                'project_id' => $project->id,
                'project_execution_id' => $execution->id,
                'checklist_item_id' => $itemId,
            ];
            $payload = [$column => $data['value']];

            if (array_key_exists('person_name', $data)) {
                $payload['person_name'] = $data['person_name'];
            }

            if ($column === 'coordinator_value' && in_array($itemId, $fileFieldItemIds, true)) {
                $this->mergeClosureAttachmentPayload($request, $project, $itemId, $attributes, $payload);
            }

            ProjectChecklistValue::updateOrCreate($attributes, $payload);
        }
    }

    private function saveExecutionClosureDocs(Request $request, Project $project, ProjectExecution $execution): void
    {
        $closureItemIds = Project::closureDocumentItemIds();

        if ($closureItemIds === []) {
            return;
        }

        $rules = ['closure_docs' => ['required', 'array']];

        foreach ($closureItemIds as $itemId) {
            $rules["closure_docs.{$itemId}.value"] = ['required', 'in:ready,not_ready'];
            $rules["closure_docs.{$itemId}.person_name"] = ['nullable', 'string', 'max:255'];
            $rules = array_merge($rules, $this->coordinatorLinkOnlyAttachmentRules('closure_docs', $itemId));
        }

        $existingValues = ProjectChecklistValue::query()
            ->where('project_id', $project->id)
            ->where('project_execution_id', $execution->id)
            ->whereIn('checklist_item_id', $closureItemIds)
            ->get()
            ->keyBy('checklist_item_id');

        $validator = Validator::make($request->all(), $rules, [
            'closure_docs.*.value.required' => 'يجب تحديد حالة كل بند في مستندات الإغلاق.',
        ]);

        $validator->after(function ($validator) use ($request, $closureItemIds, $existingValues) {
            $this->rejectCoordinatorFileUploads($validator, $request, 'closure_docs', $closureItemIds);

            foreach ($closureItemIds as $itemId) {
                $value = $request->input("closure_docs.{$itemId}.value");
                $personName = trim((string) $request->input("closure_docs.{$itemId}.person_name", ''));

                if ($value === 'ready' && $personName === '') {
                    $validator->errors()->add(
                        "closure_docs.{$itemId}.person_name",
                        'اسم الشخص مطلوب عند اختيار جاهز.'
                    );
                }

                if ($value !== 'ready') {
                    continue;
                }

                $hasNewFile = false;
                $hasAttachment = $this->closureAttachmentProvided(
                    $request,
                    'closure_docs',
                    $itemId,
                    $existingValues->get($itemId),
                    $hasNewFile
                );

                if (! $hasAttachment) {
                    $validator->errors()->add(
                        "closure_docs.{$itemId}.attachment",
                        'المرفق مطلوب عند اختيار جاهز.'
                    );
                }
            }
        });

        $validated = $validator->validate();

        foreach ($closureItemIds as $itemId) {
            $data = $validated['closure_docs'][$itemId] ?? null;

            if (! is_array($data)) {
                continue;
            }

            $attributes = [
                'project_id' => $project->id,
                'project_execution_id' => $execution->id,
                'checklist_item_id' => $itemId,
            ];
            $payload = [
                'coordinator_value' => $data['value'],
                'person_name' => $data['person_name'] ?? null,
            ];

            $this->mergeClosureAttachmentPayload($request, $project, $itemId, $attributes, $payload, 'closure_docs');

            ProjectChecklistValue::updateOrCreate($attributes, $payload);
        }
    }

    public function deleteExecutionChecklistAttachment(Request $request, Project $project, ProjectExecution $execution): RedirectResponse
    {
        $this->authorize('fill_coordinator', ProjectExecution::class);
        $this->ensureExecutionBelongsToProject($project, $execution);
        $this->ensureExecutionVisible($execution);
        $this->authorizeCoordinatorFillExecution($execution);
        $this->guardExecutionAttachmentDeleteStatus($execution);

        $validated = $request->validate([
            'checklist_item_id' => ['required', 'integer'],
            'attachment_id' => ['nullable', 'string', 'max:64'],
        ]);

        $itemId = (int) $validated['checklist_item_id'];
        $attachmentId = isset($validated['attachment_id']) && $validated['attachment_id'] !== ''
            ? (string) $validated['attachment_id']
            : null;
        $fileFieldItemIds = $this->activeChecklistFileFieldItemIds();

        if (! in_array($itemId, $fileFieldItemIds, true)) {
            abort(422, 'البند المحدد لا يدعم المرفقات.');
        }

        $value = ProjectChecklistValue::query()
            ->where('project_id', $project->id)
            ->where('project_execution_id', $execution->id)
            ->where('checklist_item_id', $itemId)
            ->first();

        if (! $value?->hasAttachment()) {
            return back()->with('success', 'لا يوجد مرفق لحذفه.');
        }

        $remaining = [];

        foreach ($value->attachmentsList() as $row) {
            $rowId = (string) ($row['id'] ?? '');
            $shouldRemove = $attachmentId === null || $rowId === $attachmentId;

            if (! $shouldRemove) {
                $remaining[] = $row;

                continue;
            }

            if (($row['type'] ?? '') === 'file' && ! empty($row['path'])) {
                Storage::disk('public')->delete($row['path']);
            }
        }

        $value->syncAttachments($remaining);

        if ($remaining === []) {
            $value->coordinator_value = 'not_ready';
        }

        $value->save();
        $execution->recalculateReadiness();

        return back()->with('success', 'تم حذف المرفق.');
    }

    private function executionCoordinatorChecklistReady(ProjectExecution $execution): bool
    {
        return $this->executionChecklistReadyForSubmission($execution, 'coordinator_value');
    }

    private function executionMonitorChecklistReady(ProjectExecution $execution): bool
    {
        return $this->executionChecklistReadyForSubmission($execution, 'monitor_value');
    }

    private function executionChecklistReadyForSubmission(ProjectExecution $execution, string $column): bool
    {
        $activeItems = $this->activeChecklistSubmissionItems();

        if ($activeItems->isEmpty()) {
            return true;
        }

        $values = $execution->checklistValues()
            ->whereIn('checklist_item_id', $activeItems->pluck('id'))
            ->get()
            ->keyBy('checklist_item_id');

        return $this->checklistRowsReadyForSubmission($activeItems, $values, $column);
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\ChecklistItem> */
    private function activeChecklistSubmissionItems(): \Illuminate\Support\Collection
    {
        return \App\Models\ChecklistItem::query()
            ->where('is_active', true)
            ->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->orderBy('group_id')
            ->orderBy('order')
            ->get(['id', 'has_person_field', 'has_file_field']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ChecklistItem>  $activeItems
     * @param  \Illuminate\Support\Collection<int, ProjectChecklistValue>  $values
     */
    private function checklistRowsReadyForSubmission(
        \Illuminate\Support\Collection $activeItems,
        \Illuminate\Support\Collection $values,
        string $column
    ): bool {
        $closureDocItemIds = Project::closureDocumentItemIds();

        foreach ($activeItems as $item) {
            $row = $values->get($item->id);
            $status = $row?->{$column};

            if ($status === null || $status === '') {
                return false;
            }

            $isDeferredClosureDoc = $item->has_file_field
                && in_array((int) $item->id, $closureDocItemIds, true)
                && in_array($column, ['coordinator_value', 'monitor_value'], true);

            if ($status === 'not_ready' && ! $isDeferredClosureDoc) {
                return false;
            }

            if ($column === 'coordinator_value'
                && $item->has_file_field
                && $status === 'ready'
                && ! ($row?->hasAttachment() ?? false)) {
                return false;
            }

            if ($item->has_person_field && in_array($status, ['ready', 'partial'], true)) {
                if (! filled(trim((string) ($row?->person_name ?? '')))) {
                    return false;
                }
            }
        }

        return true;
    }

    private function coordinatorCanUploadClosureDocsExecution(ProjectExecution $execution): bool
    {
        if (! $execution->coordinatorCanFillClosureDocs()) {
            return false;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        $personId = $user->person?->id;

        if (! $personId) {
            return false;
        }

        if ((int) $personId === (int) $execution->coordinator_id) {
            return true;
        }

        $execution->loadMissing('project');

        if ((int) $personId !== (int) $execution->project?->project_manager_id) {
            return false;
        }

        if ($execution->isSelfCoordinator()) {
            return false;
        }

        if ($execution->coordinatorHasUserAccount()) {
            return false;
        }

        if ($execution->coordinator_readiness_pct !== null
            && (int) ($execution->coordinator_filled_by ?? 0) !== (int) $user->id) {
            return false;
        }

        return true;
    }

    private function saveExecutionPrimaryActivityFields(ProjectExecution $execution, array $validated): void
    {
        $execution->unsetRelation('primaryMonitoringActivity');
        $activity = $execution->primaryMonitoringActivity;

        if (! $activity) {
            return;
        }

        $activityFields = $this->normalizeMonitorActivityPayload($validated);
        $activityFields['updated_by'] = auth()->id();

        $activity->update($activityFields);
    }

    /** @param  array<string, mixed>  $validated */
    private function normalizeMonitorActivityPayload(array $validated): array
    {
        return collect($validated)->only([
            'responsible_person_id',
            'activity_date',
            'activity_time',
            'activity_type',
            'subject',
            'notes',
            'field_problem',
            'action_taken',
            'quality_value',
            'closure_value',
            'deduction_value',
        ])->map(function ($value, $key) {
            if ($value === '') {
                return $key === 'deduction_value' ? 0 : null;
            }

            if ($key === 'field_problem') {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }

            return $value;
        })->all();
    }

    protected function mergeNormalizedActivityTime(Request $request): void
    {
        if (! $request->has('activity_time')) {
            return;
        }

        $request->merge([
            'activity_time' => $this->normalizeActivityTimeInput($request->input('activity_time')),
        ]);
    }

    protected function normalizeActivityTimeInput(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        $time = trim((string) $time);

        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            [$hours, $minutes] = array_map('intval', explode(':', $time, 2));

            return sprintf('%02d:%02d', $hours, $minutes);
        }

        if (preg_match('/^(\d{1,2}:\d{2}):\d{2}$/', $time, $matches)) {
            [$hours, $minutes] = array_map('intval', explode(':', $matches[1], 2));

            return sprintf('%02d:%02d', $hours, $minutes);
        }

        try {
            return \Carbon\Carbon::parse($time)->format('H:i');
        } catch (\Throwable) {
            return $time;
        }
    }

    /** @return array<string, string> */
    protected function monitorActivityValidationMessages(): array
    {
        return [
            'activity_time.date_format' => 'صيغة الوقت غير صحيحة — اختر الوقت من القائمة أو استخدم ساعة:دقيقة (مثل 14:30).',
        ];
    }

    /** @return array<string, mixed> */
    private function executionShowViewData(Project $project, ProjectExecution $execution, $groups, $values, $monitors, array $executionRegionsForDisplay = []): array
    {
        $user = auth()->user();
        $canViewCoordinatorData = $execution->showsCoordinatorDataTo($user);
        $canViewMonitorData = $execution->showsMonitorDataTo($user);
        $canManageCoordinatorColumn = $this->canManageCoordinatorColumnExecution($execution);
        $coordinatorPhase = in_array($execution->workflow_status, ['pending_coordinator', 'coordinator_filling'], true);
        $canFillClosureDocs = $this->coordinatorCanUploadClosureDocsExecution($execution);

        return [
            'canFillCoordinator' => $user?->can('fill_coordinator', ProjectExecution::class) ?? false,
            'canViewCoordinatorData' => $canViewCoordinatorData,
            'canViewMonitorData' => $canViewMonitorData,
            'canManageCoordinatorColumn' => $canManageCoordinatorColumn && $coordinatorPhase,
            'canFillClosureDocs' => $canFillClosureDocs && ! $coordinatorPhase,
            'canApproveDept' => $user?->can('approve_department', ProjectExecution::class) ?? false,
            'canReject' => $user?->can('reject', ProjectExecution::class) ?? false,
            'canSetMonitoringInfo' => $user?->can('set_monitoring_info', MonitoringActivity::class) ?? false,
            'canAssignMonitor' => $user?->can('assign_monitor', MonitoringActivity::class) ?? false,
            'canManageMonitoringSetup' => $this->canManageMonitoringSetup($execution, $user),
            'canRejectExecution' => $this->canRejectExecution($execution),
            'canViewMergedChecklist' => $this->canViewMergedChecklistExecution($execution),
            'canConfirmPassageExecution' => $user?->hasPersonRole('monitoring_director')
                && $execution->awaitingMonitoringDirectorConfirmation(),
            'canViewRejectionHistory' => $execution->canUserViewRejectionHistory($user),
            'canViewMonitoringStatusPanel' => $execution->canViewMonitoringStatusPanel($user),
            'isAssignedMonitor' => $execution->isAssignedMonitor($user),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
            'gapOwnerOptions' => ProjectExecution::gapOwnerOptionsForRejector(
                $user?->person,
                (bool) $user?->super_admin
            ),
            'returnTargetOptions' => ProjectExecution::returnTargetOptionsForRejector(
                $user?->person,
                (bool) $user?->super_admin
            ),
            'defaultMonitoringDate' => $project->execution_start_date?->format('Y-m-d')
                ?? $execution->monitoring_date?->format('Y-m-d'),
            'canManageRegions' => $this->canManageProjectRegions($project),
            'showWorkflowActions' => $this->executionShowHasWorkflowActions($execution, $user),
            'valueLabels' => [
                'ready' => 'جاهز',
                'partial' => 'جزئي',
                'not_ready' => 'غير جاهز',
                'not_required' => 'غير مطلوب',
            ],
            'readinessBreakdown' => $execution->readinessBreakdown(),
            'deleteAttachmentUrl' => route('dashboard.projects.executions.delete-checklist-attachment', [$project, $execution]),
            'closureLateScore' => Project::closureLateScore(),
            'projectManagerDepartmentName' => $project->projectManagerDepartmentName(),
            'executionRegionsBeneficiariesTotal' => $this->executionRegionsBeneficiariesTotalForViewer(
                $project,
                $user,
                $executionRegionsForDisplay
            ),
        ];
    }

    private function guardExecutionClosureDocsFillStatus(ProjectExecution $execution): void
    {
        if (! $execution->coordinatorCanFillClosureDocs()) {
            abort(422, 'حالة المسار الحالية لا تسمح بحفظ مستندات الإغلاق.');
        }
    }

    private function guardExecutionAttachmentDeleteStatus(ProjectExecution $execution): void
    {
        $allowedStatuses = array_merge(
            ProjectExecution::coordinatorCanFillClosureDocsStatuses(),
            ['pending_coordinator']
        );

        if (! in_array($execution->workflow_status, $allowedStatuses, true)) {
            abort(422, 'حالة المسار الحالية لا تسمح بحذف المرفقات.');
        }
    }

    private function canManageCoordinatorColumnExecution(ProjectExecution $execution): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $person = $user->person;
        $personId = $person?->id;

        if ($person?->role === 'project_manager') {
            if (! $personId) {
                return false;
            }

            $execution->loadMissing('project');

            if ((int) $personId === (int) $execution->coordinator_id) {
                return in_array($execution->workflow_status, ['pending_coordinator', 'coordinator_filling'], true);
            }

            if ((int) $personId !== (int) $execution->project?->project_manager_id) {
                return false;
            }

            if ($execution->isSelfCoordinator()) {
                return in_array($execution->workflow_status, ['pending_coordinator', 'coordinator_filling'], true);
            }

            if ($execution->coordinatorHasUserAccount()) {
                return false;
            }

            if ($execution->coordinator_readiness_pct !== null
                && (int) ($execution->coordinator_filled_by ?? 0) !== (int) $user->id) {
                return false;
            }

            return true;
        }

        if ($user->super_admin) {
            return true;
        }

        if ($person?->role === 'monitoring_director') {
            return false;
        }

        if (! $user->can('fill_coordinator', ProjectExecution::class)) {
            return false;
        }

        if (! $personId) {
            return false;
        }

        if ((int) $personId === (int) $execution->coordinator_id) {
            return true;
        }

        $execution->loadMissing('project');

        if ((int) $personId !== (int) $execution->project?->project_manager_id) {
            return false;
        }

        if ($execution->isSelfCoordinator()) {
            return in_array($execution->workflow_status, ['pending_coordinator', 'coordinator_filling'], true);
        }

        if ($execution->coordinatorHasUserAccount()) {
            return false;
        }

        if ($execution->coordinator_readiness_pct !== null
            && (int) ($execution->coordinator_filled_by ?? 0) !== (int) $user->id) {
            return false;
        }

        return true;
    }

    private function executionShowHasWorkflowActions(ProjectExecution $execution, $user): bool
    {
        if (! $user?->person) {
            return false;
        }

        $person = $user->person;

        if ($execution->workflow_status === 'pending_section_manager'
            && $execution->approvableBySectionManager($person)) {
            return true;
        }

        if ($execution->workflow_status === 'pending_dept_manager'
            && $execution->approvableByDepartmentManager($person)) {
            return true;
        }

        return $execution->workflow_status === 'monitoring_in_progress'
            && $execution->isAssignedMonitor($user);
    }

    private function canRejectExecution(ProjectExecution $execution): bool
    {
        $user = auth()->user();

        if (! $user?->can('reject', ProjectExecution::class)) {
            return false;
        }

        if ($execution->workflow_status === 'rejected') {
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
            'section_manager' => $execution->workflow_status === 'pending_section_manager'
                && $execution->approvableBySectionManager($person),
            'department_manager' => $execution->workflow_status === 'pending_dept_manager'
                && $execution->approvableByDepartmentManager($person),
            'monitoring_director' => in_array($execution->workflow_status, [
                'pending_monitoring_manager',
                'monitoring_in_progress',
                'pending_monitoring_confirmation',
            ], true),
            default => false,
        };
    }

    private function authorizeRejectExecution(ProjectExecution $execution): void
    {
        abort_unless($this->canRejectExecution($execution), 403, 'غير مصرّح لك برفض المسار في هذه المرحلة.');
    }

    private function canViewMergedChecklistExecution(ProjectExecution $execution): bool
    {
        if (! in_array($execution->workflow_status, ['pending_monitoring_confirmation', 'passage_complete'], true)) {
            return false;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return $execution->showsCoordinatorDataTo($user) && $execution->showsMonitorDataTo($user);
        }

        return $user->person?->role === 'monitoring_director'
            && $execution->showsCoordinatorDataTo($user)
            && $execution->showsMonitorDataTo($user);
    }

    private function canManageMonitoringSetup(ProjectExecution $execution, ?\App\Models\User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user?->hasPersonRole('monitoring_director')) {
            return false;
        }

        return $execution->workflow_status === 'pending_monitoring_manager';
    }
}
