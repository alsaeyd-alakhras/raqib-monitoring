@php
    $prefix = $prefix ?? 'checklist';
    $inputClass = $inputClass ?? '';
    $current = $current ?? null;
    $hasAttachment = $current?->hasAttachment() ?? false;
    $attachmentUrl = $hasAttachment ? $current->attachmentUrl() : null;
    $attachmentName = $current?->attachment_original_name ?: 'مرفق';
    $fieldName = "{$prefix}[{$item->id}][attachment]";
    $inputId = 'checklist-file-' . $prefix . '-' . $item->id;
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
    <div class="checklist-file-actions">
        @if ($hasAttachment)
            <a
                href="{{ $attachmentUrl }}"
                target="_blank"
                rel="noopener"
                class="btn btn-sm btn-icon btn-text-primary checklist-file-view-btn"
                title="عرض المرفق"
            >
                <i class="ti ti-eye"></i>
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
                title="رفع ملف"
                aria-label="رفع ملف"
            >
                <i class="ti ti-upload"></i>
            </button>
        @endif
    </div>
    @if ($showLateBadge)
        <span class="badge bg-label-warning checklist-file-late-badge mt-1">متأخر</span>
    @endif
</div>
