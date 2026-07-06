{{--
    Section wrapper: keeps section heading with first content block on the same page.
    $pairs: array of ['left' => [...], 'right' => ...|null]
    $afterHead: optional HTML rendered right after heading (when no pairs)
--}}
@if (!empty($pairs))
    <div class="section-block section-block-first">
        @include('reports.monitoring-activities.partials._section_head', ['num' => $num, 'title' => $title])
        @include('reports.monitoring-activities.partials._row_pair', $pairs[0])
    </div>

    @foreach (array_slice($pairs, 1) as $pair)
        <div class="section-block">
            @include('reports.monitoring-activities.partials._row_pair', $pair)
        </div>
    @endforeach
@else
    <div class="section-block section-block-first">
        @include('reports.monitoring-activities.partials._section_head', ['num' => $num, 'title' => $title])
        @if (!empty($afterHead))
            {!! $afterHead !!}
        @endif
    </div>
@endif
