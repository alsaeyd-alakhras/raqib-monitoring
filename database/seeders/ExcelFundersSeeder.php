<?php

namespace Database\Seeders;

use App\Models\Funder;
use App\Services\Import\ExcelSheetReader;
use Illuminate\Database\Seeder;

class ExcelFundersSeeder extends Seeder
{
    public function run(): void
    {
        if (RaqibMasterSeeder::$dryRun) {
            return;
        }

        $reader = new ExcelSheetReader(config('raqib.excel_path'));

        foreach ($reader->uniqueColumnValues('LISTS', 'W', 2) as $name) {
            Funder::firstOrCreate(['name' => $name]);
        }
    }
}
