@php
    $status = $status ?? 'not_ready';
    $statusClass = match ($status) {
        'ready' => 'checklist-st-ready',
        'partial' => 'checklist-st-partial',
        'not_ready' => 'checklist-st-not-ready',
        'not_required' => 'checklist-st-not-required',
        default => 'checklist-st-not-ready',
    };
    $label = ($valueLabels ?? [])[$status] ?? $status;
@endphp
<span class="checklist-st-badge {{ $statusClass }}">{{ $label }}</span>
