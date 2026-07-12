@php
    $c = config('brand.colors');
    $showCoordinator = $canViewCoordinatorData ?? false;
    $showMonitor = $canViewMonitorData ?? false;
@endphp

@foreach ($groups as $group)
    <div class="checklist-group">
        <div class="checklist-group-title">{{ $group->name }}</div>
        <table class="data" width="100%">
            <thead>
                <tr>
                    <th>البند</th>
                    @if ($showCoordinator)
                        <th width="18%">المنسق</th>
                    @endif
                    @if ($showMonitor)
                        <th width="18%">المراقب</th>
                    @endif
                    @if ($group->items->contains(fn ($item) => $item->has_person_field))
                        <th width="20%">الشخص / المهمة</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($group->items as $item)
                    @php
                        $current = $values->get($item->id);
                        $coordStatus = $current?->coordinator_value;
                        $monStatus = $current?->monitor_value;
                        $badgeClass = fn ($status) => match ($status) {
                            'ready' => 'badge-ready',
                            'partial' => 'badge-partial',
                            'not_ready' => 'badge-not-ready',
                            'not_required' => 'badge-muted',
                            default => 'badge-muted',
                        };
                    @endphp
                    <tr>
                        <td class="cell-wrap">{{ $item->name }}</td>
                        @if ($showCoordinator)
                            <td>
                                @if ($coordStatus)
                                    <span class="badge {{ $badgeClass($coordStatus) }}">{{ $valueLabels[$coordStatus] ?? $coordStatus }}</span>
                                @else
                                    <span class="text-empty">—</span>
                                @endif
                            </td>
                        @endif
                        @if ($showMonitor)
                            <td>
                                @if ($monStatus)
                                    <span class="badge {{ $badgeClass($monStatus) }}">{{ $valueLabels[$monStatus] ?? $monStatus }}</span>
                                @else
                                    <span class="text-empty">—</span>
                                @endif
                            </td>
                        @endif
                        @if ($group->items->contains(fn ($i) => $i->has_person_field))
                            <td class="cell-wrap">
                                @if ($item->has_person_field)
                                    {{ $current?->person_name ?: '—' }}
                                    @if ($showCoordinator && $item->has_file_field && $current?->hasAttachment())
                                        <div style="margin-top:2px;font-size:9px;">
                                            {{ $current->attachment_original_name ?: 'مرفق' }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-empty">—</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

@if ($groups->isEmpty())
    <p class="text-empty">لا توجد بنود قائمة تحقق نشطة.</p>
@endif
