<x-front-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">الأقسام</h1>
            <p class="text-muted mb-0">إدارة الأقسام وربطها بالمراكز.</p>
        </div>

        @can('create', \App\Models\Department::class)
            <a href="{{ route('dashboard.departments.create') }}" class="btn btn-primary">
                إضافة قسم
            </a>
        @endcan
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المركز</th>
                            <th>اسم القسم</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($departments as $department)
                            <tr>
                                <td>{{ $departments->firstItem() + $loop->index }}</td>
                                <td>{{ $department->center?->name ?? '-' }}</td>
                                <td>{{ $department->name }}</td>
                                <td class="text-end">
                                    @can('update', \App\Models\Department::class)
                                        <a href="{{ route('dashboard.departments.edit', $department) }}" class="btn btn-sm btn-outline-primary">
                                            تعديل
                                        </a>
                                    @endcan

                                    @can('delete', \App\Models\Department::class)
                                        <form action="{{ route('dashboard.departments.destroy', $department) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل أنت متأكد من حذف هذا القسم؟')">
                                                حذف
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4">لا توجد أقسام مضافة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $departments->links() }}
    </div>
</x-front-layout>
