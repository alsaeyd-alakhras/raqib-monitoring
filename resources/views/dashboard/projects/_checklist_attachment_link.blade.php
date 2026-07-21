@php
    $current = $current ?? null;
    $compact = $compact ?? false;
    $rows = $current?->attachmentsList() ?? [];
@endphp
@if ($rows !== [])
    <div class="checklist-attachment-links d-inline-flex flex-wrap align-items-center gap-1">
        @foreach ($rows as $row)
            @php
                $isExternal = ($row['type'] ?? '') === 'url';
                $url = $current->attachmentRowUrl($row);
                $tooltip = $current->attachmentRowLabel($row);
                if (! $isExternal && ! empty($row['uploaded_at'])) {
                    try {
                        $tooltip .= ' — ' . \Carbon\Carbon::parse($row['uploaded_at'])->format('Y-m-d');
                    } catch (\Throwable) {
                        // ignore
                    }
                }
            @endphp
            @if ($url)
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
                @unless ($compact)
                    <span class="text-muted small text-truncate" style="max-width: 8rem;" title="{{ $tooltip }}">{{ $tooltip }}</span>
                @endunless
            @endif
        @endforeach
    </div>
@else
    <span class="text-muted">—</span>
@endif
