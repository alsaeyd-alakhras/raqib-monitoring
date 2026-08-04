<x-front-layout>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.monitoring-activities.index') }}">النشاطات الرقابية</a></li>
                    <li class="breadcrumb-item active">إضافة نشاط خارجي</li>
                </ol>
            </nav>
            <h4 class="mb-1">إضافة نشاط خارجي</h4>
            <p class="text-muted mb-0">أنشطة رقابية مستقلة غير مرتبطة بمشروع</p>
        </div>
        <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-label-secondary">رجوع للقائمة</a>
    </div>

    <form action="{{ route('dashboard.external-activities.store') }}" method="post" enctype="multipart/form-data" class="external-activity-form">
        @csrf
        @include('dashboard.external-activities._form')
    </form>
</x-front-layout>
