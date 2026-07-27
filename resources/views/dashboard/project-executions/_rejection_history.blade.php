@php
    $statusLabels = \App\Models\ProjectExecution::workflowStatusLabels();
    $rejections = $execution->rejections ?? collect();
@endphp

@if (($canViewRejectionHistory ?? false) && ($execution->hasPendingReturnNotice() || $rejections->isNotEmpty()))
    <div class="execution-rejection-history mt-4">
        @if ($execution->hasPendingReturnNotice())
            <div class="alert alert-warning py-2 mb-3">
                <strong>أُرجِع المسار للمراجعة.</strong>
                @if ($execution->return_target)
                    الإجراء: {{ \App\Models\Project::returnTargetLabel($execution->return_target) }} —
                @endif
                السبب: {{ $execution->rejection_reason }}
                @if ($execution->rejectedByUser)
                    <span class="d-block small mt-1">بواسطة: {{ $execution->rejectedByUser->name }} — {{ $execution->rejected_at?->format('Y-m-d H:i') }}</span>
                @endif
            </div>
        @endif

        @if ($rejections->isNotEmpty())
            <h6 class="mb-2">سجل الرفض والإرجاع</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 execution-rejection-history-table">
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
        .execution-rejection-history-table th,
        .execution-rejection-history-table td {
            font-size: 0.8125rem;
        }

        .execution-rejection-history-table td:nth-child(5) {
            min-width: 12rem;
            white-space: normal;
        }
    </style>
    @endpush
@endonce
