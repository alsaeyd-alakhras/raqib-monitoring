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
use Illuminate\View\View;

class ProjectController extends Controller
{
    private const STATUSES = [
        'draft', 'pending_coordinator', 'coordinator_filling',
        'pending_dept_manager', 'pending_monitoring_manager',
        'monitoring_in_progress', 'rejected',
    ];

    public function index(): View
    {
        $this->authorize('view', Project::class);

        $projects = Project::with(['center', 'department', 'projectManager', 'coordinator', 'monitorPerson'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('dashboard.projects.index', compact('projects'));
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('dashboard.projects.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate($this->validationRules());
        $validated['workflow_status'] = 'draft';
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $project = Project::create($validated);

        return redirect()
            ->route('dashboard.projects.show', $project)
            ->with('success', 'تم إنشاء المشروع بنجاح.');
    }

    public function show(Project $project): View|RedirectResponse
    {
        $this->authorize('view', Project::class);

        if ($this->shouldRedirectMonitorToWork($project)) {
            return redirect()->route('dashboard.projects.monitor-work', $project);
        }

        $project->load(['center', 'department', 'section', 'funder', 'projectManager', 'monitorPerson', 'primaryMonitoringActivity', 'rejectedByUser']);

        if (! $this->canViewCoordinatorData()) {
            $project->unsetRelation('coordinator');
            $project->makeHidden(['coordinator_id', 'coordinator_readiness_pct']);
        } else {
            $project->load('coordinator');
        }

        $groups = $this->activeChecklistGroups();
        $values = $project->checklistValues()->get()->keyBy('checklist_item_id');
        $people = Person::orderBy('name')->get();

        return view('dashboard.projects.show', $this->showViewData($project, $groups, $values, $people));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', Project::class);

        return view('dashboard.projects.edit', $this->formData() + ['project' => $project]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', Project::class);

        $validated = $request->validate($this->validationRules($project->id));
        $validated['updated_by'] = auth()->id();

        $project->update($validated);

        return redirect()
            ->route('dashboard.projects.show', $project)
            ->with('success', 'تم تحديث المشروع بنجاح.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', Project::class);

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

        if (! $project->coordinator_id) {
            return back()->withErrors(['coordinator_id' => 'يجب تحديد المنسق قبل الإرسال.']);
        }

        $project->update([
            'workflow_status' => 'pending_coordinator',
            'coordinator_submitted_at' => now(),
            'coordinator_submitted_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        // تخطّي تلقائي إذا كان المنسق هو نفسه مدير المشروع
        if ((int) $project->coordinator_id === (int) $project->project_manager_id) {
            $project->update(['workflow_status' => 'coordinator_filling']);
        }

        return back()->with('success', 'تم إرسال المشروع للمنسق.');
    }

    public function fillCoordinator(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('fill_coordinator', Project::class);
        $this->guardStatus($project, ['pending_coordinator', 'coordinator_filling']);

        $this->saveChecklistValues($request, $project, 'coordinator_value');

        if ($project->workflow_status === 'pending_coordinator') {
            $project->update(['workflow_status' => 'coordinator_filling']);
        }

        $project->recalculateReadiness();

        return back()->with('success', 'تم حفظ عمود المنسق.');
    }

    public function submitToDeptManager(Project $project): RedirectResponse
    {
        $this->authorize('fill_coordinator', Project::class);
        $this->guardStatus($project, ['coordinator_filling']);

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
            'monitor_person_id' => ['required', 'exists:people,id'],
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
        $this->guardStatus($project, ['monitoring_in_progress']);

        // عزل بيانات المنسق على مستوى الاستعلام: لا يُحمَّل coordinator_id ولا coordinator_value إطلاقاً
        $project->loadMissing(['center', 'department', 'section', 'funder', 'primaryMonitoringActivity']);

        $groups = $this->activeChecklistGroups();
        $values = $project->checklistValues()
            ->select(['id', 'project_id', 'checklist_item_id', 'monitor_value', 'person_name'])
            ->get()
            ->keyBy('checklist_item_id');

        return view('dashboard.projects.monitor-work', compact('project', 'groups', 'values'));
    }

    public function fillMonitor(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('fill_monitor', Project::class);
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

        return redirect()
            ->route('dashboard.projects.monitor-work', $project)
            ->with('success', 'تم حفظ عمود المراقب.');
    }

    public function confirmMonitoring(Project $project): RedirectResponse
    {
        $this->authorize('fill_monitor', Project::class);
        $this->guardStatus($project, ['monitoring_in_progress']);

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

        return back()->with('success', 'تم تأكيد إنهاء المراقبة، بانتظار تأكيد مدير الرقابة.');
    }

    /* ===================== الرفض وإعادة التوجيه ===================== */

    public function reject(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('reject', Project::class);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
            'gap_owner' => ['required', 'in:coordinator,dept_manager,other'],
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
        $validated = $request->validate([
            'checklist' => ['required', 'array'],
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

    private function formData(): array
    {
        return [
            'centers' => Center::orderBy('name')->get(),
            'departments' => Department::with('center')->orderBy('name')->get(),
            'sections' => Section::with('department')->orderBy('name')->get(),
            'funders' => Funder::orderBy('name')->get(),
            'people' => Person::orderBy('name')->get(),
            'projectTypes' => $this->constantOptions('project_types'),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
        ];
    }

    private function showViewData(Project $project, $groups, $values, $people): array
    {
        return [
            'project' => $project,
            'groups' => $groups,
            'values' => $values,
            'people' => $people,
            'canViewCoordinatorData' => $this->canViewCoordinatorData(),
            'canSetMonitoringInfo' => auth()->user()?->can('set_monitoring_info', MonitoringActivity::class),
            'canAssignMonitor' => auth()->user()?->can('assign_monitor', MonitoringActivity::class),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
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

    private function validationRules(?int $projectId = null): array
    {
        $uniqueRule = 'unique:projects,project_number' . ($projectId ? ',' . $projectId : '');

        return [
            'project_name' => ['required', 'string', 'max:255'],
            'project_number' => ['nullable', 'integer', $uniqueRule],
            'project_type' => ['nullable', 'string'],
            'funder_id' => ['nullable', 'exists:funders,id'],
            'procurement_rep' => ['nullable', 'string', 'max:255'],
            'project_manager_id' => ['required', 'exists:people,id'],
            'coordinator_id' => ['nullable', 'exists:people,id'],
            'center_id' => ['nullable', 'exists:centers,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string'],
            'target_beneficiaries' => ['nullable', 'integer', 'min:0'],
            'execution_zones' => ['nullable', 'integer', 'min:0'],
            'estimated_duration' => ['nullable', 'string', 'max:255'],
            'allocated_budget' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
