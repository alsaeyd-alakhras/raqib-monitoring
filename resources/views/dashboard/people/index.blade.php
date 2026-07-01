<x-front-layout>
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="card-title mb-0">الأشخاص</h5>
            <div class="d-flex align-items-center">
                @can('create', 'App\Models\Person')
                    <a class="btn btn-success" href="{{ route('dashboard.people.create') }}">
                        <i class="fa-solid fa-plus"></i>
                    </a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive text-nowrap">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>الاسم</th>
                            <th>الحساب المرتبط</th>
                            <th>المسمى الوظيفي</th>
                            <th>الجهة</th>
                            <th>الهاتف</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($people as $person)
                            <tr>
                                <td>{{ $person->name }}</td>
                                <td>{{ $person->user?->name ?? '-' }}</td>
                                <td>{{ $person->job_title ?: '-' }}</td>
                                <td>{{ $person->organization ?: '-' }}</td>
                                <td>{{ $person->phone ?: '-' }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @can('update', 'App\Models\Person')
                                            <a href="{{ route('dashboard.people.edit', $person) }}" class="btn btn-sm btn-outline-primary">
                                                تعديل
                                            </a>
                                        @endcan
                                        @can('delete', 'App\Models\Person')
                                            <form action="{{ route('dashboard.people.destroy', $person) }}" method="post" onsubmit="return confirm('هل أنت متأكد من حذف هذا الشخص؟');">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    حذف
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">لا توجد بيانات متاحة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                {{ $people->links() }}
            </div>
        </div>
    </div>
</x-front-layout>
