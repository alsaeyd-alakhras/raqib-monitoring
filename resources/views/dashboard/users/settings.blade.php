<x-front-layout>
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-4">إعدادات الملف الشخصي</h5>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('info'))
                    <div class="alert alert-info">{{ session('info') }}</div>
                @endif

                <form action="{{ route('dashboard.profile.update') }}" method="post">
                    @csrf
                    @method('put')
                    <div class="row">
                        <div class="mb-4 col-md-6">
                            <x-form.input label="الاسم" :value="$user->name" name="name" placeholder="محمد ...." required autofocus />
                        </div>
                        <div class="mb-4 col-md-6">
                            <x-form.input label="اسم المستخدم" :value="$user->username" name="username" placeholder="username" required />
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </form>
            </div>
        </div>
    </div>
</x-front-layout>
