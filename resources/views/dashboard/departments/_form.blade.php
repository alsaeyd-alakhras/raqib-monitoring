<div class="row g-3">
    <div class="col-md-6">
        <x-form.select
            name="center_id"
            label="المركز"
            :optionsId="$centers"
            :value="old('center_id', $department->center_id ?? '')"
            required
        />
    </div>

    <div class="col-md-6">
        <x-form.input
            name="name"
            label="اسم القسم"
            :value="old('name', $department->name ?? '')"
            required
        />
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('dashboard.departments.index') }}" class="btn btn-outline-secondary">إلغاء</a>
    <button type="submit" class="btn btn-primary">حفظ</button>
</div>
