@extends('pdf.layout')

@section('content')
    @foreach($sections as $s)
        <div style="page-break-inside:auto; margin-bottom:8mm;">
            <table class="sec-head">
                <tr>
                    <td width="18mm"><span class="sec-badge">{{ $s['num'] }}</span></td>
                    <td class="sec-title">{{ $s['title'] }}</td>
                </tr>
            </table>
            {!! $s['body'] !!}
        </div>
    @endforeach
@endsection
