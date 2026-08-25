<?php

namespace App\Models;

use App\Models\Concerns\CalculatesChecklistReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectExecution extends Model
{
    use CalculatesChecklistReadiness;
    use HasFactory;

    protected $fillable = [
        'project_id',
        'region_key',
        'region_name',
        'region_beneficiaries',
        'region_execution_site',
        'sort_order',
        'is_active',
        'coordinator_id',
        'coordinator_external_name',
        'nomination_responsibility',
        'implementation_mechanism',
        'recipient_name',
        'recipient_phone',
        'monitor_person_id',
        'monitoring_date',
        'monitoring_method',
        'monitoring_stage',
        'coordinator_readiness_pct',
        'monitor_readiness_pct',
        'monitor_notes',
        'monitor_negative_notes',
        'monitor_recommendations',
        'workflow_status',
        'primary_monitoring_activity_id',
        'coordinator_submitted_at',
        'coordinator_submitted_by',
        'coordinator_filled_at',
        'coordinator_filled_by',
        'submitted_to_project_manager_at',
        'submitted_to_project_manager_by',
        'submitted_to_section_manager_at',
        'submitted_to_section_manager_by',
        'section_manager_approved_at',
        'section_manager_approved_by',
        'dept_manager_approved_at',
        'dept_manager_approved_by',
        'monitoring_manager_received_at',
        'monitoring_manager_received_by',
        'monitor_submitted_at',
        'monitor_submitted_by',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'gap_owner',
        'return_target',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'monitoring_date' => 'date',
        'is_active' => 'boolean',
        'monitor_notes' => 'array',
        'monitor_negative_notes' => 'array',
        'monitor_recommendations' => 'array',
        'coordinator_readiness_pct' => 'float',
        'monitor_readiness_pct' => 'float',
        'coordinator_submitted_at' => 'datetime',
        'coordinator_filled_at' => 'datetime',
        'submitted_to_project_manager_at' => 'datetime',
        'submitted_to_section_manager_at' => 'datetime',
        'section_manager_approved_at' => 'datetime',
        'dept_manager_approved_at' => 'datetime',
        'monitoring_manager_received_at' => 'datetime',
        'monitor_submitted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'coordinator_id');
    }

    public function monitorPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'monitor_person_id');
    }

    public function primaryMonitoringActivity(): BelongsTo
    {
        return $this->belongsTo(MonitoringActivity::class, 'primary_monitoring_activity_id');
    }

    public function getPrimaryMonitoringActivityRelation(): BelongsTo
    {
        return $this->primaryMonitoringActivity();
    }

    public function checklistValues(): HasMany
    {
        return $this->hasMany(ProjectChecklistValue::class);
    }

    public function rejections(): HasMany
    {
        return $this->hasMany(ProjectExecutionRejection::class, 'project_execution_id');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function displayLabel(): string
    {
        $number = $this->project?->project_number;

        return ($number ? $number . ' — ' : '') . $this->region_name;
    }

    public function hasCoordinatorAssignment(): bool
    {
        return $this->coordinator_id !== null || filled($this->coordinator_external_name);
    }

    public function isSelfCoordinator(): bool
    {
        $this->loadMissing('project');

        return $this->coordinator_id !== null
            && (int) $this->coordinator_id === (int) $this->project?->project_manager_id;
    }

    public function coordinatorDisplayName(): string
    {
        if ($this->coordinator) {
            return $this->coordinator->name;
        }

        if (filled($this->coordinator_external_name)) {
            return $this->coordinator_external_name . ' (خارجي)';
        }

        return '-';
    }

    public function isAssignedMonitor(?User $user): bool
    {
        $personId = $user?->person?->id;

        return $personId && (int) $this->monitor_person_id === (int) $personId;
    }

    public function approvableBySectionManager(?Person $person): bool
    {
        if (! $person || ! $person->hasRole('section_manager') || ! $person->section_id) {
            return false;
        }

        $this->loadMissing('project.projectManager');

        return (int) $this->project?->projectManager?->section_id === (int) $person->section_id;
    }

    public function approvableByDepartmentManager(?Person $person): bool
    {
        if (! $person || ! $person->hasRole('department_manager') || ! $person->department_id) {
            return false;
        }

        $this->loadMissing('project.projectManager');

        return (int) $this->project?->projectManager?->department_id === (int) $person->department_id;
    }

    public function syncMonitoringWorkflowState(): void
    {
        $activity = $this->primaryMonitoringActivity;

        if (! $activity) {
            return;
        }

        if ($activity->workflow_status === 'pending_confirmation'
            && $this->workflow_status === 'monitoring_in_progress') {
            $this->update(['workflow_status' => 'pending_monitoring_confirmation']);

            return;
        }

        if ($activity->workflow_status === 'completed'
            && ! $activity->is_passage_complete
            && $this->workflow_status === 'monitoring_in_progress') {
            $activity->update(['workflow_status' => 'pending_confirmation']);
            $this->update(['workflow_status' => 'pending_monitoring_confirmation']);

            return;
        }

        if ($activity->is_passage_complete
            && $activity->workflow_status === 'completed'
            && $this->workflow_status !== 'passage_complete') {
            $this->update(['workflow_status' => 'passage_complete']);
        }
    }

    public function completePassage(int $userId): void
    {
        $activity = $this->primaryMonitoringActivity;

        if ($activity) {
            $activity->update([
                'is_passage_complete' => true,
                'passage_completed_at' => $activity->passage_completed_at ?? now(),
                'passage_completed_by' => $activity->passage_completed_by ?? $userId,
                'workflow_status' => 'completed',
                'updated_by' => $userId,
            ]);
        }

        $this->update([
            'workflow_status' => 'passage_complete',
            'updated_by' => $userId,
        ]);

        if ($this->project) {
            app(\App\Services\Projects\ProjectAggregateStatusService::class)->refresh($this->project);
        }
    }

    public function canMonitorSubmitToDirector(): bool
    {
        return $this->workflow_status === 'monitoring_in_progress'
            && $this->primaryMonitoringActivity?->workflow_status === 'in_progress';
    }

    public function awaitingMonitoringDirectorConfirmation(): bool
    {
        return $this->workflow_status === 'pending_monitoring_confirmation'
            || $this->primaryMonitoringActivity?->workflow_status === 'pending_confirmation';
    }

    public static function workflowStatusLabels(): array
    {
        return Project::workflowStatusLabels();
    }

    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->super_admin || $user->canOverseeExecutions()) {
            return $query;
        }

        $person = $user->person;

        if (! $person) {
            return $query->whereRaw('1 = 0');
        }

        if ($person->hasAnyRole(['monitoring_director', 'general_management', 'admin'])) {
            return $query;
        }

        $restrictiveRoles = array_intersect($person->allRoles(), [
            'project_manager',
            'section_manager',
            'department_manager',
            'coordinator',
            'monitor',
        ]);

        if ($restrictiveRoles === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outer) use ($person, $restrictiveRoles) {
            $outer->whereRaw('0 = 1');

            if (in_array('project_manager', $restrictiveRoles, true)) {
                $outer->orWhere(function (Builder $q) use ($person) {
                    $q->whereHas('project', fn (Builder $inner) => $inner->where('project_manager_id', $person->id))
                        ->orWhere('coordinator_id', $person->id);
                });
            }

            if (in_array('section_manager', $restrictiveRoles, true) && $person->section_id) {
                $outer->orWhereHas('project.projectManager', fn (Builder $q) => $q->where('section_id', $person->section_id));
            }

            if (in_array('department_manager', $restrictiveRoles, true) && $person->department_id) {
                $outer->orWhereHas('project.projectManager', fn (Builder $q) => $q->where('department_id', $person->department_id));
            }

            if (in_array('coordinator', $restrictiveRoles, true)) {
                $outer->orWhere('coordinator_id', $person->id);
            }

            if (in_array('monitor', $restrictiveRoles, true)) {
                $outer->orWhere('monitor_person_id', $person->id);
            }
        });
    }

    public function isVisibleToUser(?User $user): bool
    {
        if (! $user || $user->super_admin || $user->canOverseeExecutions()) {
            return true;
        }

        $person = $user->person;

        if (! $person) {
            return false;
        }

        $this->loadMissing('project.projectManager');

        if ($person->hasAnyRole(['monitoring_director', 'general_management', 'admin'])) {
            return true;
        }

        $visible = false;

        if ($person->hasRole('project_manager')) {
            $visible = $visible
                || (int) $this->project?->project_manager_id === (int) $person->id
                || (int) $this->coordinator_id === (int) $person->id;
        }

        if ($person->hasRole('section_manager')) {
            $visible = $visible || ($person->section_id
                && (int) $this->project?->projectManager?->section_id === (int) $person->section_id);
        }

        if ($person->hasRole('department_manager')) {
            $visible = $visible || ($person->department_id
                && (int) $this->project?->projectManager?->department_id === (int) $person->department_id);
        }

        if ($person->hasRole('coordinator')) {
            $visible = $visible || (int) $this->coordinator_id === (int) $person->id;
        }

        if ($person->hasRole('monitor')) {
            $visible = $visible || (int) $this->monitor_person_id === (int) $person->id;
        }

        return $visible;
    }

    public function coordinatorHasUserAccount(): bool
    {
        $this->loadMissing('coordinator');

        return (bool) $this->coordinator?->user_id;
    }

    /**
     * مدير المشروع يرى عمود المنسق على مسارات مشروعه للمتابعة (قراءة بعد إرسال المنسق).
     */
    public function projectManagerCanViewCoordinatorData(User $user, Person $person): bool
    {
        if ((int) $this->project?->project_manager_id !== (int) $person->id) {
            return false;
        }

        if ($user->can('fill_coordinator', ProjectExecution::class)) {
            return true;
        }

        if ($this->isSelfCoordinator()
            && in_array($this->workflow_status, ['pending_coordinator', 'coordinator_filling'], true)) {
            return true;
        }

        return in_array($this->workflow_status, [
            'pending_section_manager',
            'pending_dept_manager',
            'pending_monitoring_manager',
            'monitoring_in_progress',
            'pending_monitoring_confirmation',
            'passage_complete',
        ], true);
    }

    public function showsCoordinatorDataTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $person = $user->person;

        if ($person?->hasRole('monitor')) {
            return false;
        }

        if ($person?->hasRole('project_manager')) {
            $this->loadMissing('project.projectManager');

            return $this->projectManagerCanViewCoordinatorData($user, $person);
        }

        if ($user->super_admin) {
            return true;
        }

        if (! $person) {
            return false;
        }

        $this->loadMissing('project.projectManager');

        if ($person->hasRole('coordinator') && (int) $this->coordinator_id === (int) $person->id) {
            return true;
        }

        if ($person->hasRole('section_manager') && $this->approvableBySectionManager($person)) {
            return true;
        }

        if ($person->hasRole('department_manager') && $this->approvableByDepartmentManager($person)) {
            return true;
        }

        if ($person->hasAnyRole(['monitoring_director', 'general_management', 'admin'])) {
            return true;
        }

        return $user->can('fill_coordinator', self::class)
            || $user->can('approve_section', self::class)
            || $user->can('approve_department', self::class)
            || $user->can('update', self::class)
            || $user->can('reject', self::class);
    }

    public function showsMonitorDataTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $person = $user->person;

        if ($person?->hasRole('project_manager') && ! $person->hasAnyRole(['monitoring_director', 'general_management', 'admin'])) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        if (! $person) {
            return false;
        }

        if ($person->hasRole('monitor') && (int) $this->monitor_person_id === (int) $person->id) {
            return true;
        }

        if ($person->hasAnyRole(['monitoring_director', 'general_management'])) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, array{entered_at: ?\Illuminate\Support\Carbon, entered_by: null, completed_at: ?\Illuminate\Support\Carbon, completed_by: null, at: ?\Illuminate\Support\Carbon, by: null}>
     */
    public function workflowStepTimestamps(): array
    {
        $passageAt = $this->primaryMonitoringActivity?->passage_completed_at;

        $steps = [
            'pending_coordinator' => [
                'entered_at' => $this->coordinator_submitted_at,
                'completed_at' => $this->coordinator_filled_at,
            ],
            'coordinator_filling' => [
                'entered_at' => $this->coordinator_filled_at,
                'completed_at' => $this->submitted_to_section_manager_at,
            ],
            'pending_section_manager' => [
                'entered_at' => $this->submitted_to_section_manager_at,
                'completed_at' => $this->section_manager_approved_at,
            ],
            'pending_dept_manager' => [
                'entered_at' => $this->section_manager_approved_at,
                'completed_at' => $this->dept_manager_approved_at,
            ],
            'pending_monitoring_manager' => [
                'entered_at' => $this->dept_manager_approved_at,
                'completed_at' => $this->monitoring_manager_received_at,
            ],
            'monitoring_in_progress' => [
                'entered_at' => $this->monitoring_manager_received_at,
                'completed_at' => $this->monitor_submitted_at,
            ],
            'pending_monitoring_confirmation' => [
                'entered_at' => $this->monitor_submitted_at,
                'completed_at' => $passageAt,
            ],
            'passage_complete' => [
                'entered_at' => $passageAt,
                'completed_at' => $passageAt,
            ],
        ];

        foreach ($steps as &$step) {
            $step['entered_by'] = null;
            $step['completed_by'] = null;
            $step['at'] = $step['completed_at'] ?? $step['entered_at'];
            $step['by'] = null;
        }
        unset($step);

        return $steps;
    }

    public function needsActionFromPerson(?Person $person): bool
    {
        if (! $person) {
            return false;
        }

        if ($person->hasRole('project_manager')) {
            if ((int) $this->project?->project_manager_id === (int) $person->id
                && $this->isSelfCoordinator()
                && in_array($this->workflow_status, ['pending_coordinator', 'coordinator_filling'], true)) {
                return true;
            }

            if ((int) $this->coordinator_id === (int) $person->id
                && in_array($this->workflow_status, ['pending_coordinator', 'coordinator_filling'], true)) {
                return true;
            }
        }

        if ($person->hasRole('coordinator')
            && (int) $this->coordinator_id === (int) $person->id
            && in_array($this->workflow_status, ['pending_coordinator', 'coordinator_filling'], true)) {
            return true;
        }

        if ($person->hasRole('section_manager')
            && $this->workflow_status === 'pending_section_manager'
            && $this->approvableBySectionManager($person)) {
            return true;
        }

        if ($person->hasRole('department_manager')
            && $this->workflow_status === 'pending_dept_manager'
            && $this->approvableByDepartmentManager($person)) {
            return true;
        }

        if ($person->hasRole('monitoring_director')
            && in_array($this->workflow_status, ['pending_monitoring_manager', 'pending_monitoring_confirmation'], true)) {
            return true;
        }

        if ($person->hasRole('monitor')
            && (int) $this->monitor_person_id === (int) $person->id
            && $this->workflow_status === 'monitoring_in_progress'
            && $this->primaryMonitoringActivity?->workflow_status === 'in_progress') {
            return true;
        }

        return false;
    }

    public static function workflowStatusForReturnTarget(string $returnTarget): ?string
    {
        return Project::workflowStatusForReturnTarget($returnTarget);
    }

    public static function returnTargetOptionsForRejector(?Person $person, bool $superAdmin = false): array
    {
        $all = [
            'return_project_manager' => 'إرجاع لمدير المشروع (مسودة)',
            'return_coordinator' => 'إرجاع للمنسق (تعبئة)',
            'return_secretariat' => 'إرجاع لسكرتاريا الدائرة',
            'return_section_manager' => 'إرجاع لمدير القسم (موافقة)',
            'return_department_manager' => 'إرجاع لمدير الدائرة (موافقة)',
            'reject_final' => 'رفض قاطع نهائي (لا إرجاع)',
        ];

        if ($superAdmin || ! $person) {
            return Project::filterSecretariatRejectOptions($all);
        }

        $allowedKeys = [];
        if ($person->hasRole('section_manager')) {
            $allowedKeys = array_merge($allowedKeys, ['return_project_manager', 'return_coordinator', 'return_secretariat', 'reject_final']);
        }
        if ($person->hasRole('department_manager')) {
            $allowedKeys = array_merge($allowedKeys, ['return_project_manager', 'return_coordinator', 'return_secretariat', 'return_section_manager', 'reject_final']);
        }
        if ($person->hasRole('monitoring_director')) {
            return Project::filterSecretariatRejectOptions($all);
        }

        if ($allowedKeys === []) {
            return Project::filterSecretariatRejectOptions($all);
        }

        return Project::filterSecretariatRejectOptions(array_intersect_key($all, array_flip(array_unique($allowedKeys))));
    }

    public static function gapOwnerOptionsForRejector(?Person $person, bool $superAdmin = false): array
    {
        return Project::gapOwnerOptionsForRejector($person, $superAdmin);
    }

    public function hasPendingReturnNotice(): bool
    {
        return filled($this->rejection_reason)
            && filled($this->rejected_at)
            && $this->workflow_status !== 'rejected';
    }

    public function personIdForReturnTarget(?string $returnTarget): ?int
    {
        if (! filled($returnTarget) || $returnTarget === 'reject_final') {
            return null;
        }

        $this->loadMissing(['project.projectManager', 'coordinator']);

        return match ($returnTarget) {
            'return_project_manager', 'return_project_manager_review' => $this->project?->project_manager_id,
            'return_coordinator' => $this->isSelfCoordinator()
                ? $this->project?->project_manager_id
                : $this->coordinator_id,
            'return_section_manager' => $this->project?->approverSectionManager()?->id,
            'return_department_manager' => $this->project?->approverDepartmentManager()?->id,
            default => null,
        };
    }

    public function canViewMonitoringStatusPanel(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        return $user->person?->hasRole('monitoring_director');
    }

    public function canUserViewRejectionHistory(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        $person = $user->person;

        if (! $person) {
            return false;
        }

        $this->loadMissing('project');

        if ((int) $person->id === (int) $this->project?->project_manager_id) {
            return true;
        }

        if ($person->hasRole('monitoring_director')) {
            return true;
        }

        if ($person->hasRole('section_manager') && $this->approvableBySectionManager($person)) {
            return true;
        }

        if ($person->hasRole('department_manager') && $this->approvableByDepartmentManager($person)) {
            return true;
        }

        if ($this->hasPendingReturnNotice() && $this->isReturnTargetPerson($person)) {
            return true;
        }

        return false;
    }

    public function isReturnTargetPerson(?Person $person): bool
    {
        if (! $person || ! filled($this->return_target)) {
            return false;
        }

        $targetPersonId = $this->personIdForReturnTarget($this->return_target);

        return $targetPersonId && (int) $targetPersonId === (int) $person->id;
    }

    /** @return list<string> */
    public static function coordinatorCanFillClosureDocsStatuses(): array
    {
        return [
            'coordinator_filling',
            'pending_section_manager',
            'pending_dept_manager',
            'pending_monitoring_manager',
            'monitoring_in_progress',
            'pending_monitoring_confirmation',
            'passage_complete',
            'rejected',
        ];
    }

    public function coordinatorCanFillClosureDocs(): bool
    {
        return in_array($this->workflow_status, self::coordinatorCanFillClosureDocsStatuses(), true);
    }

    protected function groupReadinessPercent($items, string $column): ?float
    {
        $total = $items->count();
        $notRequired = $items->filter(fn ($item) => ($item->{$column} ?? null) === 'not_required')->count();
        $denominator = $total - $notRequired;

        if ($denominator <= 0) {
            return $total > 0 ? 100.0 : null;
        }

        $weightSum = $items
            ->filter(fn ($item) => ($item->{$column} ?? null) !== 'not_required')
            ->sum(fn ($item) => $this->checklistItemReadinessWeight($item, $column));

        return round(($weightSum / $denominator) * 100, 2);
    }

    protected function checklistItemReadinessWeight(object $item, string $column): float
    {
        $status = $item->{$column} ?? null;

        if ($column === 'coordinator_value' && ($item->has_file_field ?? false)) {
            if ($status !== 'ready' || ! ($item->has_attachment ?? false)) {
                return 0.0;
            }

            $this->loadMissing('project');
            $plannedEnd = $this->project?->planned_end_date;

            if (! $plannedEnd || ! ($item->attachment_uploaded_at ?? null)) {
                return 1.0;
            }

            $uploadedAt = $item->attachment_uploaded_at instanceof \Carbon\CarbonInterface
                ? $item->attachment_uploaded_at
                : \Carbon\Carbon::parse($item->attachment_uploaded_at);

            if ($uploadedAt->toDateString() <= $plannedEnd->toDateString()) {
                return 1.0;
            }

            return Project::closureLateScore();
        }

        return match ($status) {
            'ready' => 1.0,
            'partial' => 0.5,
            default => 0.0,
        };
    }
}
