<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Department;
use App\Models\Section;
use App\Services\Import\ExcelSheetReader;
use Illuminate\Database\Seeder;

class ExcelOrganizationalSeeder extends Seeder
{
    private const SHEET = 'LISTS';

    /** @var array<string, string> */
    private const COLUMN_DEPARTMENTS = [
        'AB' => 'الجمعية',
        'AC' => 'الجمعية',
        'AD' => 'الجمعية',
        'AE' => 'الجمعية',
        'AF' => 'الجمعية',
        'AG' => 'الجمعية',
        'AH' => 'المراكز الصحية',
        'AI' => 'المراكز الصحية',
        'AJ' => 'المراكز الصحية',
    ];

    public function run(): void
    {
        if (RaqibMasterSeeder::$dryRun) {
            return;
        }

        $reader = new ExcelSheetReader(config('raqib.excel_path'));
        $sheet = $reader->sheet(self::SHEET);

        foreach (self::COLUMN_DEPARTMENTS as $column => $centerName) {
            $departmentName = $reader->cellValue($sheet, $column, 1);

            if ($departmentName === '') {
                continue;
            }

            $center = Center::firstOrCreate(['name' => $centerName]);
            $department = Department::firstOrCreate([
                'center_id' => $center->id,
                'name' => $departmentName,
            ]);

            $sections = [];

            for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                $sectionName = $reader->cellValue($sheet, $column, $row);

                if ($sectionName !== '' && ! str_starts_with($sectionName, '=')) {
                    $sections[$sectionName] = true;
                }
            }

            foreach (array_keys($sections) as $sectionName) {
                Section::firstOrCreate([
                    'department_id' => $department->id,
                    'name' => $sectionName,
                ]);
            }
        }

        $associationCenter = Center::firstOrCreate(['name' => 'الجمعية']);
        Department::firstOrCreate([
            'center_id' => $associationCenter->id,
            'name' => 'دائرة الرقابة العامة',
        ]);
    }
}
