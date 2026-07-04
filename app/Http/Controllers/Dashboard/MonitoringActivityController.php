<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Constant;
use App\Models\Department;
use App\Models\Funder;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\Project;
use App\Models\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MonitoringActivityController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('view', MonitoringActivity::class);

        $query = MonitoringActivity::with(['center', 'department', 'section', 'monitorPerson', 'responsiblePerson']);
        $query = $this->applyMonitorScope($query);
        $query = $this->applyFilters($query, $request);

        $activities = $query
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.monitoring-activities.index', [
            'activities' => $activities,
            'filters' => $request->only(['source_type', 'workflow_status', 'monitor_person_id', 'date_from', 'date_to']),
            'sourceTypes' => $this->sourceTypeLabels(),
            'workflowStatusLabels' => MonitoringActivity::workflowStatusLabels(),
            'people' => Person::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MonitoringActivity::class);

        return view('dashboard.monitoring-activities.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MonitoringActivity::class);

        $validated = $request->validate($this->validationRules());

        if (empty($validated['reference_code'])) {
            $validated['reference_code'] = $this->generateReferenceCode($validated['source_type']);
        }

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        MonitoringActivity::create($validated);

        return redirect()
            ->route('dashboard.monitoring-activities.index')
            ->with('success', 'تم إنشاء النشاط الرقابي بنجاح.');
    }

    public function show(MonitoringActivity $monitoring_activity): View
    {
        $this->authorize('view', MonitoringActivity::class);

        $monitoring_activity->load([
            'center', 'department', 'section', 'monitorPerson', 'responsiblePerson',
            'funder', 'rejectedByUser', 'project.projectManager', 'project.coordinator',
        ]);

        $linkedProject = $monitoring_activity->source_type === 'project' && $monitoring_activity->source_id
            ? Project::find($monitoring_activity->source_id)
            : null;

        if ($linkedProject) {
            $linkedProject->loadMissing(['center', 'department', 'section', 'funder', 'projectManager', 'monitorPerson']);
        }

        return view('dashboard.monitoring-activities.show', [
            'activity' => $monitoring_activity,
            'linkedProject' => $linkedProject,
            'sourceTypes' => $this->sourceTypeLabels(),
            'workflowStatusLabels' => MonitoringActivity::workflowStatusLabels(),
            'canViewCoordinatorData' => $linkedProject?->showsCoordinatorDataTo(auth()->user()) ?? false,
            'canViewMonitorData' => $linkedProject?->showsMonitorDataTo(auth()->user()) ?? true,
        ]);
    }

    public function edit(MonitoringActivity $monitoring_activity): View
    {
        $this->authorize('update', MonitoringActivity::class);
        $this->authorizeEditAfterClosure($monitoring_activity);

        $monitoring_activity->load('rejectedByUser');

        $linkedProject = $monitoring_activity->source_type === 'project' && $monitoring_activity->source_id
            ? Project::with(['projectManager', 'monitorPerson'])->find($monitoring_activity->source_id)
            : null;

        return view(
            'dashboard.monitoring-activities.edit',
            $this->formData() + [
                'activity' => $monitoring_activity,
                'linkedProject' => $linkedProject,
                'canConfirmCompletion' => auth()->user()?->can('confirm_completion', MonitoringActivity::class),
                'canReject' => auth()->user()?->can('reject', MonitoringActivity::class),
            ]
        );
    }

    public function update(Request $request, MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorize('update', MonitoringActivity::class);
        $this->authorizeEditAfterClosure($monitoring_activity);

        $validated = $request->validate($this->validationRules($monitoring_activity->id));

        if (empty($validated['reference_code'])) {
            $validated['reference_code'] = $monitoring_activity->reference_code;
        }

        $validated['updated_by'] = auth()->id();

        $monitoring_activity->update($validated);

        return redirect()
            ->route('dashboard.monitoring-activities.index')
            ->with('success', 'تم تحديث النشاط الرقابي بنجاح.');
    }

    public function destroy(MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorize('delete', MonitoringActivity::class);

        $monitoring_activity->delete();

        return redirect()
            ->route('dashboard.monitoring-activities.index')
            ->with('success', 'تم حذف النشاط الرقابي بنجاح.');
    }

    public function confirmPassage(MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorize('confirm_completion', MonitoringActivity::class);

        if (! in_array($monitoring_activity->workflow_status, ['pending_confirmation', 'in_progress'], true)) {
            abort(422, 'حالة النشاط الحالية لا تسمح بتأكيد اكتمال المرور.');
        }

        $monitoring_activity->update([
            'is_passage_complete' => true,
            'passage_completed_at' => now(),
            'passage_completed_by' => auth()->id(),
            'workflow_status' => 'completed',
            'updated_by' => auth()->id(),
        ]);

        if ($monitoring_activity->source_type === 'project' && $monitoring_activity->source_id) {
            $project = Project::find($monitoring_activity->source_id);

            if ($project && $project->primary_monitoring_activity_id === $monitoring_activity->id) {
                $project->update([
                    'workflow_status' => 'passage_complete',
                    'updated_by' => auth()->id(),
                ]);
            }
        }

        return back()->with('success', 'تم تأكيد اكتمال المرور على النشاط.');
    }

    public function reject(Request $request, MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorize('reject', MonitoringActivity::class);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
            'gap_owner' => ['required', 'in:coordinator,dept_manager,other'],
        ]);

        $monitoring_activity->update($validated + [
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم رفض النشاط الرقابي.');
    }

    private function authorizeEditAfterClosure(MonitoringActivity $monitoringActivity): void
    {
        if ($monitoringActivity->workflow_status === 'completed' && ! auth()->user()?->can('edit_ratings', MonitoringActivity::class)) {
            abort(403, 'لا يمكن تعديل نشاط مكتمل إلا من قبل مدير الرقابة العامة أو الإدارة العامة.');
        }
    }

    private function applyMonitorScope(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user || $user->super_admin) {
            return $query;
        }

        $hasBroadAccess = $user->can('create', MonitoringActivity::class)
            || $user->can('assign_monitor', MonitoringActivity::class)
            || $user->can('set_monitoring_info', MonitoringActivity::class)
            || $user->can('confirm_completion', MonitoringActivity::class)
            || $user->can('edit_ratings', MonitoringActivity::class);

        if ($hasBroadAccess) {
            return $query;
        }

        $personId = Person::where('user_id', $user->id)->value('id');

        if ($personId) {
            return $query->where('monitor_person_id', $personId);
        }

        return $query->whereRaw('1 = 0');
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->string('source_type'));
        }

        if ($request->filled('workflow_status')) {
            $query->where('workflow_status', $request->string('workflow_status'));
        }

        if ($request->filled('monitor_person_id')) {
            $query->where('monitor_person_id', $request->integer('monitor_person_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('activity_date', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('activity_date', '<=', $request->string('date_to'));
        }

        return $query;
    }

    private function formData(): array
    {
        $projects = Project::orderBy('project_name')->get()->map(fn (Project $project) => (object) [
            'id' => $project->id,
            'name' => ($project->project_number ? $project->project_number . ' — ' : '') . $project->project_name,
        ]);

        return [
            'centers' => Center::orderBy('name')->get(),
            'funders' => Funder::orderBy('name')->get(),
            'people' => Person::orderBy('name')->get(),
            'monitors' => Person::withRole('monitor')->orderBy('name')->get(),
            'projects' => $projects,
            'sourceTypes' => $this->sourceTypeLabels(),
            'activityTypes' => $this->constantOptions('activity_types'),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
            'workflowStatusLabels' => MonitoringActivity::workflowStatusLabels(),
        ];
    }

    private function sourceTypeLabels(): array
    {
        return [
            'project' => 'مشروع',
            'external' => 'خارجي',
            'meeting' => 'محضر اجتماع',
        ];
    }

    private function constantOptions(string $key): array
    {
        $value = Constant::where('key', $key)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }

    private function validationRules(?int $activityId = null): array
    {
        $uniqueRule = 'unique:monitoring_activities,reference_code' . ($activityId ? ',' . $activityId : '');

        return [
            'reference_code' => ['nullable', 'string', 'max:255', $uniqueRule],
            'source_type' => ['required', 'in:project,external,meeting'],
            'source_id' => ['nullable', 'integer', 'exists:projects,id', 'required_if:source_type,project'],
            'activity_role' => ['required', 'in:primary,secondary'],
            'center_id' => ['nullable', 'exists:centers,id', 'required_without:source_id'],
            'department_id' => ['nullable', 'exists:departments,id', 'required_without:source_id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'responsible_person_id' => ['nullable', 'exists:people,id'],
            'monitor_person_id' => [
                'nullable',
                Rule::exists('people', 'id')->where('role', 'monitor'),
            ],
            'activity_date' => ['nullable', 'date'],
            'activity_time' => ['nullable', 'date_format:H:i'],
            'activity_type' => ['nullable', 'string'],
            'funder_id' => ['nullable', 'exists:funders,id'],
            'subject' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'field_problem' => ['required', 'boolean'],
            'action_taken' => ['nullable', 'string'],
            'execution_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quality_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'closure_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deduction_value' => ['nullable', 'numeric', 'max:0'],
            'monitoring_method' => ['nullable', 'string'],
            'monitoring_stage' => ['nullable', 'string'],
            'workflow_status' => ['required', 'in:pending_monitor,in_progress,pending_confirmation,completed'],
            'is_passage_complete' => ['required', 'boolean'],
        ];
    }

    private function generateReferenceCode(string $sourceType): string
    {
        $prefix = match ($sourceType) {
            'project' => 'MP',
            'external' => 'MA',
            'meeting' => 'MM',
            default => 'MX',
        };

        $lastNumber = MonitoringActivity::where('reference_code', 'like', $prefix . '-%')
            ->selectRaw('MAX(CAST(SUBSTR(reference_code, ?) AS UNSIGNED)) as max_num', [strlen($prefix) + 2])
            ->value('max_num');

        $nextNumber = ((int) $lastNumber) + 1;

        return $prefix . '-' . $nextNumber;
    }
}
