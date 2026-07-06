@php
    $shape = $meta['value_shape'] ?? '';
@endphp

@if (str_starts_with($shape, 'list<string>'))
    @include('dashboard.pages.constants._string_list', [
        'key' => $key,
        'meta' => $meta,
        'items' => $decodedValues[$key] ?? [],
    ])
@elseif (str_contains($shape, '{min:int,label:string}'))
    @include('dashboard.pages.constants._kpi_scale', [
        'key' => $key,
        'meta' => $meta,
        'rows' => $decodedValues[$key] ?? [],
    ])
@elseif (str_contains($shape, '{value:int,label:string}'))
    @include('dashboard.pages.constants._value_scale', [
        'key' => $key,
        'meta' => $meta,
        'rows' => $decodedValues[$key] ?? [],
    ])
@endif
