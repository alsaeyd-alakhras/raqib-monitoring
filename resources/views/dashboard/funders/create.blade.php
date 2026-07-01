<x-front-layout>
    <form action="{{ route('dashboard.funders.store') }}" method="post" class="col-12">
        @csrf
        @include('dashboard.funders._form')
    </form>
</x-front-layout>
