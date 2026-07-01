<x-front-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">تعديل المركز</h1>
            <p class="text-muted mb-0">تحديث بيانات المركز.</p>
        </div>

        <a href="{{ route('dashboard.centers.index') }}" class="btn btn-outline-secondary">
            رجوع
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form action="{{ route('dashboard.centers.update', $center) }}" method="POST">
                @csrf
                @method('PUT')

                @include('dashboard.centers._form')
            </form>
        </div>
    </div>
</x-front-layout>
