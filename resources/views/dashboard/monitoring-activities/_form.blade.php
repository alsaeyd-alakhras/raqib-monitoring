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
        <h5 class="mb-0">هوية ومصدر</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-4">
                <x-form.input
                    name="reference_code"
                    label="رمز النشاط (اختياري — يُولَّد تلقائياً إن تُرك فارغاً)"
                    :value="$activity->reference_code ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="source_type"
                    label="نوع المصدر"
                    :options="$sourceTypes"
                    :value="$activity->source_type ?? ''"
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="activity_role"
                    label="دور النشاط"
                    :options="['primary' => 'أساسي', 'secondary' => 'تابع']"
                    :value="$activity->activity_role ?? 'primary'"
                    required
                />
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">الهرم التنظيمي والأطراف</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="center_id"
                    label="المركز"
                    :optionsId="$centers"
                    :value="$activity->center_id ?? ''"
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="department_id"
                    label="الدائرة"
                    :optionsId="$departmentOptions"
                    :value="$activity->department_id ?? ''"
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="section_id"
                    label="القسم (اختياري)"
                    :optionsId="$sectionOptions"
                    :value="$activity->section_id ?? ''"
                />
            </div>
            <div class="mb-4 col-md-6">
                <x-form.select
                    name="responsible_person_id"
                    label="المسؤول عن النشاط"
                    :optionsId="$people"
                    :value="$activity->responsible_person_id ?? ''"
                />
            </div>
            <div class="mb-4 col-md-6">
                <x-form.select
                    name="monitor_person_id"
                    label="المراقب"
                    :optionsId="$people"
                    :value="$activity->monitor_person_id ?? ''"
                />
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">الزمن والتصنيف</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="date"
                    name="activity_date"
                    label="التاريخ"
                    :value="isset($activity) && $activity->activity_date ? $activity->activity_date->format('Y-m-d') : ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="time"
                    name="activity_time"
                    label="الوقت"
                    :value="$activity->activity_time ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.select
                    name="activity_type"
                    label="نوع النشاط"
                    :options="$activityTypes"
                    :value="$activity->activity_type ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.select
                    name="funder_id"
                    label="الممول (اختياري)"
                    :optionsId="$funders"
                    :value="$activity->funder_id ?? ''"
                />
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">المحتوى الرقابي</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-6">
                <x-form.textarea
                    name="subject"
                    label="الموضوع"
                    :value="$activity->subject ?? ''"
                />
            </div>
            <div class="mb-4 col-md-6">
                <x-form.textarea
                    name="notes"
                    label="الملاحظة"
                    :value="$activity->notes ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                @php $fieldProblemValue = old('field_problem', isset($activity) ? (int) $activity->field_problem : 0); @endphp
                <label class="form-label" for="field_problem">هل يوجد مشكلة ميدانية؟</label>
                <select name="field_problem" id="field_problem" class="form-select @error('field_problem') is-invalid @enderror" required>
                    <option value="1" @selected((string) $fieldProblemValue === '1')>نعم</option>
                    <option value="0" @selected((string) $fieldProblemValue === '0')>لا</option>
                </select>
                @error('field_problem')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-4 col-md-8">
                <x-form.textarea
                    name="action_taken"
                    label="الإجراء المتخذ"
                    :value="$activity->action_taken ?? ''"
                />
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">التقييم</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    name="execution_value"
                    label="نسبة التنفيذ"
                    :value="$activity->execution_value ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    name="quality_value"
                    label="الجودة"
                    :value="$activity->quality_value ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    name="closure_value"
                    label="الإغلاق"
                    :value="$activity->closure_value ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    step="0.01"
                    max="0"
                    name="deduction_value"
                    label="الخصم (سالب أو صفر)"
                    :value="$activity->deduction_value ?? 0"
                />
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">المراقبة وسير العمل</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-3">
                <x-form.select
                    name="monitoring_method"
                    label="طريقة المراقبة"
                    :options="$monitoringMethods"
                    :value="$activity->monitoring_method ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.select
                    name="monitoring_stage"
                    label="مرحلة المراقبة"
                    :options="$monitoringStages"
                    :value="$activity->monitoring_stage ?? ''"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.select
                    name="workflow_status"
                    label="حالة سير العمل"
                    :options="[
                        'pending_monitor' => 'بانتظار تعيين مراقب',
                        'in_progress' => 'المراقب يعمل',
                        'pending_confirmation' => 'بانتظار التأكيد',
                        'completed' => 'مكتمل',
                    ]"
                    :value="$activity->workflow_status ?? 'pending_monitor'"
                    required
                />
            </div>
            <div class="mb-4 col-md-3">
                @php $passageCompleteValue = old('is_passage_complete', isset($activity) ? (int) $activity->is_passage_complete : 0); @endphp
                <label class="form-label" for="is_passage_complete">اكتمال المرور</label>
                <select name="is_passage_complete" id="is_passage_complete" class="form-select @error('is_passage_complete') is-invalid @enderror" required>
                    <option value="1" @selected((string) $passageCompleteValue === '1')>نعم</option>
                    <option value="0" @selected((string) $passageCompleteValue === '0')>لا</option>
                </select>
                @error('is_passage_complete')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

<div class="mt-2">
    <button type="submit" class="btn btn-primary me-3">
        حفظ
    </button>
    <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-label-secondary">
        إلغاء
    </a>
</div>
