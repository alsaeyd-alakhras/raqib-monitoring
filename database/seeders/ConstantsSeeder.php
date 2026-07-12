<?php

namespace Database\Seeders;

use App\Models\Constant;
use Illuminate\Database\Seeder;

class ConstantsSeeder extends Seeder
{
    public function run(): void
    {
        $constants = [
            'scale_execution' => [
                ['value' => 100, 'label' => 'ممتاز'],
                ['value' => 80, 'label' => 'جيد'],
                ['value' => 60, 'label' => 'مقبول'],
                ['value' => 40, 'label' => 'خطر'],
            ],
            'scale_quality' => [
                ['value' => 100, 'label' => 'ممتاز'],
                ['value' => 85, 'label' => 'جيد'],
                ['value' => 70, 'label' => 'مقبول'],
                ['value' => 50, 'label' => 'ضعيف'],
            ],
            'scale_closure' => [
                ['value' => 100, 'label' => 'مكتمل'],
                ['value' => 60, 'label' => 'جزئي'],
                ['value' => 30, 'label' => 'معلّق'],
                ['value' => 0, 'label' => 'مفتوح'],
            ],
            'scale_deduction' => [
                ['value' => 0, 'label' => 'لا خصم'],
                ['value' => -5, 'label' => 'تأخير'],
                ['value' => -10, 'label' => 'عجز'],
                ['value' => -15, 'label' => 'جودة'],
                ['value' => -20, 'label' => 'امتثال'],
                ['value' => -25, 'label' => 'مخالفة'],
            ],
            'scale_kpi' => [
                ['min' => 98, 'label' => 'ممتاز جداً'],
                ['min' => 90, 'label' => 'ممتاز'],
                ['min' => 75, 'label' => 'جيد'],
                ['min' => 60, 'label' => 'مقبول'],
                ['min' => 40, 'label' => 'ضعيف'],
                ['min' => 0, 'label' => 'خطر شديد'],
            ],
            'activity_types' => [
                'تفتيش ميداني',
                'جودة خدمة',
                'فحص سلامة',
                'فحص مخزون',
                'متابعة شكاوى',
                'مراجعة إجراءات',
                'جرد مفاجئ',
                'مراجعة عقود',
                'تدقيق مالي',
                'متابعة حضور',
            ],
            'monitoring_methods' => [
                'ميداني',
            ],
            'monitoring_stages' => [
                'أثناء التنفيذ',
            ],
            'project_types' => [
                'تنموي',
                'إغاثي',
                'طبي',
                'تعليمي',
                'إنشائي',
                'خدمات',
            ],
            'checklist_options' => [
                'جاهز',
                'جزئي',
                'غير جاهز',
                'غير مطلوب',
            ],
            'checklist_closure_late_score' => 0.5,
            'source_types' => [
                'project',
                'external',
                'meeting',
            ],
        ];

        foreach ($constants as $key => $value) {
            Constant::updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]
            );
        }
    }
}
