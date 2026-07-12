@php
    $items = is_array($items ?? null) ? array_values($items) : [];
    if ($items === []) {
        $items = [''];
    }
@endphp

<div class="card mb-4 constant-editor-card" data-editor-type="string-list" data-constant-key="{{ $key }}">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">{{ $meta['label'] }}</h6>
        <button type="button" class="btn btn-sm btn-outline-primary btn-add-string-row">
            <i class="fa-solid fa-plus me-1"></i> إضافة
        </button>
    </div>
    <div class="card-body">
        <div class="constant-string-rows">
            @foreach ($items as $index => $item)
                <div class="input-group mb-2 constant-string-row">
                    <input
                        type="text"
                        name="constants[{{ $key }}][]"
                        class="form-control"
                        value="{{ $item }}"
                        placeholder="أدخل قيمة..."
                    >
                    <button type="button" class="btn btn-outline-danger btn-remove-row" @if(count($items) === 1 && $index === 0) disabled @endif>
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
</div>
