<x-front-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">تعديل الشعبة</h1>
            <p class="text-muted mb-0">تحديث بيانات الشعبة.</p>
        </div>

        <a href="{{ route('dashboard.sections.index') }}" class="btn btn-outline-secondary">
            رجوع
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form action="{{ route('dashboard.sections.update', $section) }}" method="POST">
                @csrf
                @method('PUT')

                @include('dashboard.sections._form')
            </form>
        </div>
    </div>
</x-front-layout>
