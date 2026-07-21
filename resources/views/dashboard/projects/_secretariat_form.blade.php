@php
    $projectNumberSeq = old(
        'project_number_seq',
        $project->project_number
            ? (\App\Models\Project::sequenceFromProjectNumber($project->project_number) ?? '')
            : ($nextProjectNumberSeq ?? '')
    );
@endphp

<div class="card border-primary mb-0">
    <div class="card-header bg-label-primary">
        <h6 class="mb-0">تعبئة رقم ومرفق التخصيص — سكرتاريا الدائرة</h6>
    </div>
    <div class="card-body">
        @if ($project->hasCompletedSecretariatPhase())
            <p class="text-muted small mb-3">تصحيح بيانات التخصيص فقط — الإرسال التالي لمدير القسم مباشرة.</p>
        @endif
        <form
            action="{{ route('dashboard.projects.fill-secretariat', $project) }}"
            method="post"
            enctype="multipart/form-data"
            class="row g-3"
        >
            @csrf

            <div class="col-md-4">
                <label class="form-label" for="secretariat_project_number_seq">
                    رقم المشروع (التخصيص)
                    <span class="text-danger" style="font-size: 12px;"><i class="fa fa-asterisk"></i></span>
                </label>
                <div class="input-group">
                    <span class="input-group-text user-select-none fw-semibold">P-</span>
                    <input
                        type="number"
                        name="project_number_seq"
                        id="secretariat_project_number_seq"
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
                <div id="secretariat-project-number-feedback" class="form-text"></div>
                <div class="form-text text-muted">
                    البادئة P- ثابتة — عدّل الرقم فقط. يُفحَص التكرار فوراً عند مغادرة الحقل.
                </div>
            </div>

            <div class="col-md-8">
                <label class="form-label" for="secretariat_allocation_image">
                    مرفق التخصيص
                    <span class="text-danger" style="font-size: 12px;"><i class="fa fa-asterisk"></i></span>
                </label>
                @if ($project->allocation_image_path)
                    <div class="mb-2">
                        @if ($project->isAllocationImagePreview())
                            <a href="{{ $project->allocationImageUrl() }}" target="_blank" rel="noopener">
                                <img
                                    src="{{ $project->allocationImageUrl() }}"
                                    alt="مرفق التخصيص"
                                    class="rounded border"
                                    style="max-height: 120px; max-width: 100%;"
                                >
                            </a>
                        @else
                            <a href="{{ $project->allocationImageUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                <i class="bx bx-download"></i>
                                {{ $project->allocationAttachmentBasename() }}
                            </a>
                        @endif
                        <div class="form-text">المرفق الحالي — يمكنك استبداله برفع ملف جديد.</div>
                    </div>
                @endif
                <input
                    type="file"
                    name="allocation_image"
                    id="secretariat_allocation_image"
                    class="form-control @error('allocation_image') is-invalid @enderror"
                    accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,image/jpeg,image/png,image/webp,application/pdf"
                    @if (! $project->allocation_image_path) required @endif
                >
                @error('allocation_image')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">صورة أو مستند: JPG, PNG, WEBP, PDF, Word, Excel — حتى 10MB.</div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    @if ($project->isSelfCoordinator())
                        حفظ وإرسال لمدير القسم
                    @else
                        حفظ وإرسال للمنسق
                    @endif
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const projectNumberInput = document.getElementById('secretariat_project_number_seq');
    const projectNumberFeedback = document.getElementById('secretariat-project-number-feedback');
    const checkProjectNumberUrl = @json($checkProjectNumberUrl ?? route('dashboard.projects.check-project-number'));
    const exceptProjectId = @json($project->id);
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
})();
</script>
@endpush
