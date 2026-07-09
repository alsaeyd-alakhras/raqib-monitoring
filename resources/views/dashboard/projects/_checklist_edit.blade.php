@include('dashboard.projects._checklist_styles')

@php
    $valueField = $valueField ?? 'coordinator_value';
    $inputClass = $inputClass ?? '';
    $defaultValue = $defaultValue ?? 'not_ready';
    $readinessBreakdown = $readinessBreakdown ?? null;
    $breakdownField = $valueField === 'monitor_value' ? 'monitor_pct' : 'coordinator_pct';
    $groupPctMap = [];

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
        @endphp
        <div class="checklist-group-card">
            <h6 class="checklist-group-title d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span>{{ $group->name }}</span>
                <span class="badge bg-label-primary checklist-group-pct">{{ $groupPct !== null ? $groupPct . '%' : '—' }}</span>
            </h6>
            <div class="checklist-table-wrap">
                <table class="table table-sm table-bordered checklist-compact-table">
                    <thead>
                        <tr>
                            <th class="checklist-col-item">البند</th>
                            <th class="checklist-col-status">الحالة</th>
                            @if ($group->items->contains(fn ($item) => $item->has_person_field))
                                <th class="checklist-col-person">الشخص</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group->items as $item)
                            @php
                                $current = $values->get($item->id);
                                $selected = $current?->{$valueField} ?? $defaultValue;
                            @endphp
                            <tr>
                                <td class="checklist-col-item align-middle">{{ $item->name }}</td>
                                <td class="checklist-col-status">
                                    <select name="checklist[{{ $item->id }}][value]" class="form-select form-select-sm {{ $inputClass }}" required>
                                        @foreach ($valueLabels as $key => $label)
                                            <option value="{{ $key }}" @selected($selected === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                @if ($group->items->contains(fn ($i) => $i->has_person_field))
                                    <td class="checklist-col-person">
                                        @if ($item->has_person_field)
                                            <input
                                                type="text"
                                                name="checklist[{{ $item->id }}][person_name]"
                                                class="form-control form-control-sm {{ $inputClass }}"
                                                placeholder="اسم الشخص"
                                                value="{{ $current?->person_name }}"
                                            >
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
