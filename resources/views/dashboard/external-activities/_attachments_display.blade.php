@php
    /** @var \App\Models\MonitoringActivity $activity */
    $rows = $activity->attachmentsList();
@endphp
@if ($rows !== [])
    <div class="activity-attachments-display">
        @php
            $imageRows = [];
            $fileRows = [];
            foreach ($rows as $row) {
                $url = $activity->attachmentRowUrl($row);
                if (! $url) {
                    continue;
                }
                if ($activity->attachmentIsImage($row)) {
                    $imageRows[] = ['row' => $row, 'url' => $url];
                } else {
                    $fileRows[] = ['row' => $row, 'url' => $url];
                }
            }
        @endphp

        @if ($imageRows !== [])
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach ($imageRows as $item)
                    @php
                        $label = $activity->attachmentRowLabel($item['row']);
                        $isExternal = ($item['row']['type'] ?? '') === 'url';
                    @endphp
                    <div class="activity-attachment-thumb border rounded p-1 bg-light">
                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener" title="{{ $label }} — فتح بالحجم الكامل">
                            <img
                                src="{{ $item['url'] }}"
                                alt="{{ $label }}"
                                class="d-block rounded"
                                style="max-height:120px; max-width:180px; object-fit:contain;"
                                loading="lazy"
                            >
                        </a>
                        <div class="small text-muted text-truncate mt-1" style="max-width:180px" title="{{ $label }}">
                            @if ($isExternal)
                                <i class="ti ti-external-link me-1"></i>
                            @endif
                            {{ $label }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($fileRows !== [])
            <div class="d-flex flex-wrap gap-1">
                @foreach ($fileRows as $item)
                    @php
                        $isExternal = ($item['row']['type'] ?? '') === 'url';
                        $label = $activity->attachmentRowLabel($item['row']);
                    @endphp
                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="badge bg-label-primary text-decoration-none">
                        <i class="ti {{ $isExternal ? 'ti-external-link' : 'ti-paperclip' }} me-1"></i>{{ $label }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
