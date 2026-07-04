@include('dashboard.projects._checklist_styles')

@php
    $valueField = $valueField ?? 'coordinator_value';
    $inputClass = $inputClass ?? '';
@endphp

<div class="checklist-groups-grid">
    @foreach ($groups as $group)
        <div class="checklist-group-card">
            <h6 class="checklist-group-title">{{ $group->name }}</h6>
            <div class="checklist-table-wrap">
                <table class="table table-sm table-bordered checklist-compact-table">
                    <thead>
                        <tr>
                            <th class="checklist-col-item">البند</th>
                            <th class="checklist-col-status">الحالة</th>
                            @if ($group->items->contains(fn ($item) => $item->has_person_field))
                                <th class="checklist-col-person">الشخص</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group->items as $item)
                            @php $current = $values->get($item->id); @endphp
                            <tr>
                                <td class="checklist-col-item align-middle">{{ $item->name }}</td>
                                <td class="checklist-col-status">
                                    <select name="checklist[{{ $item->id }}][value]" class="form-select form-select-sm {{ $inputClass }}">
                                        <option value="">—</option>
                                        @foreach ($valueLabels as $key => $label)
                                            <option value="{{ $key }}" @selected($current?->{$valueField} === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                @if ($group->items->contains(fn ($i) => $i->has_person_field))
                                    <td class="checklist-col-person">
                                        @if ($item->has_person_field)
                                            <input
                                                type="text"
                                                name="checklist[{{ $item->id }}][person_name]"
                                                class="form-control form-control-sm {{ $inputClass }}"
                                                placeholder="اسم الشخص"
                                                value="{{ $current?->person_name }}"
                                            >
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
