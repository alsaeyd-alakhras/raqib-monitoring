<x-front-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">إضافة مركز</h1>
            <p class="text-muted mb-0">أدخل بيانات المركز الجديد.</p>
        </div>

        <a href="{{ route('dashboard.centers.index') }}" class="btn btn-outline-secondary">
            رجوع
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form action="{{ route('dashboard.centers.store') }}" method="POST">
                @csrf

                @include('dashboard.centers._form')
            </form>
        </div>
    </div>
</x-front-layout>
