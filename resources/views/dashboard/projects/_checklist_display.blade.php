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
            $groupHasFileField = ($valueField ?? 'coordinator_value') === 'coordinator_value'
                && $group->items->contains(fn ($item) => $item->has_file_field);
        @endphp
        <div class="checklist-group-card{{ $groupHasFileField ? '' : ' checklist-group-card--compact' }}">
            <h6 class="checklist-group-title d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span>{{ $group->name }}</span>
                @if ($groupPct !== null)
                    <span class="badge bg-label-primary">{{ number_format((float) $groupPct, 1) }}%</span>
                @endif
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
                            @if (($valueField ?? 'coordinator_value') === 'coordinator_value' && $group->items->contains(fn ($item) => $item->has_file_field))
                                <th class="checklist-col-file">المرفق</th>
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
                            @endphp
                            <tr>
                                <td class="checklist-col-item">{{ $item->name }}</td>
                                <td class="checklist-col-status text-center">
                                    @include('dashboard.projects._checklist_status_badge', [
                                        'status' => $status,
                                        'valueLabels' => $valueLabels,
                                    ])
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
                                @if (($valueField ?? 'coordinator_value') === 'coordinator_value' && $group->items->contains(fn ($i) => $i->has_file_field))
                                    <td class="checklist-col-file small text-center">
                                        @if ($item->has_file_field && $current?->hasAttachment())
                                            @php
                                                $plannedEnd = ($project ?? null)?->planned_end_date;
                                                $isLate = $plannedEnd
                                                    && $current->attachment_uploaded_at
                                                    && $current->attachment_uploaded_at->toDateString() > $plannedEnd->toDateString();
                                            @endphp
                                            <a href="{{ $current->attachmentUrl() }}" target="_blank" rel="noopener">
                                                {{ $current->attachment_original_name ?: 'مرفق' }}
                                            </a>
                                            @if ($isLate)
                                                <span class="badge bg-label-warning">متأخر</span>
                                            @endif
                                        @elseif ($item->has_file_field)
                                            <span class="text-muted">—</span>
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
