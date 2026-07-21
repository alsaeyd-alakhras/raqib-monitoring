<x-front-layout>
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-2">إكمال بيانات الجوال</h5>
                <p class="text-muted mb-4">
                    مرحباً {{ auth()->user()->name }}، يرجى إدخال رقم جوالك لإكمال أول تسجيل دخول.
                </p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('dashboard.profile.complete-phone.store') }}" method="post">
                    @csrf
                    @method('put')
                    <div class="row">
                        <div class="mb-4 col-md-6">
                            <x-form.input
                                label="رقم الجوال"
                                name="phone"
                                :value="old('phone', auth()->user()->person?->phone)"
                                placeholder="05xxxxxxxx"
                                required
                                autofocus
                            />
                        </div>
                        <div class="mb-4 col-md-6">
                            <x-form.input
                                label="رقم جوال بديل (اختياري)"
                                name="alternate_phone"
                                :value="old('alternate_phone', auth()->user()->person?->alternate_phone)"
                                placeholder="05xxxxxxxx"
                            />
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">حفظ ومتابعة</button>
                </form>
            </div>
        </div>
    </div>
</x-front-layout>
