<x-front-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">الشعب</h1>
            <p class="text-muted mb-0">إدارة الشعب وربطها بالأقسام والمراكز.</p>
        </div>

        @can('create', \App\Models\Section::class)
            <a href="{{ route('dashboard.sections.create') }}" class="btn btn-primary">
                إضافة شعبة
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
                            <th>القسم</th>
                            <th>اسم الشعبة</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sections as $section)
                            <tr>
                                <td>{{ $sections->firstItem() + $loop->index }}</td>
                                <td>{{ $section->department?->center?->name ?? '-' }}</td>
                                <td>{{ $section->department?->name ?? '-' }}</td>
                                <td>{{ $section->name }}</td>
                                <td class="text-end">
                                    @can('update', \App\Models\Section::class)
                                        <a href="{{ route('dashboard.sections.edit', $section) }}" class="btn btn-sm btn-outline-primary">
                                            تعديل
                                        </a>
                                    @endcan

                                    @can('delete', \App\Models\Section::class)
                                        <form action="{{ route('dashboard.sections.destroy', $section) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الشعبة؟')">
                                                حذف
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">لا توجد شعب مضافة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $sections->links() }}
    </div>
</x-front-layout>
