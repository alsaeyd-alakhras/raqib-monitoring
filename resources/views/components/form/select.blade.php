@props([
    'optionsId' => null,
    'options' => [],
    'name',
    'id' => null,
    'label'=>'',
    'value'=> null,
    'required' => false,
    'searchable' => false,
])
@if ($label)
    <label class="form-label" for="{{$id ?? $name}}">
        {{ $label }}
    </label>
@endif

<select 
    id="{{$id ?? $name}}"
    name="{{$name}}"
    {{$attributes->class([
        'form-select',
        'select2-searchable' => $searchable,
        'is-invalid' => $errors->has($name)
    ])}}>
    <option value="" @selected(old($name, $value) == null || old($name, $value) === '')>إختر القيمة</option>
    @if($optionsId!= null)
        @foreach ($optionsId as $item)
            <option value="{{ $item->id }}" @selected(old($name, $value) == $item->id)>{{ $item->name }}</option>
        @endforeach
    @else
        @foreach ($options as $key => $item)
            @php
                $optionValue = is_int($key) ? $item : $key;
                $optionLabel = $item;
            @endphp
            <option value="{{ $optionValue }}" @selected(old($name, $value) == $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    @endif
</select>

{{-- Validation --}}
@error($name)
    <div class="invalid-feedback">
        {{$message}}
    </div>
@enderror

@if ($searchable)
    @once
        @push('styles')
            <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
            <link rel="stylesheet" href="{{ asset('css/searchable-select.css') }}">
        @endpush
        @push('scripts')
            <script src="{{ asset('assets/vendor/libs/select2/select2.full.min.js') }}"></script>
            <script src="{{ asset('js/searchable-select.js') }}"></script>
        @endpush
    @endonce
@endif
