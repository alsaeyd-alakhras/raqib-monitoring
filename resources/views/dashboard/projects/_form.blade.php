@php
    $departmentOptions = $departments->map(fn ($d) => (object) [
        'id' => $d->id,
        'name' => $d->name . ($d->center ? ' - ' . $d->center->name : ''),
    ]);
    $sectionOptions = $sections->map(fn ($s) => (object) [
        'id' => $s->id,
        'name' => $s->name . ($s->department ? ' - ' . $s->department->name : ''),
    ]);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">أولاً — بيانات المشروع</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-4">
                <x-form.input
                    name="project_name"
                    label="اسم المشروع"
                    :value="$project->project_name ?? ''"
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.input
                    type="number"
                    name="project_number"
                    label="رقم المشروع (اختياري)"
                    :value="$project->project_number ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                @if (! empty($projectTypes))
                    <x-form.select
                        name="project_type"
                        label="نوع المشروع"
                        :options="$projectTypes"
                        :value="$project->project_type ?? ''"
                    />
                @else
                    <x-form.input
                        name="project_type"
                        label="نوع المشروع"
                        :value="$project->project_type ?? ''"
                    />
                @endif
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="funder_id"
                    label="الجهة المانحة"
                    :optionsId="$funders"
                    :value="$project->funder_id ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.input
                    name="procurement_rep"
                    label="مندوب المشتريات"
                    :value="$project->procurement_rep ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="project_manager_id"
                    label="مدير المشروع"
                    :optionsId="$people"
                    :value="$project->project_manager_id ?? ''"
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="coordinator_id"
                    label="المنسق"
                    :optionsId="$people"
                    :value="$project->coordinator_id ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="center_id"
                    label="المركز"
                    :optionsId="$centers"
                    :value="$project->center_id ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="department_id"
                    label="الدائرة"
                    :optionsId="$departmentOptions"
                    :value="$project->department_id ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="section_id"
                    label="القسم"
                    :optionsId="$sectionOptions"
                    :value="$project->section_id ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.input
                    type="date"
                    name="planned_start_date"
                    label="تاريخ بداية التنفيذ المخطط"
                    :value="isset($project) && $project->planned_start_date ? $project->planned_start_date->format('Y-m-d') : ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.input
                    type="date"
                    name="planned_end_date"
                    label="تاريخ نهاية التنفيذ المخطط"
                    :value="isset($project) && $project->planned_end_date ? $project->planned_end_date->format('Y-m-d') : ''"
                />
            </div>
            <div class="mb-4 col-md-12">
                <x-form.textarea
                    name="location"
                    label="الموقع الجغرافي"
                    :value="$project->location ?? ''"
                />
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">ثانياً — بيانات التنفيذ</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    name="target_beneficiaries"
                    label="إجمالي المستفيدين المستهدفين"
                    :value="$project->target_beneficiaries ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    name="execution_zones"
                    label="عدد مناطق التنفيذ"
                    :value="$project->execution_zones ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    name="estimated_duration"
                    label="المدة الزمنية المقدّرة"
                    :value="$project->estimated_duration ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    step="0.01"
                    name="allocated_budget"
                    label="الميزانية المرصودة"
                    :value="$project->allocated_budget ?? ''"
                />
            </div>
        </div>
    </div>
</div>

<div class="mt-2">
    <button type="submit" class="btn btn-primary me-3">
        حفظ
    </button>
    <a href="{{ route('dashboard.projects.index') }}" class="btn btn-label-secondary">
        إلغاء
    </a>
</div>
