@include('dashboard.projects._checklist_styles')

@php
    $closureValueLabels = ['ready' => 'جاهز', 'not_ready' => 'غير جاهز'];
@endphp

<div class="checklist-groups-grid" data-checklist-readiness>
    @foreach ($checklistGroups as $group)
        @php
            $groupHasFileField = $group->items->contains(fn ($item) => $item->has_file_field);
        @endphp
        <div class="checklist-group-card{{ $groupHasFileField ? ' checklist-group-card--with-files' : ' checklist-group-card--compact' }}">
            <h6 class="checklist-group-title d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span>{{ $group->name }}</span>
                <span class="badge bg-label-primary checklist-group-pct">—</span>
            </h6>
            <div class="checklist-table-wrap">
                <table class="table table-sm table-bordered checklist-compact-table coordinator-checklist-table{{ $groupHasFileField ? ' checklist-compact-table--with-files' : '' }}">
                    <thead>
                        <tr>
                            <th class="checklist-col-item">البند</th>
                            <th class="checklist-col-status">الحالة</th>
                            @if ($group->items->contains(fn ($item) => $item->has_person_field))
                                <th class="checklist-col-person">الشخص</th>
                            @endif
                            @if ($group->items->contains(fn ($item) => $item->has_file_field))
                                <th class="checklist-col-file">المرفق</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group->items as $item)
                            @include('dashboard.projects._checklist_row_edit', [
                                'item' => $item,
                                'group' => $group,
                                'values' => $checklistValues,
                                'valueLabels' => $valueLabels,
                                'prefix' => 'checklist',
                                'inputClass' => 'coordinator-checklist-input',
                                'valueField' => 'coordinator_value',
                                'showFileColumn' => true,
                                'project' => $project,
                            ])
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
