@php
    $compactLayout = $compactLayout ?? false;
    $showActions = $showActions ?? true;
    $showCoordinatorInSummary = $showCoordinatorInSummary ?? ($canViewCoordinatorData ?? false);
@endphp

@once
    @push('styles')
    <style>
        .project-summary-grid .project-summary-section {
            height: 100%;
        }

        .project-summary-section + .project-summary-section {
            margin-top: 1.25rem;
        }

        .project-summary-grid .project-summary-section {
            margin-top: 0 !important;
        }

        .project-summary-section-title {
            font-size: 0.8125rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--bs-primary);
            margin-bottom: 0.5rem;
        }

        .project-summary-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
            overflow: hidden;
            background: #fff;
        }

        .project-summary-table th {
            width: 12.5rem;
            max-width: 12.5rem;
            background: rgba(67, 89, 113, 0.04);
            font-weight: 600;
            font-size: 0.8125rem;
            color: rgba(67, 89, 113, 0.85);
            padding: 0.625rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.08);
            vertical-align: middle;
            white-space: nowrap;
        }

        .project-summary-grid .project-summary-table th {
            width: auto;
            max-width: none;
            white-space: normal;
        }

        .project-summary-table td {
            padding: 0.625rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.08);
            font-size: 0.875rem;
            color: var(--bs-body-color);
            vertical-align: middle;
        }

        .project-summary-table tr:last-child th,
        .project-summary-table tr:last-child td {
            border-bottom: none;
        }

        .project-summary-table .text-empty {
            color: rgba(67, 89, 113, 0.45);
        }

        .project-summary-table .org-chip {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            margin: 0.1rem 0.1rem 0.1rem 0;
            margin-inline-start: 0.35rem;
            background: rgba(67, 89, 113, 0.06);
            border-radius: 0.25rem;
            font-size: 0.8125rem;
        }

        .project-summary-actions {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(67, 89, 113, 0.1);
        }
    </style>
    @endpush
@endonce

@php
    $orgParts = array_filter([
        $project->center?->name,
        $project->department?->name,
        $project->section?->name,
    ]);
@endphp

@if ($compactLayout)
    <div class="row g-3 project-summary-grid">
@endif

@if ($compactLayout)<div class="col-lg-4">@endif
<div class="project-summary-section">
    <div class="project-summary-section-title">بيانات المشروع</div>
    <table class="project-summary-table">
        <tbody>
            <tr>
                <th scope="row">رقم المشروع</th>
                <td>{{ $project->project_number ?? '—' }}</td>
            </tr>
            <tr>
                <th scope="row">النوع</th>
                <td class="{{ $project->project_type ? '' : 'text-empty' }}">{{ $project->project_type ?: '—' }}</td>
            </tr>
            <tr>
                <th scope="row">الممول</th>
                <td class="{{ $project->funder?->name ? '' : 'text-empty' }}">{{ $project->funder?->name ?? '—' }}</td>
            </tr>
            <tr>
                <th scope="row">مندوب المشتريات</th>
                <td class="{{ $project->procurementRep?->name ? '' : 'text-empty' }}">{{ $project->procurementRep?->name ?? '—' }}</td>
            </tr>
            <tr>
                <th scope="row">الموقع التنظيمي</th>
                <td>
                    @if ($orgParts)
                        @foreach ($orgParts as $part)
                            <span class="org-chip">{{ $part }}</span>
                        @endforeach
                    @else
                        <span class="text-empty">—</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th scope="row">بداية التنفيذ المخطط</th>
                <td class="{{ $project->planned_start_date ? '' : 'text-empty' }}">
                    {{ $project->planned_start_date?->format('Y-m-d') ?? '—' }}
                </td>
            </tr>
            <tr>
                <th scope="row">نهاية التنفيذ المخطط</th>
                <td class="{{ $project->planned_end_date ? '' : 'text-empty' }}">
                    {{ $project->planned_end_date?->format('Y-m-d') ?? '—' }}
                </td>
            </tr>
            <tr>
                <th scope="row">تاريخ بدء التنفيذ</th>
                <td class="{{ $project->execution_start_date ? '' : 'text-empty' }}">
                    {{ $project->execution_start_date?->format('Y-m-d') ?? '—' }}
                </td>
            </tr>
        </tbody>
    </table>
</div>
@if ($compactLayout)</div>@endif

@if ($compactLayout)<div class="col-lg-4">@endif
<div class="project-summary-section">
    <div class="project-summary-section-title">الفريق والاعتماد</div>
    <table class="project-summary-table">
        <tbody>
            <tr>
                <th scope="row">مدير المشروع</th>
                <td>{{ $project->projectManager?->name ?? '—' }}</td>
            </tr>
            <tr>
                <th scope="row">دائرة مدير المشروع</th>
                <td class="{{ ($projectManagerDepartmentName ?? null) ? '' : 'text-empty' }}">
                    {{ $projectManagerDepartmentName ?? '—' }}
                </td>
            </tr>
            <tr>
                <th scope="row">مدير الدائرة المعتمد</th>
                <td class="{{ ($approverDepartmentManager ?? null) ? '' : 'text-empty' }}">
                    {{ $approverDepartmentManagerLabel ?? '—' }}
                </td>
            </tr>
            @if ($showCoordinatorInSummary)
                <tr>
                    <th scope="row">المنسق</th>
                    <td>
                        @if ($project->isSelfCoordinator())
                            {{ $project->projectManager?->name }} <span class="badge bg-label-info">مدير المشروع / منسق</span>
                        @else
                            {{ $project->coordinatorDisplayName() }}
                        @endif
                    </td>
                </tr>
                @if (($canViewCoordinatorData ?? false) && ($coordinatorFillActorLabel ?? null))
                    <tr>
                        <th scope="row">تعبئة عمود المنسق</th>
                        <td>
                            <span class="badge bg-label-info">{{ $coordinatorFillActorLabel }}</span>
                        </td>
                    </tr>
                @endif
            @endif
            @if ($canViewMonitorData ?? false)
            <tr>
                <th scope="row">المراقب</th>
                <td class="{{ $project->monitorPerson?->name ? '' : 'text-empty' }}">
                    {{ $project->monitorPerson?->name ?? '—' }}
                </td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
@if ($compactLayout)</div>@endif

@if ($compactLayout)
    <div class="col-lg-4">
        <div class="project-summary-section">
            <div class="project-summary-section-title">بيانات التنفيذ</div>
            <table class="project-summary-table">
                <tbody>
                    <tr>
                        <th scope="row">المستفيدون المستهدفون</th>
                        <td class="{{ $project->target_beneficiaries !== null ? '' : 'text-empty' }}">
                            {{ $project->target_beneficiaries !== null ? number_format($project->target_beneficiaries) : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">عدد مناطق التنفيذ</th>
                        <td class="{{ $project->execution_zones !== null ? '' : 'text-empty' }}">
                            {{ $project->execution_zones !== null ? number_format($project->execution_zones) : '—' }}
                        </td>
                    </tr>
                    @php
                        $executionRegions = $project->executionRegionsForDisplay();
                        $regionsTotal = $project->executionRegionsBeneficiariesTotal();
                    @endphp
                    @if ($executionRegions !== [])
                    <tr>
                        <th scope="row">مناطق التنفيذ</th>
                        <td>
                            @foreach ($executionRegions as $index => $region)
                                <span class="org-chip d-inline-block mb-1">
                                    {{ $index + 1 }}. {{ $region['name'] }}
                                    @if (! empty($region['execution_site']))
                                        — {{ $region['execution_site'] }}
                                    @endif
                                    @if ($region['beneficiaries'] !== null)
                                        ({{ number_format($region['beneficiaries']) }})
                                    @endif
                                </span>
                            @endforeach
                            @if ($regionsTotal !== null)
                                <div class="form-text mt-1">المجموع الموزَّع: {{ number_format($regionsTotal) }}</div>
                            @endif
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <th scope="row">المدة الزمنية المقدّرة (بالأيام)</th>
                        <td class="{{ $project->estimated_duration ? '' : 'text-empty' }}">
                            {{ $project->estimated_duration ?: '—' }}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">موازنة المشروع</th>
                        <td class="{{ $project->project_budget !== null ? '' : 'text-empty' }}">
                            @if ($project->project_budget !== null)
                                {{ number_format((float) $project->project_budget, 2) }}
                                @if ($project->currency?->code)
                                    <span class="text-muted">{{ $project->currency->code }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">مبلغ الإيرادات</th>
                        <td class="{{ $project->revenue_amount !== null ? '' : 'text-empty' }}">
                            {{ $project->revenue_amount !== null ? number_format((float) $project->revenue_amount, 2) : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">صافي المبلغ</th>
                        <td class="{{ $project->net_amount !== null ? '' : 'text-empty' }}">
                            {{ $project->net_amount !== null ? number_format((float) $project->net_amount, 2) : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">سعر الصرف</th>
                        <td class="{{ $project->exchange_rate !== null ? '' : 'text-empty' }}">
                            {{ $project->exchange_rate !== null ? number_format((float) $project->exchange_rate, 6) : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">المبلغ للتنفيذ (شيكل)</th>
                        <td class="{{ $project->execution_amount_ils !== null ? '' : 'text-empty' }}">
                            {{ $project->execution_amount_ils !== null ? number_format((float) $project->execution_amount_ils, 2) : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">مرفق التخصيص</th>
                        <td class="{{ $project->allocation_image_path ? '' : 'text-empty' }}">
                            @if ($project->allocation_image_path)
                                @if ($project->isAllocationImagePreview())
                                    <a href="{{ $project->allocationImageUrl() }}" target="_blank" rel="noopener">
                                        عرض الصورة
                                    </a>
                                @else
                                    <a href="{{ $project->allocationImageUrl() }}" target="_blank" rel="noopener">
                                        {{ $project->allocationAttachmentBasename() }}
                                    </a>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($compactLayout)
    </div>
@endif

@if ($showActions)
<div class="project-summary-actions d-flex flex-wrap gap-2">
    @can('update', 'App\Models\Project')
        <a href="{{ route('dashboard.projects.edit', $project) }}" class="btn btn-sm btn-outline-primary">
            تعديل بيانات المشروع
        </a>
    @endcan
    @if ($project->primaryMonitoringActivity && ($canViewMonitorData ?? false))
        <a href="{{ route('dashboard.monitoring-activities.show', $project->primaryMonitoringActivity) }}" class="btn btn-sm btn-outline-secondary">
            النشاط الرقابي الأساسي ({{ $project->primaryMonitoringActivity->reference_code }})
        </a>
    @endif
</div>
@endif
