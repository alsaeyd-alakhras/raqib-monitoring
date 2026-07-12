@php
    $prefix = $prefix ?? 'checklist';
    $valueField = $valueField ?? 'coordinator_value';
    $showFileColumn = $showFileColumn ?? ($prefix === 'checklist' && $valueField === 'coordinator_value');
    $inputClass = $inputClass ?? '';
    $current = $values->get($item->id);
    $selectedValue = old("{$prefix}.{$item->id}.value", $current?->{$valueField} ?? 'not_ready');
    $personName = old("{$prefix}.{$item->id}.person_name", $current?->person_name);
    $closureValueLabels = ['ready' => 'جاهز', 'not_ready' => 'غير جاهز'];
    $itemValueLabels = ($showFileColumn && $item->has_file_field) ? $closureValueLabels : $valueLabels;
@endphp
<tr @if ($item->has_file_field) data-has-file-field="1" @endif>
    <td class="checklist-col-item align-middle">{{ $item->name }}</td>
    <td class="checklist-col-status">
        <select
            name="{{ $prefix }}[{{ $item->id }}][value]"
            class="form-select form-select-sm {{ $inputClass }} checklist-status-select"
            required
        >
            @foreach ($itemValueLabels as $key => $label)
                <option value="{{ $key }}" @selected($selectedValue === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    @if ($group->items->contains(fn ($i) => $i->has_person_field))
        <td class="checklist-col-person">
            @if ($item->has_person_field)
                <input
                    type="text"
                    name="{{ $prefix }}[{{ $item->id }}][person_name]"
                    class="form-control form-control-sm {{ $inputClass }} checklist-person-input"
                    placeholder="اسم الشخص"
                    value="{{ $personName }}"
                >
            @else
                <span class="text-muted">—</span>
            @endif
        </td>
    @endif
    @if ($showFileColumn && $group->items->contains(fn ($i) => $i->has_file_field))
        <td class="checklist-col-file">
            @if ($item->has_file_field)
                @include('dashboard.projects._checklist_file_field', [
                    'prefix' => $prefix,
                    'item' => $item,
                    'current' => $current,
                    'project' => $project,
                    'inputClass' => $inputClass,
                ])
            @else
                <span class="text-muted">—</span>
            @endif
        </td>
    @endif
</tr>
