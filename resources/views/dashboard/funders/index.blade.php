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
                <option value="15" selected>15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
        @can('create', 'App\Models\Funder')
            <div class="mx-2 nav-item">
                <a href="{{ route('dashboard.funders.create') }}" class="m-0 text-white btn btn-primary">
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
        $stickyColCount = 3;
    @endphp

    <div class="shadow-lg enhanced-card raqib-dt-layout">
        <div class="enhanced-card-body">
            <div class="col-12" style="padding: 0;">
                <div class="table-container raqib-table-container">
                    <table id="funders-table" class="table enhanced-sticky raqib-dt funders-dt table-striped table-hover" style="width:100%;">
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
            const tableId = 'funders-table';
            const arabicFileJson = "{{ asset('files/Arabic.json') }}";
            const _token = "{{ csrf_token() }}";

            const urlIndex = `{{ route('dashboard.funders.index') }}`;
            const urlFilters = `{{ route('dashboard.funders.filters', ':column') }}`;
            const urlEdit = `{{ route('dashboard.funders.edit', ':id') }}`;
            const urlDelete = `{{ route('dashboard.funders.destroy', ':id') }}`;

            const fields = ['#', 'edit', 'name', 'actions'];

            const columnsTable = [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, className: 'sticky-r1 col-index text-center' },
                {
                    data: 'id', name: 'edit', orderable: false, searchable: false, className: 'sticky-r2 col-icon text-center',
                    render: function (data, type, row) {
                        if (!row.can_edit) return '';
                        return '<a href="' + urlEdit.replace(':id', data) + '" class="action-btn btn-edit" title="تعديل"><i class="fas fa-edit"></i></a>';
                    }
                },
                { data: 'name', name: 'name', orderable: false, className: 'sticky-r3 col-name' },
                {
                    data: 'id', name: 'actions', orderable: false, searchable: false, className: 'sticky-l col-icon text-center',
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
