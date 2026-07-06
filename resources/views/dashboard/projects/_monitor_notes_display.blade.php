@php
    $notes = $notes ?? ($project->monitor_notes ?? []);
    $recommendations = $recommendations ?? ($project->monitor_recommendations ?? []);
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

        .monitor-notes-display-empty {
            color: rgba(67, 89, 113, 0.45);
            font-size: 0.875rem;
            padding: 0.75rem 0;
        }
    </style>
    @endpush
@endonce

@if (count($notes) || count($recommendations))
    <div class="row g-3 mt-3">
        <div class="col-lg-6">
            <div class="monitor-notes-display-section-title">الملاحظات الميدانية</div>
            @if (count($notes))
                <div class="table-responsive">
                    <table class="monitor-notes-display-table">
                        <thead>
                            <tr>
                                <th class="col-index">#</th>
                                <th>النص</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($notes as $index => $note)
                                <tr>
                                    <td class="col-index">{{ $index + 1 }}</td>
                                    <td>{{ $note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="monitor-notes-display-empty mb-0">— لا توجد ملاحظات —</p>
            @endif
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
