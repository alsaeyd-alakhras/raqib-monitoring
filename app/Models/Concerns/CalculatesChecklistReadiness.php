<?php

namespace App\Models\Concerns;

use App\Models\ChecklistGroup;
use App\Models\ChecklistItem;
use App\Models\ProjectChecklistValue;
use Illuminate\Support\Collection;

trait CalculatesChecklistReadiness
{
    abstract public function checklistValues();

    abstract public function getPrimaryMonitoringActivityRelation();

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
            $this->getPrimaryMonitoringActivityRelation()->update(['execution_value' => $this->monitor_readiness_pct]);
        }
    }

    protected function averageReadiness(Collection $groupedValues, string $column): ?float
    {
        $percents = $groupedValues
            ->map(fn ($items) => $this->groupReadinessPercent($items, $column))
            ->filter(fn ($pct) => $pct !== null);

        if ($percents->isEmpty()) {
            return null;
        }

        return round($percents->avg(), 2);
    }

    protected function groupReadinessPercent(Collection $items, string $column): ?float
    {
        $total = $items->count();
        $notRequired = $items->where($column, 'not_required')->count();
        $denominator = $total - $notRequired;

        if ($denominator <= 0) {
            return null;
        }

        $ready = $items->where($column, 'ready')->count();
        $partial = $items->where($column, 'partial')->count();

        return round((($ready + ($partial * 0.5)) / $denominator) * 100, 2);
    }

    /**
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
}
