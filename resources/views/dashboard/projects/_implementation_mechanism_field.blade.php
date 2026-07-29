<div class="mb-4">
    <label class="form-label" for="{{ $fieldId ?? 'implementation_mechanism' }}">آلية تنفيذ المشروع</label>
    <textarea
        name="implementation_mechanism"
        id="{{ $fieldId ?? 'implementation_mechanism' }}"
        class="form-control @error('implementation_mechanism') is-invalid @enderror"
        rows="1"
        placeholder="—"
    >{{ old('implementation_mechanism', $implementationMechanism ?? '') }}</textarea>
    @error('implementation_mechanism')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
