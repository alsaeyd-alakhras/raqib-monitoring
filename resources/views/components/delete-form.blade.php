@props([
    'action',
    'itemLabel' => 'هذا السجل',
    'confirmMessage' => null,
])

<form
    {{ $attributes->merge(['method' => 'post', 'class' => 'd-inline']) }}
    onsubmit="return confirm(@js($confirmMessage ?? "هل أنت متأكد من حذف {$itemLabel}؟ لا يمكن التراجع."));"
>
    @csrf
    @method('delete')
    {{ $slot }}
</form>
