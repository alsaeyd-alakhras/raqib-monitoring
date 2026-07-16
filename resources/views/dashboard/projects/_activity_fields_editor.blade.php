@php
    $activity = $activity ?? null;
    $fieldProblemValue = old('field_problem', isset($activity) ? (int) $activity->field_problem : 0);
@endphp

@if (! $activity)
    <div class="alert alert-warning mb-0">لا يوجد نشاط رقابي أساسي مرتبط بهذا المشروع.</div>
@else
    <p class="text-muted small mb-3">
        عبّئ بيانات النشاط الرقابي الأساسي (<strong>{{ $activity->reference_code }}</strong>).
        التنفيذ يُحسب تلقائياً من قائمة التحقق — KPI وحالة التحقق تُحدَّث بعد الحفظ.
    </p>

    <div class="card mb-3 border-secondary">
        <div class="card-header py-2">
            <h6 class="mb-0">الأطراف والزمن والتصنيف</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="mb-3 col-md-6">
                    <x-form.select
                        name="responsible_person_id"
                        label="المسؤول عن النشاط"
                        :optionsId="$people ?? collect()"
                        :value="old('responsible_person_id', $activity->responsible_person_id ?? '')"
                        searchable
                    />
                </div>
                <div class="mb-3 col-md-3">
                    <x-form.input
                        type="date"
                        name="activity_date"
                        label="التاريخ"
                        :value="old('activity_date', $activity->activity_date ? $activity->activity_date->format('Y-m-d') : '')"
                    />
                </div>
                <div class="mb-3 col-md-3">
                    <x-form.input
                        type="time"
                        name="activity_time"
                        label="الوقت"
                        :value="old('activity_time', $activity->activity_time ?? '')"
                    />
                </div>
                <div class="mb-3 col-md-6">
                    <x-form.select
                        name="activity_type"
                        label="نوع النشاط"
                        :options="$activityTypes ?? []"
                        :value="old('activity_type', $activity->activity_type ?? '')"
                    />
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3 border-secondary">
        <div class="card-header py-2">
            <h6 class="mb-0">المحتوى الرقابي</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="mb-3 col-md-6">
                    <x-form.textarea
                        name="subject"
                        label="الموضوع"
                        :value="old('subject', $activity->subject ?? '')"
                    />
                </div>
                <div class="mb-3 col-md-6">
                    <x-form.textarea
                        name="notes"
                        label="ملاحظة النشاط الرقابي"
                        :value="old('notes', $activity->notes ?? '')"
                    />
                    <div class="form-text">ملاحظة عامة على النشاط — مختلفة عن ملاحظات قائمة التحقق أعلاه.</div>
                </div>
                <div class="mb-3 col-md-4">
                    <label class="form-label" for="field_problem">هل يوجد مشكلة ميدانية؟</label>
                    <select name="field_problem" id="field_problem" class="form-select @error('field_problem') is-invalid @enderror" required>
                        <option value="1" @selected((string) $fieldProblemValue === '1')>نعم</option>
                        <option value="0" @selected((string) $fieldProblemValue === '0')>لا</option>
                    </select>
                    @error('field_problem')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3 col-md-8">
                    <x-form.textarea
                        name="action_taken"
                        label="الإجراء المتخذ"
                        :value="old('action_taken', $activity->action_taken ?? '')"
                    />
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3 border-secondary">
        <div class="card-header py-2">
            <h6 class="mb-0">التقييم ومؤشر الأداء</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="mb-3 col-md-3">
                    <label class="form-label">التنفيذ</label>
                    <div class="form-control-plaintext fw-semibold">
                        {{ $activity->execution_value !== null ? $activity->execution_value . '%' : '—' }}
                        <span class="text-muted small d-block">من قائمة التحقق (للقراءة)</span>
                    </div>
                </div>
                <div class="mb-3 col-md-3">
                    <x-form.input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        name="quality_value"
                        label="الجودة"
                        :value="old('quality_value', $activity->quality_value ?? '')"
                    />
                </div>
                <div class="mb-3 col-md-3">
                    <x-form.input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        name="closure_value"
                        label="الإغلاق"
                        :value="old('closure_value', $activity->closure_value ?? '')"
                    />
                </div>
                <div class="mb-3 col-md-3">
                    <x-form.input
                        type="number"
                        step="0.01"
                        max="0"
                        name="deduction_value"
                        label="الخصم (سالب أو صفر)"
                        :value="old('deduction_value', $activity->deduction_value ?? 0)"
                    />
                </div>
                <div class="mb-0 col-md-4">
                    <label class="form-label">KPI</label>
                    <div class="form-control-plaintext">
                        @if ($activity->kpi_value !== null)
                            <strong>{{ $activity->kpi_value }}</strong>
                            @if ($activity->kpi_rating)
                                <span class="badge bg-label-info">{{ $activity->kpi_rating }}</span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        <span class="text-muted small d-block">يُحسب تلقائياً بعد الحفظ</span>
                    </div>
                </div>
                <div class="mb-0 col-md-8">
                    <label class="form-label">حالة التحقق</label>
                    <div class="form-control-plaintext">
                        <span class="{{ $activity->is_verified ? 'text-success' : 'text-warning' }}">{{ $activity->verification_status }}</span>
                        <span class="text-muted small d-block">معلوماتي — لا يمنع الإرسال</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
