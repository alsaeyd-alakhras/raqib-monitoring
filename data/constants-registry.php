<?php

/**
 * سجل ثوابت النظام (مصدر الحقيقة للمفاتيح).
 *
 * القيم الفعلية تُخزَّن في جدول `constants` (key/value JSON)
 * ويُعبَّأ أولياً عبر Database\Seeders\ConstantsSeeder.
 * عند إضافة ثابت جديد: أضفه هنا + في ConstantsSeeder + في شاشة الإدارة إن لزم.
 */
return [
    'project_types' => [
        'label' => 'أنواع المشاريع',
        'used_in' => ['projects.project_type'],
        'value_shape' => 'list<string>',
    ],
    'monitoring_methods' => [
        'label' => 'طرق المراقبة',
        'used_in' => ['projects.monitoring_method', 'monitoring_activities.monitoring_method'],
        'value_shape' => 'list<string>',
    ],
    'monitoring_stages' => [
        'label' => 'مراحل المراقبة',
        'used_in' => ['projects.monitoring_stage', 'monitoring_activities.monitoring_stage'],
        'value_shape' => 'list<string>',
    ],
    'activity_types' => [
        'label' => 'أنواع النشاط الرقابي',
        'used_in' => ['monitoring_activities.activity_type'],
        'value_shape' => 'list<string>',
    ],
    'source_types' => [
        'label' => 'مصادر النشاط الرقابي',
        'used_in' => ['monitoring_activities.source_type'],
        'value_shape' => 'list<string> (project|external|meeting)',
    ],
    'checklist_options' => [
        'label' => 'خيارات قائمة التحقق (عرض)',
        'used_in' => ['project_checklist_values UI labels'],
        'value_shape' => 'list<string>',
    ],
    'scale_execution' => [
        'label' => 'مقياس التنفيذ',
        'used_in' => ['monitoring_activities.execution_value scales'],
        'value_shape' => 'list<{value:int,label:string}>',
    ],
    'scale_quality' => [
        'label' => 'مقياس الجودة',
        'used_in' => ['monitoring_activities.quality_value scales'],
        'value_shape' => 'list<{value:int,label:string}>',
    ],
    'scale_closure' => [
        'label' => 'مقياس الإغلاق',
        'used_in' => ['monitoring_activities.closure_value scales'],
        'value_shape' => 'list<{value:int,label:string}>',
    ],
    'scale_deduction' => [
        'label' => 'مقياس الخصم',
        'used_in' => ['monitoring_activities.deduction_value scales'],
        'value_shape' => 'list<{value:int,label:string}>',
    ],
    'scale_kpi' => [
        'label' => 'تصنيف KPI',
        'used_in' => ['monitoring_activities.kpi_rating'],
        'value_shape' => 'list<{min:int,label:string}>',
    ],
];
