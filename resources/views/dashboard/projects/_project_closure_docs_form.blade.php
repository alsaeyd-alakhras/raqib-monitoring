@include('dashboard.projects._checklist_styles')

@php
    $items = $closureDocItems ?? collect();
    $projectDocValues = $projectDocValues ?? collect();
@endphp

@if ($items->isEmpty())
    <p class="text-muted mb-0">لا توجد بنود مستندات مشروع مفعّلة.</p>
@else
    <div class="checklist-table-wrap">
        <table class="table table-sm table-bordered checklist-compact-table checklist-compact-table--with-files">
            <thead>
                <tr>
                    <th class="checklist-col-item">البند</th>
                    <th class="checklist-col-file">المرفق</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    @php
                        $current = $projectDocValues->get($item->id);
                    @endphp
                    <tr data-has-file-field="1">
                        <td class="checklist-col-item align-middle">{{ $item->name }}</td>
                        <td class="checklist-col-file">
                            @include('dashboard.projects._checklist_file_field', [
                                'prefix' => 'project_docs',
                                'item' => $item,
                                'current' => $current,
                                'project' => $project,
                                'scopeProjectLevel' => true,
                                'showLateBadge' => false,
                            ])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="form-text mb-0">يمكن رفع ملفات أو إضافة روابط خارجية. الحد الأقصى 10 ميغابايت لكل ملف.</div>
@endif
