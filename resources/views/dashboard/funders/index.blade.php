<x-front-layout>
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="card-title mb-0">الجهات الممولة</h5>
            <div class="d-flex align-items-center">
                @can('create', 'App\Models\Funder')
                    <a class="btn btn-success" href="{{ route('dashboard.funders.create') }}">
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
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($funders as $funder)
                            <tr>
                                <td>{{ $funder->name }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @can('update', 'App\Models\Funder')
                                            <a href="{{ route('dashboard.funders.edit', $funder) }}" class="btn btn-sm btn-outline-primary">
                                                تعديل
                                            </a>
                                        @endcan
                                        @can('delete', 'App\Models\Funder')
                                            <form action="{{ route('dashboard.funders.destroy', $funder) }}" method="post" data-confirm="هل أنت متأكد من حذف هذه الجهة الممولة؟" data-confirm-title="تأكيد الحذف" data-confirm-variant="danger">
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
                                <td colspan="2" class="text-center py-4">لا توجد بيانات متاحة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                {{ $funders->links() }}
            </div>
        </div>
    </div>
</x-front-layout>
