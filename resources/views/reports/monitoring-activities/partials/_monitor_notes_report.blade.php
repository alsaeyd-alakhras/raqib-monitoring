@once
    @push('styles')
    <style>
        .monitor-notes-report .monitor-notes-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .monitor-notes-report-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
            overflow: hidden;
            background: #fff;
        }

        .monitor-notes-report-table thead th {
            background: rgba(67, 89, 113, 0.06);
            font-weight: 600;
            font-size: 0.8125rem;
            color: rgba(67, 89, 113, 0.9);
            padding: 0.625rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.12);
            white-space: nowrap;
        }

        .monitor-notes-report-table tbody td {
            padding: 0.75rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.08);
            font-size: 0.875rem;
            color: #2b2c40;
            vertical-align: middle;
        }

        .monitor-notes-report-table tbody tr:last-child td {
            border-bottom: none;
        }

        .monitor-notes-report-table tbody tr:hover {
            background: rgba(67, 89, 113, 0.02);
        }

        .monitor-notes-report-table .col-index {
            width: 3rem;
            text-align: center;
            color: rgba(67, 89, 113, 0.55);
            font-weight: 600;
            font-size: 0.8125rem;
        }

        .monitor-notes-report-table .col-type {
            width: 6.5rem;
        }

        .monitor-notes-report-table .col-action {
            width: 9.5rem;
            text-align: center;
        }

        .monitor-notes-report-table .item-text {
            line-height: 1.5;
        }
    </style>
    @endpush
@endonce

@php
    $notesCount = count($linkedProject->monitor_notes ?? []);
    $recsCount = count($linkedProject->monitor_recommendations ?? []);
    $rowIndex = 0;
@endphp

<div class="monitor-notes-report">
    <p class="small text-muted mb-3">هذه من قائمة تحقق المشروع — وليست «ملاحظة النشاط» أعلاه.</p>

    <div class="monitor-notes-stats">
        @if ($notesCount)
            <span class="badge bg-label-info">{{ $notesCount }} {{ $notesCount === 1 ? 'ملاحظة' : 'ملاحظات' }}</span>
        @endif
        @if ($recsCount)
            <span class="badge bg-label-warning">{{ $recsCount }} {{ $recsCount === 1 ? 'توصية' : 'توصيات' }}</span>
        @endif
    </div>

    <div class="table-responsive">
        <table class="monitor-notes-report-table">
            <thead>
                <tr>
                    <th class="col-index">#</th>
                    <th class="col-type">النوع</th>
                    <th>النص</th>
                    @if ($canConvertToSecondary ?? false)
                        <th class="col-action">إجراء</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($linkedProject->monitor_notes ?? [] as $note)
                    @php $rowIndex++; @endphp
                    <tr>
                        <td class="col-index">{{ $rowIndex }}</td>
                        <td class="col-type"><span class="badge bg-label-info">ملاحظة</span></td>
                        <td><span class="item-text">{{ $note }}</span></td>
                        @if ($canConvertToSecondary ?? false)
                            <td class="col-action">
                                <a
                                    href="{{ route('dashboard.monitoring-activities.create', [
                                        'source_type' => 'project',
                                        'source_id' => $linkedProject->id,
                                        'center_id' => $linkedProject->center_id,
                                        'department_id' => $linkedProject->department_id,
                                        'section_id' => $linkedProject->section_id,
                                        'monitor_person_id' => $linkedProject->monitor_person_id,
                                        'subject' => 'متابعة ملاحظة مراقب',
                                        'notes' => $note,
                                    ]) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bx bx-transfer-alt"></i> تحويل لنشاط تابع
                                </a>
                            </td>
                        @endif
                    </tr>
                @endforeach

                @foreach ($linkedProject->monitor_recommendations ?? [] as $rec)
                    @php $rowIndex++; @endphp
                    <tr>
                        <td class="col-index">{{ $rowIndex }}</td>
                        <td class="col-type"><span class="badge bg-label-warning">توصية</span></td>
                        <td><span class="item-text">{{ $rec }}</span></td>
                        @if ($canConvertToSecondary ?? false)
                            <td class="col-action">
                                <a
                                    href="{{ route('dashboard.monitoring-activities.create', [
                                        'source_type' => 'project',
                                        'source_id' => $linkedProject->id,
                                        'center_id' => $linkedProject->center_id,
                                        'department_id' => $linkedProject->department_id,
                                        'section_id' => $linkedProject->section_id,
                                        'monitor_person_id' => $linkedProject->monitor_person_id,
                                        'subject' => 'متابعة توصية مراقب',
                                        'notes' => $rec,
                                    ]) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    <i class="bx bx-transfer-alt"></i> تحويل لنشاط تابع
                                </a>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
