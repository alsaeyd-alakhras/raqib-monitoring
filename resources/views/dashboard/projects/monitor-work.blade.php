@php
    $valueLabels = [
        'ready' => 'جاهز',
        'partial' => 'جزئي',
        'not_ready' => 'غير جاهز',
        'not_required' => 'غير مطلوب',
    ];
@endphp
<x-front-layout>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">عمل المراقب — {{ $project->project_name }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3"><strong>المركز/الدائرة/القسم:</strong> {{ $project->center?->name }} / {{ $project->department?->name }} / {{ $project->section?->name }}</div>
                <div class="col-md-3"><strong>الممول:</strong> {{ $project->funder?->name ?? '-' }}</div>
                <div class="col-md-3"><strong>المستفيدون المستهدفون:</strong> {{ $project->target_beneficiaries ?? '-' }}</div>
                <div class="col-md-3"><strong>الميزانية المرصودة:</strong> {{ $project->allocated_budget ?? '-' }}</div>
                <div class="col-md-6"><strong>الموقع:</strong> {{ $project->location ?? '-' }}</div>
                <div class="col-md-3"><strong>عدد مناطق التنفيذ:</strong> {{ $project->execution_zones ?? '-' }}</div>
                <div class="col-md-3"><strong>المدة المقدّرة:</strong> {{ $project->estimated_duration ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('dashboard.projects.fill-monitor', $project) }}" method="post">
                @csrf
                @foreach ($groups as $group)
                    <h6 class="mt-3">{{ $group->name }}</h6>
                    <table class="table table-sm table-bordered">
                        <tbody>
                            @foreach ($group->items as $item)
                                @php $current = $values->get($item->id); @endphp
                                <tr>
                                    <td class="align-middle">{{ $item->name }}</td>
                                    <td style="max-width:220px">
                                        <select name="checklist[{{ $item->id }}][value]" class="form-select form-select-sm">
                                            <option value="">-</option>
                                            @foreach ($valueLabels as $key => $label)
                                                <option value="{{ $key }}" @selected($current?->monitor_value === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    @if ($item->has_person_field)
                                        <td style="max-width:220px">
                                            <input type="text" name="checklist[{{ $item->id }}][person_name]" class="form-control form-control-sm" placeholder="اسم الشخص" value="{{ $current?->person_name }}">
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="form-label">ملاحظات المراقب (سطر لكل ملاحظة)</label>
                        <textarea name="monitor_notes_text" class="form-control" rows="4">{{ implode("\n", $project->monitor_notes ?? []) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">توصيات المراقب (سطر لكل توصية)</label>
                        <textarea name="monitor_recommendations_text" class="form-control" rows="4">{{ implode("\n", $project->monitor_recommendations ?? []) }}</textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">حفظ عمود المراقب</button>
            </form>
        </div>
    </div>
</x-front-layout>
