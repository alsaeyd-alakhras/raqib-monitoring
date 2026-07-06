<x-front-layout>
    <form action="{{ route('dashboard.monitoring-activities.update', $activity->id) }}" method="post" class="col-12">
        @csrf
        @method('put')
        @include('dashboard.monitoring-activities._form')
    </form>

    @if ($canMonitorSubmit ?? false)
        <div class="mt-3">
            <form action="{{ route('dashboard.monitoring-activities.submit-to-director', $activity) }}" method="post" data-confirm="إرسال النشاط لمدير الرقابة؟" data-confirm-title="تأكيد الإرسال" data-confirm-variant="primary">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="bx bx-send"></i> إرسال لمدير الرقابة
                </button>
            </form>
        </div>
    @endif
</x-front-layout>
