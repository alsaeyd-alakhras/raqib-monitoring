@php
    $c = config('brand.colors');
    $noteIndex = 0;
    $notes = $project->monitor_notes ?? [];
    $recommendations = $project->monitor_recommendations ?? [];
@endphp

@if (count($notes) || count($recommendations))
    @if (count($notes))
        <div class="block-title" style="margin-bottom:2mm;">الملاحظات الميدانية</div>
        <table class="data" width="100%" style="margin-bottom:4mm;">
            <thead>
                <tr>
                    <th width="8mm">#</th>
                    <th>الملاحظة</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($notes as $note)
                    @php $noteIndex++; @endphp
                    <tr>
                        <td style="text-align:center; color:{{ $c['muted'] }}; font-weight:600;" class="num-ltr">{{ $noteIndex }}</td>
                        <td class="cell-wrap">{{ $note }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($recommendations))
        <div class="block-title" style="margin-bottom:2mm;">التوصيات</div>
        <table class="data" width="100%">
            <thead>
                <tr>
                    <th width="8mm">#</th>
                    <th>التوصية</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recommendations as $rec)
                    @php $noteIndex++; @endphp
                    <tr>
                        <td style="text-align:center; color:{{ $c['muted'] }}; font-weight:600;" class="num-ltr">{{ $noteIndex }}</td>
                        <td class="cell-wrap">{{ $rec }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@else
    <p class="text-empty">لا توجد ملاحظات أو توصيات مسجّلة.</p>
@endif
