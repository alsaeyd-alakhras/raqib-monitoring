<x-front-layout>
    <form action="{{ route('dashboard.projects.store') }}" method="post" class="col-12">
        @csrf
        @include('dashboard.projects._form')
    </form>
</x-front-layout>
