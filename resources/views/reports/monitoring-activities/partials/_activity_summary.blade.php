@php
    $compactLayout = $compactLayout ?? false;
@endphp

@once
<style>
    .activity-report-grid .activity-report-section {
        height: 100%;
    }

    .activity-report-section + .activity-report-section {
        margin-top: 1.25rem;
    }

    .activity-report-grid .activity-report-section {
        margin-top: 0 !important;
    }

    .activity-report-section-title {
        font-size: 0.8125rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        color: #435971;
        margin-bottom: 0.5rem;
    }

    .activity-report-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid rgba(67, 89, 113, 0.12);
        border-radius: 0.5rem;
        overflow: hidden;
        background: #fff;
    }

    .activity-report-table th {
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

    .activity-report-grid .activity-report-table th {
        width: auto;
        max-width: none;
        white-space: normal;
    }

    .activity-report-table td {
        padding: 0.625rem 0.875rem;
        border-bottom: 1px solid rgba(67, 89, 113, 0.08);
        font-size: 0.875rem;
        color: #2b2c40;
        vertical-align: middle;
    }

    .activity-report-table tr:last-child th,
    .activity-report-table tr:last-child td {
        border-bottom: none;
    }

    .activity-report-table .text-empty {
        color: rgba(67, 89, 113, 0.45);
    }

    .activity-report-table .org-chip {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        margin: 0.1rem 0.1rem 0.1rem 0;
        margin-inline-start: 0.35rem;
        background: rgba(67, 89, 113, 0.06);
        border-radius: 0.25rem;
        font-size: 0.8125rem;
    }
</style>
@endonce

@php
    $activityOrgParts = array_filter([
        $activity->center?->name,
        $activity->department?->name,
        $activity->section?->name,
    ]);
    $evalLabels = [
        'execution_value' => 'التنفيذ',
        'quality_value' => 'الجودة',
        'closure_value' => 'الإغلاق',
        'deduction_value' => 'الخصم',
    ];
@endphp

@if ($compactLayout)
    <div class="row g-3 activity-report-grid">
@endif

{{-- 1. هوية النشاط --}}
@if ($compactLayout)<div class="col-xl-4 col-md-6">@endif
<div class="activity-report-section">
    <div class="activity-report-section-title">هوية النشاط ومصدره</div>
    <table class="activity-report-table">
        <tbody>
            <tr>
                <th scope="row">الرمز</th>
                <td>{{ $activity->reference_code }}</td>
            </tr>
            <tr>
                <th scope="row">نوع المصدر</th>
                <td>{{ $sourceTypes[$activity->source_type] ?? $activity->source_type }}</td>
            </tr>
            <tr>
                <th scope="row">دور النشاط</th>
                <td>
                    <span class="badge bg-label-{{ $activity->activity_role === 'primary' ? 'primary' : 'secondary' }}">
                        {{ $activity->activity_role === 'primary' ? 'أساسي' : 'تابع' }}
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>
@if ($compactLayout)</div>@endif

{{-- 2. الهرم التنظيمي --}}
@if ($compactLayout)<div class="col-xl-4 col-md-6">@endif
<div class="activity-report-section">
    <div class="activity-report-section-title">الهرم التنظيمي والأطراف</div>
    <table class="activity-report-table">
        <tbody>
            <tr>
                <th scope="row">المركز/الدائرة/القسم</th>
                <td>
                    @if ($activityOrgParts)
                        @foreach ($activityOrgParts as $part)
                            <span class="org-chip">{{ $part }}</span>
                        @endforeach
                    @else
                        <span class="text-empty">—</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th scope="row">المسؤول عن النشاط</th>
                <td class="{{ $activity->responsiblePerson?->name ? '' : 'text-empty' }}">{{ $activity->responsiblePerson?->name ?? '—' }}</td>
            </tr>
            <tr>
                <th scope="row">المراقب</th>
                <td class="{{ $activity->monitorPerson?->name ? '' : 'text-empty' }}">{{ $activity->monitorPerson?->name ?? '—' }}</td>
            </tr>
        </tbody>
    </table>
</div>
@if ($compactLayout)</div>@endif

{{-- 3. الزمن والتصنيف --}}
@if ($compactLayout)<div class="col-xl-4 col-md-6">@endif
<div class="activity-report-section">
    <div class="activity-report-section-title">الزمن والتصنيف</div>
    <table class="activity-report-table">
        <tbody>
            <tr>
                <th scope="row">التاريخ</th>
                <td class="{{ $activity->activity_date ? '' : 'text-empty' }}">
                    {{ $activity->activity_date?->format('Y-m-d') ?? '—' }}
                    @if ($activity->activity_date)
                        <span class="text-muted">({{ $activity->day_name }} — شهر {{ $activity->month }} / {{ $activity->year }})</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th scope="row">الوقت</th>
                <td class="{{ $activity->activity_time ? '' : 'text-empty' }}">{{ $activity->activity_time ?? '—' }}</td>
            </tr>
            <tr>
                <th scope="row">نوع النشاط</th>
                <td class="{{ $activity->activity_type ? '' : 'text-empty' }}">{{ $activity->activity_type ?? '—' }}</td>
            </tr>
            <tr>
                <th scope="row">الممول</th>
                <td class="{{ $activity->funder?->name ? '' : 'text-empty' }}">{{ $activity->funder?->name ?? '—' }}</td>
            </tr>
        </tbody>
    </table>
</div>
@if ($compactLayout)</div>@endif

{{-- 4. المحتوى الرقابي --}}
@if ($compactLayout)<div class="col-xl-4 col-md-6">@endif
<div class="activity-report-section">
    <div class="activity-report-section-title">المحتوى الرقابي</div>
    <table class="activity-report-table">
        <tbody>
            <tr>
                <th scope="row">الموضوع</th>
                <td class="{{ $activity->subject ? '' : 'text-empty' }}">{{ $activity->subject ?: '—' }}</td>
            </tr>
            <tr>
                <th scope="row">ملاحظة النشاط</th>
                <td class="{{ $activity->notes ? '' : 'text-empty' }}">{{ $activity->notes ?: '—' }}</td>
            </tr>
            <tr>
                <th scope="row">مشكلة ميدانية؟</th>
                <td>{{ $activity->field_problem ? 'نعم' : 'لا' }}</td>
            </tr>
            <tr>
                <th scope="row">الإجراء المتخذ</th>
                <td class="{{ $activity->action_taken ? '' : 'text-empty' }}">{{ $activity->action_taken ?: '—' }}</td>
            </tr>
        </tbody>
    </table>
</div>
@if ($compactLayout)</div>@endif

{{-- 5. التقييم --}}
@if ($compactLayout)<div class="col-xl-4 col-md-6">@endif
<div class="activity-report-section">
    <div class="activity-report-section-title">التقييم ومؤشر الأداء</div>
    <table class="activity-report-table">
        <tbody>
            @foreach ($evalLabels as $field => $label)
                <tr>
                    <th scope="row">{{ $label }}</th>
                    <td class="{{ $activity->{$field} !== null ? '' : 'text-empty' }}">{{ $activity->{$field} !== null ? $activity->{$field} : '—' }}</td>
                </tr>
            @endforeach
            <tr>
                <th scope="row">KPI</th>
                <td class="{{ $activity->kpi_value !== null ? '' : 'text-empty' }}">
                    {{ $activity->kpi_value ?? '—' }}
                    @if ($activity->kpi_rating)
                        <span class="badge bg-label-info">{{ $activity->kpi_rating }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th scope="row">حالة التحقق</th>
                <td>{{ $activity->verification_status }}</td>
            </tr>
        </tbody>
    </table>
</div>
@if ($compactLayout)</div>@endif

{{-- 6. سير العمل --}}
@if ($compactLayout)<div class="col-xl-4 col-md-6">@endif
<div class="activity-report-section">
    <div class="activity-report-section-title">المراقبة وسير العمل</div>
    <table class="activity-report-table">
        <tbody>
            <tr>
                <th scope="row">طريقة المراقبة</th>
                <td class="{{ $activity->monitoring_method ? '' : 'text-empty' }}">{{ $activity->monitoring_method ?? '—' }}</td>
            </tr>
            <tr>
                <th scope="row">مرحلة المراقبة</th>
                <td class="{{ $activity->monitoring_stage ? '' : 'text-empty' }}">{{ $activity->monitoring_stage ?? '—' }}</td>
            </tr>
            <tr>
                <th scope="row">حالة سير العمل</th>
                <td>{{ $workflowStatusLabels[$activity->workflow_status] ?? $activity->workflow_status }}</td>
            </tr>
            <tr>
                <th scope="row">اكتمال المرور</th>
                <td>
                    {{ $activity->is_passage_complete ? 'نعم' : 'لا' }}
                    @if ($activity->is_passage_complete && $activity->passage_completed_at)
                        <span class="text-muted">— {{ $activity->passage_completed_at->format('Y-m-d H:i') }}
                            ({{ $activity->passageCompletedByUser?->name ?? '—' }})</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>
@if ($compactLayout)</div>@endif

{{-- 7. حقول النظام --}}
@if ($compactLayout)<div class="col-xl-4 col-md-6">@endif
<div class="activity-report-section">
    <div class="activity-report-section-title">حقول النظام</div>
    <table class="activity-report-table">
        <tbody>
            <tr>
                <th scope="row">أنشأه</th>
                <td class="{{ $activity->createdByUser?->name ? '' : 'text-empty' }}">
                    {{ $activity->createdByUser?->name ?? '—' }}
                    <span class="text-muted">— {{ $activity->created_at?->format('Y-m-d H:i') }}</span>
                </td>
            </tr>
            <tr>
                <th scope="row">آخر تعديل</th>
                <td class="{{ $activity->updatedByUser?->name ? '' : 'text-empty' }}">
                    {{ $activity->updatedByUser?->name ?? '—' }}
                    <span class="text-muted">— {{ $activity->updated_at?->format('Y-m-d H:i') }}</span>
                </td>
            </tr>
        </tbody>
    </table>
</div>
@if ($compactLayout)</div>@endif

@if ($compactLayout)
    </div>
@endif
