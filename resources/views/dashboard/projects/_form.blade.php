@php
    $selectedCoordinatorMode = old('coordinator_mode', $coordinatorMode ?? 'person');
    $isEditing = isset($project) && $project->exists;
    $lockTeamFields = (bool) ($lockTeamFieldsForMonitoringDirector ?? false);
    $lockedManagerId = $lockProjectManager ? $currentPerson->id : ($isEditing ? $project->project_manager_id : old('project_manager_id'));
    $projectTypeOptions = collect($projectTypes)->mapWithKeys(fn ($label) => [$label => $label])->all();
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
                @if (! empty($projectTypes))
                    <x-form.select
                        name="project_type"
                        label="نوع المشروع"
                        :options="$projectTypeOptions"
                        :value="$project->project_type ?? ''"
                        required
                    />
                @else
                    <x-form.input
                        name="project_type"
                        label="نوع المشروع"
                        :value="$project->project_type ?? ''"
                        required
                    />
                @endif
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="funder_id"
                    label="الجهة المانحة"
                    :optionsId="$funders"
                    :value="$project->funder_id ?? ''"
                    searchable
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.select
                    name="procurement_rep_id"
                    label="مندوب المشتريات"
                    :optionsId="$people"
                    :value="$project->procurement_rep_id ?? ''"
                    searchable
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                @if ($lockProjectManager)
                    <label class="form-label">مدير المشروع</label>
                    <input type="text" class="form-control" value="{{ $currentPerson->name }}" readonly>
                    <input type="hidden" name="project_manager_id" value="{{ $currentPerson->id }}">
                @elseif ($lockTeamFields && $isEditing)
                    <label class="form-label">مدير المشروع</label>
                    <input type="text" class="form-control" value="{{ $project->projectManager?->name ?? '—' }}" readonly>
                    <input type="hidden" name="project_manager_id" value="{{ $project->project_manager_id }}">
                @else
                    <x-form.select
                        name="project_manager_id"
                        id="project-manager-id"
                        label="مدير المشروع"
                        :optionsId="$projectManagers"
                        :value="$project->project_manager_id ?? ''"
                        searchable
                        required
                    />
                @endif
            </div>

            {{-- المنسق --}}
            <div class="mb-4 col-md-12">
                @if ($lockTeamFields && $isEditing)
                    <label class="form-label d-block">المنسق</label>
                    <div class="alert alert-secondary py-2 mb-0">
                        @if ($project->isSelfCoordinator())
                            <strong>{{ $project->projectManager?->name }}</strong> — مدير المشروع / منسق
                        @elseif (filled($project->coordinator_external_name))
                            <strong>{{ $project->coordinator_external_name }}</strong> — منسق خارجي
                        @else
                            <strong>{{ $project->coordinator?->name ?? '—' }}</strong> — منسق من النظام
                        @endif
                        <span class="d-block small text-muted mt-1">لا يمكن تعديل الفريق أو المنسق من حساب مدير الرقابة.</span>
                    </div>
                    <input type="hidden" name="coordinator_mode" value="{{ $project->coordinatorMode() === 'none' ? 'person' : $project->coordinatorMode() }}">
                    @if ($project->coordinator_id)
                        <input type="hidden" name="coordinator_id" value="{{ $project->coordinator_id }}">
                    @endif
                    @if ($project->coordinator_external_name)
                        <input type="hidden" name="coordinator_external_name" value="{{ $project->coordinator_external_name }}">
                    @endif
                @else
                <label class="form-label d-block">المنسق</label>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <div class="form-check">
                        <input class="form-check-input coordinator-mode-radio" type="radio" name="coordinator_mode" id="coordinator-mode-self" value="self" @checked($selectedCoordinatorMode === 'self')>
                        <label class="form-check-label" for="coordinator-mode-self">مدير المشروع هو المنسق</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input coordinator-mode-radio" type="radio" name="coordinator_mode" id="coordinator-mode-person" value="person" @checked($selectedCoordinatorMode === 'person')>
                        <label class="form-check-label" for="coordinator-mode-person">منسق من النظام</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input coordinator-mode-radio" type="radio" name="coordinator_mode" id="coordinator-mode-external" value="external" @checked($selectedCoordinatorMode === 'external')>
                        <label class="form-check-label" for="coordinator-mode-external">منسق خارجي (بدون حساب)</label>
                    </div>
                </div>

                <div id="coordinator-person-wrap" class="row {{ $selectedCoordinatorMode === 'person' ? '' : 'd-none' }}">
                    <div class="col-md-6">
                        <x-form.select
                            name="coordinator_id"
                            id="coordinator-id"
                            label="اختر المنسق"
                            :optionsId="$coordinators"
                            :value="$project->coordinator_id ?? ''"
                            searchable
                        />
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div id="fill-on-behalf-wrap" class="form-check mb-3 {{ ($selectedCoordinatorMode === 'person' && ($lockProjectManager ?? false)) ? '' : 'd-none' }}">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="fill_on_behalf"
                                id="fill-on-behalf-form"
                                value="1"
                                @checked(old('fill_on_behalf') || ($isEditing && ($project->coordinator_filled_by ?? false)))
                            >
                            <label class="form-check-label" for="fill-on-behalf-form">
                                أعبّئ قائمة التحقق نيابةً عن المنسق
                            </label>
                        </div>
                    </div>
                </div>

                <div id="coordinator-external-wrap" class="row {{ $selectedCoordinatorMode === 'external' ? '' : 'd-none' }}">
                    <div class="col-md-6">
                        <x-form.input
                            name="coordinator_external_name"
                            id="coordinator-external-name"
                            label="اسم المنسق الخارجي"
                            :value="$project->coordinator_external_name ?? ''"
                        />
                    </div>
                    <div class="col-md-12">
                        <div class="alert alert-info py-2 mb-0">
                            المنسق الخارجي بلا حساب — يمكنك تعبئة قائمة التحقق مباشرةً في أسفل النموذج.
                        </div>
                    </div>
                </div>

                <div id="coordinator-self-hint" class="alert alert-info py-2 {{ $selectedCoordinatorMode === 'self' ? '' : 'd-none' }}">
                    @if ($lockProjectManager ?? false)
                        أنت مدير المشروع والمنسق — ستظهر قائمة التحقق في أسفل النموذج لتعبئتها مع حفظ المشروع.
                    @else
                        <strong>مدير المشروع هو المنسق</strong> — يعبّئ قائمة التحقق بنفسه عند إنشاء/تعديل المشروع.
                    @endif
                </div>
                @endif
            </div>

            <div class="mb-4 col-md-4">
                <x-form.select
                    name="center_id"
                    id="center_id"
                    label="المركز"
                    :optionsId="$centers"
                    :value="$selectedCenterId ?? ''"
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <label class="form-label" for="department_id">
                    الدائرة
                    <span class="text-danger" style="font-size: 12px;"><i class="fa fa-asterisk"></i></span>
                </label>
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
                <label class="form-label" for="section_id">
                    القسم
                    <span class="text-danger" style="font-size: 12px;"><i class="fa fa-asterisk"></i></span>
                </label>
                <select
                    name="section_id"
                    id="section_id"
                    class="form-select select2-searchable @error('section_id') is-invalid @enderror"
                    required
                >
                    <option value="">إختر القيمة</option>
                </select>
                @error('section_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-4 col-md-4">
                <x-form.input
                    type="date"
                    name="planned_start_date"
                    label="تاريخ بداية التنفيذ المخطط"
                    :value="isset($project) && $project->planned_start_date ? $project->planned_start_date->format('Y-m-d') : ''"
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.input
                    type="date"
                    name="planned_end_date"
                    label="تاريخ نهاية التنفيذ المخطط"
                    :value="isset($project) && $project->planned_end_date ? $project->planned_end_date->format('Y-m-d') : ''"
                    required
                />
            </div>
            <div class="mb-4 col-md-4">
                <x-form.input
                    type="date"
                    name="execution_start_date"
                    label="تاريخ بدء التنفيذ"
                    :value="isset($project) && $project->execution_start_date ? $project->execution_start_date->format('Y-m-d') : ''"
                    required
                />
            </div>
            <div class="mb-4 col-md-12">
                <x-form.textarea
                    name="location"
                    label="الموقع الجغرافي"
                    :value="$project->location ?? ''"
                    :rows="1"
                    class="project-location-field"
                    required
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
                    min="0"
                    required
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    name="execution_zones"
                    id="execution_zones"
                    label="عدد مناطق التنفيذ"
                    :value="$project->execution_zones ?? ''"
                    min="0"
                    required
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    name="estimated_duration"
                    label="المدة الزمنية المقدّرة (بالأيام)"
                    :value="$project->estimated_duration ?? ''"
                    required
                />
            </div>
            <div class="col-12">
                <div id="execution-regions-panel" class="execution-regions-panel mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <label class="form-label mb-0">مناطق التنفيذ — مكاتب الجمعية</label>
                        <span class="badge bg-label-primary" id="execution-regions-count-badge">0 منطقة</span>
                    </div>
                    <div id="execution-regions-fields" class="row g-3"></div>
                    @error('execution_regions')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @error('execution_regions.*')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-12">
                <div class="project-financial-panel mb-4">
                    <label class="form-label mb-3">البيانات المالية</label>
                    <div class="row">
                        <div class="mb-4 col-md-3">
                            <x-form.input
                                type="number"
                                step="0.01"
                                name="project_budget"
                                label="موازنة المشروع (بالعملة الأصلية)"
                                :value="old('project_budget', $project->project_budget ?? '')"
                                min="0"
                                required
                            />
                        </div>
                        <div class="mb-4 col-md-3">
                            <label class="form-label" for="currency_id">العملة</label>
                            <select
                                name="currency_id"
                                id="currency_id"
                                class="form-select @error('currency_id') is-invalid @enderror"
                                required
                            >
                                <option value="">— اختر العملة —</option>
                                @foreach ($currencies ?? [] as $currency)
                                    <option
                                        value="{{ $currency->id }}"
                                        @selected((string) old('currency_id', $project->currency_id ?? '') === (string) $currency->id)
                                    >
                                        {{ $currency->name }} ({{ $currency->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-3">
                            <x-form.input
                                type="number"
                                step="0.01"
                                name="revenue_amount"
                                label="مبلغ الإيرادات (بالعملة الأصلية)"
                                :value="old('revenue_amount', $project->revenue_amount ?? '')"
                                min="0"
                            />
                        </div>
                        <div class="mb-4 col-md-3">
                            <x-form.input
                                type="number"
                                step="0.01"
                                name="net_amount"
                                label="صافي المبلغ (بالعملة الأصلية)"
                                :value="old('net_amount', $project->net_amount ?? '')"
                            />
                            <div class="form-text">يُحسب تلقائياً: موازنة − إيرادات (قابل للتعديل)</div>
                        </div>
                        <div class="mb-4 col-md-3">
                            <x-form.input
                                type="number"
                                step="0.000001"
                                name="exchange_rate"
                                label="سعر الصرف (للشيكل)"
                                :value="old('exchange_rate', $project->exchange_rate ?? '')"
                                min="0"
                            />
                            <div class="form-text">يُعبَّأ من جدول العملات ويمكن تعديله</div>
                        </div>
                        <div class="mb-4 col-md-3">
                            <x-form.input
                                type="number"
                                step="0.01"
                                name="execution_amount_ils"
                                label="المبلغ للتنفيذ (بالشيكل)"
                                :value="old('execution_amount_ils', $project->execution_amount_ils ?? '')"
                                min="0"
                            />
                            <div class="form-text">يُحسب تلقائياً: صافي × سعر الصرف (قابل للتعديل)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($canEditCoordinatorChecklistInForm ?? false)
    @if ($isEditing ?? false)
        @include('dashboard.projects._checklist_attachment_delete_modal')
        @include('dashboard.projects._checklist_attachment_upload_modal')
    @endif
    <div
        id="coordinator-checklist-section"
        class="card mb-4 {{ ($showCoordinatorChecklistInitially ?? false) ? '' : 'd-none' }}"
    >
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">ثالثاً — قائمة تحقق المنسق</h5>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span id="coordinator-checklist-badge" class="badge bg-label-primary">تُحفظ مع المشروع</span>
                <span>نسبة الجاهزية: <strong class="checklist-overall-pct">—</strong></span>
            </div>
        </div>
        <div class="card-body">
            <p id="coordinator-checklist-intro" class="text-muted small mb-3"></p>
            @include('dashboard.projects._coordinator_checklist')
        </div>
    </div>
@endif

<div class="mt-2">
    <button type="submit" class="btn btn-primary me-3">
        حفظ
    </button>
    <a href="{{ route('dashboard.projects.index') }}" class="btn btn-label-secondary">
        إلغاء
    </a>
</div>

@push('scripts')
<script src="{{ asset('js/project-execution-regions.js') }}"></script>
<script src="{{ asset('js/project-financial-fields.js') }}"></script>
<script src="{{ asset('js/org-cascade.js') }}"></script>
<script src="{{ asset('js/checklist-status-style.js') }}"></script>
<script src="{{ asset('js/checklist-attachment-ui.js') }}"></script>
<script src="{{ asset('js/checklist-readiness.js') }}"></script>
<script src="{{ asset('js/checklist-person-required.js') }}"></script>
<script src="{{ asset('js/checklist-closure-docs.js') }}"></script>
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('css/searchable-select.css') }}">
        <style>
            .execution-regions-panel {
                border: 1px solid rgba(67, 89, 113, 0.12);
                border-radius: 0.5rem;
                padding: 1rem 1.125rem;
                background: rgba(67, 89, 113, 0.03);
            }

            .project-financial-panel {
                border: 1px solid rgba(67, 89, 113, 0.12);
                border-radius: 0.5rem;
                padding: 1rem 1.125rem;
                background: rgba(67, 89, 113, 0.03);
            }

            .project-location-field {
                resize: vertical;
                min-height: calc(1.5em + 1.625rem + 2px);
                overflow-y: hidden;
            }

            .execution-region-field .region-index-badge {
                min-width: 2rem;
            }
        </style>
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/vendor/libs/select2/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/searchable-select.js') }}"></script>
    @endpush
@endonce
<script>
(function () {
    const modeRadios = document.querySelectorAll('.coordinator-mode-radio');
    const personWrap = document.getElementById('coordinator-person-wrap');
    const externalWrap = document.getElementById('coordinator-external-wrap');
    const selfHint = document.getElementById('coordinator-self-hint');
    const fillOnBehalfWrap = document.getElementById('fill-on-behalf-wrap');
    const fillOnBehalfCheckbox = document.getElementById('fill-on-behalf-form');
    const coordinatorSelect = document.getElementById('coordinator-id');
    const externalInput = document.getElementById('coordinator-external-name');
    const managerSelect = document.getElementById('project-manager-id');
    const checklistSection = document.getElementById('coordinator-checklist-section');
    const checklistIntro = document.getElementById('coordinator-checklist-intro');
    const lockedManagerId = @json($lockedManagerId);
    const canFillOnBehalf = @json((bool) ($lockProjectManager ?? false));
    const coordinatorUserMap = @json($coordinatorUserMap ?? []);
    let previousCoordinatorKey = null;

    function selectedCoordinatorHasUser() {
        const coordinatorId = coordinatorSelect?.value || '';

        return Boolean(coordinatorUserMap[coordinatorId]);
    }

    function canShowFillOnBehalfOption() {
        const mode = document.querySelector('.coordinator-mode-radio:checked')?.value || 'person';

        return mode === 'person' && canFillOnBehalf && coordinatorSelect?.value && !selectedCoordinatorHasUser();
    }

    function getCoordinatorKey() {
        const mode = document.querySelector('.coordinator-mode-radio:checked')?.value || 'person';
        if (mode === 'person') {
            return `${mode}:${coordinatorSelect?.value || ''}:${fillOnBehalfCheckbox?.checked ? '1' : '0'}`;
        }
        if (mode === 'external') {
            return `${mode}:${externalInput?.value?.trim() || ''}`;
        }
        return `${mode}:${getManagerId()}`;
    }

    function clearCoordinatorChecklistInputs() {
        document.querySelectorAll('.coordinator-checklist-input').forEach((input) => {
            if (input.tagName === 'SELECT') {
                input.value = input.dataset.defaultValue || 'not_ready';
            } else {
                input.value = '';
            }
            input.disabled = true;
        });

        if (checklistSection && window.refreshChecklistReadiness) {
            window.refreshChecklistReadiness(checklistSection);
        }
        if (checklistSection && window.initChecklistStatusStyle) {
            window.initChecklistStatusStyle(checklistSection);
        }
    }

    function setChecklistInputsEnabled(enabled) {
        document.querySelectorAll('.coordinator-checklist-input').forEach((input) => {
            input.disabled = !enabled;
        });
    }

    function shouldShowCoordinatorChecklist() {
        const mode = document.querySelector('.coordinator-mode-radio:checked')?.value || 'person';
        if (mode === 'self' || mode === 'external') {
            return true;
        }
        if (mode === 'person') {
            return Boolean(canShowFillOnBehalfOption() && fillOnBehalfCheckbox?.checked);
        }
        return false;
    }

    function updateChecklistIntro() {
        if (!checklistIntro) {
            return;
        }

        const mode = document.querySelector('.coordinator-mode-radio:checked')?.value || 'person';

        if (mode === 'self') {
            checklistIntro.textContent = 'بصفتك مدير المشروع والمنسق، عبّئ القائمة هنا ثم احفظ مرة واحدة.';
            return;
        }

        if (mode === 'external') {
            const name = externalInput?.value?.trim() || 'المنسق الخارجي';
            checklistIntro.textContent = `تعبئة نيابةً عن ${name} — سيُسجَّل أنك عبّأت القائمة.`;
            return;
        }

        const selected = coordinatorSelect?.selectedOptions?.[0]?.textContent?.trim() || 'المنسق';
        checklistIntro.textContent = `تعبئة نيابةً عن ${selected} — سيُسجَّل أنك عبّأت القائمة.`;
    }

    function syncCoordinatorChecklistSection() {
        if (!checklistSection) {
            return;
        }

        const show = shouldShowCoordinatorChecklist();
        checklistSection.classList.toggle('d-none', !show);
        setChecklistInputsEnabled(show);
        updateChecklistIntro();

        if (show) {
            if (window.initChecklistReadiness) {
                window.initChecklistReadiness(checklistSection);
            }
            if (window.initChecklistStatusStyle) {
                window.initChecklistStatusStyle(checklistSection);
            }
            if (window.initChecklistPersonRequired) {
                window.initChecklistPersonRequired(checklistSection);
            }
            if (window.initChecklistAttachmentUi) {
                window.initChecklistAttachmentUi(checklistSection);
            }
            if (window.initChecklistClosureDocs) {
                window.initChecklistClosureDocs(checklistSection);
            }
            if (window.refreshChecklistReadiness) {
                window.refreshChecklistReadiness(checklistSection);
            }
        }

        const currentKey = getCoordinatorKey();
        if (previousCoordinatorKey !== null && previousCoordinatorKey !== currentKey) {
            clearCoordinatorChecklistInputs();
            if (show) {
                setChecklistInputsEnabled(true);
            }
        }
        previousCoordinatorKey = currentKey;
    }

    function getManagerId() {
        if (lockedManagerId) {
            return String(lockedManagerId);
        }
        return managerSelect ? managerSelect.value : '';
    }

    function syncCoordinatorMode() {
        const mode = document.querySelector('.coordinator-mode-radio:checked')?.value || 'person';

        personWrap.classList.toggle('d-none', mode !== 'person');
        externalWrap.classList.toggle('d-none', mode !== 'external');
        selfHint.classList.toggle('d-none', mode !== 'self');
        fillOnBehalfWrap?.classList.toggle('d-none', !canShowFillOnBehalfOption());

        if (coordinatorSelect) {
            coordinatorSelect.disabled = mode !== 'person';
            if (mode !== 'person') {
                coordinatorSelect.value = '';
            }
        }

        if (externalInput) {
            externalInput.disabled = mode !== 'external';
            if (mode !== 'external') {
                externalInput.value = '';
            }
        }

        if (fillOnBehalfCheckbox && !canShowFillOnBehalfOption()) {
            fillOnBehalfCheckbox.checked = false;
        }

        if (mode === 'person' && window.initSearchableSelects && personWrap) {
            window.initSearchableSelects(personWrap);
        }

        syncCoordinatorChecklistSection();
    }

    modeRadios.forEach((radio) => radio.addEventListener('change', syncCoordinatorMode));
    fillOnBehalfCheckbox?.addEventListener('change', syncCoordinatorChecklistSection);
    coordinatorSelect?.addEventListener('change', syncCoordinatorChecklistSection);
    externalInput?.addEventListener('input', syncCoordinatorChecklistSection);
    if (managerSelect) {
        managerSelect.addEventListener('change', syncCoordinatorMode);
    }

    previousCoordinatorKey = getCoordinatorKey();
    syncCoordinatorMode();
})();

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.initOrgCascade === 'function') {
        window.initOrgCascade({
            departmentsUrl: @json($departmentsByCenterUrl),
            sectionsUrl: @json($sectionsByDepartmentUrl),
            allSectionsUrl: @json($allSectionsUrl),
            showAllSections: true,
            selectedCenterId: @json($selectedCenterId ?? ''),
            selectedDepartmentId: @json($selectedDepartmentId ?? ''),
            selectedSectionId: @json($selectedSectionId ?? ''),
        });
    }

    const locationField = document.querySelector('.project-location-field');

    function autoGrowLocationField() {
        if (!locationField) {
            return;
        }

        locationField.style.height = 'auto';
        locationField.style.height = `${locationField.scrollHeight}px`;
    }

    locationField?.addEventListener('input', autoGrowLocationField);
    autoGrowLocationField();

    if (typeof window.initProjectExecutionRegions === 'function') {
        window.initProjectExecutionRegions({
            offices: @json($associationOffices ?? []),
            savedRegions: @json(old('execution_regions', isset($project) ? ($project->execution_regions ?? []) : [])),
        });
    }

    if (typeof window.initProjectFinancialFields === 'function') {
        window.initProjectFinancialFields({
            rates: @json(($currencyRatesJson ?? collect())->toArray()),
        });
    }
});
</script>
@endpush
