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
