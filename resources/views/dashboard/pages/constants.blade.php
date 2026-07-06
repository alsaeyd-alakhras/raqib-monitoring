<x-front-layout>
    <div class="col-xl-12">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <h5 class="mb-1">ثوابت النظام</h5>
                <p class="text-muted mb-0">إدارة القوائم والمقاييس المستخدمة في المشاريع والنشاطات الرقابية.</p>
            </div>
            @can('update', 'App\Models\Constant')
                <button type="submit" form="constants-form" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i> حفظ التغييرات
                </button>
            @endcan
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('danger'))
            <div class="alert alert-danger">{{ session('danger') }}</div>
        @endif

        <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>الجهات الممولة</strong> لا تُدار من هنا — تُخزَّن في جدول مستقل وتُعدَّل من صفحة الممولون.
            </div>
            @can('view', 'App\Models\Funder')
                <a href="{{ route('dashboard.funders.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> إدارة الممولين
                </a>
            @endcan
        </div>

        <div class="card">
            <div class="card-body">
                <div class="nav-align-top">
                    <ul class="nav nav-pills mb-4 nav-fill flex-column flex-md-row" role="tablist">
                        @foreach ($tabGroups as $tabId => $tab)
                            <li class="nav-item mb-1 mb-md-0">
                                <button
                                    type="button"
                                    class="nav-link {{ $loop->first ? 'active' : '' }}"
                                    role="tab"
                                    data-bs-toggle="tab"
                                    data-bs-target="#tab-{{ $tabId }}"
                                    aria-controls="tab-{{ $tabId }}"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                >
                                    <i class="fa-solid {{ $tab['icon'] }} me-1"></i>
                                    {{ $tab['label'] }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <form id="constants-form" action="{{ route('dashboard.constants.store') }}" method="post">
                        @csrf
                        <div class="tab-content">
                            @foreach ($tabGroups as $tabId => $tab)
                                <div
                                    class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                                    id="tab-{{ $tabId }}"
                                    role="tabpanel"
                                >
                                    @if (($tab['type'] ?? null) === 'legacy')
                                        @include('dashboard.pages.constants._legacy', [
                                            'legacyFields' => $legacyFields,
                                            'legacyValues' => $legacyValues,
                                        ])
                                    @else
                                        @foreach ($tab['keys'] as $key)
                                            @include('dashboard.pages.constants._editor', [
                                                'key' => $key,
                                                'meta' => $registry[$key],
                                                'decodedValues' => $decodedValues,
                                            ])
                                        @endforeach
                                    @endif

                                    @can('update', 'App\Models\Constant')
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fa-solid fa-floppy-disk me-1"></i> حفظ {{ $tab['label'] }}
                                            </button>
                                        </div>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        function reindexScaleRows(tbody, key, fieldName) {
            tbody.querySelectorAll('tr').forEach((row, index) => {
                const input = row.querySelector(`input[name^="constants[${key}]"]`);
                if (!input) return;
                row.querySelectorAll('input').forEach((el) => {
                    const current = el.getAttribute('name') || '';
                    el.setAttribute('name', current.replace(/\[\d+\]/, `[${index}]`));
                });
            });
        }

        function updateRemoveButtons(container, rowSelector) {
            const rows = container.querySelectorAll(rowSelector);
            rows.forEach((row) => {
                const btn = row.querySelector('.btn-remove-row');
                if (btn) {
                    btn.disabled = rows.length <= 1;
                }
            });
        }

        document.addEventListener('click', function (event) {
            const addStringBtn = event.target.closest('.btn-add-string-row');
            if (addStringBtn) {
                const card = addStringBtn.closest('.constant-editor-card');
                const key = card.dataset.constantKey;
                const container = card.querySelector('.constant-string-rows');
                const row = document.createElement('div');
                row.className = 'input-group mb-2 constant-string-row';
                row.innerHTML = `
                    <input type="text" name="constants[${key}][]" class="form-control" placeholder="أدخل قيمة...">
                    <button type="button" class="btn btn-outline-danger btn-remove-row"><i class="fa-solid fa-trash"></i></button>
                `;
                container.appendChild(row);
                updateRemoveButtons(container, '.constant-string-row');
                row.querySelector('input')?.focus();
                return;
            }

            const addValueScaleBtn = event.target.closest('.btn-add-value-scale-row');
            if (addValueScaleBtn) {
                const card = addValueScaleBtn.closest('.constant-editor-card');
                const key = card.dataset.constantKey;
                const tbody = card.querySelector('.constant-value-scale-rows');
                const index = tbody.querySelectorAll('tr').length;
                const row = document.createElement('tr');
                row.className = 'constant-value-scale-row';
                row.innerHTML = `
                    <td><input type="number" name="constants[${key}][${index}][value]" class="form-control"></td>
                    <td><input type="text" name="constants[${key}][${index}][label]" class="form-control" placeholder="مثال: ممتاز"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="fa-solid fa-trash"></i></button></td>
                `;
                tbody.appendChild(row);
                updateRemoveButtons(tbody, 'tr');
                row.querySelector('input')?.focus();
                return;
            }

            const addKpiScaleBtn = event.target.closest('.btn-add-kpi-scale-row');
            if (addKpiScaleBtn) {
                const card = addKpiScaleBtn.closest('.constant-editor-card');
                const key = card.dataset.constantKey;
                const tbody = card.querySelector('.constant-kpi-scale-rows');
                const index = tbody.querySelectorAll('tr').length;
                const row = document.createElement('tr');
                row.className = 'constant-kpi-scale-row';
                row.innerHTML = `
                    <td><input type="number" name="constants[${key}][${index}][min]" class="form-control" min="0" max="100"></td>
                    <td><input type="text" name="constants[${key}][${index}][label]" class="form-control" placeholder="مثال: ممتاز"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="fa-solid fa-trash"></i></button></td>
                `;
                tbody.appendChild(row);
                updateRemoveButtons(tbody, 'tr');
                row.querySelector('input')?.focus();
                return;
            }

            const removeBtn = event.target.closest('.btn-remove-row');
            if (removeBtn && !removeBtn.disabled) {
                const row = removeBtn.closest('.constant-string-row, tr');
                const card = removeBtn.closest('.constant-editor-card');
                const container = row?.parentElement;
                row?.remove();

                if (card?.dataset.editorType === 'value-scale') {
                    reindexScaleRows(card.querySelector('.constant-value-scale-rows'), card.dataset.constantKey);
                    updateRemoveButtons(card.querySelector('.constant-value-scale-rows'), 'tr');
                } else if (card?.dataset.editorType === 'kpi-scale') {
                    reindexScaleRows(card.querySelector('.constant-kpi-scale-rows'), card.dataset.constantKey);
                    updateRemoveButtons(card.querySelector('.constant-kpi-scale-rows'), 'tr');
                } else if (container) {
                    updateRemoveButtons(container, '.constant-string-row');
                }
            }
        });
    })();
    </script>
    @endpush
</x-front-layout>
