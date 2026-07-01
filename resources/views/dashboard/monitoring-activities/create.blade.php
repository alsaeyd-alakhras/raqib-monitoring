<x-front-layout>
    <form action="{{ route('dashboard.monitoring-activities.store') }}" method="post" class="col-12">
        @csrf
        @include('dashboard.monitoring-activities._form')
    </form>
</x-front-layout>
