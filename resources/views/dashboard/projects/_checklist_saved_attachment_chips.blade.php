@php
    $rows = $rows ?? ($current?->attachmentsList() ?? []);
@endphp
@foreach ($rows as $row)
    @php
        $isExternal = ($row['type'] ?? '') === 'url';
        $url = $current instanceof \App\Models\ProjectChecklistValue
            ? $current->attachmentRowUrl($row)
            : (($isExternal ? ($row['url'] ?? null) : (! empty($row['path']) ? asset('storage/' . ltrim($row['path'], '/')) : null)));
        $label = $current instanceof \App\Models\ProjectChecklistValue
            ? $current->attachmentRowLabel($row)
            : ($row['original_name'] ?? 'مرفق');
        $rowId = (string) ($row['id'] ?? '');
    @endphp
    @if ($url)
        <span
            class="checklist-file-chip d-inline-flex align-items-center gap-1 border rounded px-1"
            data-saved-id="{{ $rowId }}"
        >
            <a
                href="{{ $url }}"
                target="_blank"
                rel="noopener"
                class="btn btn-sm btn-icon btn-text-primary checklist-file-view-btn"
                title="عرض"
            >
                <i class="ti {{ $isExternal ? 'ti-external-link' : 'ti-eye' }}"></i>
            </a>
            <span
                class="checklist-file-pending-name text-truncate small"
                style="max-width:7rem"
                title="{{ $label }}"
            >{{ $label }}</span>
            <button
                type="button"
                class="btn btn-sm btn-icon btn-text-danger checklist-file-delete-btn"
                data-attachment-id="{{ $rowId }}"
                title="حذف"
                aria-label="حذف"
            >
                <i class="ti ti-trash"></i>
            </button>
        </span>
    @endif
@endforeach
