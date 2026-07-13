<x-front-layout>
    @if (session('success'))
        <div class="col-12 mb-3">
            <div class="alert alert-success">{{ session('success') }}</div>
        </div>
    @endif
    @if (session('info'))
        <div class="col-12 mb-3">
            <div class="alert alert-info">{{ session('info') }}</div>
        </div>
    @endif
    <form action="{{ route('dashboard.profile.update') }}" method="post" class="col-12" enctype="multipart/form-data">
        @csrf
        @method('put')
        @include('dashboard.users._form', ['settings_profile' => true, 'btn_label' => 'حفظ'])
    </form>
</x-front-layout>
