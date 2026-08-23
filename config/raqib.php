<?php

return [
    'excel_path' => env('RAQIB_EXCEL_PATH', 'plans/بيانات2(2).xlsx'),

    'super_admin' => [
        'username' => env('RAQIB_ADMIN_USERNAME', 'saeyd_jamal'),
        'password' => env('RAQIB_ADMIN_PASSWORD', '20051118Jamal'),
        'email' => env('RAQIB_ADMIN_EMAIL', 'admin@raqib.local'),
        'name' => env('RAQIB_ADMIN_NAME', 'المهندس العام'),
    ],

    'setup_report_path' => 'logs/raqib-setup-report.json',
    'employee_import_report_path' => 'logs/employee-import-report.json',
    'promote_coordinators_report_path' => 'logs/promote-coordinators-report.json',

    'projects' => [
        // Enables secretariat as project entry creator (not a workflow stage).
        // Set RAQIB_PROJECTS_SECRETARIAT_ENTRY_ENABLED=false to disable explicitly.
        'secretariat_entry_enabled' => filter_var(
            env('RAQIB_PROJECTS_SECRETARIAT_ENTRY_ENABLED', env('RAQIB_PROJECTS_SECRETARIAT_ENABLED', true)),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
];
