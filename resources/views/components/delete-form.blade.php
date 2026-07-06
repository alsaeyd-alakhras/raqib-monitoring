@props([
    'action',
    'itemLabel' => 'هذا السجل',
    'confirmMessage' => null,
])

<form
    {{ $attributes->merge(['method' => 'post', 'class' => 'd-inline']) }}
    action="{{ $action }}"
    data-confirm="{{ $confirmMessage ?? "هل أنت متأكد من حذف {$itemLabel}؟ لا يمكن التراجع." }}"
    data-confirm-title="تأكيد الحذف"
    data-confirm-variant="danger"
>
    @csrf
    @method('delete')
    {{ $slot }}
</form>
