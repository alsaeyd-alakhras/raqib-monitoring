@php
    $c = config('brand.colors');
    $noteIndex = 0;
@endphp

<table class="data" width="100%">
    <thead>
        <tr>
            <th width="8mm">#</th>
            <th width="18mm">النوع</th>
            <th>النص</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($linkedProject->monitor_notes ?? [] as $note)
            @php $noteIndex++; @endphp
            <tr>
                <td style="text-align:center; color:{{ $c['muted'] }}; font-weight:600; direction:ltr; unicode-bidi:embed;">{{ $noteIndex }}</td>
                <td><span class="badge badge-info">ملاحظة</span></td>
                <td class="cell-wrap">{{ $note }}</td>
            </tr>
        @endforeach
        @foreach ($linkedProject->monitor_recommendations ?? [] as $rec)
            @php $noteIndex++; @endphp
            <tr>
                <td style="text-align:center; color:{{ $c['muted'] }}; font-weight:600; direction:ltr; unicode-bidi:embed;">{{ $noteIndex }}</td>
                <td><span class="badge badge-warn">توصية</span></td>
                <td class="cell-wrap">{{ $rec }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
