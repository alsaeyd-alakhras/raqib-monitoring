@if (!empty($pairs))
    <div class="section-block section-block-first">
        @include('reports.projects.partials._section_head', ['num' => $num, 'title' => $title])
        @include('reports.projects.partials._row_pair', $pairs[0])
    </div>

    @foreach (array_slice($pairs, 1) as $pair)
        <div class="section-block">
            @include('reports.projects.partials._row_pair', $pair)
        </div>
    @endforeach
@else
    <div class="section-block section-block-first">
        @include('reports.projects.partials._section_head', ['num' => $num, 'title' => $title])
        @if (!empty($afterHead))
            {!! $afterHead !!}
        @endif
    </div>
@endif
