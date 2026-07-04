@php
    $selectedCoordinatorMode = old('coordinator_mode', $coordinatorMode ?? 'person');
    $isEditing = isset($project) && $project->exists;
    $projectNumberSeq = old(
        'project_number_seq',
        $isEditing
            ? (\App\Models\Project::sequenceFromProjectNumber($project->project_number ?? '') ?? '')
            : ($nextProjectNumberSeq ?? '')
    );
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
                <label class="form-label" for="project_number_seq">
                    رقم المشروع
                    <span class="text-danger" style="font-size: 12px;"><i class="fa fa-asterisk"></i></span>
                </label>
                <div class="input-group">
                    <span class="input-group-text user-select-none fw-semibold">P-</span>
                    <input
                        type="number"
                        name="project_number_seq"
                        id="project_number_seq"
                        class="form-control @error('project_number_seq') is-invalid @enderror"
                        value="{{ $projectNumberSeq }}"
                        min="1"
                        step="1"
                        required
                        inputmode="numeric"
                        placeholder="1"
                    >
                </div>
                @error('project_number_seq')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div id="project-number-feedback" class="form-text"></div>
                <div class="form-text text-muted">
                    البادئة P- ثابتة — عدّل الرقم فقط. يُفحَص التكرار فوراً عند مغادرة الحقل.
                </div>
            </div>
            <div class="mb-4 col-md-4">
                @if (! empty($projectTypes))
                    <x-form.select
                        name="project_type"
                        label="نوع المشروع"
                        :options="$projectTypeOptions"
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
                @if ($lockProjectManager)
                    <label class="form-label">مدير المشروع</label>
                    <input type="text" class="form-control" value="{{ $currentPerson->name }}" readonly>
                    <input type="hidden" name="project_manager_id" value="{{ $currentPerson->id }}">
                @else
                    <x-form.select
                        name="project_manager_id"
                        id="project-manager-id"
                        label="مدير المشروع"
                        :optionsId="$projectManagers"
                        :value="$project->project_manager_id ?? ''"
                        required
                    />
                @endif
            </div>

            {{-- المنسق --}}
            <div class="mb-4 col-md-12">
                <label class="form-label d-block">المنسق</label>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <div class="form-check">
                        <input class="form-check-input coordinator-mode-radio" type="radio" name="coordinator_mode" id="coordinator-mode-self" value="self" @checked($selectedCoordinatorMode === 'self')>
                        <label class="form-check-label" for="coordinator-mode-self">أنا المنسق (مدير المشروع)</label>
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
                        />
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div id="fill-on-behalf-wrap" class="form-check mb-3 {{ $selectedCoordinatorMode === 'person' ? '' : 'd-none' }}">
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
                    أنت المنسق — ستظهر قائمة التحقق في أسفل النموذج لتعبئتها مع حفظ المشروع.
                </div>
            </div>

            <div class="mb-4 col-md-4">
                <x-form.select
                    name="center_id"
                    id="center_id"
                    label="المركز"
                    :optionsId="$centers"
                    :value="$selectedCenterId ?? ''"
                />
            </div>
            <div class="mb-4 col-md-4">
                <label class="form-label" for="department_id">الدائرة</label>
                <select
                    name="department_id"
                    id="department_id"
                    class="form-select @error('department_id') is-invalid @enderror"
                >
                    <option value="">إختر القيمة</option>
                </select>
                @error('department_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-4 col-md-4">
                <label class="form-label" for="section_id">القسم</label>
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
                    min="0"
                />
            </div>
            <div class="mb-4 col-md-3">
                <x-form.input
                    type="number"
                    name="execution_zones"
                    label="عدد مناطق التنفيذ"
                    :value="$project->execution_zones ?? ''"
                    min="0"
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
                    min="0"
                />
            </div>
        </div>
    </div>
</div>

@if ($canFillCoordinatorInForm ?? false)
    <div
        id="coordinator-checklist-section"
        class="card mb-4 {{ ($showCoordinatorChecklistInitially ?? false) ? '' : 'd-none' }}"
    >
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">ثالثاً — قائمة تحقق المنسق</h5>
            <span id="coordinator-checklist-badge" class="badge bg-label-primary">تُحفظ مع المشروع</span>
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
<script src="{{ asset('js/org-cascade.js') }}"></script>
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
    let previousCoordinatorKey = null;

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
                input.selectedIndex = 0;
            } else {
                input.value = '';
            }
            input.disabled = true;
        });
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
            return Boolean(fillOnBehalfCheckbox?.checked && coordinatorSelect?.value);
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
        fillOnBehalfWrap?.classList.toggle('d-none', mode !== 'person');

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

        if (fillOnBehalfCheckbox && mode !== 'person') {
            fillOnBehalfCheckbox.checked = false;
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

(function () {
    const projectNumberInput = document.getElementById('project_number_seq');
    const projectNumberFeedback = document.getElementById('project-number-feedback');
    const checkProjectNumberUrl = @json(route('dashboard.projects.check-project-number'));
    const exceptProjectId = @json($isEditing ? $project->id : null);
    let projectNumberAvailable = null;

    async function checkProjectNumberAvailability() {
        if (!projectNumberInput || !projectNumberFeedback) {
            return;
        }

        const value = projectNumberInput.value.trim();

        if (!value) {
            projectNumberFeedback.textContent = '';
            projectNumberFeedback.className = 'form-text';
            projectNumberInput.classList.remove('is-valid', 'is-invalid');
            projectNumberAvailable = null;
            return;
        }

        projectNumberFeedback.textContent = 'جاري التحقق...';
        projectNumberFeedback.className = 'form-text text-muted';
        projectNumberInput.classList.remove('is-valid', 'is-invalid');

        const params = new URLSearchParams({ project_number_seq: value });
        if (exceptProjectId) {
            params.set('except_id', String(exceptProjectId));
        }

        try {
            const response = await fetch(`${checkProjectNumberUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                throw new Error('check failed');
            }

            const data = await response.json();

            if (data.sequence) {
                projectNumberInput.value = data.sequence;
            }

            projectNumberAvailable = Boolean(data.valid && data.available);
            projectNumberInput.classList.toggle('is-valid', projectNumberAvailable);
            projectNumberInput.classList.toggle('is-invalid', !projectNumberAvailable);

            let message = data.message || '';
            if (!data.available && data.suggested_sequence) {
                message += ` — اقتراح: P-${data.suggested_sequence}`;
            }

            projectNumberFeedback.textContent = message;
            projectNumberFeedback.className = projectNumberAvailable
                ? 'form-text text-success'
                : 'form-text text-danger';
        } catch (error) {
            projectNumberAvailable = null;
            projectNumberFeedback.textContent = 'تعذّر التحقق من الرقم، حاول مرة أخرى.';
            projectNumberFeedback.className = 'form-text text-warning';
        }
    }

    projectNumberInput?.addEventListener('blur', checkProjectNumberAvailability);

    projectNumberInput?.closest('form')?.addEventListener('submit', function (event) {
        if (projectNumberAvailable === false) {
            event.preventDefault();
            projectNumberInput.classList.add('is-invalid');
            projectNumberFeedback.textContent = 'رقم المشروع غير متاح، غيّره قبل الحفظ.';
            projectNumberFeedback.className = 'form-text text-danger';
            projectNumberInput.focus();
        }
    });
})();

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.initOrgCascade === 'function') {
        window.initOrgCascade({
            departmentsUrl: @json($departmentsByCenterUrl),
            sectionsUrl: @json($sectionsByDepartmentUrl),
            selectedCenterId: @json($selectedCenterId ?? ''),
            selectedDepartmentId: @json($selectedDepartmentId ?? ''),
            selectedSectionId: @json($selectedSectionId ?? ''),
        });
    }
});
</script>
@endpush
