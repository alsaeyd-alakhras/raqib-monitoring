<table width="100%" class="row-pair" cellpadding="0" cellspacing="0">
    <tr>
        <td width="48%" valign="top" class="row-pair-cell">
            @include('reports.monitoring-activities.partials._kv_block', $left)
        </td>
        @if ($right)
            <td width="4%"></td>
            <td width="48%" valign="top" class="row-pair-cell">
                @include('reports.monitoring-activities.partials._kv_block', $right)
            </td>
        @else
            <td width="52%"></td>
        @endif
    </tr>
</table>
