@php
    $c = config('brand.colors');
    $ct = config('brand.contact');
    $img = config('brand.img');

    $activityOrgParts = array_filter([
        $activity->center?->name,
        $activity->department?->name,
        $activity->section?->name,
    ]);
    $orgHtml = $activityOrgParts
        ? collect($activityOrgParts)->map(fn ($p) => '<span class="chip">'.$p.'</span>')->implode(' ')
        : '<span class="text-empty">—</span>';

    $dateHtml = $activity->activity_date
        ? e($activity->activity_date->format('Y-m-d')).' <span class="text-muted">('.e($activity->day_name).' — شهر '.e($activity->month).' / '.e($activity->year).')</span>'
        : '<span class="text-empty">—</span>';

    $kpiHtml = $activity->kpi_value !== null
        ? e($activity->kpi_value).($activity->kpi_rating ? ' <span class="badge badge-info">'.e($activity->kpi_rating).'</span>' : '')
        : '<span class="text-empty">—</span>';

    $passageHtml = ($activity->is_passage_complete ? 'نعم' : 'لا')
        .($activity->is_passage_complete && $activity->passage_completed_at
            ? ' <span class="text-muted">— '.e($activity->passage_completed_at->format('Y-m-d H:i')).' ('.e($activity->passageCompletedByUser?->name ?? '—').')</span>'
            : '');

    $evalLabels = [
        'execution_value' => 'التنفيذ',
        'quality_value' => 'الجودة',
        'closure_value' => 'الإغلاق',
        'deduction_value' => 'الخصم',
    ];

    $evalRows = [];
    foreach ($evalLabels as $field => $label) {
        $evalRows[$label] = $activity->{$field} !== null
            ? e($activity->{$field})
            : '<span class="text-empty">—</span>';
    }
    $evalRows['KPI'] = $kpiHtml;
    $evalRows['حالة التحقق'] = e($activity->verification_status);

    $identityRows = [
        'الرمز' => e($activity->reference_code),
        'نوع المصدر' => e($sourceTypes[$activity->source_type] ?? $activity->source_type),
    ];

    $orgRows = [
        'المركز/الدائرة/القسم' => $orgHtml,
        'المسؤول عن النشاط' => $activity->responsiblePerson?->name ? e($activity->responsiblePerson->name) : '<span class="text-empty">—</span>',
        'المراقب' => $activity->monitorPerson?->name ? e($activity->monitorPerson->name) : '<span class="text-empty">—</span>',
    ];

    $timeRows = [
        'التاريخ' => $dateHtml,
        'الوقت' => $activity->activity_time ? e($activity->activity_time) : '<span class="text-empty">—</span>',
        'نوع النشاط' => $activity->activity_type ? e($activity->activity_type) : '<span class="text-empty">—</span>',
        'الممول' => $activity->funder?->name ? e($activity->funder->name) : '<span class="text-empty">—</span>',
    ];

    $contentRows = [
        'الموضوع' => $activity->subject ? e($activity->subject) : '<span class="text-empty">—</span>',
        'ملاحظة النشاط' => $activity->notes ? e($activity->notes) : '<span class="text-empty">—</span>',
        'مشكلة ميدانية؟' => $activity->field_problem ? 'نعم' : 'لا',
        'الإجراء المتخذ' => $activity->action_taken ? e($activity->action_taken) : '<span class="text-empty">—</span>',
    ];

    $workflowRows = [
        'طريقة المراقبة' => $activity->monitoring_method ? e($activity->monitoring_method) : '<span class="text-empty">—</span>',
        'مرحلة المراقبة' => $activity->monitoring_stage ? e($activity->monitoring_stage) : '<span class="text-empty">—</span>',
        'حالة سير العمل' => e($workflowStatusLabels[$activity->workflow_status] ?? $activity->workflow_status),
        'اكتمال المرور' => $passageHtml,
    ];
@endphp

{{-- عنوان التقرير --}}
<table class="report-hero" width="100%">
    <tr>
        <td colspan="2">
            <div class="report-hero-title">تقرير النشاط الرقابي</div>
            <div class="report-hero-code">{{ $activity->reference_code }}</div>
            <div class="report-hero-meta">
                {{ $sourceTypes[$activity->source_type] ?? $activity->source_type }}
                · {{ $workflowStatusLabels[$activity->workflow_status] ?? $activity->workflow_status }}
                · تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}
            </div>
        </td>
    </tr>
</table>

{{-- القسم 1: بيانات النشاط --}}
@include('reports.monitoring-activities.partials._section', [
    'num' => '1',
    'title' => 'بيانات النشاط الرقابي',
    'pairs' => [
        [
            'left' => ['title' => 'هوية النشاط ومصدره', 'rows' => $identityRows],
            'right' => ['title' => 'الهرم التنظيمي والأطراف', 'rows' => $orgRows],
        ],
        [
            'left' => ['title' => 'الزمن والتصنيف', 'rows' => $timeRows],
            'right' => ['title' => 'المحتوى الرقابي', 'rows' => $contentRows],
        ],
        [
            'left' => ['title' => 'التقييم ومؤشر الأداء', 'rows' => $evalRows],
            'right' => ['title' => 'المراقبة وسير العمل', 'rows' => $workflowRows],
        ],
    ],
])

@if ($linkedProject)
    @php
        $projectOrgParts = array_filter([
            $linkedProject->center?->name,
            $linkedProject->department?->name,
            $linkedProject->section?->name,
        ]);
        $projectOrgHtml = $projectOrgParts
            ? collect($projectOrgParts)->map(fn ($p) => '<span class="chip">'.$p.'</span>')->implode(' ')
            : '<span class="text-empty">—</span>';

        $projectDataRows = [
            'رقم المشروع' => e($linkedProject->project_number ?? '—'),
            'اسم المشروع' => e($linkedProject->project_name ?? '—'),
            'النوع' => e($linkedProject->project_type ?: '—'),
            'الممول' => $linkedProject->funder?->name ? e($linkedProject->funder->name) : '<span class="text-empty">—</span>',
            'الموقع التنظيمي' => $projectOrgHtml,
            'المستفيدون المستهدفون' => $linkedProject->target_beneficiaries !== null
                ? e(number_format($linkedProject->target_beneficiaries))
                : '<span class="text-empty">—</span>',
        ];

        $teamRows = [
            'مدير المشروع' => e($linkedProject->projectManager?->name ?? '—'),
        ];
        if ($canViewMonitorData ?? false) {
            $teamRows['المراقب'] = $linkedProject->monitorPerson?->name
                ? e($linkedProject->monitorPerson->name)
                : '<span class="text-empty">—</span>';
        }
    @endphp

    @include('reports.monitoring-activities.partials._section', [
        'num' => '2',
        'title' => 'المشروع المرتبط — '.($linkedProject->project_number ?? '').' '.($linkedProject->project_name ?? ''),
        'pairs' => [
            [
                'left' => ['title' => 'بيانات المشروع', 'rows' => $projectDataRows],
                'right' => ['title' => 'الفريق والاعتماد', 'rows' => $teamRows],
            ],
        ],
    ])

    @if (($canViewMonitorData ?? false) && ($linkedProject->monitor_notes || $linkedProject->monitor_negative_notes || $linkedProject->monitor_recommendations))
        @include('reports.monitoring-activities.partials._section', [
            'num' => '3',
            'title' => 'ملاحظات وتوصيات المراقب على المشروع',
            'pairs' => [],
            'afterHead' => view('reports.monitoring-activities.partials._notes_table', ['linkedProject' => $linkedProject])->render(),
        ])
    @endif
@endif
