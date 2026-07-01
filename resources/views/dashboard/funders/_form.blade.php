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
        <h5 class="mb-0">بيانات الجهة الممولة</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-6">
                <x-form.input
                    name="name"
                    label="الاسم"
                    :value="$funder->name ?? ''"
                    required
                />
            </div>
        </div>

        <div class="mt-2">
            <button type="submit" class="btn btn-primary me-3">
                حفظ
            </button>
            <a href="{{ route('dashboard.funders.index') }}" class="btn btn-label-secondary">
                إلغاء
            </a>
        </div>
    </div>
</div>
