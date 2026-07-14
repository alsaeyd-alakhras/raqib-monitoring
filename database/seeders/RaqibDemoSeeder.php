<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * إعداد تجريبي: ثوابت + checklist + هيكل + ممولين + 7 حسابات وهمية — بدون موظفي Excel.
 */
class RaqibDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ConstantsSeeder::class,
            ChecklistSeeder::class,
            ExcelOrganizationalSeeder::class,
            ExcelFundersSeeder::class,
            SimpleDemoUsersSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
