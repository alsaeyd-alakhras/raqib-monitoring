@include('dashboard.projects._checklist_styles')

@php
    $items = $closureDocItems ?? collect();
    $valueLabels = $valueLabels ?? ['ready' => 'جاهز', 'not_ready' => 'غير جاهز'];
@endphp

@if ($items->isEmpty())
    <p class="text-muted mb-0">لا توجد مستندات إغلاق.</p>
@else
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
                        $status = $current?->coordinator_value ?? 'not_ready';
                        $isLate = $current?->hasAttachment()
                            && $project->planned_end_date
                            && $current->attachment_uploaded_at
                            && $current->attachment_uploaded_at->toDateString() > $project->planned_end_date->toDateString();
                    @endphp
                    <tr>
                        <td class="checklist-col-item">{{ $item->name }}</td>
                        <td class="checklist-col-status text-center">
                            @include('dashboard.projects._checklist_status_badge', [
                                'status' => $status,
                                'valueLabels' => $valueLabels,
                            ])
                        </td>
                        <td class="checklist-col-person text-muted small">{{ $current?->person_name ?: '—' }}</td>
                        <td class="checklist-col-file small text-center">
                            @if ($current?->hasAttachment())
                                <a href="{{ $current->attachmentUrl() }}" target="_blank" rel="noopener">
                                    {{ $current->attachment_original_name ?: 'مرفق' }}
                                </a>
                                @if ($current->attachment_uploaded_at)
                                    <span class="text-muted">({{ $current->attachment_uploaded_at->format('Y-m-d') }})</span>
                                @endif
                                @if ($isLate)
                                    <span class="badge bg-label-warning">متأخر</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
