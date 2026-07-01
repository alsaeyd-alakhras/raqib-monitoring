<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Constant;
use App\Models\Funder;
use App\Models\MonitoringActivity;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringActivityController extends Controller
{
    public function index(): View
    {
        $this->authorize('view', MonitoringActivity::class);

        $activities = MonitoringActivity::with(['center', 'department', 'section', 'monitorPerson', 'responsiblePerson'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('dashboard.monitoring-activities.index', compact('activities'));
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

    public function edit(MonitoringActivity $monitoring_activity): View
    {
        $this->authorize('update', MonitoringActivity::class);
        $this->authorizeEditAfterClosure($monitoring_activity);

        return view(
            'dashboard.monitoring-activities.edit',
            $this->formData() + ['activity' => $monitoring_activity]
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

    private function authorizeEditAfterClosure(MonitoringActivity $monitoringActivity): void
    {
        if ($monitoringActivity->workflow_status === 'completed' && ! auth()->user()?->can('edit_ratings', MonitoringActivity::class)) {
            abort(403, 'لا يمكن تعديل نشاط مكتمل إلا من قبل مدير الرقابة العامة أو الإدارة العامة.');
        }
    }

    private function formData(): array
    {
        return [
            'centers' => Center::orderBy('name')->get(),
            'departments' => \App\Models\Department::with('center')->orderBy('name')->get(),
            'sections' => \App\Models\Section::with('department')->orderBy('name')->get(),
            'funders' => Funder::orderBy('name')->get(),
            'people' => Person::orderBy('name')->get(),
            'sourceTypes' => [
                'project' => 'مشروع',
                'external' => 'خارجي',
                'meeting' => 'محضر اجتماع',
            ],
            'activityTypes' => $this->constantOptions('activity_types'),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
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
            'activity_role' => ['required', 'in:primary,secondary'],
            'center_id' => ['required', 'exists:centers,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'responsible_person_id' => ['nullable', 'exists:people,id'],
            'monitor_person_id' => ['nullable', 'exists:people,id'],
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
