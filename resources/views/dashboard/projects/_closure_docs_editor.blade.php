@include('dashboard.projects._checklist_styles')

@php
    $items = $closureDocItems ?? collect();
    $valueLabels = $valueLabels ?? ['ready' => 'جاهز', 'not_ready' => 'غير جاهز'];
@endphp

@if ($items->isEmpty())
    <p class="text-muted mb-0">لا توجد بنود مستندات إغلاق مفعّلة.</p>
@else
    <form
        action="{{ route('dashboard.projects.fill-closure-docs', $project) }}"
        method="post"
        enctype="multipart/form-data"
        data-closure-docs-form
    >
        @csrf
        @if ($requiresFillOnBehalfConfirm ?? false)
            <input type="hidden" name="fill_on_behalf" value="1">
        @endif
        <div class="checklist-table-wrap">
            <table class="table table-sm table-bordered checklist-compact-table checklist-compact-table--with-files">
                <thead>
                    <tr>
                        <th class="checklist-col-item">البند</th>
                        <th class="checklist-col-status">الحالة</th>
                        <th class="checklist-col-person">الشخص</th>
                        <th class="checklist-col-file">المرفق</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php
                            $current = $values->get($item->id);
                            $selectedValue = old("closure_docs.{$item->id}.value", $current?->coordinator_value ?? 'not_ready');
                            $personName = old("closure_docs.{$item->id}.person_name", $current?->person_name);
                        @endphp
                        <tr data-has-file-field="1">
                            <td class="checklist-col-item align-middle">{{ $item->name }}</td>
                            <td class="checklist-col-status">
                                <select
                                    name="closure_docs[{{ $item->id }}][value]"
                                    class="form-select form-select-sm checklist-status-select"
                                    data-default-value="not_ready"
                                    required
                                >
                                    @foreach ($valueLabels as $key => $label)
                                        <option value="{{ $key }}" @selected($selectedValue === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="checklist-col-person">
                                <input
                                    type="text"
                                    name="closure_docs[{{ $item->id }}][person_name]"
                                    class="form-control form-control-sm checklist-person-input"
                                    placeholder="اسم الشخص"
                                    value="{{ $personName }}"
                                >
                            </td>
                            <td class="checklist-col-file">
                                @include('dashboard.projects._checklist_file_field', [
                                    'prefix' => 'closure_docs',
                                    'item' => $item,
                                    'current' => $current,
                                    'project' => $project,
                                    'showLateBadge' => true,
                                ])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($project->planned_end_date)
            <div class="form-text mb-3">
                تاريخ نهاية التنفيذ المخطط: <strong>{{ $project->planned_end_date->format('Y-m-d') }}</strong>
                — الرفع بعد هذا التاريخ يُخصم من نسبة الجاهزية (معامل {{ ($closureLateScore ?? 0.5) * 100 }}%).
            </div>
        @endif
        <button type="submit" class="btn btn-primary">حفظ مستندات الإغلاق</button>
    </form>
@endif
