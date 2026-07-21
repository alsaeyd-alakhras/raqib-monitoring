<?php

namespace App\Console\Commands;

use App\Services\Import\MasterDataCleaner;
use Database\Seeders\RaqibDemoSeeder;
use Illuminate\Console\Command;

class RaqibDemoCommand extends Command
{
    protected $signature = 'raqib:demo {--fresh : إعادة بناء القاعدة بالكامل (موصى به)}';

    protected $description = 'بيئة تجريبية بسيطة لشرح المشروع — 7 حسابات وهمية بدون موظفي Excel';

    public function handle(MasterDataCleaner $cleaner): int
    {
        if (! $this->option('fresh')) {
            $this->warn('يُفضّل استخدام --fresh لبيئة تجريبية نظيفة.');
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        } else {
            $this->info('تنظيف المستخدمين والموظفين الحاليين...');
            $cleaner->clean();
        }

        $this->call('db:seed', [
            '--class' => RaqibDemoSeeder::class,
            '--force' => true,
        ]);

        $this->newLine();
        $this->info('✓ بيئة التجربة جاهزة.');
        $this->line('  super_admin من .env (RAQIB_ADMIN_*)');
        $this->line('  باقي الحسابات: demo_pm, demo_sec, demo_coord, demo_sm, demo_dm, demo_mon_dir, demo_monitor, demo_gen');
        $this->line('  كلمة المرور: password');

        return self::SUCCESS;
    }
}
