<x-front-layout>
    <form action="{{ route('dashboard.people.update', $person->id) }}" method="post" class="col-12">
        @csrf
        @method('put')
        @include('dashboard.people._form')
    </form>
</x-front-layout>
