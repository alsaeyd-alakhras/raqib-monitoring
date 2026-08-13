@php
    $projectModel = $project ?? null;
    $allocationLocked = (bool) ($allocationFieldsLocked ?? false);
    $projectNumberSeq = old(
        'project_number_seq',
        $projectModel?->project_number
            ? (\App\Models\Project::sequenceFromProjectNumber($projectModel->project_number) ?? '')
            : ($nextProjectNumberSeq ?? '')
    );
    $checkProjectNumberUrl = $checkProjectNumberUrl ?? route('dashboard.projects.check-project-number');
    $exceptProjectId = $exceptProjectId ?? ($projectModel?->id);
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="project_number_seq">
            رقم المشروع (التخصيص)
            @if (! $allocationLocked)
                <span class="text-danger" style="font-size: 12px;"><i class="fa fa-asterisk"></i></span>
            @endif
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
                @if (! $allocationLocked) required @endif
                @if ($allocationLocked) readonly @endif
                inputmode="numeric"
                placeholder="1"
            >
        </div>
        @error('project_number_seq')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <div id="project-number-feedback" class="form-text"></div>
        @if (! $allocationLocked)
            <div class="form-text text-muted">
                البادئة P- ثابتة — عدّل الرقم فقط. يُفحَص التكرار فوراً عند مغادرة الحقل.
            </div>
        @endif
    </div>

    <div class="col-md-8">
        <label class="form-label" for="allocation_image">
            مرفق التخصيص
            @if (! $allocationLocked && ! ($projectModel?->allocation_image_path))
                <span class="text-danger" style="font-size: 12px;"><i class="fa fa-asterisk"></i></span>
            @endif
        </label>
        @if ($projectModel?->allocation_image_path)
            <div class="mb-2">
                @if ($projectModel->isAllocationImagePreview())
                    <a href="{{ $projectModel->allocationImageUrl() }}" target="_blank" rel="noopener">
                        <img
                            src="{{ $projectModel->allocationImageUrl() }}"
                            alt="مرفق التخصيص"
                            class="rounded border"
                            style="max-height: 120px; max-width: 100%;"
                        >
                    </a>
                @else
                    <a href="{{ $projectModel->allocationImageUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                        <i class="bx bx-download"></i>
                        {{ $projectModel->allocationAttachmentBasename() }}
                    </a>
                @endif
                @if (! $allocationLocked)
                    <div class="form-text">المرفق الحالي — يمكنك استبداله برفع ملف جديد.</div>
                @endif
            </div>
        @endif
        @if (! $allocationLocked)
            <input
                type="file"
                name="allocation_image"
                id="allocation_image"
                class="form-control @error('allocation_image') is-invalid @enderror"
                accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,image/jpeg,image/png,image/webp,application/pdf"
                @if (! ($projectModel?->allocation_image_path)) required @endif
            >
            @error('allocation_image')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <div class="form-text">صورة أو مستند: JPG, PNG, WEBP, PDF, Word, Excel — حتى 10MB.</div>
        @endif
    </div>
</div>

@if (! $allocationLocked)
    @push('scripts')
    <script>
    (function () {
        const projectNumberInput = document.getElementById('project_number_seq');
        const projectNumberFeedback = document.getElementById('project-number-feedback');
        const checkProjectNumberUrl = @json($checkProjectNumberUrl);
        const exceptProjectId = @json($exceptProjectId);
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
@endif
