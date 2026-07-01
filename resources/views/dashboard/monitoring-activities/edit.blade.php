<x-front-layout>
    <form action="{{ route('dashboard.monitoring-activities.update', $activity->id) }}" method="post" class="col-12">
        @csrf
        @method('put')
        @include('dashboard.monitoring-activities._form')
    </form>
</x-front-layout>
