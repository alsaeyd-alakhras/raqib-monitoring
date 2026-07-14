<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\ChecklistGroup;
use App\Models\Constant;
use App\Models\Currency;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Yajra\DataTables\Facades\DataTables;

class ProjectController extends Controller
{
    private const STATUSES = [
        'draft', 'pending_coordinator', 'coordinator_filling',
        'pending_project_manager', 'pending_section_manager', 'pending_dept_manager', 'pending_monitoring_manager',
        'monitoring_in_progress', 'pending_monitoring_confirmation',
        'passage_complete', 'rejected',
    ];

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('view', Project::class);

        $query = Project::with(['center', 'department', 'projectManager', 'coordinator', 'monitorPerson', 'funder', 'primaryMonitoringActivity']);
        $closureItemIds = Project::closureDocumentItemIds();
        if ($closureItemIds !== []) {
            $query->with(['checklistValues' => fn ($q) => $q->whereIn('checklist_item_id', $closureItemIds)]);
        }
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
                $closureDocs = $project->closureAttachmentSummary();

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
                    'closure_docs_attached' => $closureDocs['attached'],
                    'closure_docs_total' => $closureDocs['total'],
                    'closure_docs_complete' => $closureDocs['complete'],
                    'closure_docs_label' => $closureDocs['label'],
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
        $closureItemIds = Project::closureDocumentItemIds();
        if ($closureItemIds !== []) {
            $query->with(['checklistValues' => fn ($q) => $q->whereIn('checklist_item_id', $closureItemIds)]);
        }
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
            'closure_docs_label' => $rows->map(fn ($p) => $p->closureAttachmentSummary()['label'])->unique()->values()->toArray(),
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
        $validated = $this->mergeAllocationImageUpload($request, $validated, projectNumber: $validated['project_number']);
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

        $project->load(['center', 'department', 'section', 'funder', 'currency', 'procurementRep', 'projectManager.department', 'monitorPerson', 'primaryMonitoringActivity.passageCompletedByUser', 'rejectedByUser', 'coordinatorFilledByUser', 'coordinatorSubmittedByUser', 'submittedToProjectManagerByUser', 'submittedToSectionManagerByUser', 'sectionManagerApprovedByUser', 'deptManagerApprovedByUser', 'monitoringManagerReceivedByUser', 'monitorSubmittedByUser', 'rejections.rejectedByUser', 'rejections.returnTargetPerson']);
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
                'monitor_negative_notes',
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
        $previousProjectNumber = $project->project_number;

        $validated = $this->validateProject($request, $project);
        $validated['project_number'] = $this->resolveProjectNumberFromValidated($validated, $project);
        $newProjectNumber = $validated['project_number'];
        unset($validated['project_number_seq']);

        $relocatedImagePath = $project->relocateStorageOnNumberChange(
            (string) $previousProjectNumber,
            (string) $newProjectNumber
        );

        if ($relocatedImagePath !== null) {
            $validated['allocation_image_path'] = $relocatedImagePath;
        }

        $validated = $this->mergeAllocationImageUpload($request, $validated, $project, $newProjectNumber);
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
        $this->recordCoordinatorFilledAt($project);

        if (in_array($project->workflow_status, ['pending_coordinator', 'draft'], true)) {
            $project->update(['workflow_status' => 'coordinator_filling']);
        }

        $project->recalculateReadiness();

        return back()->with('success', 'تم حفظ عمود المنسق.');
    }

    public function fillClosureDocs(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('fill_coordinator', Project::class);
        $this->authorizeCoordinatorFill($project);
        $this->guardClosureDocsFillStatus($project);
        $this->validateCoordinatorFillOnBehalf($request, $project);

        $this->saveClosureDocs($request, $project);
        $this->recordCoordinatorFilledBy($request, $project);
        $this->recordCoordinatorFilledAt($project);

        $project->recalculateReadiness();

        return back()->with('success', 'تم حفظ مستندات الإغلاق.');
    }

    public function deleteChecklistAttachment(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('fill_coordinator', Project::class);
        $this->authorizeCoordinatorFill($project);
        $this->guardChecklistAttachmentDelete($project);

        $validated = $request->validate([
            'checklist_item_id' => ['required', 'integer'],
        ]);

        $itemId = (int) $validated['checklist_item_id'];
        $fileFieldItemIds = $this->activeChecklistFileFieldItemIds();

        if (! in_array($itemId, $fileFieldItemIds, true)) {
            abort(422, 'البند المحدد لا يدعم المرفقات.');
        }

        $value = ProjectChecklistValue::query()
            ->where('project_id', $project->id)
            ->where('checklist_item_id', $itemId)
            ->first();

        if (! $value?->hasAttachment()) {
            return back()->with('success', 'لا يوجد مرفق لحذفه.');
        }

        if ($value->attachment_path) {
            Storage::disk('public')->delete($value->attachment_path);
        }

        $value->update([
            'attachment_path' => null,
            'attachment_original_name' => null,
            'attachment_uploaded_at' => null,
            'attachment_type' => 'file',
            'attachment_url' => null,
            'coordinator_value' => 'not_ready',
        ]);

        $project->recalculateReadiness();

        return back()->with('success', 'تم حذف المرفق.');
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
            'submitted_to_project_manager_at' => now(),
            'submitted_to_project_manager_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->clearProjectReturnNotice($project);

        return back()->with('success', 'تم إرسال المشروع لمدير المشروع.');
    }

    public function submitToSectionManager(Project $project): RedirectResponse
    {
        $this->authorize('update', Project::class);
        $this->guardStatus($project, ['pending_project_manager']);
        $this->authorizeProjectManagerReview($project);
        $project->loadMissing('checklistValues');

        if (! $this->coordinatorChecklistReadyForSubmission($project)) {
            return back()->withErrors([
                'coordinator' => 'لا يمكن الإرسال لمدير القسم قبل اكتمال تعبئة المنسق.',
            ]);
        }

        $project->update([
            'workflow_status' => 'pending_section_manager',
            'submitted_to_section_manager_at' => now(),
            'submitted_to_section_manager_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->clearProjectReturnNotice($project);

        return back()->with('success', 'تم إرسال المشروع لمدير القسم.');
    }

    public function approveSection(Project $project): RedirectResponse
    {
        $this->authorize('approve_section', Project::class);
        $this->guardStatus($project, ['pending_section_manager']);
        $this->authorizeSectionApproval($project);

        $project->update([
            'workflow_status' => 'pending_dept_manager',
            'section_manager_approved_at' => now(),
            'section_manager_approved_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->clearProjectReturnNotice($project);

        return back()->with('success', 'تمت الموافقة، أُرسل المشروع لمدير الدائرة.');
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

        if (empty($validated['monitoring_date']) && $project->execution_start_date) {
            $validated['monitoring_date'] = $project->execution_start_date->format('Y-m-d');
        }

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
            'projectManager.department', 'coordinator', 'primaryMonitoringActivity.passageCompletedByUser',
            'coordinatorSubmittedByUser', 'submittedToProjectManagerByUser', 'submittedToSectionManagerByUser',
            'sectionManagerApprovedByUser', 'deptManagerApprovedByUser', 'monitoringManagerReceivedByUser',
            'monitorSubmittedByUser', 'coordinatorFilledByUser',
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
            'monitor_negative_notes_text' => ['nullable', 'string'],
            'monitor_recommendations_text' => ['nullable', 'string'],
        ]);

        $project->update([
            'monitor_notes' => $this->linesToArray($validated['monitor_notes_text'] ?? ''),
            'monitor_negative_notes' => $this->linesToArray($validated['monitor_negative_notes_text'] ?? ''),
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
            'monitor_submitted_at' => now(),
            'monitor_submitted_by' => auth()->id(),
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
        $personFieldItemIds = $this->activeChecklistPersonFieldItemIds();
        $fileFieldItemIds = $this->activeChecklistFileFieldItemIds();
        $rules = ['checklist' => ['required', 'array']];

        foreach ($activeItemIds as $itemId) {
            $allowedValues = ($column === 'coordinator_value' && in_array($itemId, $fileFieldItemIds, true))
                ? 'ready,not_ready'
                : 'ready,partial,not_ready,not_required';
            $rules["checklist.{$itemId}.value"] = ['required', 'in:' . $allowedValues];
            $rules["checklist.{$itemId}.person_name"] = ['nullable', 'string', 'max:255'];
            if ($column === 'coordinator_value' && in_array($itemId, $fileFieldItemIds, true)) {
                $rules["checklist.{$itemId}.attachment"] = ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'];
                $rules["checklist.{$itemId}.attachment_type"] = ['nullable', 'in:file,url'];
                $rules["checklist.{$itemId}.attachment_url"] = ['nullable', 'string', 'max:2048'];
            }
        }

        $validator = Validator::make($request->all(), $rules, [
            'checklist.*.value.required' => 'يجب تحديد حالة كل بند في قائمة التحقق.',
        ]);

        $existingValues = $project->checklistValues()
            ->whereIn('checklist_item_id', $fileFieldItemIds)
            ->get()
            ->keyBy('checklist_item_id');

        $validator->after(function ($validator) use ($request, $personFieldItemIds, $fileFieldItemIds, $existingValues, $column) {
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

                $hasNewFile = $request->hasFile("checklist.{$itemId}.attachment");
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

            if ($column === 'coordinator_value' && in_array($itemId, $this->activeChecklistFileFieldItemIds(), true)) {
                $this->mergeClosureAttachmentPayload($request, $project, $itemId, $attributes, $payload);
            }

            ProjectChecklistValue::updateOrCreate($attributes, $payload);
        }
    }

    private function saveClosureDocs(Request $request, Project $project): void
    {
        $closureItemIds = Project::closureDocumentItemIds();

        if ($closureItemIds === []) {
            return;
        }

        $rules = ['closure_docs' => ['required', 'array']];

        foreach ($closureItemIds as $itemId) {
            $rules["closure_docs.{$itemId}.value"] = ['required', 'in:ready,not_ready'];
            $rules["closure_docs.{$itemId}.person_name"] = ['nullable', 'string', 'max:255'];
            $rules["closure_docs.{$itemId}.attachment"] = ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'];
            $rules["closure_docs.{$itemId}.attachment_type"] = ['nullable', 'in:file,url'];
            $rules["closure_docs.{$itemId}.attachment_url"] = ['nullable', 'string', 'max:2048'];
        }

        $existingValues = $project->checklistValues()
            ->whereIn('checklist_item_id', $closureItemIds)
            ->get()
            ->keyBy('checklist_item_id');

        $validator = Validator::make($request->all(), $rules, [
            'closure_docs.*.value.required' => 'يجب تحديد حالة كل بند في مستندات الإغلاق.',
        ]);

        $validator->after(function ($validator) use ($request, $closureItemIds, $existingValues) {
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

                $hasNewFile = $request->hasFile("closure_docs.{$itemId}.attachment");
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

            $attributes = ['project_id' => $project->id, 'checklist_item_id' => $itemId];
            $payload = [
                'coordinator_value' => $data['value'],
                'person_name' => $data['person_name'] ?? null,
            ];

            $this->mergeClosureAttachmentPayload($request, $project, $itemId, $attributes, $payload, 'closure_docs');

            ProjectChecklistValue::updateOrCreate($attributes, $payload);
        }
    }

    private function closureAttachmentProvided(
        Request $request,
        string $prefix,
        int $itemId,
        ?ProjectChecklistValue $existing,
        bool $hasNewFile
    ): bool {
        if ($hasNewFile) {
            return true;
        }

        $type = $request->input("{$prefix}.{$itemId}.attachment_type", $existing?->attachment_type ?? 'file');
        $url = trim((string) $request->input("{$prefix}.{$itemId}.attachment_url", ''));

        if ($type === 'url' && $url !== '') {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        return $existing?->hasAttachment() ?? false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $payload
     */
    private function mergeClosureAttachmentPayload(
        Request $request,
        Project $project,
        int $itemId,
        array $attributes,
        array &$payload,
        string $prefix = 'checklist'
    ): void {
        $field = "{$prefix}.{$itemId}.attachment";
        $type = $request->input("{$prefix}.{$itemId}.attachment_type", 'file');
        $url = trim((string) $request->input("{$prefix}.{$itemId}.attachment_url", ''));

        $existing = ProjectChecklistValue::query()
            ->where($attributes)
            ->first();

        if ($request->hasFile($field)) {
            if ($existing?->attachment_path) {
                Storage::disk('public')->delete($existing->attachment_path);
            }

            $directory = $project->storageDirectory() . '/closure-docs';
            $file = $request->file($field);
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $filename = $itemId . '.' . $extension;

            Storage::disk('public')->putFileAs($directory, $file, $filename);

            $payload['attachment_path'] = $directory . '/' . $filename;
            $payload['attachment_original_name'] = $file->getClientOriginalName();
            $payload['attachment_uploaded_at'] = now();
            $payload['attachment_type'] = 'file';
            $payload['attachment_url'] = null;

            return;
        }

        if ($type === 'url' && $url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            if ($existing?->attachment_path) {
                Storage::disk('public')->delete($existing->attachment_path);
            }

            $host = parse_url($url, PHP_URL_HOST) ?: 'رابط خارجي';

            $payload['attachment_path'] = null;
            $payload['attachment_original_name'] = 'رابط خارجي — ' . $host;
            $payload['attachment_url'] = $url;
            $payload['attachment_type'] = 'url';
            $payload['attachment_uploaded_at'] = now();
        }
    }

    /**
     * @deprecated use mergeClosureAttachmentPayload
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $payload
     */
    private function mergeClosureAttachmentUpload(
        Request $request,
        Project $project,
        int $itemId,
        array $attributes,
        array &$payload,
        string $prefix = 'checklist'
    ): void {
        $this->mergeClosureAttachmentPayload($request, $project, $itemId, $attributes, $payload, $prefix);
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

    /** @return list<int> */
    private function activeChecklistPersonFieldItemIds(): array
    {
        return \App\Models\ChecklistItem::query()
            ->where('is_active', true)
            ->where('has_person_field', true)
            ->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function activeChecklistFileFieldItemIds(): array
    {
        return \App\Models\ChecklistItem::query()
            ->where('is_active', true)
            ->where('has_file_field', true)
            ->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function guardClosureDocsFillStatus(Project $project): void
    {
        if (! $project->coordinatorCanFillClosureDocs()) {
            abort(422, 'حالة المشروع الحالية لا تسمح بحفظ مستندات الإغلاق.');
        }
    }

    private function guardChecklistAttachmentDelete(Project $project): void
    {
        $allowedStatuses = array_merge(
            Project::coordinatorCanFillClosureDocsStatuses(),
            ['pending_coordinator', 'draft']
        );

        if (! in_array($project->workflow_status, $allowedStatuses, true)) {
            abort(422, 'حالة المشروع الحالية لا تسمح بحذف المرفقات.');
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
            : old('coordinator_mode', $lockProjectManager ? 'self' : 'person');

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
            'associationOffices' => $this->constantOptions('association_offices'),
            'currencies' => Currency::orderBy('name')->get(['id', 'name', 'code', 'value_to_ils']),
            'currencyRatesJson' => Currency::pluck('value_to_ils', 'id'),
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
            'allSectionsUrl' => route('dashboard.sections.for-project', ['department' => '__ID__']),
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
            'showCoordinatorInSummary' => $project->showsCoordinatorIdentityTo(auth()->user()),
            'canViewMonitorData' => $this->canViewMonitorData($project),
            'isAssignedMonitor' => $project->isAssignedMonitor(auth()->user()),
            'canSetMonitoringInfo' => auth()->user()?->can('set_monitoring_info', MonitoringActivity::class),
            'canAssignMonitor' => auth()->user()?->can('assign_monitor', MonitoringActivity::class),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
            'showCoordinatorFillOnDraft' => $this->canFillCoordinatorOnDraft($project),
            'canSubmitToProjectManager' => $this->canSubmitToProjectManager($project),
            'canSubmitToSectionManager' => $this->canSubmitToSectionManager($project),
            'canManageCoordinatorColumn' => $canManageCoordinatorColumn,
            'requiresFillOnBehalfConfirm' => $isProjectManager
                && ! $isAssignedCoordinator
                && ! $project->isSelfCoordinator()
                && ! $project->coordinatorHasUserAccount()
                && $project->hasCoordinatorAssignment()
                && $canManageCoordinatorColumn,
            'readinessBreakdown' => $project->readinessBreakdown(),
            'coordinatorFillActorLabel' => $project->coordinatorFilledByLabel(),
            'canApproveSection' => auth()->user()?->can('approve_section', Project::class)
                && $project->workflow_status === 'pending_section_manager'
                && $project->approvableBySectionManager(auth()->user()?->person),
            'approverSectionManager' => $project->approverSectionManager(),
            'approverSectionManagerLabel' => $project->approverSectionManagerLabel(),
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
            'canViewMergedChecklist' => $this->canViewMergedChecklist($project),
            'canFillClosureDocs' => $canManageCoordinatorColumn && $project->coordinatorCanFillClosureDocs(),
            'closureDocItems' => $groups->flatMap(fn ($group) => $group->items)->filter(fn ($item) => $item->has_file_field)->values(),
            'closureLateScore' => Project::closureLateScore(),
            'defaultMonitoringDate' => $project->execution_start_date?->format('Y-m-d')
                ?? $project->monitoring_date?->format('Y-m-d'),
        ];
    }

    /** @return array<string, mixed> */
    private function buildPdfReportData(Project $project): array
    {
        $project->load([
            'center', 'department', 'section', 'funder', 'currency',
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

    private function authorizeSectionApproval(Project $project): void
    {
        $user = auth()->user();

        if ($user?->super_admin) {
            return;
        }

        $project->loadMissing('projectManager');
        $managerSectionId = $project->projectManager?->section_id;

        if (! $managerSectionId) {
            abort(422, 'مدير المشروع بلا قسم، لا يمكن توجيه الاعتماد لمدير القسم.');
        }

        $person = $user?->person;

        abort_if(! $person || $person->role !== 'section_manager', 403);
        abort_if((int) $person->section_id !== (int) $managerSectionId, 403);
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

    private function canSubmitToSectionManager(Project $project): bool
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
            'غير مصرّح لك بإرسال المشروع لمدير القسم.'
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

        if (! $user->can('fill_coordinator', Project::class)) {
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
        return $this->checklistReadyForSubmission($project, 'coordinator_value');
    }

    private function monitorChecklistReadyForSubmission(Project $project): bool
    {
        return $this->checklistReadyForSubmission($project, 'monitor_value');
    }

    private function checklistReadyForSubmission(Project $project, string $column): bool
    {
        $activeItems = \App\Models\ChecklistItem::query()
            ->where('is_active', true)
            ->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->orderBy('group_id')
            ->orderBy('order')
            ->get(['id', 'has_person_field']);

        if ($activeItems->isEmpty()) {
            return true;
        }

        $values = $project->checklistValues()
            ->whereIn('checklist_item_id', $activeItems->pluck('id'))
            ->get()
            ->keyBy('checklist_item_id');

        foreach ($activeItems as $item) {
            $row = $values->get($item->id);
            $status = $row?->{$column};

            if ($status === null || $status === '') {
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

    private function canViewMergedChecklist(Project $project): bool
    {
        if (! in_array($project->workflow_status, ['pending_monitoring_confirmation', 'passage_complete'], true)) {
            return false;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return $this->canViewCoordinatorData($project) && $this->canViewMonitorData($project);
        }

        return $user->person?->role === 'monitoring_director'
            && $this->canViewCoordinatorData($project)
            && $this->canViewMonitorData($project);
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

    private function mergeAllocationImageUpload(
        Request $request,
        array $validated,
        ?Project $project = null,
        ?string $projectNumber = null
    ): array {
        if (! $request->hasFile('allocation_image')) {
            return $validated;
        }

        if ($project?->allocation_image_path) {
            Storage::disk('public')->delete($project->allocation_image_path);
        }

        $directory = $project
            ? $project->storageDirectory($projectNumber ?? $validated['project_number'] ?? null)
            : Project::storageDirectoryForNumber($projectNumber ?? $validated['project_number'] ?? null);

        $file = $request->file('allocation_image');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = 'allocation.' . $extension;

        Storage::disk('public')->putFileAs($directory, $file, $filename);

        $validated['allocation_image_path'] = $directory . '/' . $filename;

        return $validated;
    }

    /** @return list<array{name: string, beneficiaries: int|null}> */
    private function normalizeExecutionRegions(int $zones, mixed $rawRegions): array
    {
        if ($zones <= 0) {
            return [];
        }

        $regions = is_array($rawRegions) ? array_values($rawRegions) : [];

        return array_slice(array_map(function ($region) {
            if (is_string($region)) {
                return [
                    'name' => trim($region),
                    'beneficiaries' => null,
                ];
            }

            $beneficiaries = $region['beneficiaries'] ?? null;

            return [
                'name' => trim((string) ($region['name'] ?? '')),
                'beneficiaries' => $beneficiaries === null || $beneficiaries === ''
                    ? null
                    : (int) $beneficiaries,
            ];
        }, $regions), 0, $zones);
    }

    private function applyFinancialDefaults(array $validated): array
    {
        $budget = isset($validated['project_budget']) ? (float) $validated['project_budget'] : null;
        $revenue = isset($validated['revenue_amount']) ? (float) ($validated['revenue_amount'] ?? 0) : 0.0;

        if (! array_key_exists('net_amount', $validated) || $validated['net_amount'] === null || $validated['net_amount'] === '') {
            if ($budget !== null) {
                $validated['net_amount'] = round($budget - $revenue, 2);
            }
        }

        $netAmount = isset($validated['net_amount']) ? (float) $validated['net_amount'] : null;

        if (! array_key_exists('exchange_rate', $validated) || $validated['exchange_rate'] === null || $validated['exchange_rate'] === '') {
            $currencyId = $validated['currency_id'] ?? null;

            if ($currencyId) {
                $currency = Currency::find($currencyId);
                $validated['exchange_rate'] = $currency?->value_to_ils ?? 1;
            }
        }

        $exchangeRate = isset($validated['exchange_rate']) ? (float) $validated['exchange_rate'] : null;

        if (! array_key_exists('execution_amount_ils', $validated) || $validated['execution_amount_ils'] === null || $validated['execution_amount_ils'] === '') {
            if ($netAmount !== null && $exchangeRate !== null) {
                $validated['execution_amount_ils'] = round($netAmount * $exchangeRate, 2);
            }
        }

        return $validated;
    }

    private function validationRules(?Project $project = null): array
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
            'execution_start_date' => ['required', 'date'],
            'location' => ['required', 'string'],
            'target_beneficiaries' => ['required', 'integer', 'min:0'],
            'execution_zones' => ['required', 'integer', 'min:0'],
            'execution_regions' => ['nullable', 'array'],
            'execution_regions.*.name' => ['nullable', 'string', 'max:255'],
            'execution_regions.*.beneficiaries' => ['nullable', 'integer', 'min:0'],
            'estimated_duration' => ['required', 'string', 'max:255'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'project_budget' => ['required', 'numeric', 'min:0'],
            'revenue_amount' => ['nullable', 'numeric', 'min:0'],
            'net_amount' => ['nullable', 'numeric'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'execution_amount_ils' => ['nullable', 'numeric', 'min:0'],
            'allocation_image' => [
                $project && $project->allocation_image_path ? 'nullable' : 'required',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:5120',
            ],
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
        $rules = $this->validationRules($project);
        $currentPerson = auth()->user()?->person;
        $isMonitoringDirector = $currentPerson?->role === 'monitoring_director';

        if ($currentPerson?->role === 'project_manager' && ! auth()->user()?->super_admin) {
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

            $zones = (int) $request->input('execution_zones', 0);
            $associationOffices = $this->constantOptions('association_offices');
            $regions = $this->normalizeExecutionRegions(
                $zones,
                $request->input('execution_regions', [])
            );
            $regionNames = array_map(fn ($region) => $region['name'], $regions);
            $targetBeneficiaries = (int) $request->input('target_beneficiaries', 0);
            $beneficiariesTotal = 0;
            $hasBeneficiaries = false;

            foreach ($regions as $region) {
                if ($region['beneficiaries'] === null) {
                    continue;
                }

                $hasBeneficiaries = true;
                $beneficiariesTotal += $region['beneficiaries'];
            }

            if ($zones > 0) {
                if (count($regions) !== $zones) {
                    $validator->errors()->add('execution_regions', 'يجب اختيار مكتب لكل منطقة تنفيذ.');
                } elseif (in_array('', $regionNames, true)) {
                    $validator->errors()->add('execution_regions', 'يجب اختيار مكتب لكل منطقة تنفيذ.');
                } elseif ($associationOffices !== [] && count(array_diff($regionNames, $associationOffices)) > 0) {
                    $validator->errors()->add('execution_regions', 'يجب اختيار المكاتب من قائمة مكاتب الجمعية.');
                } elseif (count($regionNames) !== count(array_unique($regionNames))) {
                    $validator->errors()->add('execution_regions', 'لا يمكن تكرار نفس المكتب أكثر من مرة.');
                }
            } elseif ($regions !== []) {
                $validator->errors()->add('execution_regions', 'لا يمكن إدخال مناطق عندما يكون عدد المناطق صفراً.');
            }

            if ($hasBeneficiaries && $beneficiariesTotal > $targetBeneficiaries) {
                $validator->errors()->add(
                    'execution_regions',
                    'مجموع المستفيدين في المناطق (' . number_format($beneficiariesTotal) . ') يتجاوز إجمالي المستهدفين (' . number_format($targetBeneficiaries) . ').'
                );
            }
        });

        $validated = $validator->validate();
        $validated['execution_regions'] = $this->normalizeExecutionRegions(
            (int) ($validated['execution_zones'] ?? 0),
            $request->input('execution_regions', [])
        );
        $validated = $this->applyFinancialDefaults($validated);
        unset($validated['allocation_image']);

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
        $user = auth()->user();
        $person = $user?->person;

        if ($user?->super_admin) {
            return (int) ($validated['project_manager_id'] ?? $request->input('project_manager_id'));
        }

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
                case 'closure_docs_label':
                    Project::applyClosureDocsLabelScope($query, $filteredValues);
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
            'section_manager' => $project->workflow_status === 'pending_section_manager'
                && $project->approvableBySectionManager($person),
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

    private function recordCoordinatorFilledAt(Project $project): void
    {
        if ($project->coordinator_filled_at) {
            return;
        }

        $project->update(['coordinator_filled_at' => now()]);
    }
}
