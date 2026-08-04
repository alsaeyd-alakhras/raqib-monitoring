@once
    @push('styles')
    <style>
        .monitor-notes-editor-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
            overflow: hidden;
            background: #fff;
        }

        .monitor-notes-editor-table thead th {
            background: rgba(67, 89, 113, 0.06);
            font-weight: 600;
            font-size: 0.8125rem;
            padding: 0.625rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.12);
        }

        .monitor-notes-editor-table tbody td {
            padding: 0.5rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.08);
            vertical-align: middle;
        }

        .monitor-notes-editor-table tbody tr:last-child td {
            border-bottom: none;
        }

        .monitor-notes-editor-table .col-index {
            width: 3rem;
            text-align: center;
            color: rgba(67, 89, 113, 0.55);
            font-weight: 600;
            font-size: 0.8125rem;
        }

        .monitor-notes-editor-table .col-action {
            width: 3.5rem;
            text-align: center;
        }

        .monitor-notes-editor-section-title {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--bs-primary);
            margin-bottom: 0.5rem;
        }

        .monitor-notes-editor-group-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: #566a7f;
            margin-bottom: 0.75rem;
            padding-bottom: 0.375rem;
            border-bottom: 2px solid rgba(105, 108, 255, 0.2);
        }

        .monitor-notes-editor-subsection-title {
            font-size: 0.8125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .monitor-notes-editor-subsection-title--positive {
            color: #28a745;
        }

        .monitor-notes-editor-subsection-title--negative {
            color: #dc3545;
        }

        .monitor-notes-editor-field-notes-block {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
    </style>
    @endpush
@endonce
