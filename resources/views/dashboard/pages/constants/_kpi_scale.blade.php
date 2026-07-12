@php
    $rows = is_array($rows ?? null) ? array_values($rows) : [];
    if ($rows === []) {
        $rows = [['min' => '', 'label' => '']];
    }
@endphp

<div class="card mb-4 constant-editor-card" data-editor-type="kpi-scale" data-constant-key="{{ $key }}">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">{{ $meta['label'] }}</h6>
        <button type="button" class="btn btn-sm btn-outline-primary btn-add-kpi-scale-row">
            <i class="fa-solid fa-plus me-1"></i> إضافة
        </button>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">يُرتَّب تلقائياً تنازلياً حسب الحد الأدنى عند الحفظ.</p>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8rem;">الحد الأدنى</th>
                        <th>التصنيف</th>
                        <th style="width: 4rem;"></th>
                    </tr>
                </thead>
                <tbody class="constant-kpi-scale-rows">
                    @foreach ($rows as $index => $row)
                        <tr class="constant-kpi-scale-row">
                            <td>
                                <input
                                    type="number"
                                    name="constants[{{ $key }}][{{ $index }}][min]"
                                    class="form-control"
                                    value="{{ $row['min'] ?? '' }}"
                                    min="0"
                                    max="100"
                                >
                            </td>
                            <td>
                                <input
                                    type="text"
                                    name="constants[{{ $key }}][{{ $index }}][label]"
                                    class="form-control"
                                    value="{{ $row['label'] ?? '' }}"
                                    placeholder="مثال: ممتاز"
                                >
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" @if(count($rows) === 1 && $index === 0) disabled @endif>
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
