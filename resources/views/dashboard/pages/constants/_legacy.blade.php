@php
    $advanceKeys = collect($legacyFields)->filter(fn ($field) => ($field['group'] ?? '') === 'advance')->keys();
    $terminationKeys = collect($legacyFields)->filter(fn ($field) => ($field['group'] ?? '') === 'termination')->keys();
    $healthKeys = collect($legacyFields)->filter(fn ($field) => ($field['group'] ?? '') === 'health')->keys();
@endphp

<div class="alert alert-secondary">
    <strong>ثوابت إدارية قديمة</strong> — هذه القيم من نظام سابق ولا ترتبط مباشرةً بمشاريع الرقابة أو النشاطات الرقابية.
</div>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">مبالغ السلفة</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach ($advanceKeys as $key)
                @php $field = $legacyFields[$key]; @endphp
                <div class="col-md-6">
                    <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>
                    <div class="input-group">
                        <input
                            type="number"
                            id="{{ $key }}"
                            name="{{ $key }}"
                            class="form-control"
                            min="0"
                            value="{{ old($key, $legacyValues[$key] ?? 0) }}"
                        >
                        <span class="input-group-text">{{ $field['suffix'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">نسب نهاية الخدمة</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach ($terminationKeys as $key)
                @php $field = $legacyFields[$key]; @endphp
                <div class="col-md-6">
                    <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>
                    <div class="input-group">
                        <input
                            type="number"
                            id="{{ $key }}"
                            name="{{ $key }}"
                            class="form-control"
                            min="0"
                            value="{{ old($key, $legacyValues[$key] ?? 0) }}"
                        >
                        <span class="input-group-text">{{ $field['suffix'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">رواتب الصحة المثبتين</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach ($healthKeys as $key)
                @php $field = $legacyFields[$key]; @endphp
                <div class="col-md-6">
                    <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>
                    <div class="input-group">
                        <input
                            type="number"
                            id="{{ $key }}"
                            name="{{ $key }}"
                            class="form-control"
                            min="0"
                            value="{{ old($key, $legacyValues[$key] ?? 0) }}"
                        >
                        <span class="input-group-text">{{ $field['suffix'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
