@php
    $activity = $project->primaryMonitoringActivity;
    $statusLabel = $workflowStatusLabels[$project->workflow_status] ?? $project->workflow_status;

    $orgParts = array_filter([
        $project->center?->name,
        $project->department?->name,
        $project->section?->name,
    ]);
    $orgHtml = $orgParts
        ? collect($orgParts)->map(fn ($p) => '<span class="chip">' . e($p) . '</span>')->implode(' ')
        : '<span class="text-empty">—</span>';

    $plannedDates = ($project->planned_start_date || $project->planned_end_date)
        ? trim(
            ($project->planned_start_date?->format('Y-m-d') ?? '—') . ' — ' . ($project->planned_end_date?->format('Y-m-d') ?? '—')
        )
        : null;

    $projectCoreRows = [
        'اسم المشروع' => e($project->project_name),
        'رقم المشروع' => '<span class="num-ltr">' . e($project->project_number ?? '—') . '</span>',
        'نوع المشروع' => $project->project_type ? e($project->project_type) : '<span class="text-empty">—</span>',
        'الجهة المانحة' => $project->funder?->name ? e($project->funder->name) : '<span class="text-empty">—</span>',
        'مدير المشروع' => e($project->projectManager?->name ?? '—'),
    ];

    if ($canViewCoordinatorData ?? false) {
        $coordinatorLabel = $project->isSelfCoordinator()
            ? e($project->projectManager?->name ?? '—') . ' <span class="badge badge-info">مدير المشروع / منسق</span>'
            : e($project->coordinatorDisplayName());
        $projectCoreRows['منسق المشروع'] = $coordinatorLabel;
    }

    $projectCoreRows['الموقع التنظيمي'] = $orgHtml;
    $projectCoreRows['تاريخ التنفيذ المخطط'] = $plannedDates
        ? '<span class="num-ltr">' . e($plannedDates) . '</span>'
        : '<span class="text-empty">—</span>';

    $implementationRows = [
        'الموقع / المنطقة' => $project->location ? e($project->location) : '<span class="text-empty">—</span>',
        'المستفيدون المستهدفون' => $project->target_beneficiaries !== null
            ? '<span class="num-ltr">' . e(number_format($project->target_beneficiaries)) . '</span>'
            : '<span class="text-empty">—</span>',
        'مناطق التنفيذ' => $project->execution_zones ? e($project->execution_zones) : '<span class="text-empty">—</span>',
        'المدة التقديرية' => $project->estimated_duration ? e($project->estimated_duration) : '<span class="text-empty">—</span>',
        'الميزانية المخصصة' => $project->allocated_budget !== null
            ? '<span class="num-ltr">' . e(number_format((float) $project->allocated_budget, 2)) . '</span>'
            : '<span class="text-empty">—</span>',
        'حالة سير العمل' => e($statusLabel),
    ];

    $monitorRows = [];
    if ($canViewMonitorData ?? false) {
        $monitorDate = $project->monitoring_date?->format('Y-m-d')
            ?? $activity?->activity_date?->format('Y-m-d');
        $monitorTime = $activity?->activity_time;

        $monitorRows = [
            'اسم المراقب' => e($project->monitorPerson?->name ?? '—'),
            'طريقة المراقبة' => e($project->monitoring_method ?? $activity?->monitoring_method ?? '—'),
            'تاريخ المراقبة' => $monitorDate
                ? '<span class="num-ltr">' . e($monitorDate) . ($monitorTime ? ' — ' . e($monitorTime) : '') . '</span>'
                : '<span class="text-empty">—</span>',
            'مرحلة المراقبة' => e($project->monitoring_stage ?? $activity?->monitoring_stage ?? '—'),
            'رمز النشاط الرقابي' => $activity?->reference_code
                ? '<span class="num-ltr">' . e($activity->reference_code) . '</span>'
                : '<span class="text-empty">—</span>',
        ];

        if ($activity?->kpi_value !== null) {
            $kpiHtml = '<span class="num-ltr">' . e($activity->kpi_value) . '</span>';
            if ($activity->kpi_rating) {
                $kpiHtml .= ' <span class="badge badge-info">' . e($activity->kpi_rating) . '</span>';
            }
            $monitorRows['مؤشر KPI'] = $kpiHtml;
        }
    }

    $readinessStatus = ($canViewMonitorData ?? false) ? $project->readiness_status : null;
    $readinessLabel = $readinessStatus
        ? ($readinessStatusLabels[$readinessStatus] ?? $readinessStatus)
        : null;
    $bannerClass = match ($readinessStatus) {
        'ready' => 'status-banner',
        'partially_ready' => 'status-banner status-banner-warn',
        'stopped' => 'status-banner status-banner-danger',
        default => 'status-banner status-banner-warn',
    };

    $sectionNum = 1;
@endphp

{{-- Hero --}}
<table class="report-hero" width="100%">
    <tr>
        <td>
            <div class="report-hero-title">تقرير جاهزية مشروع</div>
            <div class="report-hero-subtitle">READINESS REPORT</div>
            <div class="report-hero-code">{{ $project->project_number }} — {{ $project->project_name }}</div>
            <div class="report-hero-meta">
                {{ $statusLabel }}
                · تاريخ الطباعة: <span class="num-ltr">{{ now()->format('Y-m-d H:i') }}</span>
                @if ($project->location)
                    · {{ $project->location }}
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- 1: بيانات المشروع --}}
@include('reports.projects.partials._section', [
    'num' => $sectionNum++,
    'title' => 'بيانات المشروع',
    'pairs' => [
        [
            'left' => ['title' => 'البيانات الأساسية', 'rows' => $projectCoreRows],
            'right' => ['title' => 'بيانات التنفيذ', 'rows' => $implementationRows],
        ],
    ],
])

{{-- 2: بيانات المراقب --}}
@if (($canViewMonitorData ?? false) && !empty($monitorRows))
    @include('reports.projects.partials._section', [
        'num' => $sectionNum++,
        'title' => 'بيانات المراقب الميداني',
        'pairs' => [
            [
                'left' => ['title' => 'المراقبة والنشاط', 'rows' => $monitorRows],
                'right' => null,
            ],
        ],
    ])
@endif

{{-- قائمة التحقق --}}
@if (($canViewCoordinatorData ?? false) || ($canViewMonitorData ?? false))
    @include('reports.projects.partials._section', [
        'num' => $sectionNum++,
        'title' => 'قائمة التحقق من الجاهزية',
        'pairs' => [],
        'afterHead' => view('reports.projects.partials._checklist_grid', [
            'groups' => $groups,
            'values' => $values,
            'valueLabels' => $valueLabels,
            'canViewCoordinatorData' => $canViewCoordinatorData,
            'canViewMonitorData' => $canViewMonitorData,
        ])->render(),
    ])

    @include('reports.projects.partials._section', [
        'num' => $sectionNum++,
        'title' => 'ملخص الجاهزية الميدانية',
        'pairs' => [],
        'afterHead' => view('reports.projects.partials._readiness_summary', [
            'readinessBreakdown' => $readinessBreakdown,
            'canViewCoordinatorData' => $canViewCoordinatorData,
            'canViewMonitorData' => $canViewMonitorData,
        ])->render(),
    ])
@endif

{{-- ملاحظات وتوصيات --}}
@if (($canViewMonitorData ?? false) && (($project->monitor_notes ?? []) || ($project->monitor_recommendations ?? [])))
    @include('reports.projects.partials._section', [
        'num' => $sectionNum++,
        'title' => 'ملاحظات وتوصيات المراقب الميداني',
        'pairs' => [],
        'afterHead' => view('reports.projects.partials._notes_table', ['project' => $project])->render(),
    ])
@endif

{{-- Status banner --}}
@if ($readinessLabel)
    <div class="{{ $bannerClass }}">{{ $readinessLabel }}</div>
@endif

{{-- Signatures --}}
@include('reports.projects.partials._section', [
    'num' => $sectionNum,
    'title' => 'الاعتماد والتوقيع',
    'pairs' => [],
    'afterHead' => view('reports.projects.partials._signatures', ['project' => $project])->render(),
])
