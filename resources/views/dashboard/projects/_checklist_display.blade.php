@include('dashboard.projects._checklist_styles')

@php
    $valueField = $valueField ?? 'coordinator_value';
    $readinessBreakdown = $readinessBreakdown ?? null;
    $breakdownField = $valueField === 'monitor_value' ? 'monitor_pct' : 'coordinator_pct';
    $groupPctMap = [];

    if ($readinessBreakdown && ! empty($readinessBreakdown['groups'])) {
        foreach ($readinessBreakdown['groups'] as $groupRow) {
            $groupPctMap[$groupRow['name']] = $groupRow[$breakdownField] ?? null;
        }
    }
@endphp

<div class="checklist-groups-grid">
    @foreach ($groups as $group)
        @php
            $groupPct = $groupPctMap[$group->name] ?? null;
        @endphp
        <div class="checklist-group-card">
            <h6 class="checklist-group-title d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span>{{ $group->name }}</span>
                @if ($groupPct !== null)
                    <span class="badge bg-label-primary">{{ number_format((float) $groupPct, 1) }}%</span>
                @endif
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
                                $status = $current?->{$valueField} ?? null;
                                if ($status === null || $status === '') {
                                    $status = 'not_ready';
                                }
                                $badgeClass = match ($status) {
                                    'ready' => 'bg-label-success',
                                    'partial' => 'bg-label-warning',
                                    'not_ready' => 'bg-label-danger',
                                    'not_required' => 'bg-label-secondary',
                                    default => 'bg-label-danger',
                                };
                            @endphp
                            <tr>
                                <td class="checklist-col-item">{{ $item->name }}</td>
                                <td class="checklist-col-status">
                                    <span class="badge {{ $badgeClass }}">{{ $valueLabels[$status] ?? $status }}</span>
                                </td>
                                @if ($group->items->contains(fn ($i) => $i->has_person_field))
                                    <td class="checklist-col-person text-muted small">
                                        @if ($item->has_person_field)
                                            {{ $current?->person_name ?: '—' }}
                                        @else
                                            —
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
