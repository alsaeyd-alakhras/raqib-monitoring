<x-front-layout>
    <form action="{{ route('dashboard.projects.store') }}" method="post" enctype="multipart/form-data" class="col-12">
        @csrf
        @include('dashboard.projects._form')
    </form>
</x-front-layout>
