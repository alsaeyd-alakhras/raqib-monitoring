<?php

namespace App\Exports;

use App\Models\MonitoringActivity;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MonitoringActivityExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected MonitoringActivity $activity, protected array $sourceTypes, protected array $workflowStatusLabels)
    {
    }

    public function collection()
    {
        return collect([$this->activity]);
    }

    public function map($activity): array
    {
        return [
            $activity->reference_code,
            $this->sourceTypes[$activity->source_type] ?? $activity->source_type,
            $activity->activity_role === 'primary' ? 'أساسي' : 'تابع',
            $activity->center?->name,
            $activity->department?->name,
            $activity->section?->name,
            $activity->responsiblePerson?->name,
            $activity->monitorPerson?->name,
            $activity->activity_date?->format('Y-m-d'),
            $activity->day_name,
            $activity->month,
            $activity->year,
            $activity->activity_time,
            $activity->activity_type,
            $activity->funder?->name,
            $activity->subject,
            $activity->notes,
            $activity->field_problem ? 'نعم' : 'لا',
            $activity->action_taken,
            $activity->execution_value,
            $activity->quality_value,
            $activity->closure_value,
            $activity->deduction_value,
            $activity->kpi_value,
            $activity->kpi_rating,
            $activity->monitoring_method,
            $activity->monitoring_stage,
            $this->workflowStatusLabels[$activity->workflow_status] ?? $activity->workflow_status,
            $activity->is_passage_complete ? 'نعم' : 'لا',
            $activity->passage_completed_at?->format('Y-m-d H:i'),
            $activity->passageCompletedByUser?->name,
            $activity->verification_status,
            $activity->createdByUser?->name,
            $activity->created_at?->format('Y-m-d H:i'),
            $activity->updatedByUser?->name,
            $activity->updated_at?->format('Y-m-d H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'رمز النشاط',
            'نوع المصدر',
            'دور النشاط',
            'المركز',
            'الدائرة',
            'القسم',
            'المسؤول عن النشاط',
            'المراقب',
            'التاريخ',
            'اليوم',
            'الشهر',
            'السنة',
            'الوقت',
            'نوع النشاط',
            'الممول',
            'الموضوع',
            'الملاحظة',
            'مشكلة ميدانية',
            'الإجراء المتخذ',
            'نسبة التنفيذ',
            'الجودة',
            'الإغلاق',
            'الخصم',
            'KPI',
            'تقييم KPI',
            'طريقة المراقبة',
            'مرحلة المراقبة',
            'حالة سير العمل',
            'اكتمال المرور',
            'تاريخ تأكيد المرور',
            'أكّده',
            'حالة التحقق',
            'أنشأه',
            'تاريخ الإنشاء',
            'آخر تعديل بواسطة',
            'تاريخ آخر تعديل',
        ];
    }
}
