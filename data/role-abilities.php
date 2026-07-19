<?php

/**
 * مصدر حقيقة واحد: صلاحيات الدور الوظيفي (Person.role) → abilities.
 * يُستخدم في دليل الأشخاص، الاستيراد، وبذور التجربة.
 *
 * @return array<string, array<int, string>>
 */
return [
    'project_manager' => [
        'projects.view',
        'projects.create',
        'projects.update',
    ],
    'project_secretariat' => [
        'projects.view',
        'projects.fill_secretariat',
    ],
    'coordinator' => [
        'projects.view',
        'projects.fill_coordinator',
    ],
    'section_manager' => [
        'projects.view',
        'projects.approve_section',
        'projects.reject',
        'people.view',
        'people.create',
        'people.update',
    ],
    'department_manager' => [
        'projects.view',
        'projects.approve_department',
        'projects.reject',
    ],
    'monitor' => [
        'projects.view',
        'projects.fill_monitor',
        'monitoringactivities.view',
        'monitoringactivities.update',
    ],
    'monitoring_director' => [
        'projects.view',
        'projects.update',
        'projects.reject',
        'monitoringactivities.view',
        'monitoringactivities.create',
        'monitoringactivities.update',
        'monitoringactivities.set_monitoring_info',
        'monitoringactivities.assign_monitor',
        'monitoringactivities.confirm_completion',
        'monitoringactivities.edit_ratings',
        'monitoringactivities.reject',
    ],
    'general_management' => [
        'projects.view',
        'monitoringactivities.view',
        'monitoringactivities.edit_ratings',
        'people.view',
        'funders.view',
        'centers.view',
        'departments.view',
    ],
    'admin' => [
        'users.view',
        'users.create',
        'users.update',
        'people.view',
        'people.create',
        'people.update',
        'people.delete',
        'constants.view',
        'constants.create',
        'constants.update',
        'centers.view',
        'centers.create',
        'centers.update',
        'departments.view',
        'departments.create',
        'departments.update',
        'sections.view',
        'sections.create',
        'sections.update',
        'funders.view',
        'funders.create',
        'funders.update',
        'checklist_admin.manage',
    ],
];
