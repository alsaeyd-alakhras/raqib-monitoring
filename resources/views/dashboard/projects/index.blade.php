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
        @can('checklist_admin.manage')
            <div class="mx-2 nav-item">
                <a class="btn btn-label-secondary" href="{{ route('dashboard.checklist-admin.index') }}">إدارة قائمة التحقق</a>
            </div>
        @endcan
        @can('create', 'App\Models\Project')
            <div class="mx-2 nav-item">
                <a href="{{ route('dashboard.projects.create') }}" class="m-0 text-white btn btn-success">
                    <i class="fa-solid fa-plus"></i> إضافة
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
        $canCoordinator = $canViewCoordinatorColumnInList ?? true;
        $canMonitor = $canViewMonitorColumnInList ?? false;
        $stickyColCount = 4;

        $scrollFields = [
            'project_name' => 'اسم المشروع',
            'project_type' => 'النوع',
            'org_label' => 'المركز / الدائرة',
            'project_manager_name' => 'مدير المشروع',
        ];
        if ($canCoordinator) {
            $scrollFields['coordinator_name'] = 'المنسق';
            $scrollFields['coordinator_readiness_pct'] = 'جاهزية المنسق';
        }
        if ($canMonitor) {
            $scrollFields['monitor_name'] = 'المراقب';
            $scrollFields['monitor_readiness_pct'] = 'جاهزية المراقب';
        }
        $scrollFields['funder_name'] = 'الممول';
        $scrollFields['workflow_status_label'] = 'الحالة';
        $scrollFields['current_action_label'] = 'الإجراء الحالي';
    @endphp

    <div class="shadow-lg enhanced-card raqib-dt-layout">
        <div class="enhanced-card-body">
            <div class="col-12" style="padding: 0;">
                <div class="table-container raqib-table-container">
                    <table id="projects-table" class="table enhanced-sticky raqib-dt projects-dt table-striped table-hover" style="width:100%;">
                        <thead>
                            <tr>
                                <th class="sticky-r1 col-index text-center">#</th>
                                <th class="sticky-r2 col-icon text-center" title="عرض"><i class="fas fa-eye"></i></th>
                                <th class="sticky-r3 col-icon text-center" title="تعديل"><i class="fas fa-edit"></i></th>
                                <th class="sticky-r4 col-code text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <span>الرقم</span>
                                        <div class="enhanced-filter-dropdown d-flex align-items-center">
                                            <button class="btn-sort btn btn-sm border-0 p-0" type="button" data-sort-field="project_number" title="فرز">
                                                <i class="fas fa-sort text-white-50"></i>
                                            </button>
                                            <div class="dropdown">
                                                <button class="enhanced-btn-filter btn-filter btn btn-sm btn-secondary py-0 px-1" type="button" data-bs-toggle="dropdown" id="btn-filter-3">
                                                    <i class="fas fa-filter"></i>
                                                </button>
                                                <div class="dropdown-menu enhanced-filter-menu filterDropdownMenu">
                                                    <div class="mb-3 d-flex justify-content-between align-items-center">
                                                        <input type="search" class="form-control search-checkbox form-control-sm" placeholder="ابحث..." data-index="3">
                                                        <button class="enhanced-apply-btn ms-2 filter-apply-btn-checkbox" data-target="3" data-field="project_number" type="button">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </div>
                                                    <div class="enhanced-checkbox-list checkbox-list-box">
                                                        <label style="display: block;">
                                                            <input type="checkbox" value="all" class="all-checkbox" data-index="3"> الكل
                                                        </label>
                                                        <div class="checkbox-list checkbox-list-3"></div>
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
            const tableId = 'projects-table';
            const arabicFileJson = "{{ asset('files/Arabic.json') }}";
            const _token = "{{ csrf_token() }}";

            const urlIndex = `{{ route('dashboard.projects.index') }}`;
            const urlFilters = `{{ route('dashboard.projects.filters', ':column') }}`;
            const urlShow = `{{ route('dashboard.projects.show', ':id') }}`;
            const urlEdit = `{{ route('dashboard.projects.edit', ':id') }}`;
            const urlDelete = `{{ route('dashboard.projects.destroy', ':id') }}`;

            const abilityView = {{ Auth::user()->can('view', 'App\Models\Project') ? 'true' : 'false' }};
            const abilityEdit = {{ Auth::user()->can('update', 'App\Models\Project') ? 'true' : 'false' }};
            const abilityDelete = {{ Auth::user()->can('delete', 'App\Models\Project') ? 'true' : 'false' }};

            const canCoordinator = {{ $canCoordinator ? 'true' : 'false' }};
            const canMonitor = {{ $canMonitor ? 'true' : 'false' }};

            const fields = [
                '#', 'view', 'edit', 'project_number', 'project_name', 'project_type', 'org_label', 'project_manager_name',
                ...(canCoordinator ? ['coordinator_name', 'coordinator_readiness_pct'] : []),
                ...(canMonitor ? ['monitor_name', 'monitor_readiness_pct'] : []),
                'funder_name', 'workflow_status_label', 'current_action_label', 'actions'
            ];

            const columnsTable = [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, className: 'sticky-r1 col-index text-center' },
                {
                    data: 'id', name: 'view', orderable: false, searchable: false, className: 'sticky-r2 col-icon',
                    render: function (data) {
                        if (!abilityView) return '';
                        return '<a href="' + urlShow.replace(':id', data) + '" class="action-btn btn-view" title="عرض"><i class="fas fa-eye"></i></a>';
                    }
                },
                {
                    data: 'id', name: 'edit', orderable: false, searchable: false, className: 'sticky-r3 col-icon',
                    render: function (data) {
                        if (!abilityEdit) return '';
                        return '<a href="' + urlEdit.replace(':id', data) + '" class="action-btn btn-edit" title="تعديل"><i class="fas fa-edit"></i></a>';
                    }
                },
                { data: 'project_number', name: 'project_number', orderable: false, className: 'sticky-r4 col-code',
                    render: function (data) {
                        return renderTruncatedCode(data);
                    }
                },
                {
                    data: 'project_name_display', name: 'project_name', orderable: false,
                    render: function (data, type, row) {
                        let html = data;
                        if (row.needs_my_action) {
                            html += ' <span class="badge bg-warning text-dark">يتطلب إجراءك</span>';
                        }
                        return html;
                    }
                },
                { data: 'project_type', name: 'project_type', orderable: false },
                { data: 'org_label', name: 'org_label', orderable: false },
                { data: 'project_manager_name', name: 'project_manager_name', orderable: false },
            ];

            if (canCoordinator) {
                columnsTable.push(
                    { data: 'coordinator_name', name: 'coordinator_name', orderable: false },
                    { data: 'coordinator_readiness_pct', name: 'coordinator_readiness_pct', orderable: false, className: 'text-center' }
                );
            }
            if (canMonitor) {
                columnsTable.push(
                    { data: 'monitor_name', name: 'monitor_name', orderable: false },
                    { data: 'monitor_readiness_pct', name: 'monitor_readiness_pct', orderable: false, className: 'text-center' }
                );
            }

            columnsTable.push(
                { data: 'funder_name', name: 'funder_name', orderable: false },
                { data: 'workflow_status_label', name: 'workflow_status_label', orderable: false },
                { data: 'current_action_label', name: 'current_action_label', orderable: false },
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

            function rowCallbackFn(row, data) {
                if (data.needs_my_action) {
                    $(row).addClass('needs-action');
                }
            }
        </script>
        <script src="{{ asset('js/datatable.js') }}"></script>
    @endpush
</x-front-layout>
