@php
    $prefill = $prefill ?? [];
    $isMonitorEditor = $isMonitorEditor ?? false;
    $canMonitorSubmit = $canMonitorSubmit ?? false;
    $selectedCenterId = old('center_id', isset($activity) ? $activity->center_id : ($prefill['center_id'] ?? ''));
    $selectedDepartmentId = old('department_id', isset($activity) ? $activity->department_id : ($prefill['department_id'] ?? ''));
    $selectedSectionId = old('section_id', isset($activity) ? $activity->section_id : ($prefill['section_id'] ?? ''));
    $selectedSourceType = old('source_type', isset($activity) ? $activity->source_type : ($prefill['source_type'] ?? 'project'));
    $selectedSourceId = old('source_id', isset($activity) ? $activity->source_id : ($prefill['source_id'] ?? ''));
    $referenceCodeValue = old('reference_code', isset($activity) ? $activity->reference_code : ($suggestedReferenceCode ?? ''));
    $isEditing = isset($activity);
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

@if (isset($activity))
    <div class="alert alert-{{ $activity->is_verified ? 'success' : 'warning' }} d-flex justify-content-between align-items-center">
        <span><strong>حالة التحقق:</strong> {{ $activity->verification_status }}</span>
        @if (! empty($canConfirmCompletion) && ! $activity->is_passage_complete && in_array($activity->workflow_status, ['pending_confirmation', 'in_progress']))
            <form action="{{ route('dashboard.monitoring-activities.confirm-passage', $activity) }}" method="post" data-confirm="تأكيد اكتمال المرور على هذا النشاط؟" data-confirm-title="تأكيد المرور" data-confirm-variant="primary">
                @csrf
                <button type="submit" class="btn btn-sm btn-success">تأكيد اكتمال المرور</button>
            </form>
        @endif
    </div>

    @if ($activity->rejection_reason)
        <div class="alert alert-danger">
            <div><strong>سبب الرفض:</strong> {{ $activity->rejection_reason }}</div>
            <div><strong>مسؤولية النقص:</strong> {{ $activity->gap_owner }}</div>
            <div><strong>رُفض بواسطة:</strong> {{ $activity->rejectedByUser?->name ?? '-' }}</div>
            <div><strong>رُفض بتاريخ:</strong> {{ $activity->rejected_at }}</div>
        </div>
    @endif

    @if (! empty($canReject) && ! $activity->is_passage_complete)
        <div class="card mb-4 border-danger">
            <div class="card-header">
                <h5 class="mb-0 text-danger">رفض النشاط</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('dashboard.monitoring-activities.reject', $activity) }}" method="post" data-confirm="هل أنت متأكد من رفض هذا النشاط؟" data-confirm-title="تأكيد الرفض" data-confirm-variant="danger">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label">سبب الرفض</label>
                            <textarea name="rejection_reason" class="form-control" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">مسؤولية النقص</label>
                            <select name="gap_owner" class="form-select" required>
                                <option value="coordinator">المنسق</option>
                                <option value="dept_manager">مدير الدائرة</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-danger">رفض النشاط</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endif

@if ($isMonitorEditor)
    <div class="alert alert-info">
        أنت تعمل كمراقب مُسنَد — عدّل بيانات الميدان ثم أرسل النشاط لمدير الرقابة من الأسفل.
    </div>
@endif

@if (empty($isMonitorEditor))
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">هوية ومصدر</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="mb-4 col-md-4">
                <x-form.input
                    id="reference_code"
                    name="reference_code"
                    label="رمز النشاط (يُقترح تلقائياً — قابل للتعديل، يجب ألا يتكرر)"
                    :value="$referenceCodeValue"
                />
                <div id="reference-code-feedback" class="form-text"></div>
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="source_type"
                    id="source_type"
                    label="نوع المصدر"
                    :options="$sourceTypes"
                    :value="$selectedSourceType"
                    required
                />
            </div>
            @if ($isEditing)
                <div class="mb-4 col-md-4">
                    <label class="form-label">دور النشاط</label>
                    <div class="form-control-plaintext">
                        <span class="badge bg-label-{{ $activity->activity_role === 'primary' ? 'primary' : 'secondary' }}">
                            {{ $activity->activity_role === 'primary' ? 'أساسي' : 'تابع' }}
                        </span>
                    </div>
                    <div class="form-text">لا يمكن تغيير دور النشاط بعد الإنشاء — الأساسي يُنشأ من دورة اعتماد المشروع فقط.</div>
                </div>
            @endif
            <div class="mb-4 col-md-4" id="source-id-field">
                <x-form.select
                    name="source_id"
                    label="المشروع المرتبط"
                    :optionsId="$projects"
                    :value="$selectedSourceId"
                    searchable
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
            <div class="mb-4 col-md-6">
                <x-form.select
                    name="responsible_person_id"
                    label="المسؤول عن النشاط"
                    :optionsId="$people"
                    :value="$activity->responsible_person_id ?? ''"
                    searchable
                />
            </div>
            <div class="mb-4 col-md-6">
                <x-form.select
                    name="monitor_person_id"
                    label="المراقب"
                    :optionsId="$monitors ?? collect()"
                    :value="old('monitor_person_id', isset($activity) ? $activity->monitor_person_id : ($prefill['monitor_person_id'] ?? ''))"
                    searchable
                />
            </div>
        </div>
    </div>
</div>
@endif

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
                    searchable
                />
            </div>
        </div>
    </div>
</div>

@if (isset($linkedProject) && $linkedProject && ($linkedProject->monitor_notes || $linkedProject->monitor_recommendations))
    @once
        @push('styles')
        <style>
            .activity-notes-table {
                width: 100%;
                margin-bottom: 0;
                border-collapse: separate;
                border-spacing: 0;
                border: 1px solid rgba(67, 89, 113, 0.12);
                border-radius: 0.5rem;
                overflow: hidden;
                background: #fff;
            }

            .activity-notes-table th {
                width: 12.5rem;
                max-width: 12.5rem;
                background: rgba(67, 89, 113, 0.04);
                font-weight: 600;
                font-size: 0.8125rem;
                color: rgba(67, 89, 113, 0.85);
                padding: 0.625rem 0.875rem;
                border-bottom: 1px solid rgba(67, 89, 113, 0.08);
                vertical-align: top;
                white-space: nowrap;
            }

            .activity-notes-table td {
                padding: 0.625rem 0.875rem;
                border-bottom: 1px solid rgba(67, 89, 113, 0.08);
                font-size: 0.875rem;
                color: var(--bs-body-color);
                vertical-align: top;
            }

            .activity-notes-table tr:last-child th,
            .activity-notes-table tr:last-child td {
                border-bottom: none;
            }

            .activity-notes-table ul {
                margin-bottom: 0;
                padding-inline-start: 1.1rem;
            }
        </style>
        @endpush
    @endonce
    <div class="card mb-4 border-info">
        <div class="card-header bg-label-info">
            <h5 class="mb-0">ملاحظات/توصيات المراقب على المشروع المرتبط (للقراءة)</h5>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">من قائمة تحقق المشروع — مختلفة عن «ملاحظة النشاط الرقابي» في هذا النموذج.</p>
            <table class="activity-notes-table">
                <tbody>
                    <tr>
                        <th scope="row">ملاحظات المراقب</th>
                        <td>
                            @if ($linkedProject->monitor_notes)
                                <ul>@foreach ($linkedProject->monitor_notes as $note)<li>{{ $note }}</li>@endforeach</ul>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">توصيات المراقب</th>
                        <td>
                            @if ($linkedProject->monitor_recommendations)
                                <ul>@foreach ($linkedProject->monitor_recommendations as $rec)<li>{{ $rec }}</li>@endforeach</ul>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif

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
                    :value="old('subject', isset($activity) ? $activity->subject : ($prefill['subject'] ?? ''))"
                />
            </div>
            <div class="mb-4 col-md-6">
                <x-form.textarea
                    name="notes"
                    label="ملاحظة النشاط الرقابي"
                    :value="old('notes', isset($activity) ? $activity->notes : ($prefill['notes'] ?? ''))"
                />
                <div class="form-text">ملاحظة عامة على النشاط الرقابي — مختلفة عن ملاحظات المراقب على قائمة التحقق في المشروع.</div>
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
                @if (empty($isMonitorEditor))
                <x-form.select
                    name="workflow_status"
                    label="حالة سير العمل"
                    :options="$workflowStatusLabels"
                    :value="$activity->workflow_status ?? 'pending_monitor'"
                    required
                />
                @else
                <label class="form-label">حالة سير العمل</label>
                <div class="form-control-plaintext">{{ $activity->workflow_status_label }}</div>
                @endif
            </div>
            <div class="mb-4 col-md-3">
                @if (empty($isMonitorEditor))
                @php $passageCompleteValue = old('is_passage_complete', isset($activity) ? (int) $activity->is_passage_complete : 0); @endphp
                <label class="form-label" for="is_passage_complete">اكتمال المرور</label>
                <select name="is_passage_complete" id="is_passage_complete" class="form-select @error('is_passage_complete') is-invalid @enderror" required>
                    <option value="1" @selected((string) $passageCompleteValue === '1')>نعم</option>
                    <option value="0" @selected((string) $passageCompleteValue === '0')>لا</option>
                </select>
                @error('is_passage_complete')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @else
                <label class="form-label">اكتمال المرور</label>
                <div class="form-control-plaintext">{{ $activity->is_passage_complete ? 'نعم' : 'لا' }}</div>
                @endif
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

@push('scripts')
<script src="{{ asset('js/org-cascade.js') }}"></script>
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

        const sourceTypeSelect = document.querySelector('select[name="source_type"]');
        const sourceIdField = document.getElementById('source-id-field');

        function toggleSourceIdField() {
            if (!sourceTypeSelect || !sourceIdField) {
                return;
            }

            sourceIdField.style.display = sourceTypeSelect.value === 'project' ? '' : 'none';
        }

        if (sourceTypeSelect) {
            sourceTypeSelect.addEventListener('change', toggleSourceIdField);
            toggleSourceIdField();
        }
    });
</script>
<script>
    (function () {
        const referenceCodeInput = document.getElementById('reference_code');
        const referenceCodeFeedback = document.getElementById('reference-code-feedback');
        const sourceTypeSelect = document.querySelector('select[name="source_type"]');
        const checkReferenceCodeUrl = @json(route('dashboard.monitoring-activities.check-reference-code'));
        const exceptActivityId = @json(isset($activity) ? $activity->id : null);
        let referenceCodeAvailable = null;
        let userEditedReferenceCode = @json(isset($activity) && $activity->reference_code ? true : false);

        if (referenceCodeInput) {
            referenceCodeInput.addEventListener('input', function () {
                userEditedReferenceCode = true;
            });
        }

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
                source_type: sourceTypeSelect ? sourceTypeSelect.value : 'project',
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
                referenceCodeFeedback.className = referenceCodeAvailable
                    ? 'form-text text-success'
                    : 'form-text text-danger';
            } catch (error) {
                referenceCodeAvailable = null;
                referenceCodeFeedback.textContent = 'تعذّر التحقق من الرمز، حاول مرة أخرى.';
                referenceCodeFeedback.className = 'form-text text-warning';
            }
        }

        referenceCodeInput?.addEventListener('blur', checkReferenceCodeAvailability);

        referenceCodeInput?.closest('form')?.addEventListener('submit', function (event) {
            if (referenceCodeAvailable === false) {
                event.preventDefault();
                referenceCodeInput.classList.add('is-invalid');
                referenceCodeFeedback.textContent = 'رمز النشاط غير متاح، غيّره قبل الحفظ.';
                referenceCodeFeedback.className = 'form-text text-danger';
                referenceCodeInput.focus();
            }
        });

        if (sourceTypeSelect) {
            sourceTypeSelect.addEventListener('change', async function () {
                if (userEditedReferenceCode || !referenceCodeInput) {
                    return;
                }

                try {
                    const response = await fetch(`${checkReferenceCodeUrl}?${new URLSearchParams({ source_type: sourceTypeSelect.value }).toString()}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await response.json();
                    if (data.suggested) {
                        referenceCodeInput.value = data.suggested;
                        referenceCodeInput.classList.remove('is-valid', 'is-invalid');
                        referenceCodeFeedback.textContent = '';
                        referenceCodeFeedback.className = 'form-text';
                    }
                } catch (error) {
                    // silent — user can still type manually
                }
            });
        }
    })();
</script>
@endpush
