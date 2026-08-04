@php
    $activityModel = $activity ?? null;
    $attachmentRows = $activityModel?->attachmentsList() ?? [];
    $deleteUrl = $activityModel?->exists
        ? route('dashboard.external-activities.delete-attachment', $activityModel)
        : '';
    $savedForJs = collect($attachmentRows)->map(function (array $row) use ($activityModel) {
        $url = ($row['type'] ?? '') === 'url'
            ? ($row['url'] ?? null)
            : (! empty($row['path']) ? asset('storage/' . ltrim($row['path'], '/')) : null);

        return [
            'id' => (string) ($row['id'] ?? ''),
            'type' => (string) ($row['type'] ?? 'file'),
            'url' => $url,
            'label' => $activityModel ? $activityModel->attachmentRowLabel($row) : ($row['original_name'] ?? 'مرفق'),
        ];
    })->values()->all();
@endphp
<div
    class="activity-attachment-field checklist-file-field"
    data-activity-attachment-field
    data-delete-url="{{ $deleteUrl }}"
    data-saved-attachments='@json($savedForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)'
>
    <input
        type="file"
        name="activity_attachments[]"
        id="activity-attachments-input"
        class="d-none activity-file-input checklist-file-input"
        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
        multiple
    >
    <div id="activity-pending-urls-container"></div>
    <div class="checklist-file-actions d-flex flex-wrap align-items-center gap-1">
        <div class="activity-attachment-list checklist-file-attachment-list d-flex flex-wrap align-items-center gap-1">
            @if ($activityModel)
                @foreach ($attachmentRows as $row)
                    @php
                        $isExternal = ($row['type'] ?? '') === 'url';
                        $url = $isExternal
                            ? ($row['url'] ?? null)
                            : (! empty($row['path']) ? asset('storage/' . ltrim($row['path'], '/')) : null);
                        $label = $activityModel->attachmentRowLabel($row);
                        $rowId = (string) ($row['id'] ?? '');
                    @endphp
                    @if ($url)
                        <span class="checklist-file-chip d-inline-flex align-items-center gap-1 border rounded px-1" data-saved-id="{{ $rowId }}">
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-text-primary checklist-file-view-btn" title="عرض">
                                <i class="ti {{ $isExternal ? 'ti-external-link' : 'ti-eye' }}"></i>
                            </a>
                            <span class="checklist-file-pending-name text-truncate small" style="max-width:7rem" title="{{ $label }}">{{ $label }}</span>
                            <button type="button" class="btn btn-sm btn-icon btn-text-danger activity-file-delete-btn checklist-file-delete-btn" data-attachment-id="{{ $rowId }}" title="حذف" aria-label="حذف">
                                <i class="ti ti-trash"></i>
                            </button>
                        </span>
                    @endif
                @endforeach
            @endif
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary activity-file-upload-btn checklist-file-upload-btn" title="إضافة مرفق" aria-label="إضافة مرفق">
            <i class="ti ti-upload"></i> إضافة مرفق
        </button>
    </div>
    <div class="form-text">PDF، Word، Excel، صور — أو رابط خارجي. يمكن إضافة عدة مرفقات.</div>
</div>
