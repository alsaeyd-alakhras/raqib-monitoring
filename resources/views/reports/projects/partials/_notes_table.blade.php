@php
    $c = config('brand.colors');
    $positiveNotes = $project->monitor_notes ?? [];
    $negativeNotes = $project->monitor_negative_notes ?? [];
    $recommendations = $project->monitor_recommendations ?? [];
    $hasFieldNotes = count($positiveNotes) || count($negativeNotes);
@endphp

@if ($hasFieldNotes || count($recommendations))
    @if ($hasFieldNotes)
        <div class="block-title" style="margin-bottom:2mm;">الملاحظات الميدانية</div>

        @if (count($positiveNotes))
            <div class="block-title" style="margin-bottom:1.5mm; font-size:9pt; color:{{ $c['success'] ?? '#28a745' }};">ملاحظات إيجابية</div>
            <table class="data" width="100%" style="margin-bottom:3mm;">
                <thead>
                    <tr>
                        <th width="8mm">#</th>
                        <th>الملاحظة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($positiveNotes as $index => $note)
                        <tr>
                            <td style="text-align:center; color:{{ $c['muted'] }}; font-weight:600;" class="num-ltr">{{ $index + 1 }}</td>
                            <td class="cell-wrap">{{ $note }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (count($negativeNotes))
            <div class="block-title" style="margin-bottom:1.5mm; font-size:9pt; color:{{ $c['danger'] ?? '#dc3545' }};">ملاحظات سلبية</div>
            <table class="data" width="100%" style="margin-bottom:4mm;">
                <thead>
                    <tr>
                        <th width="8mm">#</th>
                        <th>الملاحظة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($negativeNotes as $index => $note)
                        <tr>
                            <td style="text-align:center; color:{{ $c['muted'] }}; font-weight:600;" class="num-ltr">{{ $index + 1 }}</td>
                            <td class="cell-wrap">{{ $note }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
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
                @foreach ($recommendations as $index => $rec)
                    <tr>
                        <td style="text-align:center; color:{{ $c['muted'] }}; font-weight:600;" class="num-ltr">{{ $index + 1 }}</td>
                        <td class="cell-wrap">{{ $rec }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@else
    <p class="text-empty">لا توجد ملاحظات أو توصيات مسجّلة.</p>
@endif
