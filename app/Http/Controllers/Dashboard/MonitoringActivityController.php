<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\MonitoringActivityExport;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Yajra\DataTables\Facades\DataTables;

class MonitoringActivityController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('view', MonitoringActivity::class);

        $query = MonitoringActivity::with(['center', 'department', 'section', 'monitorPerson', 'responsiblePerson']);
        $query = $this->applyMonitorScope($query);

        if ($request->ajax()) {
            if ($request->from_date) {
                $query->whereDate('activity_date', '>=', $request->from_date);
            }
            if ($request->to_date) {
                $query->whereDate('activity_date', '<=', $request->to_date);
            }
            if ($request->column_filters) {
                $this->applyColumnFilters($query, $request->column_filters);
            }
            $this->applySort($query, $request->sort_column, $request->sort_direction);

            $sourceTypes = $this->sourceTypeLabels();
            $workflowLabels = MonitoringActivity::workflowStatusLabels();

            $rows = $query->get()->map(function (MonitoringActivity $activity) use ($sourceTypes, $workflowLabels) {
                return [
                    'id' => $activity->id,
                    'reference_code' => $activity->reference_code,
                    'activity_date' => optional($activity->activity_date)->format('Y-m-d') ?? '-',
                    'source_type_label' => $sourceTypes[$activity->source_type] ?? $activity->source_type,
                    'activity_type' => $activity->activity_type ?? '-',
                    'org_label' => trim(($activity->center?->name ?? '-') . ' / ' . ($activity->department?->name ?? '-')),
                    'responsible_name' => $activity->responsiblePerson?->name ?? '-',
                    'monitor_name' => $activity->monitorPerson?->name ?? '-',
                    'subject' => $activity->subject ?? '-',
                    'kpi_value' => $activity->kpi_value !== null ? number_format((float) $activity->kpi_value, 2) : '-',
                    'kpi_rating' => $activity->kpi_rating ?? '-',
                    'workflow_status_label' => $workflowLabels[$activity->workflow_status] ?? $activity->workflow_status,
                    'is_verified' => $activity->is_verified,
                    'verification_issues' => $activity->verificationIssues(),
                ];
            })->values();

            return DataTables::of($rows)
                ->addIndexColumn()
                ->make(true);
        }

        return view('dashboard.monitoring-activities.index', [
            'sourceTypes' => $this->sourceTypeLabels(),
            'workflowStatusLabels' => MonitoringActivity::workflowStatusLabels(),
        ]);
    }

    public function getFilterOptions(Request $request, string $column): JsonResponse
    {
        $this->authorize('view', MonitoringActivity::class);

        $query = MonitoringActivity::with(['center', 'department', 'monitorPerson', 'responsiblePerson']);
        $query = $this->applyMonitorScope($query);

        if ($request->from_date) {
            $query->whereDate('activity_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('activity_date', '<=', $request->to_date);
        }
        if ($request->active_filters) {
            $this->applyColumnFilters($query, $request->active_filters);
        }

        $rows = $query->get();
        $sourceTypes = $this->sourceTypeLabels();
        $workflowLabels = MonitoringActivity::workflowStatusLabels();

        $options = match ($column) {
            'activity_date' => $rows->pluck('activity_date')->filter()->map(fn ($d) => $d->format('Y-m-d'))->unique()->values()->toArray(),
            'source_type_label' => $rows->map(fn ($a) => $sourceTypes[$a->source_type] ?? $a->source_type)->filter()->unique()->values()->toArray(),
            'activity_type' => $rows->pluck('activity_type')->filter()->unique()->values()->toArray(),
            'org_label' => $rows->map(fn ($a) => trim(($a->center?->name ?? '-') . ' / ' . ($a->department?->name ?? '-')))->unique()->values()->toArray(),
            'responsible_name' => $rows->pluck('responsiblePerson.name')->filter()->unique()->values()->toArray(),
            'monitor_name' => $rows->pluck('monitorPerson.name')->filter()->unique()->values()->toArray(),
            'subject' => $rows->pluck('subject')->filter()->unique()->values()->toArray(),
            'kpi_value' => $rows->map(fn ($a) => $a->kpi_value !== null ? number_format((float) $a->kpi_value, 2) : null)->filter()->unique()->values()->toArray(),
            'kpi_rating' => $rows->pluck('kpi_rating')->filter()->unique()->values()->toArray(),
            'workflow_status_label' => $rows->map(fn ($a) => $workflowLabels[$a->workflow_status] ?? $a->workflow_status)->unique()->values()->toArray(),
            'reference_code' => $rows->pluck('reference_code')->filter()->unique()->values()->toArray(),
            default => [],
        };

        return response()->json($options);
    }

    public function create(Request $request): View
    {
        $this->authorizeActivityManagement();

        $prefill = $request->only([
            'source_type', 'source_id',
            'center_id', 'department_id', 'section_id',
            'subject', 'notes', 'monitor_person_id',
        ]);

        $sourceType = $prefill['source_type'] ?? 'project';

        return view('dashboard.monitoring-activities.create', $this->formData() + [
            'prefill' => $prefill,
            'suggestedReferenceCode' => $this->generateReferenceCode($sourceType),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeActivityManagement();

        $validated = $request->validate($this->validationRules());

        $validated['activity_role'] = 'secondary';

        if (empty($validated['reference_code'])) {
            $validated['reference_code'] = $this->generateReferenceCode($validated['source_type']);
        }

        $validated = $this->normalizeWorkflowStatus($validated);
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

        return view('dashboard.monitoring-activities.show', $this->buildShowData($monitoring_activity));
    }

    public function exportPdf(MonitoringActivity $monitoring_activity)
    {
        $this->authorize('view', MonitoringActivity::class);

        $pdf = PDF::loadView(
            'reports.monitoring-activities.pdf',
            $this->buildShowData($monitoring_activity),
            [],
            config('pdf')
        );

        return $pdf->stream('النشاط الرقابي ' . $monitoring_activity->reference_code . '.pdf');
    }

    public function exportExcel(MonitoringActivity $monitoring_activity)
    {
        $this->authorize('view', MonitoringActivity::class);

        $monitoring_activity->load([
            'center', 'department', 'section', 'monitorPerson', 'responsiblePerson',
            'funder', 'createdByUser', 'updatedByUser', 'passageCompletedByUser',
        ]);

        return Excel::download(
            new MonitoringActivityExport($monitoring_activity, $this->sourceTypeLabels(), MonitoringActivity::workflowStatusLabels()),
            'النشاط الرقابي ' . $monitoring_activity->reference_code . '.xlsx'
        );
    }

    private function buildShowData(MonitoringActivity $monitoring_activity): array
    {
        $monitoring_activity->load([
            'center', 'department', 'section', 'monitorPerson', 'responsiblePerson',
            'funder', 'rejectedByUser', 'createdByUser', 'updatedByUser', 'passageCompletedByUser',
            'project.projectManager', 'project.coordinator',
        ]);

        $linkedProject = $monitoring_activity->source_type === 'project' && $monitoring_activity->source_id
            ? Project::find($monitoring_activity->source_id)
            : null;

        if ($linkedProject) {
            $linkedProject->loadMissing([
                'center', 'department', 'section', 'funder', 'procurementRep',
                'projectManager.department', 'coordinator', 'monitorPerson',
            ]);
        }

        $secondaryActivities = collect();
        if (
            $monitoring_activity->activity_role === 'primary'
            && $monitoring_activity->source_type === 'project'
            && $monitoring_activity->source_id
        ) {
            $secondaryActivities = MonitoringActivity::secondaryForProject((int) $monitoring_activity->source_id)
                ->with('monitorPerson')
                ->orderBy('reference_code')
                ->get();
        }

        $user = auth()->user();

        return [
            'activity' => $monitoring_activity,
            'linkedProject' => $linkedProject,
            'secondaryActivities' => $secondaryActivities,
            'sourceTypes' => $this->sourceTypeLabels(),
            'workflowStatusLabels' => MonitoringActivity::workflowStatusLabels(),
            'canViewCoordinatorData' => $linkedProject?->showsCoordinatorDataTo($user) ?? false,
            'canViewMonitorData' => $linkedProject?->showsMonitorDataTo($user) ?? true,
            'canMonitorSubmit' => $monitoring_activity->canMonitorSubmit() && $monitoring_activity->isAssignedMonitor($user),
            'isMonitorEditor' => $this->isMonitorOnlyEditor($monitoring_activity),
        ];
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
                'isMonitorEditor' => $this->isMonitorOnlyEditor($monitoring_activity),
                'canMonitorSubmit' => $monitoring_activity->canMonitorSubmit() && $monitoring_activity->isAssignedMonitor(auth()->user()),
            ]
        );
    }

    public function update(Request $request, MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorize('update', MonitoringActivity::class);
        $this->authorizeEditAfterClosure($monitoring_activity);
        $this->guardMonitorActivityUpdate($monitoring_activity);

        if ($this->isMonitorOnlyEditor($monitoring_activity)) {
            $validated = $request->validate($this->monitorValidationRules());
            $validated['updated_by'] = auth()->id();
            $monitoring_activity->update($validated);

            return redirect()
                ->route('dashboard.monitoring-activities.show', $monitoring_activity)
                ->with('success', 'تم حفظ عمل المراقب بنجاح.');
        }

        $validated = $request->validate($this->validationRules($monitoring_activity->id));

        $validated['activity_role'] = $monitoring_activity->activity_role;

        if (empty($validated['reference_code'])) {
            $validated['reference_code'] = $monitoring_activity->reference_code;
        }

        $validated = $this->normalizeWorkflowStatus($validated, $monitoring_activity);
        $validated['updated_by'] = auth()->id();

        $monitoring_activity->update($validated);

        return redirect()
            ->route('dashboard.monitoring-activities.show', $monitoring_activity)
            ->with('success', 'تم تحديث النشاط الرقابي بنجاح.');
    }

    public function destroy(Request $request, MonitoringActivity $monitoring_activity): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', MonitoringActivity::class);

        $monitoring_activity->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => 'تم حذف النشاط الرقابي بنجاح.']);
        }

        return redirect()
            ->route('dashboard.monitoring-activities.index')
            ->with('success', 'تم حذف النشاط الرقابي بنجاح.');
    }

    public function submitToDirector(MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorize('update', MonitoringActivity::class);
        $this->guardMonitorSubmit($monitoring_activity);

        if ($monitoring_activity->activity_role === 'primary') {
            abort(422, 'النشاط الأساسي يُدار عبر شاشة عمل المراقب في المشروع.');
        }

        $monitoring_activity->update([
            'workflow_status' => 'pending_confirmation',
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('dashboard.monitoring-activities.show', $monitoring_activity)
            ->with('success', 'تم إرسال النشاط لمدير الرقابة — بانتظار تأكيد المرور.');
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

    private function isMonitorOnlyEditor(MonitoringActivity $activity): bool
    {
        $user = auth()->user();

        if (! $user || $user->super_admin || ! $activity->isAssignedMonitor($user)) {
            return false;
        }

        return ! $user->can('assign_monitor', MonitoringActivity::class)
            && ! $user->can('create', MonitoringActivity::class);
    }

    private function authorizeActivityManagement(): void
    {
        $user = auth()->user();

        if ($user?->can('create', MonitoringActivity::class)
            || $user?->can('assign_monitor', MonitoringActivity::class)) {
            return;
        }

        abort(403);
    }

    private function guardMonitorActivityUpdate(MonitoringActivity $activity): void
    {
        if (! $this->isMonitorOnlyEditor($activity)) {
            return;
        }

        if ($activity->activity_role === 'primary') {
            abort(403, 'النشاط الأساسي يُدار عبر شاشة عمل المراقب في المشروع.');
        }

        if ($activity->workflow_status !== 'in_progress') {
            abort(403, 'لا يمكن تعديل النشاط بعد إرساله لمدير الرقابة.');
        }
    }

    private function guardMonitorSubmit(MonitoringActivity $activity): void
    {
        if (! $activity->isAssignedMonitor(auth()->user())) {
            abort(403, 'هذا النشاط غير مُسنَد إليك.');
        }

        if (! $activity->canMonitorSubmit()) {
            abort(422, 'حالة النشاط الحالية لا تسمح بالإرسال لمدير الرقابة.');
        }
    }

    /** @param  array<string, mixed>  $validated */
    private function normalizeWorkflowStatus(array $validated, ?MonitoringActivity $existing = null): array
    {
        $status = $validated['workflow_status'] ?? ($existing?->workflow_status ?? 'pending_monitor');

        if (! empty($validated['monitor_person_id']) && $status === 'pending_monitor') {
            $status = 'in_progress';
        }

        if (empty($validated['monitor_person_id']) && $status === 'in_progress' && ! $existing) {
            $status = 'pending_monitor';
        }

        $validated['workflow_status'] = $status;

        return $validated;
    }

    /** @return array<string, mixed> */
    private function monitorValidationRules(): array
    {
        return [
            'activity_date' => ['nullable', 'date'],
            'activity_time' => ['nullable', 'date_format:H:i'],
            'activity_type' => ['nullable', 'string'],
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
        ];
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

    private function applyColumnFilters(Builder $query, array $columnFilters): void
    {
        foreach ($columnFilters as $fieldName => $values) {
            if (empty($values)) {
                continue;
            }

            if ($fieldName === 'activity_date' && is_array($values)) {
                if (isset($values['from'])) {
                    $query->whereDate('activity_date', '>=', $values['from']);
                }
                if (isset($values['to'])) {
                    $query->whereDate('activity_date', '<=', $values['to']);
                }

                continue;
            }

            $filteredValues = array_values(array_filter((array) $values, fn ($v) => ! in_array($v, ['الكل', 'all', 'All'], true)));

            if ($filteredValues === []) {
                continue;
            }

            switch ($fieldName) {
                case 'source_type_label':
                    $sourceMap = array_flip($this->sourceTypeLabels());
                    $keys = array_values(array_filter(array_map(fn ($v) => $sourceMap[$v] ?? null, $filteredValues)));
                    if ($keys !== []) {
                        $query->whereIn('source_type', $keys);
                    }
                    break;
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
                case 'responsible_name':
                    $query->whereHas('responsiblePerson', fn ($q) => $q->whereIn('name', $filteredValues));
                    break;
                case 'monitor_name':
                    $query->whereHas('monitorPerson', fn ($q) => $q->whereIn('name', $filteredValues));
                    break;
                case 'workflow_status_label':
                    $statusMap = array_flip(MonitoringActivity::workflowStatusLabels());
                    $keys = array_values(array_filter(array_map(fn ($v) => $statusMap[$v] ?? null, $filteredValues)));
                    if ($keys !== []) {
                        $query->whereIn('workflow_status', $keys);
                    }
                    break;
                case 'kpi_rating':
                    $query->whereIn('kpi_rating', $filteredValues);
                    break;
                case 'kpi_value':
                    $query->whereIn('kpi_value', array_map('floatval', $filteredValues));
                    break;
                default:
                    $query->whereIn($fieldName, $filteredValues);
                    break;
            }
        }
    }

    private function applySort(Builder $query, ?string $sortColumn, ?string $sortDirection): void
    {
        $dir = in_array(strtolower((string) $sortDirection), ['asc', 'desc'], true)
            ? strtolower($sortDirection)
            : null;

        if (empty($sortColumn) || $dir === null) {
            $query->orderBy('monitoring_activities.created_at', 'desc');

            return;
        }

        $baseTable = 'monitoring_activities';

        switch ($sortColumn) {
            case 'activity_date':
                $query->orderBy("{$baseTable}.activity_date", $dir);
                break;
            case 'reference_code':
                $query->orderBy("{$baseTable}.reference_code", $dir);
                break;
            case 'source_type_label':
                $query->orderBy("{$baseTable}.source_type", $dir);
                break;
            case 'activity_type':
                $query->orderBy("{$baseTable}.activity_type", $dir);
                break;
            case 'subject':
                $query->orderBy("{$baseTable}.subject", $dir);
                break;
            case 'kpi_value':
                $query->orderBy("{$baseTable}.kpi_value", $dir);
                break;
            case 'kpi_rating':
                $query->orderBy("{$baseTable}.kpi_rating", $dir);
                break;
            case 'workflow_status_label':
                $query->orderBy("{$baseTable}.workflow_status", $dir);
                break;
            case 'responsible_name':
                $query->leftJoin('people as responsible_people', "{$baseTable}.responsible_person_id", '=', 'responsible_people.id')
                    ->select("{$baseTable}.*")
                    ->orderBy('responsible_people.name', $dir);
                break;
            case 'monitor_name':
                $query->leftJoin('people as monitor_people', "{$baseTable}.monitor_person_id", '=', 'monitor_people.id')
                    ->select("{$baseTable}.*")
                    ->orderBy('monitor_people.name', $dir);
                break;
            default:
                $query->orderBy("{$baseTable}.created_at", 'desc');
                break;
        }
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

    public function checkReferenceCode(Request $request)
    {
        $this->authorize('view', MonitoringActivity::class);

        $sourceType = (string) $request->query('source_type', 'project');
        $code = trim((string) $request->query('reference_code', ''));
        $exceptId = $request->query('except_id');

        if ($code === '') {
            return response()->json([
                'valid' => false,
                'available' => false,
                'message' => 'أدخل رمز النشاط.',
                'suggested' => $this->generateReferenceCode($sourceType),
            ]);
        }

        $exists = MonitoringActivity::where('reference_code', $code)
            ->when(filled($exceptId), fn ($query) => $query->where('id', '!=', (int) $exceptId))
            ->exists();

        return response()->json([
            'valid' => true,
            'available' => ! $exists,
            'message' => $exists ? 'رمز النشاط مستخدم مسبقاً.' : 'الرمز متاح.',
            'suggested' => $exists ? $this->generateReferenceCode($sourceType) : null,
        ]);
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
