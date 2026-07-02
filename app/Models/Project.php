<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_name',
        'project_number',
        'project_type',
        'funder_id',
        'procurement_rep',
        'project_manager_id',
        'coordinator_id',
        'center_id',
        'department_id',
        'section_id',
        'planned_start_date',
        'planned_end_date',
        'location',
        'target_beneficiaries',
        'execution_zones',
        'estimated_duration',
        'allocated_budget',
        'monitor_person_id',
        'monitoring_date',
        'monitoring_method',
        'monitoring_stage',
        'coordinator_readiness_pct',
        'monitor_readiness_pct',
        'monitor_notes',
        'monitor_recommendations',
        'workflow_status',
        'primary_monitoring_activity_id',
        'coordinator_submitted_at',
        'coordinator_submitted_by',
        'dept_manager_approved_at',
        'dept_manager_approved_by',
        'monitoring_manager_received_at',
        'monitoring_manager_received_by',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'gap_owner',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'monitoring_date' => 'date',
        'allocated_budget' => 'decimal:2',
        'coordinator_readiness_pct' => 'float',
        'monitor_readiness_pct' => 'float',
        'monitor_notes' => 'array',
        'monitor_recommendations' => 'array',
        'coordinator_submitted_at' => 'datetime',
        'dept_manager_approved_at' => 'datetime',
        'monitoring_manager_received_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'project_manager_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'coordinator_id');
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
        $values = $this->checklistValues()->with('checklistItem.group')->get()
            ->filter(fn (ProjectChecklistValue $value) => $value->checklistItem && $value->checklistItem->group && $value->checklistItem->group->is_active && $value->checklistItem->is_active)
            ->groupBy(fn (ProjectChecklistValue $value) => $value->checklistItem->group_id);

        $this->coordinator_readiness_pct = $this->averageReadiness($values, 'coordinator_value');
        $this->monitor_readiness_pct = $this->averageReadiness($values, 'monitor_value');
        $this->save();

        if ($this->primary_monitoring_activity_id) {
            $this->primaryMonitoringActivity()->update(['execution_value' => $this->monitor_readiness_pct]);
        }
    }

    protected function averageReadiness($groupedValues, string $column): ?float
    {
        $groupPercentages = [];

        foreach ($groupedValues as $items) {
            $total = $items->count();
            $notRequired = $items->where($column, 'not_required')->count();
            $denominator = $total - $notRequired;

            if ($denominator <= 0) {
                continue;
            }

            $ready = $items->where($column, 'ready')->count();
            $partial = $items->where($column, 'partial')->count();

            $groupPercentages[] = (($ready + 0.5 * $partial) / $denominator) * 100;
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
        $monitorValues = $this->checklistValues()->pluck('monitor_value');

        if ($monitorValues->isEmpty()) {
            return null;
        }

        if ($monitorValues->contains('not_ready')) {
            return 'stopped';
        }

        if ($monitorValues->contains('partial')) {
            return 'partially_ready';
        }

        return 'ready';
    }
}
