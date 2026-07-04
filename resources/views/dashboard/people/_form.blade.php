@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">بيانات الشخص</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-6">
                <x-form.input
                    name="name"
                    label="الاسم"
                    :value="$person->name ?? ''"
                    required
                />
            </div>

            <div class="mb-4 col-md-6">
                <x-form.select
                    name="role"
                    id="person-role"
                    label="الدور الوظيفي"
                    :options="$roleLabels"
                    :value="$person->role ?? ''"
                    required
                />
            </div>

            <div class="mb-4 col-md-6" id="department-field">
                <x-form.select
                    name="department_id"
                    id="person-department"
                    label="الدائرة (اختياري)"
                    :optionsId="$departments"
                    :value="$person->department_id ?? null"
                />
                <div id="department-hint" class="form-text text-warning d-none"></div>
            </div>

            <div class="mb-4 col-md-6">
                <x-form.select
                    name="user_id"
                    label="ربط بحساب مستخدم (اختياري)"
                    :optionsId="$users"
                    :value="$person->user_id ?? null"
                />
            </div>

            <div class="mb-4 col-md-6">
                <x-form.input
                    name="job_title"
                    label="المسمى الوظيفي"
                    :value="$person->job_title ?? ''"
                />
            </div>

            <div class="mb-4 col-md-6">
                <x-form.input
                    name="organization"
                    label="الجهة إن كان خارجياً"
                    :value="$person->organization ?? ''"
                />
            </div>

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

@push('scripts')
<script>
(function () {
    const rolesRequiringDept = @json($rolesRequiringDepartment);
    const occupiedManagers = @json($occupiedDepartmentManagers);
    const occupiedByDeptId = Object.fromEntries(
        occupiedManagers.map(item => [String(item.id), item.manager])
    );

    const roleSelect = document.getElementById('person-role');
    const deptSelect = document.getElementById('person-department');
    const deptField = document.getElementById('department-field');
    const deptHint = document.getElementById('department-hint');
    const deptLabel = deptField?.querySelector('label');

    function syncDepartmentField() {
        if (!roleSelect || !deptSelect) {
            return;
        }

        const role = roleSelect.value;
        const requiresDept = rolesRequiringDept.includes(role);
        const isDeptManager = role === 'department_manager';

        if (deptLabel) {
            deptLabel.textContent = requiresDept ? 'الدائرة *' : 'الدائرة (اختياري)';
        }

        deptSelect.required = requiresDept;

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

        updateDepartmentHint();
    }

    function updateDepartmentHint() {
        if (!deptHint || !roleSelect || !deptSelect) {
            return;
        }

        const role = roleSelect.value;
        const deptId = deptSelect.value;
        const occupiedBy = occupiedByDeptId[deptId];

        deptHint.classList.add('d-none');
        deptHint.textContent = '';

        if (role === 'department_manager' && deptId && occupiedBy) {
            deptHint.textContent = 'تنبيه: هذه الدائرة لديها مدير دائرة بالفعل (' + occupiedBy + ').';
            deptHint.classList.remove('d-none');
        } else if (rolesRequiringDept.includes(role) && !deptId) {
            deptHint.textContent = 'الدائرة إلزامية لهذا الدور.';
            deptHint.classList.remove('d-none');
        }
    }

    roleSelect?.addEventListener('change', syncDepartmentField);
    deptSelect?.addEventListener('change', syncDepartmentField);

    syncDepartmentField();
})();
</script>
@endpush
