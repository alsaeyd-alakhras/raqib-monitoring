@php
    $workflowSteps = [
        'draft',
        'pending_coordinator',
        'coordinator_filling',
        'pending_project_manager',
        'pending_section_manager',
        'pending_dept_manager',
        'pending_monitoring_manager',
        'monitoring_in_progress',
        'pending_monitoring_confirmation',
        'passage_complete',
    ];
    $statusLabels = \App\Models\Project::workflowStatusLabels();
    $currentStatus = $project->workflow_status;
    $isRejected = $currentStatus === 'rejected';
    $currentIndex = $isRejected
        ? -1
        : array_search($currentStatus, $workflowSteps, true);
    $stepTimestamps = $project->workflowStepTimestamps();
@endphp

@once
    @push('styles')
    <style>
        .project-workflow-stepper {
            overflow-x: auto;
            padding-bottom: 0.25rem;
        }

        .project-workflow-stepper-track {
            display: flex;
            align-items: flex-start;
            gap: 0;
            min-width: max-content;
            padding: 0.5rem 0;
        }

        .project-workflow-step {
            flex: 1 1 0;
            min-width: 7rem;
            position: relative;
            text-align: center;
            padding: 0 0.35rem;
        }

        .project-workflow-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 1.05rem;
            inset-inline-start: calc(50% + 1.05rem);
            width: calc(100% - 2.1rem);
            height: 2px;
            background: rgba(67, 89, 113, 0.15);
            z-index: 0;
        }

        .project-workflow-step.is-done:not(:last-child)::after {
            background: var(--bs-primary);
        }

        .project-workflow-step-circle {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 50%;
            font-size: 0.8125rem;
            font-weight: 600;
            border: 2px solid rgba(67, 89, 113, 0.2);
            background: #fff;
            color: rgba(67, 89, 113, 0.55);
            margin-bottom: 0.5rem;
        }

        .project-workflow-step.is-done .project-workflow-step-circle {
            border-color: var(--bs-primary);
            background: var(--bs-primary);
            color: #fff;
        }

        .project-workflow-step.is-current .project-workflow-step-circle {
            border-color: var(--bs-primary);
            background: #fff;
            color: var(--bs-primary);
            box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.15);
        }

        .project-workflow-step-label {
            display: block;
            font-size: 0.6875rem;
            line-height: 1.35;
            color: rgba(67, 89, 113, 0.65);
            max-width: 6.5rem;
            margin-inline: auto;
        }

        .project-workflow-step.is-done .project-workflow-step-label,
        .project-workflow-step.is-current .project-workflow-step-label {
            color: var(--bs-body-color);
            font-weight: 600;
        }

        .project-workflow-step-date {
            display: block;
            font-size: 0.625rem;
            line-height: 1.35;
            color: rgba(67, 89, 113, 0.55);
            max-width: 6.5rem;
            margin: 0.25rem auto 0;
        }

        .project-workflow-step.is-done .project-workflow-step-date {
            color: rgba(67, 89, 113, 0.7);
        }
    </style>
    @endpush
@endonce

@if ($isRejected)
    <div class="alert alert-danger py-2 mb-3">
        <strong>{{ $statusLabels['rejected'] ?? 'مرفوض' }}</strong>
        — المشروع خارج مسار الاعتماد المعتاد.
    </div>
@endif

<div class="project-workflow-stepper">
    <div class="project-workflow-stepper-track">
        @foreach ($workflowSteps as $index => $stepKey)
            @php
                $stepClass = match (true) {
                    $isRejected => 'is-upcoming',
                    $currentIndex !== false && $index < $currentIndex => 'is-done',
                    $currentIndex !== false && $index === $currentIndex => 'is-current',
                    default => 'is-upcoming',
                };
                $timestamp = $stepTimestamps[$stepKey] ?? null;
                $stepAt = $timestamp['at'] ?? null;
                $stepBy = $timestamp['by'] ?? null;
            @endphp
            <div class="project-workflow-step {{ $stepClass }}">
                <span class="project-workflow-step-circle">{{ $index + 1 }}</span>
                <span class="project-workflow-step-label">{{ $statusLabels[$stepKey] ?? $stepKey }}</span>
                @if ($stepClass === 'is-done' && $stepAt)
                    <span class="project-workflow-step-date" title="{{ $stepBy?->name ?? '' }}">
                        {{ $stepAt->format('Y-m-d H:i') }}
                    </span>
                @endif
            </div>
        @endforeach
    </div>
</div>
