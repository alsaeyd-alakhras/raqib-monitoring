<?php

namespace App\Console\Commands;

use Database\Seeders\DemoProjectsSeeder;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Console\Command;

class SeedDemoDataCommand extends Command
{
    protected $signature = 'raqib:seed-demo-data
                            {--users-only : المستخدمون فقط بدون مشاريع}
                            {--projects-only : المشاريع فقط (يتطلب users)}';

    protected $description = 'إضافة بيانات تجريبية للاختبار اليدوي — لا يمسح البيانات الموجودة';

    public function handle(): int
    {
        $this->info('إضافة بيانات تجريبية (additive — بدون مسح)...');

        if (! $this->option('projects-only')) {
            $this->call('db:seed', ['--class' => DemoUsersSeeder::class, '--force' => true]);
        }

        if (! $this->option('users-only')) {
            $this->call('db:seed', ['--class' => DemoProjectsSeeder::class, '--force' => true]);
        }

        $this->newLine();
        $this->info('✓ جاهز للاختبار اليدوي.');
        $this->line('  حسابات: pm_ahmad, sec_hana, coord_layla, sm_projects, dm_projects, mon_dir, monitor1');
        $this->line('  مشاريع: DEMO-01 … DEMO-10');
        $this->line('  كلمة المرور: password');

        return self::SUCCESS;
    }
}
