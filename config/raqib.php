<?php

return [
    'excel_path' => env('RAQIB_EXCEL_PATH', 'plans/بيانات2(2).xlsx'),

    'super_admin' => [
        'username' => env('RAQIB_ADMIN_USERNAME', 'admin'),
        'password' => env('RAQIB_ADMIN_PASSWORD', 'password'),
        'email' => env('RAQIB_ADMIN_EMAIL', 'admin@raqib.local'),
        'name' => env('RAQIB_ADMIN_NAME', 'مدير النظام'),
    ],

    'setup_report_path' => 'logs/raqib-setup-report.json',
    'employee_import_report_path' => 'logs/employee-import-report.json',
];
