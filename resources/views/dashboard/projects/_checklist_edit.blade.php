@include('dashboard.projects._checklist_styles')

@php
    $valueField = $valueField ?? 'coordinator_value';
    $inputClass = $inputClass ?? '';
    $defaultValue = $defaultValue ?? 'not_ready';
    $readinessBreakdown = $readinessBreakdown ?? null;
    $breakdownField = $valueField === 'monitor_value' ? 'monitor_pct' : 'coordinator_pct';
    $groupPctMap = [];
    $prefix = $prefix ?? 'checklist';
    $showFileColumn = $prefix === 'checklist' && $valueField === 'coordinator_value';

    if ($readinessBreakdown && ! empty($readinessBreakdown['groups'])) {
        foreach ($readinessBreakdown['groups'] as $groupRow) {
            $groupPctMap[$groupRow['name']] = $groupRow[$breakdownField] ?? null;
        }
    }
@endphp

<div class="checklist-groups-grid" data-checklist-readiness>
    @foreach ($groups as $group)
        @php
            $groupPct = $groupPctMap[$group->name] ?? null;
            $groupHasFileField = $showFileColumn && $group->items->contains(fn ($item) => $item->has_file_field);
        @endphp
        <div class="checklist-group-card{{ $groupHasFileField ? '' : ' checklist-group-card--compact' }}">
            <h6 class="checklist-group-title d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span>{{ $group->name }}</span>
                <span class="badge bg-label-primary checklist-group-pct">{{ $groupPct !== null ? $groupPct . '%' : '—' }}</span>
            </h6>
            <div class="checklist-table-wrap">
                <table class="table table-sm table-bordered checklist-compact-table{{ $groupHasFileField ? ' checklist-compact-table--with-files' : '' }}">
                    <thead>
                        <tr>
                            <th class="checklist-col-item">البند</th>
                            <th class="checklist-col-status">الحالة</th>
                            @if ($group->items->contains(fn ($item) => $item->has_person_field))
                                <th class="checklist-col-person">الشخص</th>
                            @endif
                            @if ($showFileColumn && $group->items->contains(fn ($item) => $item->has_file_field))
                                <th class="checklist-col-file">المرفق</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group->items as $item)
                            @include('dashboard.projects._checklist_row_edit', [
                                'item' => $item,
                                'group' => $group,
                                'values' => $values,
                                'valueLabels' => $valueLabels,
                                'prefix' => $prefix,
                                'inputClass' => $inputClass,
                                'valueField' => $valueField,
                                'showFileColumn' => $showFileColumn,
                                'project' => $project,
                            ])
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
