<x-front-layout>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">إضافة مجموعة جديدة</h5></div>
        <div class="card-body">
            <form action="{{ route('dashboard.checklist-admin.groups.store') }}" method="post" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">اسم المجموعة</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>

    @foreach ($groups as $group)
        <div class="card mb-4 {{ $group->is_active ? '' : 'opacity-50' }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <form action="{{ route('dashboard.checklist-admin.groups.update', $group) }}" method="post" class="d-flex align-items-center gap-2">
                    @csrf
                    @method('put')
                    <input type="text" name="name" class="form-control form-control-sm" value="{{ $group->name }}" style="width:250px">
                    <button type="submit" class="btn btn-sm btn-outline-primary">حفظ</button>
                </form>
                <div class="d-flex gap-2">
                    <form action="{{ route('dashboard.checklist-admin.groups.move', $group) }}" method="post">
                        @csrf
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">↑</button>
                    </form>
                    <form action="{{ route('dashboard.checklist-admin.groups.move', $group) }}" method="post">
                        @csrf
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">↓</button>
                    </form>
                    <form action="{{ route('dashboard.checklist-admin.groups.toggle', $group) }}" method="post">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-{{ $group->is_active ? 'warning' : 'success' }}">
                            {{ $group->is_active ? 'تعطيل' : 'تفعيل' }}
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>البند</th>
                            <th>حقل اسم شخص؟</th>
                            <th style="width:220px">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group->items as $item)
                            <tr class="{{ $item->is_active ? '' : 'opacity-50' }}">
                                <td>
                                    <form action="{{ route('dashboard.checklist-admin.items.update', $item) }}" method="post" class="d-flex align-items-center gap-2">
                                        @csrf
                                        @method('put')
                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $item->name }}">
                                        <input type="hidden" name="has_person_field" value="0">
                                        <input type="checkbox" name="has_person_field" value="1" class="form-check-input" @checked($item->has_person_field) title="حقل اسم شخص">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">حفظ</button>
                                    </form>
                                </td>
                                <td>{{ $item->has_person_field ? 'نعم' : 'لا' }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <form action="{{ route('dashboard.checklist-admin.items.move', $item) }}" method="post">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">↑</button>
                                        </form>
                                        <form action="{{ route('dashboard.checklist-admin.items.move', $item) }}" method="post">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">↓</button>
                                        </form>
                                        <form action="{{ route('dashboard.checklist-admin.items.toggle', $item) }}" method="post">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-{{ $item->is_active ? 'warning' : 'success' }}">
                                                {{ $item->is_active ? 'تعطيل' : 'تفعيل' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="3">
                                <form action="{{ route('dashboard.checklist-admin.items.store') }}" method="post" class="d-flex align-items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="group_id" value="{{ $group->id }}">
                                    <input type="text" name="name" class="form-control form-control-sm" placeholder="بند جديد" required>
                                    <div class="form-check">
                                        <input type="hidden" name="has_person_field" value="0">
                                        <input type="checkbox" name="has_person_field" value="1" class="form-check-input" id="new-person-{{ $group->id }}">
                                        <label class="form-check-label" for="new-person-{{ $group->id }}">حقل اسم شخص</label>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-success">إضافة بند</button>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</x-front-layout>
