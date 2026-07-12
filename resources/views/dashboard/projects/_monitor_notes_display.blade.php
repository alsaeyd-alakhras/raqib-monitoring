@php
    $positiveNotes = $positiveNotes ?? ($project->monitor_notes ?? []);
    $negativeNotes = $negativeNotes ?? ($project->monitor_negative_notes ?? []);
    $recommendations = $recommendations ?? ($project->monitor_recommendations ?? []);
    $hasFieldNotes = count($positiveNotes) || count($negativeNotes);
@endphp

@once
    @push('styles')
    <style>
        .monitor-notes-display-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
            overflow: hidden;
            background: #fff;
        }

        .monitor-notes-display-table thead th {
            background: rgba(67, 89, 113, 0.06);
            font-weight: 600;
            font-size: 0.8125rem;
            color: rgba(67, 89, 113, 0.9);
            padding: 0.625rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.12);
        }

        .monitor-notes-display-table tbody td {
            padding: 0.75rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.08);
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .monitor-notes-display-table tbody tr:last-child td {
            border-bottom: none;
        }

        .monitor-notes-display-table .col-index {
            width: 3rem;
            text-align: center;
            color: rgba(67, 89, 113, 0.55);
            font-weight: 600;
            font-size: 0.8125rem;
        }

        .monitor-notes-display-section-title {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--bs-primary);
            margin-bottom: 0.5rem;
        }

        .monitor-notes-display-group-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: #566a7f;
            margin-bottom: 0.75rem;
            padding-bottom: 0.375rem;
            border-bottom: 2px solid rgba(105, 108, 255, 0.2);
        }

        .monitor-notes-display-subsection-title {
            font-size: 0.8125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .monitor-notes-display-subsection-title--positive {
            color: #28a745;
        }

        .monitor-notes-display-subsection-title--negative {
            color: #dc3545;
        }

        .monitor-notes-display-empty {
            color: rgba(67, 89, 113, 0.45);
            font-size: 0.875rem;
            padding: 0.75rem 0;
        }

        .monitor-notes-display-field-notes-block {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
    </style>
    @endpush
@endonce

@if ($hasFieldNotes || count($recommendations))
    <div class="row g-3 mt-3">
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
