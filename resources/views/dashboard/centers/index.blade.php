<x-front-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">المراكز</h1>
            <p class="text-muted mb-0">إدارة المراكز المتاحة في الهيكل التنظيمي.</p>
        </div>

        @can('create', \App\Models\Center::class)
            <a href="{{ route('dashboard.centers.create') }}" class="btn btn-primary">
                إضافة مركز
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
                            <th>الاسم</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($centers as $center)
                            <tr>
                                <td>{{ $centers->firstItem() + $loop->index }}</td>
                                <td>{{ $center->name }}</td>
                                <td class="text-end">
                                    @can('update', \App\Models\Center::class)
                                        <a href="{{ route('dashboard.centers.edit', $center) }}" class="btn btn-sm btn-outline-primary">
                                            تعديل
                                        </a>
                                    @endcan

                                    @can('delete', \App\Models\Center::class)
                                        <form action="{{ route('dashboard.centers.destroy', $center) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المركز؟')">
                                                حذف
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-4">لا توجد مراكز مضافة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $centers->links() }}
    </div>
</x-front-layout>
