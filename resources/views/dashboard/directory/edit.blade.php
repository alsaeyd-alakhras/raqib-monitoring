@include('dashboard.directory._form', [
    'formAction' => route('dashboard.directory.update', $recordKey),
    'formMethod' => 'put',
    'submitLabel' => 'تحديث',
])
