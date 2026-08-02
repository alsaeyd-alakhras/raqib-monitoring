@php
    $isEditing = isset($activity);
    $selectedCenterId = old('center_id', $isEditing ? $activity->center_id : '');
    $selectedDepartmentId = old('department_id', $isEditing ? $activity->department_id : '');
    $selectedSectionId = old('section_id', $isEditing ? $activity->section_id : '');
    $referenceCode = $isEditing ? $activity->reference_code : ($suggestedReferenceCode ?? '');
    $defaultDate = old('activity_date', $isEditing && $activity->activity_date ? $activity->activity_date->format('Y-m-d') : now()->format('Y-m-d'));
    $defaultTime = old('activity_time', $isEditing ? $activity->activity_time : now()->format('H:i'));
    $fieldProblemValue = old('field_problem', $isEditing ? (int) $activity->field_problem : 0);
    $canPickMonitor = $canPickMonitor ?? false;
@endphp

@push('styles')
<style>
    .external-activity-form textarea[data-autogrow] {
        min-height: calc(1.5em + 0.75rem + 2px);
        overflow: hidden;
        resize: none;
    }

    .external-activity-form .sticky-actions {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(6px);
        border-top: 1px solid rgba(67, 89, 113, 0.12);
        padding: 1rem 1.25rem;
    }
</style>
@endpush

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($isEditing && $activity->rejection_reason && $activity->workflow_status === 'in_progress')
    <div class="alert alert-warning">
        <div><strong>سبب الإرجاع:</strong> {{ $activity->rejection_reason }}</div>
        <div class="small text-muted mt-1">
            مسؤولية النقص: {{ $activity->gap_owner === 'monitor' ? 'المراقب' : 'أخرى' }}
            @if ($activity->rejected_at)
                — {{ $activity->rejected_at->format('Y-m-d H:i') }}
            @endif
        </div>
    </div>
@endif

<div class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <h5 class="mb-0">الأساسيات</h5>
        <span class="text-muted small">رمز النشاط:</span>
        <span class="badge bg-label-primary">{{ $referenceCode }}</span>
        @if ($isEditing)
            <span class="text-muted small">(يُولَّد تلقائياً ولا يمكن تغييره)</span>
        @endif
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-4">
                <x-form.input
                    type="date"
                    name="activity_date"
                    label="التاريخ"
                    :value="$defaultDate"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.input
                    type="time"
                    name="activity_time"
                    label="الوقت"
                    :value="$defaultTime"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="activity_type"
                    label="نوع النشاط"
                    :options="$activityTypes"
                    :value="old('activity_type', $isEditing ? $activity->activity_type : '')"
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="funder_id"
                    label="الممول (اختياري)"
                    :optionsId="$funders"
                    :value="old('funder_id', $isEditing ? $activity->funder_id : '')"
                    searchable
                />
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">الجهة والأطراف</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="center_id"
                    id="center_id"
                    label="المركز"
                    :optionsId="$centers"
                    :value="$selectedCenterId"
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <label class="form-label" for="department_id">الدائرة</label>
                <select
                    name="department_id"
                    id="department_id"
                    class="form-select @error('department_id') is-invalid @enderror"
                    required
                >
                    <option value="">إختر القيمة</option>
                </select>
                @error('department_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-4 col-md-4">
                <label class="form-label" for="section_id">القسم (اختياري)</label>
                <select
                    name="section_id"
                    id="section_id"
                    class="form-select @error('section_id') is-invalid @enderror"
                >
                    <option value="">إختر القيمة</option>
                </select>
                @error('section_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="responsible_person_id"
                    label="المسؤول عن النشاط"
                    :optionsId="$people"
                    :value="old('responsible_person_id', $isEditing ? $activity->responsible_person_id : '')"
                    searchable
                />
            </div>
            @if ($canPickMonitor)
                <div class="mb-4 col-md-4">
                    <x-form.select
                        name="monitor_person_id"
                        label="المراقب"
                        :optionsId="$monitors ?? collect()"
                        :value="old('monitor_person_id', $isEditing ? $activity->monitor_person_id : '')"
                        searchable
                    />
                </div>
            @endif
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
                    rows="2"
                    data-autogrow
                    :value="old('subject', $isEditing ? $activity->subject : '')"
                />
            </div>
            <div class="mb-4 col-md-6">
                <x-form.textarea
                    name="notes"
                    label="ملاحظة النشاط الرقابي"
                    rows="2"
                    data-autogrow
                    :value="old('notes', $isEditing ? $activity->notes : '')"
                />
            </div>
            <div class="mb-4 col-md-4">
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
                    rows="3"
                    data-autogrow
                    :value="old('action_taken', $isEditing ? $activity->action_taken : '')"
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
                    :value="old('execution_value', $isEditing ? $activity->execution_value : '')"
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
                    :value="old('quality_value', $isEditing ? $activity->quality_value : '')"
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
                    :value="old('closure_value', $isEditing ? $activity->closure_value : '')"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    step="0.01"
                    max="0"
                    name="deduction_value"
                    label="الخصم (سالب أو صفر)"
                    :value="old('deduction_value', $isEditing ? $activity->deduction_value : 0)"
                />
            </div>
            <div class="mb-4 col-md-6">
                <x-form.select
                    name="monitoring_method"
                    label="طريقة المراقبة"
                    :options="$monitoringMethods"
                    :value="old('monitoring_method', $isEditing ? $activity->monitoring_method : '')"
                />
            </div>
            <div class="mb-4 col-md-6">
                <x-form.select
                    name="monitoring_stage"
                    label="مرحلة المراقبة"
                    :options="$monitoringStages"
                    :value="old('monitoring_stage', $isEditing ? $activity->monitoring_stage : '')"
                />
            </div>
        </div>
    </div>

    <div class="sticky-actions d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div class="text-muted small">
            @if ($isEditing && $activity->submitted_at)
                آخر إرسال: {{ $activity->submitted_at->format('Y-m-d H:i') }}
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" name="action" value="save" class="btn btn-primary">
                <i class="bx bx-save"></i> حفظ
            </button>
            <button type="submit" name="action" value="submit" class="btn btn-success"
                data-confirm="إرسال النشاط لمدير الرقابة؟"
                data-confirm-title="تأكيد الإرسال"
                data-confirm-variant="primary">
                <i class="bx bx-send"></i> حفظ وإرسال لمدير الرقابة
            </button>
            <a href="{{ route('dashboard.monitoring-activities.index') }}" class="btn btn-label-secondary">إلغاء</a>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/org-cascade.js') }}"></script>
<script src="{{ asset('js/autogrow-textarea.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.initOrgCascade === 'function') {
            window.initOrgCascade({
                departmentsUrl: @json(route('dashboard.departments.by-center', ['center' => '__ID__'])),
                sectionsUrl: @json(route('dashboard.sections.by-department', ['department' => '__ID__'])),
                selectedCenterId: @json($selectedCenterId),
                selectedDepartmentId: @json($selectedDepartmentId),
                selectedSectionId: @json($selectedSectionId),
            });
        }
    });
</script>
@endpush
