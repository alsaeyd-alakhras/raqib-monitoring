@once
    @push('styles')
    <style>
        :root {
            --checklist-st-ready-bg: #d1fae5;
            --checklist-st-ready-text: #047857;
            --checklist-st-ready-border: #6ee7b7;
            --checklist-st-partial-bg: #fef3c7;
            --checklist-st-partial-text: #b45309;
            --checklist-st-partial-border: #fcd34d;
            --checklist-st-not-ready-bg: #fee2e2;
            --checklist-st-not-ready-text: #b91c1c;
            --checklist-st-not-ready-border: #fca5a5;
            --checklist-st-not-required-bg: #eef1f6;
            --checklist-st-not-required-text: #697a8d;
            --checklist-st-not-required-border: #d9dee3;
        }

        .checklist-groups-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            align-items: start;
        }

        @media (min-width: 992px) {
            .checklist-groups-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .checklist-group-card--with-files {
                grid-column: 1 / -1;
            }
        }

        .checklist-group-card {
            min-width: 0;
            border: 1px solid rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
            padding: 0.65rem 0.65rem 0.75rem;
            background: #fff;
        }

        .checklist-group-card--compact {
            padding: 0.55rem 0.5rem 0.65rem;
        }

        .checklist-group-card--compact .checklist-group-title {
            font-size: 0.8125rem;
            margin-bottom: 0.4rem;
            padding-bottom: 0.3rem;
        }

        .checklist-group-card--compact .checklist-compact-table thead th,
        .checklist-group-card--compact .checklist-compact-table tbody td {
            padding: 0.3rem 0.35rem;
            font-size: 0.75rem;
        }

        .checklist-group-card--compact .checklist-status-select,
        .checklist-group-card--compact .checklist-st-badge {
            min-width: 3.75rem;
            font-size: 0.625rem;
        }

        .checklist-group-card--compact .checklist-compact-table .checklist-col-item {
            width: 56%;
        }

        .checklist-group-card--compact .checklist-compact-table .checklist-col-status {
            width: 44%;
        }

        .checklist-table-wrap {
            width: 100%;
            overflow: visible;
        }

        @media (max-width: 575.98px) {
            .checklist-table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .checklist-compact-table--with-files {
                min-width: 20rem;
            }
        }

        .checklist-compact-table {
            width: 100%;
            margin-bottom: 0;
            table-layout: fixed;
        }

        .checklist-compact-table thead th {
            background-color: var(--bs-gray-50, #f8f9fa);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.4rem 0.45rem;
            white-space: nowrap;
            vertical-align: middle;
        }

        .checklist-compact-table tbody td {
            padding: 0.35rem 0.45rem;
            vertical-align: middle;
            font-size: 0.8125rem;
        }

        .checklist-compact-table .checklist-col-item {
            width: 40%;
            word-break: normal;
            overflow-wrap: anywhere;
            line-height: 1.35;
        }

        .checklist-compact-table .checklist-col-status {
            width: 20%;
            white-space: nowrap;
            text-align: center;
        }

        .checklist-compact-table .checklist-col-person {
            width: 30%;
            overflow-wrap: anywhere;
        }

        .checklist-compact-table--with-files .checklist-col-item {
            width: 36%;
        }

        .checklist-compact-table--with-files .checklist-col-status {
            width: 18%;
        }

        .checklist-compact-table--with-files .checklist-col-person {
            width: 28%;
        }

        .checklist-compact-table .checklist-col-file {
            width: 2.75rem;
            min-width: 2.75rem;
            max-width: 2.75rem;
            white-space: nowrap;
            text-align: center;
            overflow: visible;
            padding-inline: 0.2rem;
        }

        .checklist-compact-table .checklist-col-file:has(.checklist-file-actions) {
            width: 4.75rem;
            min-width: 4.75rem;
            max-width: 4.75rem;
        }

        .checklist-merged-table .checklist-col-item {
            width: 30%;
        }

        .checklist-merged-table .checklist-col-status {
            width: 14%;
        }

        .checklist-merged-table .checklist-col-person {
            width: 24%;
        }

        .checklist-merged-table .checklist-col-coordinator {
            border-inline-start: 2px solid rgba(105, 108, 255, 0.18);
        }

        .checklist-merged-table .checklist-col-monitor {
            border-inline-end: 2px solid rgba(3, 195, 236, 0.18);
        }

        .checklist-merged-table thead .checklist-col-coordinator,
        .checklist-merged-table thead .checklist-col-monitor {
            font-size: 0.6875rem;
        }

        .checklist-st-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 4.25rem;
            padding: 0.15rem 0.45rem;
            border-radius: 0.3rem;
            border: 1px solid transparent;
            font-size: 0.6875rem;
            font-weight: 700;
            line-height: 1.3;
            white-space: nowrap;
        }

        .checklist-st-ready {
            background: var(--checklist-st-ready-bg);
            color: var(--checklist-st-ready-text);
            border-color: var(--checklist-st-ready-border);
        }

        .checklist-st-partial {
            background: var(--checklist-st-partial-bg);
            color: var(--checklist-st-partial-text);
            border-color: var(--checklist-st-partial-border);
        }

        .checklist-st-not-ready {
            background: var(--checklist-st-not-ready-bg);
            color: var(--checklist-st-not-ready-text);
            border-color: var(--checklist-st-not-ready-border);
        }

        .checklist-st-not-required {
            background: var(--checklist-st-not-required-bg);
            color: var(--checklist-st-not-required-text);
            border-color: var(--checklist-st-not-required-border);
        }

        .checklist-status-select {
            --checklist-select-arrow: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none'%3e%3cpath d='M5.5 8l4.5 4.5L14.5 8' stroke='%2369748b' stroke-width='1.75' stroke-linecap='round' stroke-linejoin='round'/%3e%3c/svg%3e");
            min-width: 4.5rem;
            max-width: 100%;
            width: 100%;
            height: 1.75rem;
            padding-block: 0.15rem !important;
            padding-inline-start: 0.4rem !important;
            padding-inline-end: 1.45rem !important;
            font-size: 0.6875rem;
            font-weight: 700;
            line-height: 1.2;
            text-align: start;
            border-radius: 0.3rem;
            cursor: pointer;
            border-width: 1px;
            border-style: solid;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: var(--checklist-select-arrow) !important;
            background-repeat: no-repeat !important;
            background-size: 12px 12px !important;
            background-position: inline-start 0.4rem center !important;
            transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
            box-shadow: none;
        }

        .checklist-status-select:focus,
        .checklist-status-select:focus-visible {
            border-width: 1px !important;
            padding-block: 0.15rem !important;
            padding-inline-start: 0.4rem !important;
            padding-inline-end: 1.45rem !important;
            background-image: var(--checklist-select-arrow) !important;
            background-repeat: no-repeat !important;
            background-size: 12px 12px !important;
            background-position: inline-start 0.4rem center !important;
            box-shadow: none;
            outline: none;
        }

        .checklist-group-card--compact .checklist-status-select {
            min-width: 3.75rem;
            padding-inline-end: 1.25rem !important;
            background-size: 10px 10px !important;
            background-position: inline-start 0.3rem center !important;
        }

        .checklist-group-card--compact .checklist-status-select:focus,
        .checklist-group-card--compact .checklist-status-select:focus-visible {
            padding-inline-end: 1.25rem !important;
            background-size: 10px 10px !important;
            background-position: inline-start 0.3rem center !important;
        }

        .checklist-status-select.checklist-st-ready {
            background-color: var(--checklist-st-ready-bg) !important;
            color: var(--checklist-st-ready-text);
            border-color: var(--checklist-st-ready-border);
        }

        .checklist-status-select.checklist-st-partial {
            background-color: var(--checklist-st-partial-bg) !important;
            color: var(--checklist-st-partial-text);
            border-color: var(--checklist-st-partial-border);
        }

        .checklist-status-select.checklist-st-not-ready {
            background-color: var(--checklist-st-not-ready-bg) !important;
            color: var(--checklist-st-not-ready-text);
            border-color: var(--checklist-st-not-ready-border);
        }

        .checklist-status-select.checklist-st-not-required {
            background-color: var(--checklist-st-not-required-bg) !important;
            color: var(--checklist-st-not-required-text);
            border-color: var(--checklist-st-not-required-border);
        }

        .checklist-file-field {
            display: inline-flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 0.1rem;
        }

        .checklist-file-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.1rem;
            min-height: 1.75rem;
        }

        .checklist-file-actions .btn-icon {
            width: 1.65rem;
            height: 1.65rem;
            padding: 0;
        }

        .checklist-file-pending-name {
            max-width: 4rem;
            font-size: 0.625rem;
            color: var(--bs-secondary-color);
            line-height: 1.2;
        }

        .checklist-file-late-badge {
            font-size: 0.625rem;
            font-weight: 500;
        }

        .checklist-attachment-icon-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.65rem;
            height: 1.65rem;
            border-radius: 0.375rem;
            color: var(--bs-primary);
            text-decoration: none;
            vertical-align: middle;
        }

        .checklist-attachment-icon-link:hover,
        .checklist-attachment-icon-link:focus-visible {
            color: var(--bs-primary);
            background: rgba(var(--bs-primary-rgb), 0.08);
        }

        .checklist-file-cell-content {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.15rem;
        }

        .checklist-attachment-icon-link::after {
            content: attr(data-tooltip);
            position: absolute;
            top: calc(100% + 0.35rem);
            left: 50%;
            transform: translateX(-50%);
            z-index: 20;
            min-width: 8rem;
            max-width: 16rem;
            padding: 0.35rem 0.5rem;
            border-radius: 0.375rem;
            background: rgba(47, 43, 61, 0.92);
            color: #fff;
            font-size: 0.6875rem;
            line-height: 1.35;
            text-align: center;
            white-space: normal;
            word-break: break-word;
            box-shadow: 0 0.25rem 0.75rem rgba(47, 43, 61, 0.2);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.15s ease, visibility 0.15s ease;
        }

        .checklist-attachment-icon-link:hover::after,
        .checklist-attachment-icon-link:focus-visible::after {
            opacity: 1;
            visibility: visible;
        }

        .checklist-col-file .checklist-attachment-icon-link::after {
            top: auto;
            bottom: calc(100% + 0.35rem);
        }

        .checklist-compact-table .form-select-sm,
        .checklist-compact-table .form-control-sm {
            min-width: 0;
            font-size: 0.75rem;
            height: 1.75rem;
            padding-top: 0.15rem;
            padding-bottom: 0.15rem;
        }

        .checklist-compact-table .checklist-col-person .form-control-sm {
            font-size: 0.75rem;
        }

        .checklist-compact-table .badge {
            font-size: 0.6875rem;
            font-weight: 700;
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
