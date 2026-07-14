@include('dashboard.projects._checklist_styles')

@php
    $readinessBreakdown = $readinessBreakdown ?? null;
    $groupCoordinatorPctMap = [];
    $groupMonitorPctMap = [];

    if ($readinessBreakdown && ! empty($readinessBreakdown['groups'])) {
        foreach ($readinessBreakdown['groups'] as $groupRow) {
            $groupCoordinatorPctMap[$groupRow['name']] = $groupRow['coordinator_pct'] ?? null;
            $groupMonitorPctMap[$groupRow['name']] = $groupRow['monitor_pct'] ?? null;
        }
    }
@endphp

<div class="checklist-groups-grid">
    @foreach ($groups as $group)
        @php
            $coordinatorGroupPct = $groupCoordinatorPctMap[$group->name] ?? null;
            $monitorGroupPct = $groupMonitorPctMap[$group->name] ?? null;
            $hasPersonColumn = $group->items->contains(fn ($item) => $item->has_person_field);
            $groupHasFileField = $group->items->contains(fn ($item) => $item->has_file_field);
        @endphp
        <div class="checklist-group-card{{ $groupHasFileField ? ' checklist-group-card--with-files' : ' checklist-group-card--compact' }}">
            <h6 class="checklist-group-title d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span>{{ $group->name }}</span>
                <span class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-label-primary">المنسق: {{ $coordinatorGroupPct !== null ? number_format((float) $coordinatorGroupPct, 1) . '%' : '—' }}</span>
                    <span class="badge bg-label-info">المراقب: {{ $monitorGroupPct !== null ? number_format((float) $monitorGroupPct, 1) . '%' : '—' }}</span>
                </span>
            </h6>
            <div class="checklist-table-wrap">
                <table class="table table-sm table-bordered checklist-compact-table checklist-merged-table{{ $groupHasFileField ? ' checklist-compact-table--with-files' : '' }}">
                    <thead>
                        <tr>
                            <th class="checklist-col-item" rowspan="2">البند</th>
                            <th class="text-center checklist-col-status" colspan="2">قائمة التحقق</th>
                            @if ($hasPersonColumn)
                                <th class="checklist-col-person" rowspan="2">الشخص</th>
                            @endif
                        </tr>
                        <tr>
                            <th class="text-center checklist-col-status bg-label-primary bg-opacity-10">المنسق</th>
                            <th class="text-center checklist-col-status bg-label-info bg-opacity-10">المراقب</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group->items as $item)
                            @php
                                $current = $values->get($item->id);
                                $coordinatorStatus = $current?->coordinator_value ?? 'not_ready';
                                $monitorStatus = $current?->monitor_value ?? 'not_ready';
                            @endphp
                            <tr>
                                <td class="checklist-col-item align-middle">{{ $item->name }}</td>
                                <td class="checklist-col-status text-center checklist-col-coordinator">
                                    @include('dashboard.projects._checklist_status_badge', [
                                        'status' => $coordinatorStatus,
                                        'valueLabels' => $valueLabels,
                                    ])
                                </td>
                                <td class="checklist-col-status text-center checklist-col-monitor">
                                    @include('dashboard.projects._checklist_status_badge', [
                                        'status' => $monitorStatus,
                                        'valueLabels' => $valueLabels,
                                    ])
                                </td>
                                @if ($hasPersonColumn)
                                    <td class="checklist-col-person text-muted small">
                                        @if ($item->has_person_field)
                                            {{ $current?->person_name ?: '—' }}
                                            @if ($item->has_file_field && $current?->hasAttachment())
                                                <div class="mt-1">
                                                    @include('dashboard.projects._checklist_attachment_link', ['current' => $current])
                                                </div>
                                            @endif
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
