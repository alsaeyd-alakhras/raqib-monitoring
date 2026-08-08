<x-front-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/datatable/jquery.dataTables.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/datatable/dataTables.bootstrap4.css') }}">
        <link rel="stylesheet" href="{{ asset('css/datatable/dataTables.dataTables.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/stickyTable.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/style.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/datatableIndex.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/datatableIndex2.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/raqib-datatable-sticky.css') }}">
        <style>
            .closure-docs-indicator {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.25rem;
                min-width: 3.25rem;
                padding: 0.2rem 0.55rem;
                border-radius: 0.35rem;
                font-size: 0.75rem;
                font-weight: 700;
                line-height: 1.2;
                white-space: nowrap;
            }

            .closure-docs-indicator.complete {
                background: #d1fae5;
                color: #047857;
                border: 1px solid #6ee7b7;
            }

            .closure-docs-indicator.complete i {
                font-size: 0.95rem;
            }

            .closure-docs-indicator.partial {
                background: #fef3c7;
                color: #b45309;
                border: 1px solid #fcd34d;
            }

            #monitoring-activities-table tbody tr.table-row-needs-approval > td {
                background-color: #fff8e6 !important;
            }

            #monitoring-activities-table tbody tr.table-row-needs-approval:hover > td {
                background-color: #ffefb8 !important;
            }

            #monitoring-activities-table tbody tr.table-row-needs-monitor-action > td {
                background-color: #fff8e6 !important;
            }

            #monitoring-activities-table tbody tr.table-row-needs-monitor-action:hover > td {
                background-color: #ffefb8 !important;
            }

            #monitoring-activities-table tbody tr.table-row-returned-to-monitor > td {
                background-color: #fff1e6 !important;
                box-shadow: inset 4px 0 0 #f97316;
            }

            #monitoring-activities-table tbody tr.table-row-returned-to-monitor:hover > td {
                background-color: #ffe8d6 !important;
            }

            #monitoring-activities-table tbody tr.table-row-my-pending > td {
                background-color: #eff6ff !important;
            }

            #monitoring-activities-table tbody tr.table-row-my-pending:hover > td {
                background-color: #dbeafe !important;
            }
        </style>
    @endpush

    <x-slot:extra_nav>
        <div class="nav-item">
            <select class="form-control" name="advanced-pagination" id="advanced-pagination">
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
            </select>
        </div>
        @can('create', 'App\Models\MonitoringActivity')
            <div class="mx-2 nav-item">
                <a href="{{ route('dashboard.monitoring-activities.create') }}" class="m-0 text-white btn btn-primary">
                    <i class="fa-solid fa-plus"></i> إضافة
                </a>
            </div>
        @elsecan('assign_monitor', 'App\Models\MonitoringActivity')
            <div class="mx-2 nav-item">
                <a href="{{ route('dashboard.monitoring-activities.create') }}" class="m-0 text-white btn btn-primary">
                    <i class="fa-solid fa-plus"></i> إضافة
                </a>
            </div>
        @endcan
        @can('create_external', 'App\Models\MonitoringActivity')
            <div class="mx-2 nav-item">
                <a href="{{ route('dashboard.external-activities.create') }}" class="m-0 btn btn-primary">
                    <i class="fa-solid fa-globe"></i> إضافة نشاط خارجي
                </a>
            </div>
        @endcan
        @if ($canFilterPendingApproval ?? false)
            <div class="mx-2 nav-item">
                <button type="button" class="btn btn-outline-warning" id="filterPendingApproval" title="أنشطة خارجية بانتظار اعتمادي">
                    <i class="fa-solid fa-clock"></i> خارجي بانتظار اعتمادي
                    @if (($pendingApprovalCount ?? 0) > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $pendingApprovalCount }}</span>
                    @endif
                </button>
            </div>
        @endif
        @if ($canFilterNeedsMyAction ?? false)
            <div class="mx-2 nav-item">
                <button type="button" class="btn btn-outline-warning" id="filterNeedsMyAction" title="أنشطة تتطلب إجرائي">
                    <i class="fa-solid fa-user-check"></i> يتطلب إجرائي
                    @if (($needsMyActionCount ?? 0) > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $needsMyActionCount }}</span>
                    @endif
                </button>
            </div>
        @endif
        <div class="mx-2 nav-item">
            <button class="p-2 border-0 btn btn-outline-danger rounded-pill d-none" type="button" id="filterBtnClear" title="إزالة التصفية">
                <i class="fa-solid fa-eraser"></i>
            </button>
        </div>
        <div class="mx-2 nav-item">
            <button type="button" class="btn" id="refreshData" title="تحديث">
                <i class="fa-solid fa-arrows-rotate"></i>
            </button>
        </div>
    </x-slot:extra_nav>

    @php
        $stickyColCount = 5;
        $canClosureDocs = $canViewClosureDocsColumnInList ?? true;

        $scrollFields = [
            'activity_date' => 'التاريخ',
            'source_type_label' => 'المصدر',
            'activity_type' => 'النوع',
            'org_label' => 'المركز / الدائرة',
            'responsible_name' => 'المسؤول',
            'monitor_name' => 'المراقب',
            'subject' => 'الموضوع',
            'kpi_value' => 'KPI',
            'kpi_rating' => 'التصنيف',
            'workflow_status_label' => 'الحالة',
        ];
        if ($canClosureDocs) {
            $scrollFields['closure_docs_label'] = 'مستندات الإغلاق';
        }
    @endphp

    <div class="shadow-lg enhanced-card raqib-dt-layout">
        <div class="enhanced-card-body">
            <div class="col-12" style="padding: 0;">
                <div class="table-container raqib-table-container">
                    <table id="monitoring-activities-table" class="table enhanced-sticky raqib-dt monitoring-dt table-striped table-hover" style="width:100%;">
                        <thead>
                            <tr>
                                <th class="sticky-r1 col-index text-center">#</th>
                                <th class="sticky-r2 col-verify text-center" title="التحقق"><i class="fas fa-shield-alt"></i></th>
                                <th class="sticky-r3 col-icon text-center" title="عرض"><i class="fas fa-eye"></i></th>
                                <th class="sticky-r4 col-icon text-center" title="تعديل"><i class="fas fa-edit"></i></th>
                                <th class="sticky-r5 col-code text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <span>الرمز</span>
                                        <div class="enhanced-filter-dropdown d-flex align-items-center">
                                            <button class="btn-sort btn btn-sm border-0 p-0" type="button" data-sort-field="reference_code" title="فرز">
                                                <i class="fas fa-sort text-white-50"></i>
                                            </button>
                                            <div class="dropdown">
                                                <button class="enhanced-btn-filter btn-filter btn btn-sm btn-secondary py-0 px-1" type="button" data-bs-toggle="dropdown" id="btn-filter-4">
                                                    <i class="fas fa-filter"></i>
                                                </button>
                                                <div class="dropdown-menu enhanced-filter-menu filterDropdownMenu">
                                                    <div class="mb-3 d-flex justify-content-between align-items-center">
                                                        <input type="search" class="form-control search-checkbox form-control-sm" placeholder="ابحث..." data-index="4">
                                                        <button class="enhanced-apply-btn ms-2 filter-apply-btn-checkbox" data-target="4" data-field="reference_code" type="button">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </div>
                                                    <div class="enhanced-checkbox-list checkbox-list-box">
                                                        <label style="display: block;">
                                                            <input type="checkbox" value="all" class="all-checkbox" data-index="4"> الكل
                                                        </label>
                                                        <div class="checkbox-list checkbox-list-4"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </th>
                                @foreach ($scrollFields as $index => $label)
                                    @php $colIdx = $stickyColCount + $loop->index; @endphp
                                    <th>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>{{ $label }}</span>
                                            <div class="enhanced-filter-dropdown d-flex align-items-center gap-1">
                                                <button class="btn-sort btn btn-sm border-0 p-1" type="button" data-sort-field="{{ $index }}" title="فرز">
                                                    <i class="fas fa-sort text-muted"></i>
                                                </button>
                                                <div class="dropdown">
                                                    <button class="enhanced-btn-filter btn-filter btn btn-sm btn-secondary" type="button" data-bs-toggle="dropdown" id="btn-filter-{{ $colIdx }}">
                                                        <i class="fas fa-filter"></i>
                                                    </button>
                                                    <div class="dropdown-menu enhanced-filter-menu filterDropdownMenu">
                                                        @if ($index === 'activity_date')
                                                            <div class="mb-3">
                                                                <label class="form-label text-muted small">من تاريخ:</label>
                                                                <input type="date" class="form-control form-control-sm" id="from_date">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label text-muted small">إلى تاريخ:</label>
                                                                <input type="date" class="form-control form-control-sm" id="to_date">
                                                            </div>
                                                            <div class="gap-2 d-flex">
                                                                <button class="enhanced-apply-btn flex-fill" id="filter-date-btn" type="button"><i class="fas fa-check me-1"></i> تطبيق</button>
                                                                <button class="btn btn-outline-secondary btn-sm flex-fill" id="filter-date-clear-btn" type="button"><i class="fas fa-times me-1"></i> مسح</button>
                                                            </div>
                                                        @else
                                                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                                                <input type="search" class="form-control search-checkbox" placeholder="ابحث..." data-index="{{ $colIdx }}">
                                                                <button class="enhanced-apply-btn ms-2 filter-apply-btn-checkbox" data-target="{{ $colIdx }}" data-field="{{ $index }}" type="button">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </div>
                                                            <div class="enhanced-checkbox-list checkbox-list-box">
                                                                <label style="display: block;">
                                                                    <input type="checkbox" value="all" class="all-checkbox" data-index="{{ $colIdx }}"> الكل
                                                                </label>
                                                                <div class="checkbox-list checkbox-list-{{ $colIdx }}"></div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </th>
                                @endforeach
                                <th class="sticky-l col-icon text-center" title="حذف"><i class="fas fa-trash"></i></th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/plugins/datatable/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('js/plugins/datatable/dataTables.js') }}"></script>
        <script>
            const tableId = 'monitoring-activities-table';
            const arabicFileJson = "{{ asset('files/Arabic.json') }}";
            const _token = "{{ csrf_token() }}";
            const dateFilterField = 'activity_date';

            const urlIndex = `{{ route('dashboard.monitoring-activities.index') }}`;
            const urlFilters = `{{ route('dashboard.monitoring-activities.filters', ':column') }}`;
            const urlShow = `{{ route('dashboard.monitoring-activities.show', ':id') }}`;
            const urlEdit = `{{ route('dashboard.monitoring-activities.edit', ':id') }}`;
            const urlDelete = `{{ route('dashboard.monitoring-activities.destroy', ':id') }}`;

            const abilityView = {{ Auth::user()->can('view', 'App\Models\MonitoringActivity') ? 'true' : 'false' }};
            const abilityEdit = {{ Auth::user()->can('update', 'App\Models\MonitoringActivity') ? 'true' : 'false' }};
            const abilityDelete = {{ Auth::user()->can('delete', 'App\Models\MonitoringActivity') ? 'true' : 'false' }};
            const canClosureDocs = {{ $canClosureDocs ? 'true' : 'false' }};
            const urlParams = new URLSearchParams(window.location.search);
            let pendingMyApproval = urlParams.get('pending_my_approval') === '1';
            let needsMyAction = urlParams.get('needs_my_action') === '1';

            function setPendingApprovalFilter(active) {
                pendingMyApproval = active;
                const btn = document.getElementById('filterPendingApproval');
                if (!btn) {
                    return;
                }
                btn.classList.toggle('active', active);
                btn.classList.toggle('btn-warning', active);
                btn.classList.toggle('btn-outline-warning', !active);
            }

            function setNeedsMyActionFilter(active) {
                needsMyAction = active;
                const btn = document.getElementById('filterNeedsMyAction');
                if (!btn) {
                    return;
                }
                btn.classList.toggle('active', active);
                btn.classList.toggle('btn-warning', active);
                btn.classList.toggle('btn-outline-warning', !active);
            }

            if (pendingMyApproval) {
                setPendingApprovalFilter(true);
            }

            if (needsMyAction) {
                setNeedsMyActionFilter(true);
            }

            function extraAjaxDataFn(d) {
                if (pendingMyApproval) {
                    d.pending_my_approval = 1;
                }
                if (needsMyAction) {
                    d.needs_my_action = 1;
                }
            }

            function rowCallbackFn(row, data) {
                if (data.needs_director_approval) {
                    $(row).addClass('table-row-needs-approval');
                }
                if (data.was_returned_to_monitor) {
                    $(row).addClass('table-row-returned-to-monitor');
                } else if (data.needs_monitor_action) {
                    $(row).addClass('table-row-needs-monitor-action');
                } else if (data.is_assigned_to_me && data.workflow_status_key === 'pending_confirmation') {
                    $(row).addClass('table-row-my-pending');
                }
            }

            function renderSourceBadge(label, key) {
                const variant = {
                    project: 'primary',
                    external: 'info',
                    meeting: 'secondary',
                    project_execution: 'warning',
                }[key] || 'secondary';

                return '<span class="badge bg-label-' + variant + '">' + (label || '—') + '</span>';
            }

            function renderWorkflowBadge(label, key, row) {
                const variant = {
                    completed: 'success',
                    pending_confirmation: 'warning',
                    in_progress: 'info',
                    rejected: 'danger',
                    pending_monitor: 'secondary',
                }[key] || 'secondary';

                let html = '<span class="badge bg-label-' + variant + '">' + (label || '—') + '</span>';

                if (row?.was_returned_to_monitor) {
                    html += ' <span class="badge bg-label-danger ms-1">مرجع للتعديل</span>';
                } else if (row?.needs_monitor_action) {
                    html += ' <span class="badge bg-label-warning ms-1">يتطلب إجراءك</span>';
                }

                return html;
            }

            function renderClosureDocsIndicator(row) {
                if (!row.closure_docs_total) {
                    return '<span class="text-muted">—</span>';
                }

                if (row.closure_docs_complete) {
                    return '<span class="closure-docs-indicator complete" title="مكتمل — ' + row.closure_docs_total + ' مستندات">'
                        + '<i class="ti ti-circle-check"></i>'
                        + '<span>' + row.closure_docs_total + '/' + row.closure_docs_total + '</span>'
                        + '</span>';
                }

                return '<span class="closure-docs-indicator partial" title="ناقص — ' + row.closure_docs_attached + ' من ' + row.closure_docs_total + '">'
                    + '<span>' + row.closure_docs_label + '</span>'
                    + '</span>';
            }

            const fields = [
                '#', 'verification', 'view', 'edit', 'reference_code',
                'activity_date', 'source_type_label', 'activity_type', 'org_label',
                'responsible_name', 'monitor_name', 'subject',
                'kpi_value', 'kpi_rating', 'workflow_status_label',
                ...(canClosureDocs ? ['closure_docs_label'] : []),
                'actions'
            ];

            function buildVerificationHtml(isVerified, issues) {
                if (isVerified) {
                    return '<span class="verify-icon ok" title="تحقق"><i class="fas fa-check-circle"></i></span>';
                }
                const listItems = (issues || []).map(i => '<li>' + i + '</li>').join('');
                const content = listItems ? '<ul class="mb-0 ps-3 small">' + listItems + '</ul>' : 'يوجد مشاكل في التحقق';
                const escaped = content.replace(/"/g, '&quot;');
                return '<span class="verify-icon fail" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true" data-bs-placement="left" data-bs-content="' + escaped + '"><i class="fas fa-times-circle"></i></span>';
            }

            const columnsTable = [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, className: 'sticky-r1 col-index text-center' },
                {
                    data: null, name: 'verification', orderable: false, searchable: false, className: 'sticky-r2 col-verify text-center',
                    render: function (data, type, row) {
                        return buildVerificationHtml(row.is_verified, row.verification_issues);
                    }
                },
                {
                    data: 'id', name: 'view', orderable: false, searchable: false, className: 'sticky-r3 col-icon',
                    render: function (data, type, row) {
                        if (!abilityView) return '';
                        const href = row.show_url || urlShow.replace(':id', data);
                        return '<a href="' + href + '" class="action-btn btn-view" title="عرض"><i class="fas fa-eye"></i></a>';
                    }
                },
                {
                    data: 'id', name: 'edit', orderable: false, searchable: false, className: 'sticky-r4 col-icon',
                    render: function (data, type, row) {
                        if (!row.can_edit) return '';
                        const href = row.edit_url || urlEdit.replace(':id', data);
                        return '<a href="' + href + '" class="action-btn btn-edit" title="تعديل"><i class="fas fa-edit"></i></a>';
                    }
                },
                { data: 'reference_code', name: 'reference_code', orderable: false, className: 'sticky-r5 col-code',
                    render: function (data) {
                        return renderTruncatedCode(data);
                    }
                },
                { data: 'activity_date', name: 'activity_date', orderable: false, className: 'text-center' },
                {
                    data: 'source_type_label', name: 'source_type_label', orderable: false,
                    render: function (data, type, row) {
                        return renderSourceBadge(data, row.source_type_key);
                    }
                },
                { data: 'activity_type', name: 'activity_type', orderable: false },
                { data: 'org_label', name: 'org_label', orderable: false },
                { data: 'responsible_name', name: 'responsible_name', orderable: false },
                { data: 'monitor_name', name: 'monitor_name', orderable: false },
                { data: 'subject', name: 'subject', orderable: false },
                { data: 'kpi_value', name: 'kpi_value', orderable: false, className: 'text-center' },
                { data: 'kpi_rating', name: 'kpi_rating', orderable: false, className: 'text-center' },
                {
                    data: 'workflow_status_label', name: 'workflow_status_label', orderable: false,
                    render: function (data, type, row) {
                        return renderWorkflowBadge(data, row.workflow_status_key, row);
                    }
                }
            ];

            if (canClosureDocs) {
                columnsTable.push({
                    data: 'closure_docs_label',
                    name: 'closure_docs_label',
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return renderClosureDocsIndicator(row);
                    }
                });
            }

            columnsTable.push(
                {
                    data: 'id', name: 'actions', orderable: false, searchable: false, className: 'sticky-l col-icon',
                    render: function (data) {
                        if (!abilityDelete) return '';
                        return '<button type="button" class="action-btn btn-delete delete_row" data-id="' + data + '" title="حذف"><i class="fas fa-trash"></i></button>';
                    }
                }
            );

            const SUMMABLE_COLUMNS = { enabled: false, columns: {} };
            const sortConfig = { enabled: true };
            let currentSortColumn = '';
            let currentSortDirection = '';

            function initVerificationPopovers() {
                document.querySelectorAll('#' + tableId + ' [data-bs-toggle="popover"]').forEach(function (el) {
                    bootstrap.Popover.getInstance(el)?.dispose();
                    new bootstrap.Popover(el);
                });
            }

            function drawCallbackFn() {
                initVerificationPopovers();
            }

            document.getElementById('filterPendingApproval')?.addEventListener('click', function () {
                setPendingApprovalFilter(!pendingMyApproval);
                if (typeof table !== 'undefined') {
                    table.ajax.reload();
                }
            });

            document.getElementById('filterNeedsMyAction')?.addEventListener('click', function () {
                setNeedsMyActionFilter(!needsMyAction);
                if (typeof table !== 'undefined') {
                    table.ajax.reload();
                }
            });
        </script>
        <script src="{{ asset('js/datatable.js') }}"></script>
    @endpush
</x-front-layout>
