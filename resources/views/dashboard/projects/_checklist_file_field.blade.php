@php
    $prefix = $prefix ?? 'checklist';
    $inputClass = $inputClass ?? '';
    $current = $current ?? null;
    $attachmentRows = $current?->attachmentsList() ?? [];
    $hasAttachment = $attachmentRows !== [];
    $isExternalUrl = $hasAttachment && count($attachmentRows) === 1 && ($attachmentRows[0]['type'] ?? '') === 'url';
    $attachmentType = old("{$prefix}.{$item->id}.attachment_type", $current?->attachment_type ?? 'file');
    $attachmentUrlValue = old("{$prefix}.{$item->id}.attachment_url", $current?->attachment_url ?? '');
    $fieldName = "{$prefix}[{$item->id}][attachments][]";
    $typeFieldName = "{$prefix}[{$item->id}][attachment_type]";
    $urlFieldName = "{$prefix}[{$item->id}][attachment_url]";
    $inputId = 'checklist-file-' . $prefix . '-' . $item->id;
    $typeInputId = 'checklist-file-type-' . $prefix . '-' . $item->id;
    $urlInputId = 'checklist-file-url-' . $prefix . '-' . $item->id;
    $showLateBadge = ($showLateBadge ?? false)
        && $hasAttachment
        && ($project->planned_end_date ?? null)
        && $current?->attachment_uploaded_at
        && $current->attachment_uploaded_at->toDateString() > $project->planned_end_date->toDateString();
    $deleteUrl = ($project->exists ?? false)
        ? route('dashboard.projects.delete-checklist-attachment', $project)
        : '';
    $savedForJs = collect($attachmentRows)->map(function (array $row) use ($current) {
        $url = ($row['type'] ?? '') === 'url'
            ? ($row['url'] ?? null)
            : (! empty($row['path']) ? asset('storage/' . ltrim($row['path'], '/')) : null);
        $label = $current instanceof \App\Models\ProjectChecklistValue
            ? $current->attachmentRowLabel($row)
            : ($row['original_name'] ?? 'مرفق');

        return [
            'id' => (string) ($row['id'] ?? ''),
            'type' => (string) ($row['type'] ?? 'file'),
            'url' => $url,
            'label' => $label,
        ];
    })->values()->all();
@endphp
<div
    class="checklist-file-field"
    data-closure-file-field
    data-has-attachment="{{ $hasAttachment ? '1' : '0' }}"
    data-attachment-type="{{ $hasAttachment ? ($isExternalUrl ? 'url' : 'file') : '' }}"
    data-item-id="{{ $item->id }}"
    data-delete-url="{{ $deleteUrl }}"
    data-saved-attachments='@json($savedForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)'
>
    <input
        type="file"
        name="{{ $fieldName }}"
        id="{{ $inputId }}"
        class="d-none checklist-file-input {{ $inputClass }}"
        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
        multiple
    >
    <input
        type="hidden"
        name="{{ $typeFieldName }}"
        id="{{ $typeInputId }}"
        class="checklist-attachment-type-input"
        value="{{ $attachmentType }}"
    >
    <input
        type="hidden"
        name="{{ $urlFieldName }}"
        id="{{ $urlInputId }}"
        class="checklist-attachment-url-input"
        value="{{ $attachmentUrlValue }}"
    >
    <div class="checklist-file-actions d-flex flex-wrap align-items-center gap-1">
        <div class="checklist-file-attachment-list d-flex flex-wrap align-items-center gap-1">
            @include('dashboard.projects._checklist_saved_attachment_chips', [
                'rows' => $attachmentRows,
                'current' => $current,
            ])
        </div>
        <button
            type="button"
            class="btn btn-sm btn-icon btn-text-secondary checklist-file-upload-btn"
            title="إضافة مرفق"
            aria-label="إضافة مرفق"
        >
            <i class="ti ti-upload"></i>
        </button>
    </div>
    @if ($showLateBadge)
        <span class="badge bg-label-warning checklist-file-late-badge mt-1">متأخر</span>
    @endif
</div>
