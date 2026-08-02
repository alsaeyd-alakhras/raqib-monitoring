<x-front-layout>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.monitoring-activities.index') }}">النشاطات الرقابية</a></li>
                    <li class="breadcrumb-item active">تعديل نشاط خارجي</li>
                </ol>
            </nav>
            <h4 class="mb-1">تعديل نشاط خارجي</h4>
            <p class="text-muted mb-0">
                {{ $activity->reference_code }}
                <span class="badge bg-label-{{ match($activity->workflow_status) {
                    'rejected' => 'danger',
                    'completed' => 'success',
                    'pending_confirmation' => 'warning',
                    'in_progress' => 'info',
                    default => 'secondary',
                } }}">{{ $activity->workflow_status_label }}</span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('dashboard.monitoring-activities.show', $activity) }}" class="btn btn-outline-primary">عرض</a>
            <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-label-secondary">رجوع</a>
        </div>
    </div>

    <form action="{{ route('dashboard.external-activities.update', $activity) }}" method="post" class="external-activity-form">
        @csrf
        @include('dashboard.external-activities._form')
    </form>
</x-front-layout>
