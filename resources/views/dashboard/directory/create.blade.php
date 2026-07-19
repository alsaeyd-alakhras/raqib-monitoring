@include('dashboard.directory._form', [
    'formAction' => route('dashboard.directory.store'),
    'formMethod' => 'post',
    'submitLabel' => 'حفظ',
])
