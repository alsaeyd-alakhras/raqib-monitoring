<x-front-layout>
    @push('styles')
        <style>
            .org-tree-panel {
                max-height: calc(100vh - 220px);
                overflow-y: auto;
            }

            .org-tree ul {
                list-style: none;
                padding-right: 0;
                margin: 0;
            }

            .org-tree .org-children {
                padding-right: 1.25rem;
                border-right: 1px dashed #d9dee3;
                margin-right: 0.5rem;
            }

            .org-node-row {
                display: flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.35rem 0.5rem;
                border-radius: 0.375rem;
                cursor: pointer;
            }

            .org-node-row:hover,
            .org-node-row.active {
                background: rgba(105, 108, 255, 0.08);
            }

            .org-node-row.active {
                font-weight: 600;
            }

            .org-toggle {
                width: 1.25rem;
                text-align: center;
                color: #697a8d;
                flex-shrink: 0;
            }

            .org-type-badge {
                font-size: 0.7rem;
                padding: 0.1rem 0.4rem;
            }

            .org-detail-empty {
                min-height: 280px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #a1acb8;
            }
        </style>
    @endpush

    <x-slot:extra_nav>
        @if ($canManageCenters)
            <div class="mx-2 nav-item">
                <button type="button" class="btn btn-primary" id="btn-add-center">
                    <i class="fa-solid fa-plus"></i> مركز
                </button>
            </div>
        @endif
    </x-slot:extra_nav>

    <div class="row g-4">
        <div class="col-lg-5 col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">الشجرة التنظيمية</h5>
                    <button type="button" class="btn btn-sm btn-label-secondary" id="btn-refresh-tree" title="تحديث">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </button>
                </div>
                <div class="card-body org-tree-panel">
                    <div id="org-tree" class="org-tree">
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-spinner fa-spin"></i> جاري التحميل...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7 col-xl-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">تفاصيل العنصر</h5>
                </div>
                <div class="card-body" id="org-detail-panel">
                    <div class="org-detail-empty">
                        <div class="text-center">
                            <i class="fa-solid fa-sitemap fa-2x mb-3 d-block"></i>
                            <p class="mb-0">اختر مركزاً أو دائرة أو قسماً من الشجرة</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal إضافة/تعديل --}}
    <div class="modal fade" id="orgModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="org-form">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="org-modal-title">—</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="type" id="org-form-type">
                        <input type="hidden" name="id" id="org-form-id">
                        <input type="hidden" name="center_id" id="org-form-center-id">
                        <input type="hidden" name="department_id" id="org-form-department-id">

                        <div class="mb-3" id="org-parent-center-field" style="display:none;">
                            <label class="form-label">المركز</label>
                            <select class="form-select" id="org-form-center-select"></select>
                        </div>
                        <div class="mb-3" id="org-parent-department-field" style="display:none;">
                            <label class="form-label">الدائرة</label>
                            <select class="form-select" id="org-form-department-select"></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="org-form-name">الاسم</label>
                            <input type="text" class="form-control" name="name" id="org-form-name" required>
                        </div>
                        <div id="org-form-error" class="alert alert-danger d-none mb-0"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="org-form-submit">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.orgStructureConfig = {
                treeUrl: @json(route('dashboard.org-structure.tree')),
                nodeUrlTemplate: @json(route('dashboard.org-structure.node', ['type' => '__TYPE__', 'id' => '__ID__'])),
                storeUrl: @json(route('dashboard.org-structure.store')),
                updateUrlTemplate: @json(route('dashboard.org-structure.update', ['type' => '__TYPE__', 'id' => '__ID__'])),
                destroyUrlTemplate: @json(route('dashboard.org-structure.destroy', ['type' => '__TYPE__', 'id' => '__ID__'])),
                departmentsByCenterUrl: @json(route('dashboard.departments.by-center', ['center' => '__ID__'])),
                canManageCenters: @json($canManageCenters),
                canManageDepartments: @json($canManageDepartments),
                canManageSections: @json($canManageSections),
                csrf: @json(csrf_token()),
            };
        </script>
        <script src="{{ asset('js/org-structure.js') }}"></script>
    @endpush
</x-front-layout>
