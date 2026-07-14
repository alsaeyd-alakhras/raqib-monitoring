@php
    $prefix = $prefix ?? 'checklist';
    $inputClass = $inputClass ?? '';
    $current = $current ?? null;
    $hasAttachment = $current?->hasAttachment() ?? false;
    $isExternalUrl = $current?->isExternalUrl() ?? false;
    $attachmentUrl = $hasAttachment ? $current->attachmentUrl() : null;
    $attachmentName = $current?->attachmentDisplayLabel() ?: 'مرفق';
    $attachmentType = old("{$prefix}.{$item->id}.attachment_type", $current?->attachment_type ?? 'file');
    $attachmentUrlValue = old("{$prefix}.{$item->id}.attachment_url", $current?->attachment_url ?? '');
    $fieldName = "{$prefix}[{$item->id}][attachment]";
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
@endphp
<div
    class="checklist-file-field"
    data-closure-file-field
    data-has-attachment="{{ $hasAttachment ? '1' : '0' }}"
    data-attachment-type="{{ $hasAttachment ? ($isExternalUrl ? 'url' : 'file') : '' }}"
    data-item-id="{{ $item->id }}"
    data-delete-url="{{ $deleteUrl }}"
    data-attachment-name="{{ $attachmentName }}"
    data-attachment-url="{{ $attachmentUrl }}"
>
    <input
        type="file"
        name="{{ $fieldName }}"
        id="{{ $inputId }}"
        class="d-none checklist-file-input {{ $inputClass }}"
        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
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
    <div class="checklist-file-actions">
        @if ($hasAttachment)
            <a
                href="{{ $attachmentUrl }}"
                target="_blank"
                rel="noopener"
                class="btn btn-sm btn-icon btn-text-primary checklist-file-view-btn"
                title="عرض المرفق"
            >
                <i class="ti {{ $isExternalUrl ? 'ti-external-link' : 'ti-eye' }}"></i>
            </a>
            <button
                type="button"
                class="btn btn-sm btn-icon btn-text-danger checklist-file-delete-btn"
                title="حذف المرفق"
                aria-label="حذف المرفق"
            >
                <i class="ti ti-trash"></i>
            </button>
        @else
            <button
                type="button"
                class="btn btn-sm btn-icon btn-text-secondary checklist-file-upload-btn"
                title="إضافة مرفق"
                aria-label="إضافة مرفق"
            >
                <i class="ti ti-upload"></i>
            </button>
        @endif
    </div>
    @if ($showLateBadge)
        <span class="badge bg-label-warning checklist-file-late-badge mt-1">متأخر</span>
    @endif
</div>
