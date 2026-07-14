@php
    $current = $current ?? null;
    $isExternal = $current?->isExternalUrl() ?? false;
    $url = $current?->attachmentUrl();
    $tooltip = $isExternal
        ? ($current?->attachment_url ?: 'رابط خارجي')
        : ($current?->attachment_original_name ?: 'مرفق');
    if ($current?->attachment_uploaded_at) {
        $tooltip .= ' — ' . $current->attachment_uploaded_at->format('Y-m-d');
    }
@endphp
@if ($current?->hasAttachment() && $url)
    <a
        href="{{ $url }}"
        target="_blank"
        rel="noopener"
        class="checklist-attachment-icon-link"
        data-tooltip="{{ $tooltip }}"
        aria-label="{{ $tooltip }}"
        title="{{ $tooltip }}"
    >
        <i class="ti {{ $isExternal ? 'ti-external-link' : 'ti-file' }}"></i>
    </a>
@else
    <span class="text-muted">—</span>
@endif
