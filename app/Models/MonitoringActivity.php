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
        'reference_code', 'source_type', 'source_id', 'project_execution_id', 'activity_role',
        'center_id', 'department_id', 'section_id', 'responsible_person_id', 'monitor_person_id',
        'activity_date', 'activity_time',
        'activity_type', 'detail', 'funder_id',
        'subject', 'notes', 'field_problem', 'action_taken',
        'closure_date', 'attachments', 'positive_notes', 'negative_notes', 'recommendations',
        'execution_value', 'quality_value', 'closure_value', 'deduction_value',
        'kpi_value', 'kpi_rating',
        'monitoring_method', 'monitoring_stage', 'workflow_status', 'is_passage_complete',
        'passage_completed_at', 'passage_completed_by',
        'submitted_at', 'submitted_by',
        'rejection_reason', 'rejected_by', 'rejected_at', 'gap_owner',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'closure_date' => 'date',
        'attachments' => 'array',
        'positive_notes' => 'array',
        'negative_notes' => 'array',
        'recommendations' => 'array',
        'field_problem' => 'boolean',
        'is_passage_complete' => 'boolean',
        'execution_value' => 'float',
        'quality_value' => 'float',
        'closure_value' => 'float',
        'deduction_value' => 'float',
        'kpi_value' => 'float',
        'passage_completed_at' => 'datetime',
        'submitted_at' => 'datetime',
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

    public function projectExecution(): BelongsTo
    {
        return $this->belongsTo(ProjectExecution::class);
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

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isExternal(): bool
    {
        return $this->source_type === 'external' && $this->activity_role !== 'primary';
    }

    public function canMonitorEditExternal(?User $user): bool
    {
        if (! $this->isExternal() || $this->workflow_status !== 'in_progress') {
            return false;
        }

        if (! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        $personId = $user->person?->id;

        if (! $personId) {
            return false;
        }

        return (int) $this->monitor_person_id === (int) $personId
            || (int) $this->created_by === (int) $user->id;
    }

    public function canDirectorReview(): bool
    {
        return $this->isExternal() && $this->workflow_status === 'pending_confirmation';
    }

    /**
     * صفحة سير العمل المرتبطة (مشروع/مسار) — null للأنشطة الخارجية والثانوية.
     */
    public function workflowContextUrl(): ?string
    {
        if ($this->source_type === 'project_execution' && $this->project_execution_id) {
            $execution = $this->relationLoaded('projectExecution')
                ? $this->projectExecution
                : $this->projectExecution()->with('project')->first();

            if ($execution?->project) {
                return route('dashboard.projects.executions.show', [$execution->project, $execution]);
            }
        }

        if ($this->source_type === 'project' && $this->source_id && $this->activity_role === 'primary') {
            return route('dashboard.projects.show', $this->source_id);
        }

        return null;
    }

    public function clearRejection(): void
    {
        $this->rejection_reason = null;
        $this->rejected_by = null;
        $this->rejected_at = null;
        $this->gap_owner = null;
    }

    public function scopeExternal(Builder $query): Builder
    {
        return $query
            ->where('source_type', 'external')
            ->where('activity_role', '!=', 'primary');
    }

    public function scopePendingDirectorApproval(Builder $query): Builder
    {
        return $query
            ->external()
            ->where('workflow_status', 'pending_confirmation');
    }

    public function isAssignedMonitor(?User $user): bool
    {
        $personId = $user?->person?->id;

        return $personId && (int) $this->monitor_person_id === (int) $personId;
    }

    public function isExternalCreator(?User $user): bool
    {
        return $user && (int) $this->created_by === (int) $user->id;
    }

    public function canSubmitExternal(?User $user): bool
    {
        if (! $this->isExternal() || ! $user) {
            return false;
        }

        if ($user->super_admin) {
            return true;
        }

        if ($this->isAssignedMonitor($user)) {
            return true;
        }

        return $user->can('create_external', self::class) && $this->isExternalCreator($user);
    }

    public function canViewExternal(?User $user): bool
    {
        if (! $this->isExternal() || ! $user) {
            return false;
        }

        if ($user->super_admin || $user->isMonitoringDirector()) {
            return true;
        }

        if ($user->can('approve_external', self::class) || $user->can('view', self::class)) {
            return true;
        }

        if (! $user->can('create_external', self::class)) {
            return false;
        }

        return $this->isAssignedMonitor($user) || $this->isExternalCreator($user);
    }

    public function canMonitorSubmit(): bool
    {
        if ($this->activity_role === 'primary') {
            return false;
        }

        if ($this->workflow_status !== 'in_progress' || $this->monitor_person_id === null) {
            return false;
        }

        return $this->isExternal() || $this->activity_role === 'secondary';
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

    public static function sourceTypeLabels(): array
    {
        return [
            'project' => 'مشروع',
            'external' => 'خارجي',
            'meeting' => 'محضر اجتماع',
            'project_execution' => 'مسار تنفيذ',
        ];
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return self::sourceTypeLabels()[$this->source_type] ?? $this->source_type;
    }

    public static function workflowStatusLabels(): array
    {
        return [
            'pending_monitor' => 'بانتظار تعيين مراقب',
            'in_progress' => 'المراقب يعمل',
            'pending_confirmation' => 'بانتظار اعتماد مدير الرقابة',
            'completed' => 'مكتمل',
            'rejected' => 'مرفوض',
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

        if (in_array($this->source_type, ['project', 'project_execution'], true)) {
            if ($this->source_type === 'project' && ! $this->source_id) {
                $requiredFields['center_id'] = 'المركز';
                $requiredFields['department_id'] = 'الدائرة';
            }
        } else {
            $requiredFields['center_id'] = 'المركز';
            $requiredFields['department_id'] = 'الدائرة';
            $requiredFields['responsible_person_id'] = 'المسؤول عن النشاط';
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

        if ($this->detail && ! in_array($this->detail, $this->getConstantValues('activity_details'), true)) {
            $invalid[] = 'التفصيل';
        }

        return $invalid;
    }

    /** @return list<array<string, mixed>> */
    public function attachmentsList(): array
    {
        $stored = is_array($this->attachments) ? $this->attachments : [];

        return array_values(array_map(fn (array $row) => $this->normalizeAttachmentRow($row), $stored));
    }

    public function hasAttachments(): bool
    {
        return $this->attachmentsList() !== [];
    }

    /** @param  array<string, mixed>  $row */
    public function attachmentRowLabel(array $row): string
    {
        if (($row['type'] ?? '') === 'url') {
            $host = parse_url((string) ($row['url'] ?? ''), PHP_URL_HOST);

            return $host ? 'رابط — ' . $host : 'رابط خارجي';
        }

        return (string) ($row['original_name'] ?? $row['name'] ?? 'مرفق');
    }

    /** @param  array<string, mixed>  $row */
    public function attachmentRowUrl(array $row): ?string
    {
        if (($row['type'] ?? '') === 'url') {
            return $row['url'] ?? null;
        }

        $path = $row['path'] ?? null;

        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    /** @param  array<string, mixed>  $row */
    public function attachmentIsImage(array $row): bool
    {
        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (($row['type'] ?? '') === 'url') {
            $path = parse_url((string) ($row['url'] ?? ''), PHP_URL_PATH) ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            return in_array($ext, $extensions, true);
        }

        $name = (string) ($row['original_name'] ?? $row['path'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($ext, $extensions, true);
    }

    public function scopeAssignedToMonitor(Builder $query, int $personId): Builder
    {
        return $query->where('monitor_person_id', $personId);
    }

    public function needsActionFromMonitor(?User $user): bool
    {
        if (! $this->isAssignedMonitor($user)) {
            return false;
        }

        if ($this->workflow_status !== 'in_progress') {
            return false;
        }

        return $this->isExternal() || $this->activity_role === 'secondary';
    }

    public function wasReturnedToMonitor(): bool
    {
        return $this->workflow_status === 'in_progress' && $this->rejected_at !== null;
    }

    /** @param  list<array<string, mixed>>  $attachments */
    public function syncAttachments(array $attachments): void
    {
        $this->attachments = array_values(array_map(
            fn (array $row) => $this->normalizeAttachmentRow($row),
            $attachments
        ));
    }

    public function attachmentsStorageDirectory(): string
    {
        return 'monitoring-activities/' . $this->id . '/attachments';
    }

    public function scaleLabelFor(string $field): ?string
    {
        $scaleKey = match ($field) {
            'execution_value' => 'scale_execution',
            'quality_value' => 'scale_quality',
            'closure_value' => 'scale_closure',
            'deduction_value' => 'scale_deduction',
            default => null,
        };

        if ($scaleKey === null || $this->{$field} === null) {
            return null;
        }

        $value = (float) $this->{$field};
        $scale = $this->getConstantScale($scaleKey);

        foreach ($scale as $tier) {
            if (isset($tier['value']) && (float) $tier['value'] === $value) {
                return $tier['label'] ?? null;
            }
        }

        return null;
    }

    public function formattedScaleValue(string $field): ?string
    {
        if ($this->{$field} === null) {
            return null;
        }

        $label = $this->scaleLabelFor($field);

        if ($label) {
            return $this->{$field} . '% — ' . $label;
        }

        return (string) $this->{$field};
    }

    /** @return list<array{value:int|float,label:string}> */
    public static function scaleOptions(string $scaleKey): array
    {
        $value = Constant::where('key', $scaleKey)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<array{value:int|float,label:string}> */
    protected function getConstantScale(string $key): array
    {
        return self::scaleOptions($key);
    }

    /** @param  array<string, mixed>  $row */
    protected function normalizeAttachmentRow(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'type' => (string) ($row['type'] ?? 'file'),
            'path' => isset($row['path']) ? (string) $row['path'] : null,
            'url' => isset($row['url']) ? (string) $row['url'] : null,
            'original_name' => isset($row['original_name']) ? (string) $row['original_name'] : (isset($row['name']) ? (string) $row['name'] : null),
            'uploaded_at' => isset($row['uploaded_at']) ? (string) $row['uploaded_at'] : null,
        ];
    }

    protected function getConstantValues(string $key): array
    {
        $value = Constant::where('key', $key)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }
}
