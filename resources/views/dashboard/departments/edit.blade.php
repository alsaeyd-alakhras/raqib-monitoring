<x-front-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">تعديل القسم</h1>
            <p class="text-muted mb-0">تحديث بيانات القسم.</p>
        </div>

        <a href="{{ route('dashboard.departments.index') }}" class="btn btn-outline-secondary">
            رجوع
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form action="{{ route('dashboard.departments.update', $department) }}" method="POST">
                @csrf
                @method('PUT')

                @include('dashboard.departments._form')
            </form>
        </div>
    </div>
</x-front-layout>
