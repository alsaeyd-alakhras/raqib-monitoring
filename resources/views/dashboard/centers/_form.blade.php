<div class="row g-3">
    <div class="col-12">
        <x-form.input
            name="name"
            label="اسم المركز"
            :value="old('name', $center->name ?? '')"
            required
        />
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('dashboard.centers.index') }}" class="btn btn-outline-secondary">إلغاء</a>
    <button type="submit" class="btn btn-primary">حفظ</button>
</div>
