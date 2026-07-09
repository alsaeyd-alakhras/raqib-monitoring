@php
    $statusLabels = \App\Models\Project::workflowStatusLabels();
    $rejections = $project->rejections ?? collect();
@endphp

@if (($canViewRejectionHistory ?? false) && ($project->hasPendingReturnNotice() || $rejections->isNotEmpty()))
    <div class="project-rejection-history mt-4">
        @if ($project->hasPendingReturnNotice())
            <div class="alert alert-warning py-2 mb-3">
                <strong>أُرجِع المشروع للمراجعة.</strong>
                @if ($project->return_target)
                    الإجراء: {{ \App\Models\Project::returnTargetLabel($project->return_target) }} —
                @endif
                السبب: {{ $project->rejection_reason }}
                @if ($project->rejectedByUser)
                    <span class="d-block small mt-1">بواسطة: {{ $project->rejectedByUser->name }} — {{ $project->rejected_at?->format('Y-m-d H:i') }}</span>
                @endif
            </div>
        @endif

        @if ($rejections->isNotEmpty())
            <h6 class="mb-2">سجل الرفض والإرجاع</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 project-rejection-history-table">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">التاريخ</th>
                            <th scope="col">الإجراء</th>
                            <th scope="col">أُرجِع إلى</th>
                            <th scope="col">مسؤولية النقص</th>
                            <th scope="col">السبب</th>
                            <th scope="col">بواسطة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rejections as $rejection)
                            <tr>
                                <td class="text-nowrap">{{ $rejection->rejected_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if ($rejection->return_target)
                                        {{ \App\Models\Project::returnTargetLabel($rejection->return_target) }}
                                    @else
                                        <span class="text-danger">رفض قاطع نهائي</span>
                                    @endif
                                </td>
                                <td>{{ $rejection->returnTargetPerson?->name ?? '—' }}</td>
                                <td>{{ \App\Models\Project::gapOwnerLabel($rejection->gap_owner) }}</td>
                                <td>{{ $rejection->rejection_reason }}</td>
                                <td>{{ $rejection->rejectedByUser?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif

@once
    @push('styles')
    <style>
        .project-rejection-history-table th,
        .project-rejection-history-table td {
            font-size: 0.8125rem;
        }

        .project-rejection-history-table td:nth-child(5) {
            min-width: 12rem;
            white-space: normal;
        }
    </style>
    @endpush
@endonce
