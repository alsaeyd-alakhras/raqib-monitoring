<x-front-layout>
    <form action="{{ route('dashboard.funders.update', $funder->id) }}" method="post" class="col-12">
        @csrf
        @method('put')
        @include('dashboard.funders._form')
    </form>
</x-front-layout>
