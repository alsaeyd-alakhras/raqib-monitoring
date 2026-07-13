@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $limitedSectionManagerForm = $limitedPersonFormForSectionManager ?? false;
    $sectionManagerCreate = $sectionManagerCreatingPerson ?? false;
    $sectionManagerMode = $limitedSectionManagerForm || $sectionManagerCreate;
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">بيانات الشخص</h5>
    </div>
    <div class="card-body">
        <div class="row">
            @if ($limitedSectionManagerForm)
                <div class="mb-4 col-md-6">
                    <label class="form-label">الاسم</label>
                    <input type="text" class="form-control" value="{{ $person->name }}" readonly>
                </div>
            @else
                <div class="mb-4 col-md-6">
                    <x-form.input
                        name="name"
                        label="الاسم"
                        :value="$person->name ?? ''"
                        required
                    />
                </div>
            @endif

            <div class="mb-4 col-md-6">
                <x-form.select
                    name="role"
                    id="person-role"
                    label="الدور الوظيفي"
                    :options="$roleLabels"
                    :value="$person->role ?? ''"
                />
            </div>

            @unless ($sectionManagerMode)
                <div class="mb-4 col-md-4" id="center-field">
                    <x-form.select
                        name="center_id"
                        id="person-center"
                        label="المركز"
                        :optionsId="$centers"
                        :value="$selectedCenterId ?? ''"
                    />
                </div>

                <div class="mb-4 col-md-4" id="department-field">
                    <label class="form-label" for="person-department">
                        الدائرة
                    </label>
                    <select
                        name="department_id"
                        id="person-department"
                        class="form-select @error('department_id') is-invalid @enderror"
                    >
                        <option value="">إختر القيمة</option>
                    </select>
                    @error('department_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div id="department-hint" class="form-text text-warning d-none"></div>
                </div>

                <div class="mb-4 col-md-4" id="section-field">
                    <label class="form-label" for="person-section">
                        القسم
                    </label>
                    <select
                        name="section_id"
                        id="person-section"
                        class="form-select @error('section_id') is-invalid @enderror"
                        @if ($lockSectionForSectionManager ?? false) disabled @endif
                    >
                        <option value="">إختر القيمة</option>
                    </select>
                    @if ($lockSectionForSectionManager ?? false)
                        <input type="hidden" name="section_id" value="{{ $selectedSectionId }}">
                    @endif
                    @error('section_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div id="section-hint" class="form-text text-warning d-none"></div>
                </div>

                <div class="mb-4 col-md-6">
                    <x-form.select
                        name="user_id"
                        label="ربط بحساب مستخدم (اختياري)"
                        :optionsId="$users"
                        :value="$person->user_id ?? null"
                    />
                </div>
            @endunless

            @if ($sectionManagerCreate)
                <input type="hidden" name="section_id" value="{{ $selectedSectionId }}">
                <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
            @endif

            <div class="mb-4 col-md-6">
                <x-form.input
                    name="job_title"
                    label="المسمى الوظيفي"
                    :value="$person->job_title ?? ''"
                />
            </div>

            @unless ($sectionManagerMode)
                <div class="mb-4 col-md-6">
                    <x-form.input
                        name="organization"
                        label="الجهة إن كان خارجياً"
                        :value="$person->organization ?? ''"
                    />
                </div>
            @endunless

            <div class="mb-4 col-md-6">
                <x-form.input
                    name="phone"
                    label="الهاتف"
                    :value="$person->phone ?? ''"
                />
            </div>
        </div>

        <div class="mt-2">
            <button type="submit" class="btn btn-primary me-3">
                حفظ
            </button>
            <a href="{{ route('dashboard.people.index') }}" class="btn btn-label-secondary">
                إلغاء
            </a>
        </div>
    </div>
</div>

@unless ($sectionManagerMode)
    @push('scripts')
    <script src="{{ asset('js/org-cascade.js') }}"></script>
    <script>
    (function () {
        const rolesRequiringDept = @json($rolesRequiringDepartment);
        const rolesRequiringSection = @json($rolesRequiringSection);
        const occupiedDeptManagers = @json($occupiedDepartmentManagers);
        const occupiedSectionManagers = @json($occupiedSectionManagers);
        const lockSection = @json($lockSectionForSectionManager ?? false);

        const occupiedByDeptId = Object.fromEntries(
            occupiedDeptManagers.map(item => [String(item.id), item.manager])
        );
        const occupiedBySectionId = Object.fromEntries(
            occupiedSectionManagers.map(item => [String(item.id), item.manager])
        );

        const roleSelect = document.getElementById('person-role');
        const centerSelect = document.getElementById('person-center');
        const deptSelect = document.getElementById('person-department');
        const sectionSelect = document.getElementById('person-section');
        const centerField = document.getElementById('center-field');
        const deptField = document.getElementById('department-field');
        const sectionField = document.getElementById('section-field');
        const deptHint = document.getElementById('department-hint');
        const sectionHint = document.getElementById('section-hint');
        const deptLabel = deptField?.querySelector('label');
        const sectionLabel = sectionField?.querySelector('label');

        function syncOrgFields() {
            if (!roleSelect) {
                return;
            }

            const role = roleSelect.value;
            const requiresDept = rolesRequiringDept.includes(role);
            const requiresSection = rolesRequiringSection.includes(role);
            const isDeptManager = role === 'department_manager';
            const isSectionManager = role === 'section_manager';

            if (centerField) {
                centerField.classList.toggle('d-none', !requiresDept && !requiresSection);
            }

            if (deptField) {
                deptField.classList.toggle('d-none', !requiresDept && !requiresSection);
            }

            if (sectionField) {
                sectionField.classList.toggle('d-none', !requiresSection);
            }

            if (deptLabel) {
                deptLabel.textContent = requiresDept || requiresSection ? 'الدائرة *' : 'الدائرة (اختياري)';
            }

            if (sectionLabel) {
                sectionLabel.textContent = requiresSection ? 'القسم *' : 'القسم (اختياري)';
            }

            if (deptSelect) {
                deptSelect.required = requiresDept || requiresSection;
            }

            if (sectionSelect && !lockSection) {
                sectionSelect.required = requiresSection;
            }

            if (deptSelect) {
                Array.from(deptSelect.options).forEach(option => {
                    if (!option.value) {
                        option.disabled = false;
                        option.hidden = false;
                        return;
                    }

                    const occupiedBy = occupiedByDeptId[option.value];
                    const blockOption = isDeptManager && occupiedBy && deptSelect.value !== option.value;

                    option.disabled = blockOption;
                    option.hidden = blockOption;
                });
            }

            if (sectionSelect && !lockSection) {
                Array.from(sectionSelect.options).forEach(option => {
                    if (!option.value) {
                        option.disabled = false;
                        option.hidden = false;
                        return;
                    }

                    const occupiedBy = occupiedBySectionId[option.value];
                    const blockOption = isSectionManager && occupiedBy && sectionSelect.value !== option.value;

                    option.disabled = blockOption;
                    option.hidden = blockOption;
                });
            }

            updateHints();
        }

        function updateHints() {
            if (!roleSelect) {
                return;
            }

            const role = roleSelect.value;
            const deptId = deptSelect?.value;
            const sectionId = sectionSelect?.value;

            if (deptHint) {
                deptHint.classList.add('d-none');
                deptHint.textContent = '';
            }

            if (sectionHint) {
                sectionHint.classList.add('d-none');
                sectionHint.textContent = '';
            }

            if (role === 'department_manager' && deptId && occupiedByDeptId[deptId] && deptHint) {
                deptHint.textContent = 'تنبيه: هذه الدائرة لديها مدير دائرة بالفعل (' + occupiedByDeptId[deptId] + ').';
                deptHint.classList.remove('d-none');
            } else if (rolesRequiringDept.includes(role) && !deptId && deptHint) {
                deptHint.textContent = 'الدائرة إلزامية لهذا الدور.';
                deptHint.classList.remove('d-none');
            }

            if (role === 'section_manager' && sectionId && occupiedBySectionId[sectionId] && sectionHint) {
                sectionHint.textContent = 'تنبيه: هذا القسم لديه مدير قسم بالفعل (' + occupiedBySectionId[sectionId] + ').';
                sectionHint.classList.remove('d-none');
            } else if (rolesRequiringSection.includes(role) && !sectionId && sectionHint) {
                sectionHint.textContent = 'القسم إلزامي لهذا الدور.';
                sectionHint.classList.remove('d-none');
            }
        }

        roleSelect?.addEventListener('change', syncOrgFields);
        deptSelect?.addEventListener('change', syncOrgFields);
        sectionSelect?.addEventListener('change', syncOrgFields);

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.initOrgCascade === 'function') {
                window.initOrgCascade({
                    centerId: 'person-center',
                    departmentId: 'person-department',
                    sectionId: 'person-section',
                    departmentsUrl: @json($departmentsByCenterUrl),
                    sectionsUrl: @json($sectionsByDepartmentUrl),
                    selectedCenterId: @json($selectedCenterId ?? ''),
                    selectedDepartmentId: @json($selectedDepartmentId ?? ''),
                    selectedSectionId: @json($selectedSectionId ?? ''),
                });
            }

            syncOrgFields();
        });
    })();
    </script>
    @endpush
@endunless
