<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_name',
        'project_number',
        'project_type',
        'funder_id',
        'procurement_rep_id',
        'project_manager_id',
        'coordinator_id',
        'coordinator_external_name',
        'center_id',
        'department_id',
        'section_id',
        'planned_start_date',
        'planned_end_date',
        'execution_start_date',
        'location',
        'target_beneficiaries',
        'execution_zones',
        'execution_regions',
        'estimated_duration',
        'currency_id',
        'project_budget',
        'revenue_amount',
        'net_amount',
        'exchange_rate',
        'execution_amount_ils',
        'allocation_image_path',
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
        'coordinator_filled_by',
        'coordinator_filled_at',
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
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'execution_start_date' => 'date',
        'monitoring_date' => 'date',
        'execution_regions' => 'array',
        'project_budget' => 'decimal:2',
        'revenue_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'execution_amount_ils' => 'decimal:2',
        'coordinator_readiness_pct' => 'float',
        'monitor_readiness_pct' => 'float',
        'monitor_notes' => 'array',
        'monitor_negative_notes' => 'array',
        'monitor_recommendations' => 'array',
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

    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return list<array{name: string, beneficiaries: int|null}> */
    public function executionRegionsForDisplay(): array
    {
        $regions = $this->execution_regions;

        if (! is_array($regions) || $regions === []) {
            return [];
        }

        return array_values(array_map(function ($region) {
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
        }, $regions));
    }

    public function executionRegionsBeneficiariesTotal(): ?int
    {
        $regions = $this->executionRegionsForDisplay();
        $hasAny = false;
        $total = 0;

        foreach ($regions as $region) {
            if ($region['beneficiaries'] === null) {
                continue;
            }

            $hasAny = true;
            $total += $region['beneficiaries'];
        }

        return $hasAny ? $total : null;
    }

    public function procurementRep(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'procurement_rep_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'project_manager_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'coordinator_id');
    }

    public function coordinatorFilledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_filled_by');
    }

    public function coordinatorSubmittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_submitted_by');
    }

    public function submittedToProjectManagerByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_to_project_manager_by');
    }

    public function submittedToSectionManagerByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_to_section_manager_by');
    }

    public function sectionManagerApprovedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'section_manager_approved_by');
    }

    public function deptManagerApprovedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dept_manager_approved_by');
    }

    public function monitoringManagerReceivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'monitoring_manager_received_by');
    }

    public function monitorSubmittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'monitor_submitted_by');
    }

    /**
     * @return array<string, array{at: ?\Illuminate\Support\Carbon, by: ?User}>
     */
    public function workflowStepTimestamps(): array
    {
        return [
            'pending_coordinator' => [
                'at' => $this->coordinator_submitted_at,
                'by' => $this->coordinatorSubmittedByUser,
            ],
            'coordinator_filling' => [
                'at' => $this->coordinator_filled_at,
                'by' => $this->coordinatorFilledByUser,
            ],
            'pending_project_manager' => [
                'at' => $this->submitted_to_project_manager_at,
                'by' => $this->submittedToProjectManagerByUser,
            ],
            'pending_section_manager' => [
                'at' => $this->submitted_to_section_manager_at,
                'by' => $this->submittedToSectionManagerByUser,
            ],
            'pending_dept_manager' => [
                'at' => $this->section_manager_approved_at,
                'by' => $this->sectionManagerApprovedByUser,
            ],
            'pending_monitoring_manager' => [
                'at' => $this->dept_manager_approved_at,
                'by' => $this->deptManagerApprovedByUser,
            ],
            'monitoring_in_progress' => [
                'at' => $this->monitoring_manager_received_at,
                'by' => $this->monitoringManagerReceivedByUser,
            ],
            'pending_monitoring_confirmation' => [
                'at' => $this->monitor_submitted_at,
                'by' => $this->monitorSubmittedByUser,
            ],
            'passage_complete' => [
                'at' => $this->primaryMonitoringActivity?->passage_completed_at,
                'by' => $this->primaryMonitoringActivity?->passageCompletedByUser,
            ],
        ];
    }

    public static function formatFromSequence(int $sequence): string
    {
        return 'P-' . max(1, $sequence);
    }

    public static function sequenceFromProjectNumber(?string $number): ?int
    {
        if (! $number || ! self::isValidProjectNumberFormat($number)) {
            return null;
        }

        return (int) substr(self::normalizeProjectNumber($number), 2);
    }

    public static function normalizeProjectNumber(string $number): string
    {
        return strtoupper(trim($number));
    }

    public static function isValidProjectNumberFormat(string $number): bool
    {
        return (bool) preg_match('/^P-\d+$/', self::normalizeProjectNumber($number));
    }

    /**
     * مسار مجلد ملفات المشروع على قرص public (مثل projects/P-12).
     */
    public function storageDirectory(?string $projectNumber = null): string
    {
        $number = $projectNumber ?? $this->project_number;

        if ($number && self::isValidProjectNumberFormat($number)) {
            return 'projects/' . self::normalizeProjectNumber($number);
        }

        if ($this->id) {
            return 'projects/id-' . $this->id;
        }

        return 'projects/unassigned';
    }

    public static function storageDirectoryForNumber(?string $projectNumber): string
    {
        if ($projectNumber && self::isValidProjectNumberFormat($projectNumber)) {
            return 'projects/' . self::normalizeProjectNumber($projectNumber);
        }

        return 'projects/unassigned';
    }

    /**
     * نقل مجلد ملفات المشروع عند تغيير رقم المشروع وتحديث مسار صورة التخصيص.
     */
    public function relocateStorageOnNumberChange(string $oldProjectNumber, string $newProjectNumber): ?string
    {
        $oldNumber = self::normalizeProjectNumber($oldProjectNumber);
        $newNumber = self::normalizeProjectNumber($newProjectNumber);

        if ($oldNumber === $newNumber) {
            return null;
        }

        $oldDir = self::storageDirectoryForNumber($oldNumber);
        $newDir = self::storageDirectoryForNumber($newNumber);

        if ($oldDir === $newDir) {
            return null;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($oldDir)) {
            if ($disk->exists($newDir)) {
                foreach ($disk->allFiles($oldDir) as $file) {
                    $target = $newDir . '/' . basename($file);

                    if (! $disk->exists($target)) {
                        $disk->move($file, $target);
                    } else {
                        $disk->delete($file);
                    }
                }

                $disk->deleteDirectory($oldDir);
            } else {
                $disk->move($oldDir, $newDir);
            }
        }

        if (! $this->allocation_image_path) {
            return null;
        }

        $path = str_replace('\\', '/', $this->allocation_image_path);

        if (str_starts_with($path, $oldDir . '/')) {
            return $newDir . '/' . basename($path);
        }

        $relocatedAllocation = $newDir . '/' . basename($path);

        return $disk->exists($relocatedAllocation) ? $relocatedAllocation : null;
    }

    public function allocationImageUrl(): ?string
    {
        return $this->allocation_image_path
            ? asset('storage/' . $this->allocation_image_path)
            : null;
    }

    public function coordinatorFilledByLabel(): ?string
    {
        if ($this->coordinator_filled_by) {
            return ($this->coordinatorFilledByUser?->name ?? '-') . ' (نيابةً عن المنسق)';
        }

        if ($this->isSelfCoordinator() && $this->coordinator_readiness_pct !== null) {
            return $this->projectManager?->name . ' (مدير المشروع / منسق)';
        }

        if ($this->coordinator_id && $this->coordinator_readiness_pct !== null) {
            return $this->coordinator?->name;
        }

        return null;
    }

    public static function usedProjectNumberSequence(): array
    {
        return self::query()
            ->where('project_number', 'like', 'P-%')
            ->pluck('project_number')
            ->map(fn ($code) => (int) substr((string) $code, 2))
            ->filter(fn ($n) => $n > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function generateProjectNumber(): string
    {
        $next = 1;

        foreach (self::usedProjectNumberSequence() as $used) {
            if ($used > $next) {
                break;
            }

            if ($used === $next) {
                $next++;
            }
        }

        return 'P-' . $next;
    }

    public static function isProjectNumberAvailable(string $number, ?int $exceptProjectId = null): bool
    {
        $normalized = self::normalizeProjectNumber($number);

        if (! self::isValidProjectNumberFormat($normalized)) {
            return false;
        }

        return ! self::query()
            ->where('project_number', $normalized)
            ->when($exceptProjectId, fn ($query) => $query->where('id', '!=', $exceptProjectId))
            ->exists();
    }

    public function hasCoordinatorAssignment(): bool
    {
        return $this->coordinator_id !== null || filled($this->coordinator_external_name);
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

    public function isSelfCoordinator(): bool
    {
        return $this->coordinator_id !== null
            && (int) $this->coordinator_id === (int) $this->project_manager_id;
    }

    public function coordinatorMode(): string
    {
        if (filled($this->coordinator_external_name)) {
            return 'external';
        }

        if ($this->isSelfCoordinator()) {
            return 'self';
        }

        if ($this->coordinator_id) {
            return 'person';
        }

        return 'none';
    }

    /**
     * هل المنسق المعيّن (وضع person) له حساب دخول؟
     * self و external يُعاملان كبلا حساب منسق مستقل للنيابة.
     */
    public function coordinatorHasUserAccount(): bool
    {
        if ($this->isSelfCoordinator() || filled($this->coordinator_external_name)) {
            return false;
        }

        $this->loadMissing('coordinator');

        return (bool) $this->coordinator?->user_id;
    }

    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->super_admin) {
            return $query;
        }

        $person = $user->person;

        if (! $person) {
            return $query->whereRaw('1 = 0');
        }

        return match ($person->role) {
            'project_manager' => $query->where('project_manager_id', $person->id),
            'section_manager' => $person->section_id
                ? $query->whereHas('projectManager', fn (Builder $q) => $q->where('section_id', $person->section_id))
                : $query->whereRaw('1 = 0'),
            'department_manager' => $person->department_id
                ? $query->whereHas('projectManager', fn (Builder $q) => $q->where('department_id', $person->department_id))
                : $query->whereRaw('1 = 0'),
            'coordinator' => $query->where('coordinator_id', $person->id),
            'monitor' => $query->where('monitor_person_id', $person->id),
            default => $query,
        };
    }

    public function isVisibleToUser(?User $user): bool
    {
        if (! $user || $user->super_admin) {
            return true;
        }

        $person = $user->person;

        if (! $person) {
            return false;
        }

        return match ($person->role) {
            'project_manager' => (int) $this->project_manager_id === (int) $person->id,
            'section_manager' => $person->section_id
                && (int) $this->projectManager?->section_id === (int) $person->section_id,
            'department_manager' => $person->department_id
                && (int) $this->projectManager?->department_id === (int) $person->department_id,
            'coordinator' => (int) $this->coordinator_id === (int) $person->id,
            'monitor' => (int) $this->monitor_person_id === (int) $person->id,
            default => true,
        };
    }

    /**
     * هل يُعرَض اسم/هوية المنسق في ملخص المشروع وسير العمل؟
     * أوسع من showsCoordinatorDataTo — مدير المشروع يرى المنسق حتى أثناء مرحلة التعبئة.
     */
    public function showsCoordinatorIdentityTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->showsCoordinatorDataTo($user)) {
            return true;
        }

        $person = $user->person;

        if (! $person) {
            return false;
        }

        return $person->role === 'project_manager'
            && (int) $this->project_manager_id === (int) $person->id
            && $this->hasCoordinatorAssignment();
    }

    /**
     * هل يرى مدير المشروع تفاصيل عمود المنسق؟
     * بدون صلاحية fill_coordinator: يرى فقط بعد انتهاء تعبئة المنسق (مرحلة المراجعة فما بعد).
     */
    public function projectManagerCanViewCoordinatorData(User $user, Person $person): bool
    {
        if ((int) $this->project_manager_id !== (int) $person->id) {
            return false;
        }

        if ($user->can('fill_coordinator', self::class)) {
            return true;
        }

        return in_array($this->workflow_status, [
            'pending_project_manager',
            'pending_section_manager',
            'pending_dept_manager',
            'pending_monitoring_manager',
            'monitoring_in_progress',
            'pending_monitoring_confirmation',
            'passage_complete',
        ], true);
    }

    /**
     * هل يُعرَض عمود/بيانات المنسق لهذا المستخدم على هذا المشروع؟
     */
    public function showsCoordinatorDataTo(?User $user): bool
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

        if ($person->role === 'monitor') {
            return false;
        }

        return match ($person->role) {
            'project_manager' => $this->projectManagerCanViewCoordinatorData($user, $person),
            'coordinator' => (int) $this->coordinator_id === (int) $person->id,
            'section_manager' => $this->approvableBySectionManager($person),
            'department_manager' => $this->approvableByDepartmentManager($person),
            'monitoring_director', 'general_management' => true,
            default => $user->can('fill_coordinator', self::class)
                || $user->can('approve_section', self::class)
                || $user->can('approve_department', self::class)
                || $user->can('update', self::class)
                || $user->can('reject', self::class),
        };
    }

    /**
     * هل يُعرَض لوحة «حالة المراقبة» في صفحة المشروع؟ (مدير الرقابة فقط)
     */
    public function canViewMonitoringStatusPanel(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        return $user->person?->role === 'monitoring_director';
    }

    /**
     * هل يُعرَض عمود/بيانات المراقب لهذا المستخدم على هذا المشروع؟
     */
    public function showsMonitorDataTo(?User $user): bool
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

        return match ($person->role) {
            'monitor' => (int) $this->monitor_person_id === (int) $person->id,
            'monitoring_director', 'general_management' => true,
            default => false,
        };
    }

    /**
     * هل المستخدم هو المراقب المعيّن على هذا المشروع؟
     */
    public function isAssignedMonitor(?User $user): bool
    {
        $personId = $user?->person?->id;

        return $personId && (int) $this->monitor_person_id === (int) $personId;
    }

    /** @return array<string, string> خيارات إرجاع المشروع بعد الرفض */
    public static function returnTargetOptionsForRejector(?Person $person, bool $superAdmin = false): array
    {
        $all = [
            'return_project_manager' => 'إرجاع لمدير المشروع (مسودة)',
            'return_project_manager_review' => 'إرجاع لمدير المشروع (مراجعة)',
            'return_coordinator' => 'إرجاع للمنسق (تعبئة)',
            'return_section_manager' => 'إرجاع لمدير القسم (موافقة)',
            'return_department_manager' => 'إرجاع لمدير الدائرة (موافقة)',
            'reject_final' => 'رفض قاطع نهائي (لا إرجاع)',
        ];

        if ($superAdmin || ! $person) {
            return $all;
        }

        $allowedKeys = match ($person->role) {
            'section_manager' => ['return_project_manager', 'return_project_manager_review', 'return_coordinator', 'reject_final'],
            'department_manager' => ['return_project_manager', 'return_project_manager_review', 'return_coordinator', 'return_section_manager', 'reject_final'],
            'monitoring_director' => ['return_project_manager', 'return_project_manager_review', 'return_coordinator', 'return_section_manager', 'return_department_manager', 'reject_final'],
            default => array_keys($all),
        };

        return array_intersect_key($all, array_flip($allowedKeys));
    }

    public static function returnTargetLabel(?string $key): string
    {
        return self::returnTargetOptionsForRejector(null, true)[$key] ?? ($key ?: '—');
    }

    public static function workflowStatusForReturnTarget(string $returnTarget): ?string
    {
        return match ($returnTarget) {
            'return_project_manager' => 'draft',
            'return_project_manager_review' => 'pending_project_manager',
            'return_coordinator' => 'coordinator_filling',
            'return_section_manager' => 'pending_section_manager',
            'return_department_manager' => 'pending_dept_manager',
            'reject_final' => 'rejected',
            default => null,
        };
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

        $this->loadMissing(['projectManager', 'coordinator']);

        return match ($returnTarget) {
            'return_project_manager', 'return_project_manager_review' => $this->project_manager_id,
            'return_coordinator' => $this->isSelfCoordinator()
                ? $this->project_manager_id
                : $this->coordinator_id,
            'return_section_manager' => $this->approverSectionManager()?->id,
            'return_department_manager' => $this->approverDepartmentManager()?->id,
            default => null,
        };
    }

    public function isReturnTargetPerson(?Person $person): bool
    {
        if (! $person || ! filled($this->return_target)) {
            return false;
        }

        $targetPersonId = $this->personIdForReturnTarget($this->return_target);

        return $targetPersonId && (int) $targetPersonId === (int) $person->id;
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

        if ((int) $person->id === (int) $this->project_manager_id) {
            return true;
        }

        if ($person->role === 'monitoring_director') {
            return true;
        }

        if ($person->role === 'section_manager' && $this->approvableBySectionManager($person)) {
            return true;
        }

        if ($person->role === 'department_manager' && $this->approvableByDepartmentManager($person)) {
            return true;
        }

        if ($this->hasPendingReturnNotice() && $this->isReturnTargetPerson($person)) {
            return true;
        }

        return $this->rejections()
            ->where('return_target_person_id', $person->id)
            ->exists();
    }

    public function clearReturnNotice(): void
    {
        $this->forceFill([
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'return_target' => null,
            'gap_owner' => null,
        ])->save();
    }

    public function approvableBySectionManager(?Person $person): bool
    {
        if (! $person || $person->role !== 'section_manager' || ! $person->section_id) {
            return false;
        }

        $this->loadMissing('projectManager');

        return (int) $person->section_id === (int) $this->projectManager?->section_id;
    }

    public function approverSectionManager(): ?Person
    {
        $this->loadMissing('projectManager');
        $sectionId = $this->projectManager?->section_id;

        if (! $sectionId) {
            return null;
        }

        return Person::query()
            ->where('role', 'section_manager')
            ->where('section_id', $sectionId)
            ->first();
    }

    public function approverSectionManagerLabel(): string
    {
        $manager = $this->approverSectionManager();

        if ($manager) {
            return $manager->name;
        }

        $this->loadMissing('projectManager');

        if (! $this->projectManager?->section_id) {
            return '— (مدير المشروع غير مرتبط بقسم)';
        }

        return '— (لا يوجد مدير قسم معيّن لقسم مدير المشروع)';
    }

    public function approvableByDepartmentManager(?Person $person): bool
    {
        if (! $person || $person->role !== 'department_manager' || ! $person->department_id) {
            return false;
        }

        $this->loadMissing('projectManager');

        return (int) $person->department_id === (int) $this->projectManager?->department_id;
    }

    public function approverDepartmentManager(): ?Person
    {
        $this->loadMissing('projectManager');

        $departmentId = $this->projectManager?->department_id;

        if (! $departmentId) {
            return null;
        }

        static $cache = null;
        $cache ??= Person::query()
            ->where('role', 'department_manager')
            ->get()
            ->keyBy('department_id');

        return $cache->get($departmentId);
    }

    public function approverDepartmentManagerLabel(): string
    {
        $manager = $this->approverDepartmentManager();

        if ($manager) {
            return $manager->name;
        }

        if (! $this->projectManager?->department_id) {
            return '— (مدير المشروع غير مرتبط بدائرة)';
        }

        return '— (لا يوجد مدير دائرة معيّن لهذه الدائرة)';
    }

    public function projectManagerDepartmentName(): ?string
    {
        $this->loadMissing('projectManager.department');

        return $this->projectManager?->department?->name;
    }

    public static function gapOwnerLabels(): array
    {
        return [
            'project_manager' => 'مدير المشروع',
            'coordinator' => 'المنسق',
            'section_manager' => 'مدير القسم',
            'department_manager' => 'مدير الدائرة',
            'monitor' => 'المراقب',
            'other' => 'أخرى',
        ];
    }

    public static function gapOwnerLabel(?string $key): string
    {
        if ($key === 'dept_manager') {
            $key = 'department_manager';
        }

        return self::gapOwnerLabels()[$key] ?? ($key ?: '—');
    }

    /** @return array<string, string> */
    public static function gapOwnerOptionsForRejector(?Person $person, bool $superAdmin = false): array
    {
        $labels = self::gapOwnerLabels();

        if ($superAdmin || ! $person) {
            return $labels;
        }

        $allowedKeys = match ($person->role) {
            'section_manager' => ['project_manager', 'coordinator', 'other'],
            'department_manager' => ['project_manager', 'coordinator', 'section_manager', 'other'],
            'monitoring_director' => ['project_manager', 'coordinator', 'section_manager', 'department_manager', 'monitor', 'other'],
            'monitor' => ['project_manager', 'coordinator', 'section_manager', 'department_manager', 'other'],
            default => array_keys($labels),
        };

        return array_intersect_key($labels, array_flip($allowedKeys));
    }

    public function currentActionLabel(): string
    {
        $this->loadMissing(['projectManager', 'monitorPerson', 'primaryMonitoringActivity']);

        return match ($this->workflow_status) {
            'draft' => 'مدير المشروع: ' . ($this->projectManager?->name ?? '—'),
            'pending_coordinator', 'coordinator_filling' => 'المنسق: ' . $this->coordinatorDisplayName(),
            'pending_project_manager' => 'مدير المشروع: ' . ($this->projectManager?->name ?? '—') . ' — مراجعة وإرسال',
            'pending_section_manager' => 'مدير القسم: ' . $this->approverSectionManagerLabel(),
            'pending_dept_manager' => 'مدير الدائرة: ' . $this->approverDepartmentManagerLabel(),
            'pending_monitoring_manager' => 'مدير الرقابة العامة — تعيين مراقب',
            'monitoring_in_progress' => 'المراقب: ' . ($this->monitorPerson?->name ?? '—') . ' — تعبئة وإرسال',
            'pending_monitoring_confirmation' => 'مدير الرقابة العامة — تأكيد المرور',
            'passage_complete' => 'تم المرور — المشروع مكتمل',
            'rejected' => 'مرفوض — النقص: ' . self::gapOwnerLabel($this->gap_owner),
            default => self::workflowStatusLabels()[$this->workflow_status] ?? $this->workflow_status,
        };
    }

    public function needsActionFromPerson(?Person $person): bool
    {
        if (! $person) {
            return false;
        }

        return match ($person->role) {
            'project_manager' => (int) $this->project_manager_id === (int) $person->id
                && in_array($this->workflow_status, ['draft', 'pending_coordinator', 'coordinator_filling', 'pending_project_manager'], true),
            'coordinator' => (int) $this->coordinator_id === (int) $person->id
                && (
                    in_array($this->workflow_status, ['pending_coordinator', 'coordinator_filling'], true)
                    || $this->hasPendingClosureDocs()
                ),
            'section_manager' => $this->workflow_status === 'pending_section_manager'
                && $this->approvableBySectionManager($person),
            'department_manager' => $this->workflow_status === 'pending_dept_manager'
                && $this->approvableByDepartmentManager($person),
            'monitoring_director' => in_array($this->workflow_status, ['pending_monitoring_manager', 'pending_monitoring_confirmation'], true),
            'monitor' => (int) $this->monitor_person_id === (int) $person->id
                && $this->workflow_status === 'monitoring_in_progress'
                && $this->primaryMonitoringActivity?->workflow_status === 'in_progress',
            default => false,
        };
    }

    public static function workflowStatusLabels(): array
    {
        return [
            'draft' => 'مسودة',
            'pending_coordinator' => 'بانتظار المنسق',
            'coordinator_filling' => 'المنسق يعمل',
            'pending_project_manager' => 'بانتظار مدير المشروع',
            'pending_section_manager' => 'بانتظار مدير القسم',
            'pending_dept_manager' => 'بانتظار مدير الدائرة',
            'pending_monitoring_manager' => 'بانتظار مدير الرقابة العامة',
            'monitoring_in_progress' => 'قيد المراقبة',
            'pending_monitoring_confirmation' => 'بانتظار تأكيد مدير الرقابة',
            'passage_complete' => 'تم المرور',
            'rejected' => 'مرفوض',
        ];
    }

    /**
     * يُصلِح حالات المشروع/النشاط غير المتزامنة (مثلاً بعد تعديل يدوي للنشاط).
     */
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
    }

    public function canMonitorSubmitToDirector(): bool
    {
        return $this->workflow_status === 'monitoring_in_progress'
            && $this->primaryMonitoringActivity?->workflow_status === 'in_progress';
    }

    /**
     * هل حفظ المراقب عمله (قائمة التحقق و/أو الملاحظات) على الأقل مرة واحدة؟
     */
    public function hasSavedMonitorWork(): bool
    {
        if ($this->monitor_readiness_pct !== null) {
            return true;
        }

        if (filled($this->monitor_notes) || filled($this->monitor_negative_notes) || filled($this->monitor_recommendations)) {
            return true;
        }

        return $this->checklistValues()->whereNotNull('monitor_value')->exists();
    }

    public function awaitingMonitoringDirectorConfirmation(): bool
    {
        return $this->workflow_status === 'pending_monitoring_confirmation'
            || $this->primaryMonitoringActivity?->workflow_status === 'pending_confirmation';
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function monitorPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'monitor_person_id');
    }

    public function primaryMonitoringActivity(): BelongsTo
    {
        return $this->belongsTo(MonitoringActivity::class, 'primary_monitoring_activity_id');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function rejections(): HasMany
    {
        return $this->hasMany(ProjectRejection::class)->orderByDesc('rejected_at');
    }

    public function checklistValues(): HasMany
    {
        return $this->hasMany(ProjectChecklistValue::class);
    }

    public function secondaryMonitoringActivities(): Builder
    {
        return MonitoringActivity::query()
            ->where('source_type', 'project')
            ->where('source_id', $this->id)
            ->where('activity_role', 'secondary');
    }

    /**
     * Recompute coordinator/monitor readiness % from checklist values and persist.
     * Formula (per checklist-schema.md): per-group % = (ready + 0.5*partial) / (total - not_required),
     * overall % = simple average of active groups' %. "partial" counts half, "not_required" excluded from denominator.
     */
    public function recalculateReadiness(): void
    {
        $savedValues = $this->checklistValues()->with('checklistItem.group')->get()
            ->filter(fn (ProjectChecklistValue $value) => $value->checklistItem && $value->checklistItem->group && $value->checklistItem->group->is_active && $value->checklistItem->is_active)
            ->keyBy('checklist_item_id');

        $activeGroups = ChecklistGroup::query()
            ->where('is_active', true)
            ->with(['items' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('order')
            ->get();

        $groupedValues = collect();

        foreach ($activeGroups as $group) {
            $items = $group->items->map(function (ChecklistItem $item) use ($savedValues) {
                $saved = $savedValues->get($item->id);

                return (object) [
                    'coordinator_value' => $saved?->coordinator_value,
                    'monitor_value' => $saved?->monitor_value,
                    'has_file_field' => (bool) $item->has_file_field,
                    'attachment_uploaded_at' => $saved?->attachment_uploaded_at,
                    'has_attachment' => $saved?->hasAttachment(),
                ];
            });

            if ($items->isNotEmpty()) {
                $groupedValues->put($group->id, $items);
            }
        }

        $this->coordinator_readiness_pct = $this->averageReadiness($groupedValues, 'coordinator_value');
        $this->monitor_readiness_pct = $this->averageReadiness($groupedValues, 'monitor_value');
        $this->save();

        if ($this->primary_monitoring_activity_id) {
            $this->primaryMonitoringActivity()->update(['execution_value' => $this->monitor_readiness_pct]);
        }
    }

    /**
     * Per-group and overall readiness percentages for reports.
     *
     * @return array{groups: list<array{name: string, coordinator_pct: float|null, monitor_pct: float|null}>, overall: array{coordinator_pct: float|null, monitor_pct: float|null}}
     */
    public function readinessBreakdown(): array
    {
        $savedValues = $this->checklistValues()->with('checklistItem.group')->get()
            ->filter(fn (ProjectChecklistValue $value) => $value->checklistItem && $value->checklistItem->group && $value->checklistItem->group->is_active && $value->checklistItem->is_active)
            ->keyBy('checklist_item_id');

        $activeGroups = ChecklistGroup::query()
            ->where('is_active', true)
            ->with(['items' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('order')
            ->get();

        $groups = [];

        foreach ($activeGroups as $group) {
            $items = $group->items->map(function (ChecklistItem $item) use ($savedValues) {
                $saved = $savedValues->get($item->id);

                return (object) [
                    'coordinator_value' => $saved?->coordinator_value,
                    'monitor_value' => $saved?->monitor_value,
                    'has_file_field' => (bool) $item->has_file_field,
                    'attachment_uploaded_at' => $saved?->attachment_uploaded_at,
                    'has_attachment' => $saved?->hasAttachment(),
                ];
            });

            if ($items->isEmpty()) {
                continue;
            }

            $groups[] = [
                'name' => $group->name,
                'coordinator_pct' => $this->groupReadinessPercent($items, 'coordinator_value'),
                'monitor_pct' => $this->groupReadinessPercent($items, 'monitor_value'),
            ];
        }

        return [
            'groups' => $groups,
            'overall' => [
                'coordinator_pct' => $this->coordinator_readiness_pct,
                'monitor_pct' => $this->monitor_readiness_pct,
            ],
        ];
    }

    /** @return list<int> */
    public static function closureDocumentItemIds(): array
    {
        static $ids = null;

        $ids ??= ChecklistItem::query()
            ->where('is_active', true)
            ->where('has_file_field', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * @return array{total: int, attached: int, complete: bool, label: string}
     */
    public function closureAttachmentSummary(): array
    {
        $itemIds = self::closureDocumentItemIds();
        $total = count($itemIds);

        if ($total === 0) {
            return [
                'total' => 0,
                'attached' => 0,
                'complete' => true,
                'label' => '—',
            ];
        }

        $values = $this->relationLoaded('checklistValues')
            ? $this->checklistValues->whereIn('checklist_item_id', $itemIds)
            : $this->checklistValues()->whereIn('checklist_item_id', $itemIds)->get();

        $attached = $values->filter(fn (ProjectChecklistValue $row) => $row->hasAttachment())->count();
        $complete = $attached >= $total;

        return [
            'total' => $total,
            'attached' => $attached,
            'complete' => $complete,
            'label' => $complete ? 'مكتمل' : "{$attached}/{$total}",
        ];
    }

    public static function applyClosureDocsLabelScope(Builder $query, array $labels): void
    {
        $itemIds = self::closureDocumentItemIds();
        $total = count($itemIds);

        if ($total === 0 || $labels === []) {
            return;
        }

        $idList = implode(',', array_map('intval', $itemIds));

        $query->where(function ($outer) use ($labels, $total, $idList) {
            foreach ($labels as $label) {
                $outer->orWhere(function ($sub) use ($label, $total, $idList) {
                    if ($label === 'مكتمل') {
                        $sub->whereRaw(
                            "(SELECT COUNT(*) FROM project_checklist_values WHERE project_id = projects.id AND checklist_item_id IN ({$idList}) AND ((attachment_path IS NOT NULL AND attachment_path != '') OR (attachment_url IS NOT NULL AND attachment_url != ''))) = ?",
                            [$total]
                        );

                        return;
                    }

                    if (preg_match('/^(\d+)\/' . preg_quote((string) $total, '/') . '$/', (string) $label, $matches)) {
                        $sub->whereRaw(
                            "(SELECT COUNT(*) FROM project_checklist_values WHERE project_id = projects.id AND checklist_item_id IN ({$idList}) AND ((attachment_path IS NOT NULL AND attachment_path != '') OR (attachment_url IS NOT NULL AND attachment_url != ''))) = ?",
                            [(int) $matches[1]]
                        );
                    }
                });
            }
        });
    }

    public function hasPendingClosureDocs(): bool
    {
        $closureItemIds = self::closureDocumentItemIds();

        if ($closureItemIds === []) {
            return false;
        }

        if (in_array($this->workflow_status, ['draft', 'pending_coordinator', 'coordinator_filling'], true)) {
            return false;
        }

        $values = $this->checklistValues()
            ->whereIn('checklist_item_id', $closureItemIds)
            ->get()
            ->keyBy('checklist_item_id');

        foreach ($closureItemIds as $itemId) {
            $row = $values->get($itemId);
            $status = $row?->coordinator_value ?? 'not_ready';

            if ($status !== 'ready' || ! $row?->hasAttachment()) {
                return true;
            }
        }

        return false;
    }

    public static function coordinatorCanFillClosureDocsStatuses(): array
    {
        return [
            'coordinator_filling',
            'pending_project_manager',
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

    public static function closureLateScore(): float
    {
        $raw = Constant::where('key', 'checklist_closure_late_score')->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        $score = is_numeric($decoded) ? (float) $decoded : 0.5;

        return max(0.0, min(1.0, $score));
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

            if (! $this->planned_end_date || ! ($item->attachment_uploaded_at ?? null)) {
                return 1.0;
            }

            $uploadedAt = $item->attachment_uploaded_at instanceof \Carbon\CarbonInterface
                ? $item->attachment_uploaded_at
                : \Carbon\Carbon::parse($item->attachment_uploaded_at);

            if ($uploadedAt->toDateString() <= $this->planned_end_date->toDateString()) {
                return 1.0;
            }

            return self::closureLateScore();
        }

        return match ($status) {
            'ready' => 1.0,
            'partial' => 0.5,
            default => 0.0,
        };
    }

    protected function averageReadiness($groupedValues, string $column): ?float
    {
        $groupPercentages = [];

        foreach ($groupedValues as $items) {
            $pct = $this->groupReadinessPercent($items, $column);

            if ($pct !== null) {
                $groupPercentages[] = $pct;
            }
        }

        if ($groupPercentages === []) {
            return null;
        }

        return round(array_sum($groupPercentages) / count($groupPercentages), 2);
    }

    /**
     * 3-level readiness status derived from the monitor column (checklist-schema.md).
     */
    public function getReadinessStatusAttribute(): ?string
    {
        $savedValues = $this->checklistValues()->get()->keyBy('checklist_item_id');
        $activeItems = ChecklistItem::query()
            ->where('is_active', true)
            ->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->get();

        if ($activeItems->isEmpty()) {
            return null;
        }

        $monitorValues = $activeItems->map(fn (ChecklistItem $item) => $savedValues->get($item->id)?->monitor_value);

        if ($monitorValues->contains(fn ($value) => $value === null || $value === '' || $value === 'not_ready')) {
            return 'stopped';
        }

        if ($monitorValues->contains('partial')) {
            return 'partially_ready';
        }

        return 'ready';
    }
}
