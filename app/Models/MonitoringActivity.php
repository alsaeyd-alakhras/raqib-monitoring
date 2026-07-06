<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_code', 'source_type', 'source_id', 'activity_role',
        'center_id', 'department_id', 'section_id', 'responsible_person_id', 'monitor_person_id',
        'activity_date', 'activity_time',
        'activity_type', 'funder_id',
        'subject', 'notes', 'field_problem', 'action_taken',
        'execution_value', 'quality_value', 'closure_value', 'deduction_value',
        'kpi_value', 'kpi_rating',
        'monitoring_method', 'monitoring_stage', 'workflow_status', 'is_passage_complete',
        'passage_completed_at', 'passage_completed_by',
        'rejection_reason', 'rejected_by', 'rejected_at', 'gap_owner',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'field_problem' => 'boolean',
        'is_passage_complete' => 'boolean',
        'execution_value' => 'float',
        'quality_value' => 'float',
        'closure_value' => 'float',
        'deduction_value' => 'float',
        'kpi_value' => 'float',
        'passage_completed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (MonitoringActivity $activity) {
            $activity->kpi_value = $activity->calculateKpi();
            $activity->kpi_rating = $activity->deriveKpiRating($activity->kpi_value);
        });
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

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'responsible_person_id');
    }

    public function monitorPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'monitor_person_id');
    }

    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'source_id');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function passageCompletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passage_completed_by');
    }

    public function isAssignedMonitor(?User $user): bool
    {
        $personId = $user?->person?->id;

        return $personId && (int) $this->monitor_person_id === (int) $personId;
    }

    public function canMonitorSubmit(): bool
    {
        return $this->activity_role !== 'primary'
            && $this->workflow_status === 'in_progress'
            && $this->monitor_person_id !== null;
    }

    public function scopeSecondaryForProject(Builder $query, int $projectId): Builder
    {
        return $query
            ->where('source_type', 'project')
            ->where('source_id', $projectId)
            ->where('activity_role', 'secondary');
    }

    public static function hasOtherPrimaryForProject(int $projectId, ?int $exceptId = null): bool
    {
        return self::query()
            ->where('source_type', 'project')
            ->where('source_id', $projectId)
            ->where('activity_role', 'primary')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public static function workflowStatusLabels(): array
    {
        return [
            'pending_monitor' => 'بانتظار تعيين مراقب',
            'in_progress' => 'المراقب يعمل',
            'pending_confirmation' => 'بانتظار التأكيد',
            'completed' => 'مكتمل',
        ];
    }

    public function getWorkflowStatusLabelAttribute(): string
    {
        return self::workflowStatusLabels()[$this->workflow_status] ?? $this->workflow_status;
    }

    public function getDayNameAttribute(): ?string
    {
        return $this->activity_date?->locale('ar')->dayName;
    }

    public function getMonthAttribute(): ?int
    {
        return $this->activity_date?->month;
    }

    public function getYearAttribute(): ?int
    {
        return $this->activity_date?->year;
    }

    public function calculateKpi(): ?float
    {
        if (
            $this->execution_value === null || $this->quality_value === null
            || $this->closure_value === null || $this->deduction_value === null
        ) {
            return null;
        }

        return round(
            ($this->execution_value * 0.4)
            + ($this->quality_value * 0.3)
            + ($this->closure_value * 0.3)
            + $this->deduction_value,
            2
        );
    }

    protected function deriveKpiRating(?float $kpiValue): ?string
    {
        if ($kpiValue === null) {
            return null;
        }

        $scale = Constant::where('key', 'scale_kpi')->value('value');
        $scale = is_string($scale) ? json_decode($scale, true) : $scale;

        if (!is_array($scale)) {
            return null;
        }

        foreach ($scale as $tier) {
            if ($kpiValue >= ($tier['min'] ?? 0)) {
                return $tier['label'] ?? null;
            }
        }

        return null;
    }

    public function getVerificationStatusAttribute(): string
    {
        if (! $this->isValidHierarchy()) {
            return '✗ هرم';
        }

        $invalidLists = $this->collectInvalidListValues();
        if ($invalidLists !== []) {
            return '✗ قوائم: ' . implode('، ', $invalidLists);
        }

        $deductionValue = $this->deduction_value;
        $hasDeduction = $deductionValue !== null && (float) $deductionValue !== 0.0;

        if ((! $this->field_problem && $hasDeduction) || ($this->field_problem && ! $hasDeduction)) {
            return '✗ خصم';
        }

        if (
            (float) $this->execution_value === 100.0
            && (float) $this->quality_value === 100.0
            && $this->closure_value !== null
            && (float) $this->closure_value !== 100.0
        ) {
            return '✗ إغلاق';
        }

        $missingFields = $this->collectMissingFields();
        if ($missingFields !== []) {
            return '✗ ناقص: ' . implode('، ', $missingFields);
        }

        return '✓ تحقق';
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verification_status === '✓ تحقق';
    }

    /**
     * @return array<int, string>
     */
    public function verificationIssues(): array
    {
        $issues = [];

        if (! $this->isValidHierarchy()) {
            $issues[] = 'الهرم التنظيمي غير متسق (مركز / دائرة / قسم)';
        }

        $invalidLists = $this->collectInvalidListValues();
        foreach ($invalidLists as $label) {
            $issues[] = 'قيمة غير صالحة: ' . $label;
        }

        $deductionValue = $this->deduction_value;
        $hasDeduction = $deductionValue !== null && (float) $deductionValue !== 0.0;

        if ((! $this->field_problem && $hasDeduction) || ($this->field_problem && ! $hasDeduction)) {
            $issues[] = 'تناقض بين «مشكلة ميدانية» وقيمة الخصم';
        }

        if (
            (float) $this->execution_value === 100.0
            && (float) $this->quality_value === 100.0
            && $this->closure_value !== null
            && (float) $this->closure_value !== 100.0
        ) {
            $issues[] = 'التنفيذ والجودة 100% لكن الإغلاق ليس 100%';
        }

        foreach ($this->collectMissingFields() as $label) {
            $issues[] = 'حقل ناقص: ' . $label;
        }

        return $issues;
    }

    protected function isValidHierarchy(): bool
    {
        if ($this->center_id && $this->department_id) {
            $department = $this->relationLoaded('department')
                ? $this->department
                : Department::find($this->department_id);

            if (! $department || (int) $department->center_id !== (int) $this->center_id) {
                return false;
            }
        }

        if ($this->section_id) {
            if (! $this->department_id) {
                return false;
            }

            $section = $this->relationLoaded('section')
                ? $this->section
                : Section::find($this->section_id);

            if (! $section || (int) $section->department_id !== (int) $this->department_id) {
                return false;
            }
        }

        return true;
    }

    protected function collectMissingFields(): array
    {
        $requiredFields = [
            'activity_date' => 'التاريخ',
            'activity_time' => 'الوقت',
            'activity_type' => 'نوع النشاط',
            'subject' => 'الموضوع',
            'execution_value' => 'التنفيذ',
            'quality_value' => 'الجودة',
            'closure_value' => 'الإغلاق',
            'deduction_value' => 'الخصم',
        ];

        if ($this->source_type !== 'project') {
            $requiredFields['center_id'] = 'المركز';
            $requiredFields['department_id'] = 'الدائرة';
            $requiredFields['responsible_person_id'] = 'المسؤول عن النشاط';
        } elseif (! $this->source_id) {
            $requiredFields['center_id'] = 'المركز';
            $requiredFields['department_id'] = 'الدائرة';
        }

        $missingFields = [];

        foreach ($requiredFields as $field => $label) {
            if ($this->{$field} === null || $this->{$field} === '') {
                $missingFields[] = $label;
            }
        }

        return $missingFields;
    }

    protected function collectInvalidListValues(): array
    {
        $invalid = [];

        if ($this->activity_type && ! in_array($this->activity_type, $this->getConstantValues('activity_types'), true)) {
            $invalid[] = 'نوع النشاط';
        }

        if ($this->monitoring_method && ! in_array($this->monitoring_method, $this->getConstantValues('monitoring_methods'), true)) {
            $invalid[] = 'طريقة المراقبة';
        }

        if ($this->monitoring_stage && ! in_array($this->monitoring_stage, $this->getConstantValues('monitoring_stages'), true)) {
            $invalid[] = 'مرحلة المراقبة';
        }

        return $invalid;
    }

    protected function getConstantValues(string $key): array
    {
        $value = Constant::where('key', $key)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }
}
