@php
    $positiveNotes = is_array($activity->positive_notes ?? null) ? $activity->positive_notes : [];
    $negativeNotes = is_array($activity->negative_notes ?? null) ? $activity->negative_notes : [];
    $recommendations = is_array($activity->recommendations ?? null) ? $activity->recommendations : [];
    $hasFieldNotes = count($positiveNotes) || count($negativeNotes);
@endphp

@include('dashboard.partials._monitor_notes_display_styles')

@if ($hasFieldNotes || count($recommendations))
    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="monitor-notes-display-group-title">الملاحظات الميدانية</div>
            <div class="monitor-notes-display-field-notes-block">
                <div>
                    <div class="monitor-notes-display-subsection-title monitor-notes-display-subsection-title--positive">ملاحظات إيجابية</div>
                    @if (count($positiveNotes))
                        <div class="table-responsive">
                            <table class="monitor-notes-display-table">
                                <thead>
                                    <tr>
                                        <th class="col-index">#</th>
                                        <th>النص</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($positiveNotes as $index => $note)
                                        <tr>
                                            <td class="col-index">{{ $index + 1 }}</td>
                                            <td>{{ $note }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="monitor-notes-display-empty mb-0">— لا توجد ملاحظات إيجابية —</p>
                    @endif
                </div>

                <div>
                    <div class="monitor-notes-display-subsection-title monitor-notes-display-subsection-title--negative">ملاحظات سلبية</div>
                    @if (count($negativeNotes))
                        <div class="table-responsive">
                            <table class="monitor-notes-display-table">
                                <thead>
                                    <tr>
                                        <th class="col-index">#</th>
                                        <th>النص</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($negativeNotes as $index => $note)
                                        <tr>
                                            <td class="col-index">{{ $index + 1 }}</td>
                                            <td>{{ $note }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="monitor-notes-display-empty mb-0">— لا توجد ملاحظات سلبية —</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="monitor-notes-display-section-title">التوصيات</div>
            @if (count($recommendations))
                <div class="table-responsive">
                    <table class="monitor-notes-display-table">
                        <thead>
                            <tr>
                                <th class="col-index">#</th>
                                <th>النص</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recommendations as $index => $rec)
                                <tr>
                                    <td class="col-index">{{ $index + 1 }}</td>
                                    <td>{{ $rec }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="monitor-notes-display-empty mb-0">— لا توجد توصيات —</p>
            @endif
        </div>
    </div>
@endif
