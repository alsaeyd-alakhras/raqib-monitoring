<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\RoleUser;
use App\Models\User;
use App\Services\Import\EmployeeImportMapper;
use App\Services\Import\ExcelSheetReader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class ExcelPeopleImportSeeder extends Seeder
{
    public static array $report = [];

    public function run(): void
    {
        $mappings = require base_path('data/employee-import-mappings.php');
        $reader = new ExcelSheetReader(config('raqib.excel_path'));
        $rows = $reader->readAssociativeRows($mappings['employee_sheet'], 2, 3);

        $mapper = new EmployeeImportMapper();
        $result = $mapper->mapRows($rows);
        self::$report = $result['report'];

        if (RaqibMasterSeeder::$dryRun) {
            return;
        }

        foreach ($result['rows'] as $item) {
            $user = User::updateOrCreate(
                ['username' => $item['national_id']],
                [
                    'name' => $item['name'],
                    'email' => null,
                    'password' => Hash::make($item['national_id']),
                    'user_type' => 'employee',
                    'is_active' => true,
                    'super_admin' => false,
                ]
            );

            Person::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $item['name'],
                    'role' => $item['role'],
                    'department_id' => $item['department_id'],
                    'section_id' => $item['section_id'],
                    'job_title' => $item['job_title'],
                ]
            );

            RoleUser::where('user_id', $user->id)->delete();

            foreach ($mapper->abilitiesForRole($item['role']) as $ability) {
                RoleUser::create([
                    'role_name' => $ability,
                    'user_id' => $user->id,
                    'ability' => 'allow',
                ]);
            }
        }

        $path = storage_path(config('raqib.employee_import_report_path'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(self::$report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
