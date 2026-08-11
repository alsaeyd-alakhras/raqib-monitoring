<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\ConvertsMultilineNotes;
use App\Http\Controllers\Dashboard\Concerns\GeneratesActivityReferenceCode;
use App\Http\Controllers\Dashboard\Concerns\ManagesExternalActivityAttachments;
use App\Models\Center;
use App\Models\Constant;
use App\Models\Funder;
use App\Models\MonitoringActivity;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExternalActivityController extends Controller
{
    use GeneratesActivityReferenceCode;
    use ManagesExternalActivityAttachments;
    use ConvertsMultilineNotes;

    public function create(): View
    {
        $this->authorize('create_external', MonitoringActivity::class);

        return view('dashboard.external-activities.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create_external', MonitoringActivity::class);

        $validated = $request->validate($this->externalValidationRules());
        $user = auth()->user();
        $monitorPersonId = $this->resolveMonitorPersonId($request, $user);

        $activity = MonitoringActivity::create([
            ...$this->payloadWithNotes($request, $validated),
            'source_type' => 'external',
            'source_id' => null,
            'activity_role' => 'secondary',
            'workflow_status' => 'in_progress',
            'is_passage_complete' => false,
            'monitor_person_id' => $monitorPersonId,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        $this->mergeActivityAttachments($request, $activity);

        if ($request->input('action') === 'submit') {
            return $this->performSubmit($activity);
        }

        return redirect()
            ->route('dashboard.external-activities.edit', $activity)
            ->with('success', 'تم إنشاء النشاط الخارجي بنجاح.');
    }

    public function edit(MonitoringActivity $monitoring_activity): View
    {
        $this->authorizeExternalEdit($monitoring_activity);

        $monitoring_activity->load(['rejectedByUser', 'submittedByUser']);

        return view('dashboard.external-activities.edit', $this->formData() + [
            'activity' => $monitoring_activity,
        ]);
    }

    public function update(Request $request, MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorizeExternalEdit($monitoring_activity);

        $validated = $request->validate($this->externalValidationRules($monitoring_activity));

        $monitoring_activity->update($this->payloadWithNotes($request, $validated) + [
            'updated_by' => auth()->id(),
        ]);

        $this->mergeActivityAttachments($request, $monitoring_activity);

        if ($request->input('action') === 'submit') {
            return $this->performSubmit($monitoring_activity);
        }

        return redirect()
            ->route('dashboard.external-activities.edit', $monitoring_activity)
            ->with('success', 'تم حفظ النشاط الخارجي بنجاح.');
    }

    public function submit(MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorizeExternalEdit($monitoring_activity);

        return $this->performSubmit($monitoring_activity);
    }

    public function approve(MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorize('approve_external', MonitoringActivity::class);
        $this->guardExternalActivity($monitoring_activity);
        $this->guardStatus($monitoring_activity, ['pending_confirmation']);

        $monitoring_activity->update([
            'workflow_status' => 'completed',
            'is_passage_complete' => true,
            'passage_completed_at' => now(),
            'passage_completed_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('dashboard.monitoring-activities.show', $monitoring_activity)
            ->with('success', 'تم اعتماد النشاط الخارجي.');
    }

    public function returnToMonitor(Request $request, MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorize('approve_external', MonitoringActivity::class);
        $this->guardExternalActivity($monitoring_activity);
        $this->guardStatus($monitoring_activity, ['pending_confirmation']);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
            'gap_owner' => ['required', 'in:monitor,other'],
        ]);

        $monitoring_activity->update($validated + [
            'workflow_status' => 'in_progress',
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('dashboard.monitoring-activities.show', $monitoring_activity)
            ->with('success', 'تم إرجاع النشاط للمراقب للتعديل.');
    }

    public function rejectFinal(Request $request, MonitoringActivity $monitoring_activity): RedirectResponse
    {
        $this->authorize('approve_external', MonitoringActivity::class);
        $this->guardExternalActivity($monitoring_activity);
        $this->guardStatus($monitoring_activity, ['pending_confirmation']);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
            'gap_owner' => ['required', 'in:monitor,other'],
        ]);

        $monitoring_activity->update($validated + [
            'workflow_status' => 'rejected',
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('dashboard.monitoring-activities.show', $monitoring_activity)
            ->with('success', 'تم رفض النشاط الخارجي نهائياً.');
    }

    private function performSubmit(MonitoringActivity $activity): RedirectResponse
    {
        $this->guardExternalActivity($activity);
        $this->guardStatus($activity, ['in_progress']);

        if (! $activity->canSubmitExternal(auth()->user())) {
            abort(403, 'هذا النشاط غير مُسنَد إليك.');
        }

        $activity->update([
            'workflow_status' => 'pending_confirmation',
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'gap_owner' => null,
        ]);

        return redirect()
            ->route('dashboard.monitoring-activities.show', $activity)
            ->with('success', 'تم إرسال النشاط لمدير الرقابة — بانتظار الاعتماد.');
    }

    private function authorizeExternalEdit(MonitoringActivity $activity): void
    {
        $this->authorize('create_external', MonitoringActivity::class);
        $this->guardExternalActivity($activity);

        $user = auth()->user();

        if ($user?->super_admin || $user?->isMonitoringDirector()) {
            return;
        }

        if (! $activity->canMonitorEditExternal($user)) {
            abort(403, 'لا يمكن تعديل هذا النشاط في حالته الحالية.');
        }
    }

    private function guardExternalActivity(MonitoringActivity $activity): void
    {
        if (! $activity->isExternal()) {
            abort(404);
        }
    }

    /** @param  array<int, string>  $allowed */
    private function guardStatus(MonitoringActivity $activity, array $allowed): void
    {
        if (! in_array($activity->workflow_status, $allowed, true)) {
            abort(422, 'حالة النشاط الحالية لا تسمح بهذا الإجراء.');
        }
    }

    private function resolveMonitorPersonId(Request $request, $user): ?int
    {
        if ($user?->isMonitoringDirector() || $user?->super_admin) {
            $monitorId = $request->integer('monitor_person_id');

            return $monitorId > 0 ? $monitorId : null;
        }

        return $user?->person?->id;
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'centers' => Center::orderBy('name')->get(),
            'funders' => Funder::orderBy('name')->get(),
            'people' => Person::orderBy('name')->get(),
            'monitors' => Person::withRole('monitor')->orderBy('name')->get(),
            'activityTypes' => $this->constantOptions('activity_types'),
            'activityDetails' => $this->constantOptions('activity_details'),
            'monitoringMethods' => $this->constantOptions('monitoring_methods'),
            'monitoringStages' => $this->constantOptions('monitoring_stages'),
            'scaleExecution' => MonitoringActivity::scaleOptions('scale_execution'),
            'scaleQuality' => MonitoringActivity::scaleOptions('scale_quality'),
            'scaleClosure' => MonitoringActivity::scaleOptions('scale_closure'),
            'scaleDeduction' => MonitoringActivity::scaleOptions('scale_deduction'),
            'suggestedReferenceCode' => $this->generateReferenceCode('external'),
            'canPickMonitor' => auth()->user()?->isMonitoringDirector() || auth()->user()?->super_admin,
            'checkReferenceCodeUrl' => route('dashboard.monitoring-activities.check-reference-code'),
        ];
    }

    /** @return array<string, mixed> */
    private function externalValidationRules(?MonitoringActivity $activity = null): array
    {
        $detailOptions = array_values($this->constantOptions('activity_details'));

        return [
            'reference_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('monitoring_activities', 'reference_code')->ignore($activity?->id),
            ],
            'center_id' => ['required', 'exists:centers,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'responsible_person_id' => ['nullable', 'exists:people,id'],
            'activity_date' => ['nullable', 'date'],
            'activity_time' => ['nullable', 'date_format:H:i'],
            'activity_type' => ['nullable', 'string'],
            'detail' => ['nullable', 'string', Rule::in($detailOptions)],
            'funder_id' => ['nullable', 'exists:funders,id'],
            'subject' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'field_problem' => ['required', 'boolean'],
            'action_taken' => ['nullable', 'string'],
            'closure_date' => ['nullable', 'date'],
            'positive_notes_text' => ['nullable', 'string'],
            'negative_notes_text' => ['nullable', 'string'],
            'recommendations_text' => ['nullable', 'string'],
            'execution_value' => ['nullable', Rule::in($this->scaleAllowedValues('scale_execution'))],
            'quality_value' => ['nullable', Rule::in($this->scaleAllowedValues('scale_quality'))],
            'closure_value' => ['nullable', Rule::in($this->scaleAllowedValues('scale_closure'))],
            'deduction_value' => ['nullable', Rule::in($this->scaleAllowedValues('scale_deduction'))],
            'monitoring_method' => ['nullable', 'string'],
            'monitoring_stage' => ['nullable', 'string'],
            'activity_attachment_urls' => ['nullable', 'array'],
            'activity_attachment_urls.*' => ['nullable', 'url', 'max:2048'],
            'activity_attachments' => ['nullable', 'array'],
            'activity_attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadWithNotes(Request $request, array $validated): array
    {
        unset($validated['positive_notes_text'], $validated['negative_notes_text'], $validated['recommendations_text']);

        if (empty($validated['field_problem'])) {
            $validated['closure_date'] = null;
        }

        return $validated + [
            'positive_notes' => $this->linesToArray($request->input('positive_notes_text')),
            'negative_notes' => $this->linesToArray($request->input('negative_notes_text')),
            'recommendations' => $this->linesToArray($request->input('recommendations_text')),
        ];
    }

    /** @return list<int|float> */
    private function scaleAllowedValues(string $scaleKey): array
    {
        return array_map(
            fn (array $tier) => $tier['value'],
            MonitoringActivity::scaleOptions($scaleKey)
        );
    }

    /** @return array<int|string, string> */
    private function constantOptions(string $key): array
    {
        $value = Constant::where('key', $key)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }
}
