@php
    $perPage = (int) request('per_page', $defaultPerPage ?? 15);
    $allowed = [15, 25, 50, 100];
    if (! in_array($perPage, $allowed, true)) {
        $perPage = $defaultPerPage ?? 15;
    }
@endphp
<form method="get" class="d-flex align-items-center gap-2 mb-3">
    @foreach (request()->except(['per_page', 'page']) as $key => $value)
        @if (is_array($value))
            @foreach ($value as $subKey => $subValue)
                <input type="hidden" name="{{ $key }}[{{ $subKey }}]" value="{{ $subValue }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach
    <label for="per_page_select" class="form-label mb-0 text-nowrap small text-muted">عدد الصفوف:</label>
    <select name="per_page" id="per_page_select" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        @foreach ($allowed as $option)
            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
        @endforeach
    </select>
</form>
