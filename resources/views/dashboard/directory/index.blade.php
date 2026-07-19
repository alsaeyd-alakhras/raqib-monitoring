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
            .link-badge {
                display: inline-flex;
                align-items: center;
                padding: 0.2rem 0.55rem;
                border-radius: 0.35rem;
                font-size: 0.75rem;
                font-weight: 600;
                white-space: nowrap;
            }

            .link-badge.linked {
                background: #d1fae5;
                color: #047857;
                border: 1px solid #6ee7b7;
            }

            .link-badge.person-only {
                background: #f3f4f6;
                color: #4b5563;
                border: 1px solid #d1d5db;
            }

            .link-badge.user-only {
                background: #fef3c7;
                color: #b45309;
                border: 1px solid #fcd34d;
            }

            .status-badge.active {
                background: #dbeafe;
                color: #1d4ed8;
            }

            .status-badge.inactive {
                background: #fee2e2;
                color: #b91c1c;
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
        @can('create', 'App\Models\Person')
            <div class="mx-2 nav-item">
                <a href="{{ route('dashboard.directory.create') }}" class="m-0 text-white btn btn-primary">
                    <i class="fa-solid fa-plus"></i> إضافة
                </a>
            </div>
        @elsecan('create', 'App\Models\User')
            <div class="mx-2 nav-item">
                <a href="{{ route('dashboard.directory.create', ['mode' => 'user_only']) }}" class="m-0 text-white btn btn-primary">
                    <i class="fa-solid fa-plus"></i> إضافة حساب
                </a>
            </div>
        @endcan
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
        $stickyColCount = 3;
        $scrollFields = [
            'role_label' => 'الدور',
            'org_label' => 'الدائرة / القسم',
            'username' => 'اسم المستخدم',
            'is_active_label' => 'الحالة',
            'link_type_label' => 'نوع الربط',
        ];
    @endphp

    <div class="shadow-lg enhanced-card raqib-dt-layout">
        <div class="enhanced-card-body">
            <div class="col-12" style="padding: 0;">
                <div class="table-container raqib-table-container">
                    <table id="directory-table" class="table enhanced-sticky raqib-dt directory-dt table-striped table-hover" style="width:100%;">
                        <thead>
                            <tr>
                                <th class="sticky-r1 col-index text-center">#</th>
                                <th class="sticky-r2 col-icon text-center" title="تعديل"><i class="fas fa-edit"></i></th>
                                <th class="sticky-r3 col-name">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span>الاسم</span>
                                        <div class="enhanced-filter-dropdown d-flex align-items-center gap-1">
                                            <button class="btn-sort btn btn-sm border-0 p-0" type="button" data-sort-field="name" title="فرز">
                                                <i class="fas fa-sort text-white-50"></i>
                                            </button>
                                            <div class="dropdown">
                                                <button class="enhanced-btn-filter btn-filter btn btn-sm btn-secondary py-0 px-1" type="button" data-bs-toggle="dropdown" id="btn-filter-2">
                                                    <i class="fas fa-filter"></i>
                                                </button>
                                                <div class="dropdown-menu enhanced-filter-menu filterDropdownMenu">
                                                    <div class="mb-3 d-flex justify-content-between align-items-center">
                                                        <input type="search" class="form-control search-checkbox form-control-sm" placeholder="ابحث..." data-index="2">
                                                        <button class="enhanced-apply-btn ms-2 filter-apply-btn-checkbox" data-target="2" data-field="name" type="button">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </div>
                                                    <div class="enhanced-checkbox-list checkbox-list-box">
                                                        <label style="display: block;">
                                                            <input type="checkbox" value="all" class="all-checkbox" data-index="2"> الكل
                                                        </label>
                                                        <div class="checkbox-list checkbox-list-2"></div>
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
            const tableId = 'directory-table';
            const arabicFileJson = "{{ asset('files/Arabic.json') }}";
            const _token = "{{ csrf_token() }}";

            const urlIndex = `{{ route('dashboard.directory.index') }}`;
            const urlFilters = `{{ route('dashboard.directory.filters', ':column') }}`;
            const urlEdit = `{{ route('dashboard.directory.edit', ['record' => '__RECORD__']) }}`;
            const urlDelete = `{{ route('dashboard.directory.destroy', ['record' => '__RECORD__']) }}`;

            window.buildDeleteUrl = function (recordKey) {
                return urlDelete.replace('__RECORD__', encodeURIComponent(recordKey));
            };

            const fields = [
                '#', 'edit', 'name',
                'role_label', 'org_label', 'username', 'is_active_label', 'link_type_label',
                'actions'
            ];

            function renderLinkBadge(label) {
                const map = {
                    'مربوط': 'linked',
                    'بدون دخول': 'person-only',
                    'حساب فقط': 'user-only',
                };
                const cls = map[label] || 'person-only';
                return '<span class="link-badge ' + cls + '">' + label + '</span>';
            }

            function renderStatusBadge(label) {
                if (label === 'نشط') {
                    return '<span class="link-badge status-badge active">نشط</span>';
                }
                if (label === 'معطل') {
                    return '<span class="link-badge status-badge inactive">معطل</span>';
                }
                return '<span class="text-muted">' + label + '</span>';
            }

            const columnsTable = [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, className: 'sticky-r1 col-index text-center' },
                {
                    data: 'record_key', name: 'edit', orderable: false, searchable: false, className: 'sticky-r2 col-icon text-center',
                    render: function (data, type, row) {
                        if (!row.can_edit) return '';
                        return '<a href="' + urlEdit.replace('__RECORD__', encodeURIComponent(data)) + '" class="action-btn btn-edit" title="تعديل"><i class="fas fa-edit"></i></a>';
                    }
                },
                { data: 'name', name: 'name', orderable: false, className: 'sticky-r3 col-name' },
                { data: 'role_label', name: 'role_label', orderable: false },
                { data: 'org_label', name: 'org_label', orderable: false },
                { data: 'username', name: 'username', orderable: false },
                {
                    data: 'is_active_label', name: 'is_active_label', orderable: false, className: 'text-center',
                    render: function (data) { return renderStatusBadge(data); }
                },
                {
                    data: 'link_type_label', name: 'link_type_label', orderable: false, className: 'text-center',
                    render: function (data) { return renderLinkBadge(data); }
                },
                {
                    data: 'record_key', name: 'actions', orderable: false, searchable: false, className: 'sticky-l col-icon text-center',
                    render: function (data, type, row) {
                        if (!row.can_delete) return '';
                        return '<button type="button" class="action-btn btn-delete delete_row" data-id="' + data + '" title="حذف"><i class="fas fa-trash"></i></button>';
                    }
                }
            ];

            const SUMMABLE_COLUMNS = { enabled: false, columns: {} };
            const sortConfig = { enabled: true };
            let currentSortColumn = '';
            let currentSortDirection = '';
        </script>
        <script src="{{ asset('js/datatable.js') }}"></script>
    @endpush
</x-front-layout>
