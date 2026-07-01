<div class="row g-3">
    <div class="col-md-6">
        @php
            $departmentOptions = $departments->map(fn ($d) => (object) [
                'id' => $d->id,
                'name' => $d->name . ($d->center ? ' - ' . $d->center->name : ''),
            ]);
        @endphp
        <x-form.select
            name="department_id"
            label="القسم"
            :optionsId="$departmentOptions"
            :value="old('department_id', $section->department_id ?? '')"
            required
        />
    </div>

    <div class="col-md-6">
        <x-form.input
            name="name"
            label="اسم الشعبة"
            :value="old('name', $section->name ?? '')"
            required
        />
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('dashboard.sections.index') }}" class="btn btn-outline-secondary">إلغاء</a>
    <button type="submit" class="btn btn-primary">حفظ</button>
</div>
