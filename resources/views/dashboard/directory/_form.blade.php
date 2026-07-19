@php
    $limitedSectionManagerForm = $limitedPersonFormForSectionManager ?? false;
    $sectionManagerCreate = $sectionManagerCreatingPerson ?? false;
    $sectionManagerMode = $limitedSectionManagerForm || $sectionManagerCreate;
    $initialRecordType = old('record_mode', $recordType ?? 'linked');
    if (request('mode') === 'user_only' && !isset($recordKey)) {
        $initialRecordType = 'user_only';
    }
@endphp

<x-front-layout>
    <form action="{{ $formAction }}" method="post" class="col-12">
        @csrf
        @if ($formMethod === 'put')
            @method('put')
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $sectionManagerMode && ($canCreateUsers ?? false) && ! isset($recordKey))
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">نوع السجل</h5></div>
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="record_mode" id="mode-linked" value="linked" @checked($initialRecordType === 'linked')>
                        <label class="btn btn-outline-primary" for="mode-linked">شخص + حساب</label>

                        <input type="radio" class="btn-check" name="record_mode" id="mode-person-only" value="person_only" @checked($initialRecordType === 'person_only')>
                        <label class="btn btn-outline-primary" for="mode-person-only">شخص فقط</label>

                        <input type="radio" class="btn-check" name="record_mode" id="mode-user-only" value="user_only" @checked($initialRecordType === 'user_only')>
                        <label class="btn btn-outline-primary" for="mode-user-only">حساب فقط</label>
                    </div>
                </div>
            </div>
        @else
            <input type="hidden" name="record_mode" value="{{ $initialRecordType }}">
        @endif

        <div id="person-section" class="card mb-4" @if($initialRecordType === 'user_only') style="display:none" @endif>
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">البيانات الوظيفية</h5>
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
                            <x-form.input name="name" label="الاسم" :value="old('name', $person->name ?? $user->name ?? '')" required />
                        </div>
                    @endif

                    <div class="mb-4 col-md-6">
                        <x-form.select
                            name="role"
                            id="directory-role"
                            label="الدور الوظيفي"
                            :options="$roleLabels"
                            :value="old('role', $person->role ?? '')"
                        />
                    </div>

                    @unless ($sectionManagerMode)
                        <div class="mb-4 col-md-4" id="center-field">
                            <x-form.select name="center_id" id="directory-center" label="المركز" :optionsId="$centers" :value="$selectedCenterId ?? ''" />
                        </div>
                        <div class="mb-4 col-md-4">
                            <label class="form-label" for="directory-department">الدائرة</label>
                            <select name="department_id" id="directory-department" class="form-select @error('department_id') is-invalid @enderror">
                                <option value="">اختر القيمة</option>
                            </select>
                            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label class="form-label" for="directory-section">القسم</label>
                            <select name="section_id" id="directory-section" class="form-select @error('section_id') is-invalid @enderror">
                                <option value="">اختر القيمة</option>
                            </select>
                            @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <input type="hidden" name="section_id" value="{{ $selectedSectionId }}">
                        <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                    @endunless

                    <div class="mb-4 col-md-4">
                        <x-form.input name="job_title" label="المسمى الوظيفي" :value="old('job_title', $person->job_title ?? '')" />
                    </div>
                    <div class="mb-4 col-md-4">
                        <x-form.input name="organization" label="الجهة (خارجي)" :value="old('organization', $person->organization ?? '')" />
                    </div>
                    <div class="mb-4 col-md-4">
                        <x-form.input name="phone" label="الهاتف" :value="old('phone', $person->phone ?? $user->phone ?? '')" />
                    </div>
                </div>
            </div>
        </div>

        @if ($canManageUsers ?? false)
            <div id="account-section" class="card mb-4" @if($initialRecordType === 'person_only') style="display:none" @endif>
                <div class="card-header">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="has_account" id="has-account" value="1"
                            @checked(old('has_account', $initialRecordType !== 'person_only'))>
                        <label class="form-check-label" for="has-account">تفعيل حساب الدخول</label>
                    </div>
                </div>
                <div class="card-body" id="account-fields">
                    <div class="row">
                        <div class="mb-4 col-md-4">
                            <x-form.input name="username" label="اسم المستخدم" :value="old('username', $user->username ?? '')" />
                        </div>
                        <div class="mb-4 col-md-4">
                            <x-form.input type="email" name="email" label="البريد" :value="old('email', $user->email ?? '')" />
                        </div>
                        <div class="mb-4 col-md-4">
                            <label class="form-label">نوع المستخدم</label>
                            <select name="user_type" id="user_type" class="form-select">
                                <option value="employee" @selected(old('user_type', $user->user_type ?? 'employee') === 'employee')>موظف</option>
                                <option value="admin" @selected(old('user_type', $user->user_type ?? '') === 'admin')>مدير</option>
                            </select>
                        </div>
                        <div class="mb-4 col-md-4">
                            <x-form.input type="password" name="password" label="كلمة المرور" :required="!($user->exists ?? false)" />
                        </div>
                        <div class="mb-4 col-md-4">
                            <x-form.input type="password" name="confirm_password" label="تأكيد كلمة المرور" />
                        </div>
                        <div class="mb-4 col-md-4">
                            <label class="form-label">الحالة</label>
                            <select name="is_active" class="form-select">
                                <option value="1" @selected(old('is_active', $user->is_active ?? true))>نشط</option>
                                <option value="0" @selected(old('is_active', $user->is_active ?? true) == false)>معطل</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div id="abilities-section" class="card mb-4" @if($initialRecordType === 'person_only') style="display:none" @endif>
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">الصلاحيات</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-label-secondary" id="btn-apply-role-abilities">تطبيق صلاحيات الدور</button>
                        <button type="button" class="btn btn-sm btn-label-warning" id="btn-reset-role-abilities">إعادة ضبط للدور</button>
                    </div>
                </div>
                <div class="card-body">
                    <input type="hidden" name="apply_role_abilities" id="apply-role-abilities-flag" value="0">
                    <input type="hidden" name="reset_role_abilities" id="reset-role-abilities-flag" value="0">
                    @include('dashboard.directory._abilities_matrix', [
                        'selectedAbilities' => $selectedAbilities ?? [],
                        'user' => $user,
                    ])
                </div>
            </div>
        @endif

        <div class="mb-4">
            <a href="{{ route('dashboard.directory.index') }}" class="btn btn-label-secondary">إلغاء</a>
            <button type="submit" class="btn btn-primary">{{ $submitLabel ?? 'حفظ' }}</button>
        </div>
    </form>

    @push('scripts')
        <script src="{{ asset('js/org-cascade.js') }}"></script>
        <script>
            window.directoryFormConfig = {
                roleAbilitiesMap: @json($roleAbilitiesMap),
                roleAbilitiesUrl: @json($roleAbilitiesUrl),
                rolesRequiringDepartment: @json($rolesRequiringDepartment),
                rolesRequiringSection: @json($rolesRequiringSection),
                selectedAbilities: @json($selectedAbilities ?? []),
                departmentsByCenterUrl: @json($departmentsByCenterUrl),
                sectionsByDepartmentUrl: @json($sectionsByDepartmentUrl),
                selectedCenterId: @json($selectedCenterId ?? ''),
                selectedDepartmentId: @json($selectedDepartmentId ?? ''),
                selectedSectionId: @json($selectedSectionId ?? ''),
            };
        </script>
        <script src="{{ asset('js/directory-form.js') }}"></script>
    @endpush
</x-front-layout>
