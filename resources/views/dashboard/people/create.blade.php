<x-front-layout>
    <form action="{{ route('dashboard.people.store') }}" method="post" class="col-12">
        @csrf
        @include('dashboard.people._form')
    </form>
</x-front-layout>
