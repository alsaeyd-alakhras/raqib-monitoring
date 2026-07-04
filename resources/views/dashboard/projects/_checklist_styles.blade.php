@once
    @push('styles')
    <style>
        .checklist-groups-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            align-items: start;
        }

        @media (max-width: 1199.98px) {
            .checklist-groups-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .checklist-groups-grid {
                grid-template-columns: 1fr;
            }
        }

        .checklist-group-card {
            min-width: 0;
            border: 1px solid rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
            padding: 0.65rem 0.65rem 0.75rem;
            background: #fff;
            height: 100%;
        }

        .checklist-table-wrap {
            width: 100%;
        }

        .checklist-compact-table {
            table-layout: fixed;
            margin-bottom: 0;
        }

        .checklist-compact-table thead th {
            background-color: var(--bs-gray-50, #f8f9fa);
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.45rem 0.5rem;
            white-space: nowrap;
        }

        .checklist-compact-table tbody td {
            padding: 0.45rem 0.5rem;
            vertical-align: middle;
            font-size: 0.8125rem;
        }

        .checklist-compact-table .checklist-col-item {
            width: auto;
            word-break: break-word;
        }

        .checklist-compact-table .checklist-col-status {
            width: 6.25rem;
        }

        .checklist-compact-table .checklist-col-person {
            width: 8rem;
        }

        .checklist-compact-table .form-select-sm,
        .checklist-compact-table .form-control-sm {
            min-width: 0;
            font-size: 0.8125rem;
        }

        .checklist-compact-table .badge {
            font-size: 0.75rem;
            font-weight: 500;
        }

        .checklist-group-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--bs-heading-color);
            margin: 0 0 0.5rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid var(--bs-border-color);
            line-height: 1.35;
        }
    </style>
    @endpush
@endonce
