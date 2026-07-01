<?php

namespace App\Models;

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

        return '✓';
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verification_status === '✓';
    }

    protected function isValidHierarchy(): bool
    {
        if (! $this->department_id || ! $this->center_id) {
            return true;
        }

        $department = $this->relationLoaded('department')
            ? $this->department
            : Department::find($this->department_id);

        if (! $department || (int) $department->center_id !== (int) $this->center_id) {
            return false;
        }

        if ($this->section_id) {
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
            'center_id' => 'المركز',
            'department_id' => 'الدائرة',
            'responsible_person_id' => 'المسؤول عن النشاط',
            'activity_type' => 'نوع النشاط',
            'subject' => 'الموضوع',
        ];

        $missingFields = [];

        foreach ($requiredFields as $field => $label) {
            if (empty($this->{$field})) {
                $missingFields[] = $label;
            }
        }

        return $missingFields;
    }
}
