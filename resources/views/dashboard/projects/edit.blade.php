<x-front-layout>
    <form action="{{ route('dashboard.projects.update', $project) }}" method="post" class="col-12">
        @csrf
        @method('put')
        @include('dashboard.projects._form')
    </form>
</x-front-layout>
