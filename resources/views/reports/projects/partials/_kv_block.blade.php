<div class="kv-card">
    <div class="block-title">{{ $title }}</div>
    <div class="block-title-gap">&nbsp;</div>
    <table class="kv" width="100%">
        <tbody>
            @foreach ($rows as $label => $value)
                <tr>
                    <th scope="row">{{ $label }}</th>
                    <td>{!! $value !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
