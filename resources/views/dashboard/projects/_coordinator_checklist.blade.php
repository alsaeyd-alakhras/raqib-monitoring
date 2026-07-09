@include('dashboard.projects._checklist_styles')

<div class="checklist-groups-grid" data-checklist-readiness>
    @foreach ($checklistGroups as $group)
        <div class="checklist-group-card">
            <h6 class="checklist-group-title d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span>{{ $group->name }}</span>
                <span class="badge bg-label-primary checklist-group-pct">—</span>
            </h6>
            <div class="checklist-table-wrap">
                <table class="table table-sm table-bordered checklist-compact-table coordinator-checklist-table">
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
                            @php
                                $current = $checklistValues->get($item->id);
                                $selectedValue = old("checklist.{$item->id}.value", $current?->coordinator_value ?? 'not_ready');
                                $personName = old("checklist.{$item->id}.person_name", $current?->person_name);
                            @endphp
                            <tr>
                                <td class="checklist-col-item align-middle">{{ $item->name }}</td>
                                <td class="checklist-col-status">
                                    <select
                                        name="checklist[{{ $item->id }}][value]"
                                        class="form-select form-select-sm coordinator-checklist-input"
                                        required
                                    >
                                        @foreach ($valueLabels as $key => $label)
                                            <option value="{{ $key }}" @selected($selectedValue === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                @if ($group->items->contains(fn ($i) => $i->has_person_field))
                                    <td class="checklist-col-person">
                                        @if ($item->has_person_field)
                                            <input
                                                type="text"
                                                name="checklist[{{ $item->id }}][person_name]"
                                                class="form-control form-control-sm coordinator-checklist-input"
                                                placeholder="اسم الشخص"
                                                value="{{ $personName }}"
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
