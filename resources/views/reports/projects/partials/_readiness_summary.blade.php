@php
    $breakdown = $readinessBreakdown ?? ['groups' => [], 'overall' => []];
    $showCoordinator = $canViewCoordinatorData ?? false;
    $showMonitor = $canViewMonitorData ?? false;
    $fmtPct = fn ($v) => $v !== null ? '<span class="num-ltr">' . e(number_format((float) $v, 1)) . '%</span>' : '<span class="text-empty">—</span>';
@endphp

<table class="data" width="100%">
    <thead>
        <tr>
            <th>المجموعة / المؤشر</th>
            @if ($showCoordinator)
                <th width="22%">المنسق</th>
            @endif
            @if ($showMonitor)
                <th width="22%">المراقب</th>
            @endif
            @if ($showCoordinator && $showMonitor)
                <th width="18%">فجوة الجاهزية</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($breakdown['groups'] as $groupRow)
            @php
                $gap = null;
                if ($showCoordinator && $showMonitor && $groupRow['coordinator_pct'] !== null && $groupRow['monitor_pct'] !== null) {
                    $gap = round(abs($groupRow['coordinator_pct'] - $groupRow['monitor_pct']), 1);
                }
            @endphp
            <tr>
                <td>{{ $groupRow['name'] }}</td>
                @if ($showCoordinator)
                    <td>{!! $fmtPct($groupRow['coordinator_pct']) !!}</td>
                @endif
                @if ($showMonitor)
                    <td>{!! $fmtPct($groupRow['monitor_pct']) !!}</td>
                @endif
                @if ($showCoordinator && $showMonitor)
                    <td>{!! $gap !== null ? '<span class="num-ltr">' . e(number_format($gap, 1)) . '%</span>' : '<span class="text-empty">—</span>' !!}</td>
                @endif
            </tr>
        @endforeach
        @if (!empty($breakdown['overall']))
            @php
                $overallGap = null;
                $oc = $breakdown['overall']['coordinator_pct'] ?? null;
                $om = $breakdown['overall']['monitor_pct'] ?? null;
                if ($showCoordinator && $showMonitor && $oc !== null && $om !== null) {
                    $overallGap = round(abs($oc - $om), 1);
                }
            @endphp
            <tr>
                <td><strong>الجاهزية الإجمالية</strong></td>
                @if ($showCoordinator)
                    <td>{!! $fmtPct($oc) !!}</td>
                @endif
                @if ($showMonitor)
                    <td>{!! $fmtPct($om) !!}</td>
                @endif
                @if ($showCoordinator && $showMonitor)
                    <td>{!! $overallGap !== null ? '<span class="num-ltr">' . e(number_format($overallGap, 1)) . '%</span>' : '<span class="text-empty">—</span>' !!}</td>
                @endif
            </tr>
        @endif
    </tbody>
</table>
