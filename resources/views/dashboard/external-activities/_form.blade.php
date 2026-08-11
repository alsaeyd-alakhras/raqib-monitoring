@php
    $isEditing = isset($activity);
    $selectedCenterId = old('center_id', $isEditing ? $activity->center_id : '');
    $selectedDepartmentId = old('department_id', $isEditing ? $activity->department_id : '');
    $selectedSectionId = old('section_id', $isEditing ? $activity->section_id : '');
    $referenceCodeValue = old('reference_code', $isEditing ? $activity->reference_code : ($suggestedReferenceCode ?? ''));
    $defaultDate = old('activity_date', $isEditing && $activity->activity_date ? $activity->activity_date->format('Y-m-d') : now()->format('Y-m-d'));
    $defaultTime = old('activity_time', $isEditing ? $activity->activity_time : now()->format('H:i'));
    $fieldProblemValue = old('field_problem', $isEditing ? (int) $activity->field_problem : 0);
    $closureDateValue = old('closure_date', $isEditing && $activity->closure_date ? $activity->closure_date->format('Y-m-d') : '');
    $canPickMonitor = $canPickMonitor ?? false;
    $checkReferenceCodeUrl = $checkReferenceCodeUrl ?? route('dashboard.monitoring-activities.check-reference-code');

    $scaleFields = [
        'execution_value' => ['label' => 'التنفيذ', 'options' => $scaleExecution ?? []],
        'quality_value' => ['label' => 'الجودة', 'options' => $scaleQuality ?? []],
        'closure_value' => ['label' => 'الإغلاق', 'options' => $scaleClosure ?? []],
        'deduction_value' => ['label' => 'الخصم', 'options' => $scaleDeduction ?? []],
    ];
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
    <div class="card-header">
        <h5 class="mb-0">الأساسيات</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-4">
                <label class="form-label" for="reference_code">رمز النشاط</label>
                <input
                    type="text"
                    name="reference_code"
                    id="reference_code"
                    class="form-control @error('reference_code') is-invalid @enderror"
                    value="{{ $referenceCodeValue }}"
                    required
                    autocomplete="off"
                >
                <div id="reference-code-feedback" class="form-text"></div>
                @error('reference_code')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
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
                    rows="1"
                    data-autogrow
                    :value="old('subject', $isEditing ? $activity->subject : '')"
                />
            </div>
            <div class="mb-4 col-md-6">
                <x-form.select
                    name="detail"
                    label="التفصيل"
                    :options="$activityDetails ?? []"
                    :value="old('detail', $isEditing ? $activity->detail : '')"
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
            <div class="mb-4 col-md-4 {{ (string) $fieldProblemValue === '1' ? '' : 'd-none' }}" id="closure-date-wrap">
                <x-form.input
                    type="date"
                    name="closure_date"
                    id="closure_date"
                    label="تاريخ الإغلاق"
                    :value="$closureDateValue"
                />
                <div class="form-text">يُفضّل تحديد تاريخ إغلاق المشكلة عند وجود مشكلة ميدانية.</div>
            </div>
            <div class="mb-4 col-md-12">
                <x-form.textarea
                    name="action_taken"
                    label="الإجراء المتخذ"
                    rows="1"
                    data-autogrow
                    :value="old('action_taken', $isEditing ? $activity->action_taken : '')"
                />
            </div>
            <div class="mb-4 col-md-12">
                <x-form.textarea
                    name="notes"
                    label="ملاحظة النشاط الرقابي"
                    rows="1"
                    data-autogrow
                    :value="old('notes', $isEditing ? $activity->notes : '')"
                />
            </div>
            <div class="mb-4 col-md-12">
                <label class="form-label">المرفقات</label>
                @include('dashboard.external-activities._attachments_field', ['activity' => $activity ?? null])
            </div>
            <div class="mb-4 col-md-12">
                @include('dashboard.external-activities._field_notes_editor', ['activity' => $activity ?? null])
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
            @foreach ($scaleFields as $fieldName => $scaleConfig)
                <div class="mb-4 col-md-3">
                    <label class="form-label" for="{{ $fieldName }}">{{ $scaleConfig['label'] }}</label>
                    <select
                        name="{{ $fieldName }}"
                        id="{{ $fieldName }}"
                        class="form-select @error($fieldName) is-invalid @enderror"
                    >
                        <option value="">— اختر —</option>
                        @foreach ($scaleConfig['options'] as $tier)
                            @php
                                $tierValue = $tier['value'] ?? null;
                                $tierLabel = $tier['label'] ?? $tierValue;
                                $selectedValue = old($fieldName, $isEditing ? $activity->{$fieldName} : '');
                            @endphp
                            @if ($tierValue !== null)
                                <option value="{{ $tierValue }}" @selected((string) $selectedValue === (string) $tierValue)>
                                    {{ $tierLabel }} — {{ $tierValue }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    @error($fieldName)
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach
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

@include('dashboard.external-activities._attachment_modal')
@include('dashboard.external-activities._attachment_delete_modal')

@push('scripts')
<script src="{{ asset('js/org-cascade.js') }}"></script>
<script src="{{ asset('js/autogrow-textarea.js') }}"></script>
<script src="{{ asset('js/activity-attachment-ui.js') }}"></script>
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
<script>
    (function () {
        const referenceCodeInput = document.getElementById('reference_code');
        const referenceCodeFeedback = document.getElementById('reference-code-feedback');
        const checkReferenceCodeUrl = @json($checkReferenceCodeUrl);
        const exceptActivityId = @json($isEditing ? $activity->id : null);
        let referenceCodeAvailable = null;

        async function checkReferenceCodeAvailability() {
            if (!referenceCodeInput || !referenceCodeFeedback) {
                return;
            }

            const value = referenceCodeInput.value.trim();

            if (!value) {
                referenceCodeFeedback.textContent = '';
                referenceCodeFeedback.className = 'form-text';
                referenceCodeInput.classList.remove('is-valid', 'is-invalid');
                referenceCodeAvailable = null;
                return;
            }

            referenceCodeFeedback.textContent = 'جاري التحقق...';
            referenceCodeFeedback.className = 'form-text text-muted';
            referenceCodeInput.classList.remove('is-valid', 'is-invalid');

            const params = new URLSearchParams({
                reference_code: value,
                source_type: 'external',
            });
            if (exceptActivityId) {
                params.set('except_id', String(exceptActivityId));
            }

            try {
                const response = await fetch(`${checkReferenceCodeUrl}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('check failed');
                }

                const data = await response.json();
                referenceCodeAvailable = Boolean(data.valid && data.available);
                referenceCodeInput.classList.toggle('is-valid', referenceCodeAvailable);
                referenceCodeInput.classList.toggle('is-invalid', !referenceCodeAvailable);

                let message = data.message || '';
                if (!data.available && data.suggested) {
                    message += ` — اقتراح: ${data.suggested}`;
                }
                referenceCodeFeedback.textContent = message;
                referenceCodeFeedback.className = referenceCodeAvailable ? 'form-text text-success' : 'form-text text-danger';
            } catch (error) {
                referenceCodeFeedback.textContent = 'تعذّر التحقق من الرمز.';
                referenceCodeFeedback.className = 'form-text text-muted';
            }
        }

        referenceCodeInput?.addEventListener('blur', checkReferenceCodeAvailability);
    })();
</script>
<script>
    (function () {
        const fieldProblemSelect = document.getElementById('field_problem');
        const closureDateWrap = document.getElementById('closure-date-wrap');
        const closureDateInput = document.getElementById('closure_date');

        function syncClosureDateVisibility() {
            if (!fieldProblemSelect || !closureDateWrap) {
                return;
            }

            const hasProblem = fieldProblemSelect.value === '1';
            closureDateWrap.classList.toggle('d-none', !hasProblem);

            if (!hasProblem && closureDateInput) {
                closureDateInput.value = '';
            }
        }

        fieldProblemSelect?.addEventListener('change', syncClosureDateVisibility);
        syncClosureDateVisibility();
    })();
</script>
@endpush
