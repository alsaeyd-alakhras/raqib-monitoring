<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Department;
use App\Models\Funder;
use App\Models\Person;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class RaqibMasterSeeder extends Seeder
{
    public static bool $dryRun = false;

    /** @var array<string, mixed> */
    public static array $report = [];

    public function run(): void
    {
        $this->call([
            ConstantsSeeder::class,
            ChecklistSeeder::class,
            ExcelOrganizationalSeeder::class,
            ExcelFundersSeeder::class,
            ExcelPeopleImportSeeder::class,
            SuperAdminSeeder::class,
        ]);

        self::$report = [
            'dry_run' => self::$dryRun,
            'counts' => [
                'centers' => Center::count(),
                'departments' => Department::count(),
                'sections' => Section::count(),
                'funders' => Funder::count(),
                'users' => User::where('super_admin', 0)->count(),
                'people' => Person::count(),
                'system_roles' => Person::whereNotNull('role')->count(),
                'ordinary_staff' => Person::whereNull('role')->count(),
            ],
            'employee_import' => ExcelPeopleImportSeeder::$report,
        ];

        if (! self::$dryRun) {
            $this->writeReport(config('raqib.setup_report_path'), self::$report);
        }

        $counts = self::$report['counts'];
        $import = self::$report['employee_import'];

        $this->command?->info('✓ مراكز: ' . $counts['centers'] . ' | دوائر: ' . $counts['departments'] . ' | أقسام: ' . $counts['sections']);
        $this->command?->info('✓ ممولون: ' . $counts['funders']);
        $this->command?->info(
            '✓ موظفون: ' . ($import['imported'] ?? 0) . ' مستورد | ' . ($import['skipped'] ?? 0) . ' متخطى'
        );
        $this->command?->info(
            '✓ أدوار نظامية: ' . ($import['system_roles'] ?? 0) . ' | عاديون بدون دور: ' . ($import['without_role'] ?? 0)
        );

        $conflicts = count($import['manager_conflicts'] ?? []);

        if ($conflicts > 0) {
            $this->command?->warn('⚠ تعارضات مديرين: ' . $conflicts . ' (انظر التقرير)');
        }

        if (! self::$dryRun) {
            $this->command?->info('→ storage/' . config('raqib.setup_report_path'));
        }
    }

    private function writeReport(string $relativePath, array $payload): void
    {
        $path = storage_path($relativePath);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
