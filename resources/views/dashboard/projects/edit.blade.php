<x-front-layout>
    <form action="{{ route('dashboard.projects.update', $project) }}" method="post" enctype="multipart/form-data" class="col-12">
        @csrf
        @method('put')
        @include('dashboard.projects._form')
    </form>
</x-front-layout>
