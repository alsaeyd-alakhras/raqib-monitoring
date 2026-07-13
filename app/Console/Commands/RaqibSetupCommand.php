<?php

namespace App\Console\Commands;

use App\Services\Import\MasterDataCleaner;
use Database\Seeders\RaqibMasterSeeder;
use Illuminate\Console\Command;

class RaqibSetupCommand extends Command
{
    protected $signature = 'raqib:setup {--fresh : إعادة بناء القاعدة بالكامل} {--dry-run : معاينة بدون حفظ}';

    protected $description = 'إعداد قاعدة raqib من Excel — تنظيف + هيكل + ممولين + موظفين + super_admin';

    public function handle(MasterDataCleaner $cleaner): int
    {
        $fresh = (bool) $this->option('fresh');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('وضع المعاينة — لن يتم حفظ أي بيانات.');
        }

        if (app()->environment('production') && ! $dryRun) {
            if (! $this->confirm('هل أنت متأكد من تشغيل الإعداد على بيئة الإنتاج؟')) {
                $this->info('تم الإلغاء.');

                return self::SUCCESS;
            }
        }

        if ($fresh) {
            if (! $dryRun) {
                $this->call('migrate:fresh', ['--force' => true]);
            } else {
                $this->info('[dry-run] migrate:fresh');
            }
        } elseif (! $dryRun) {
            $this->info('تنظيف البيانات القديمة...');
            $cleaner->clean();
        } else {
            $this->info('[dry-run] MasterDataCleaner');
        }

        RaqibMasterSeeder::$dryRun = $dryRun;

        if ($dryRun) {
            $seeder = app(RaqibMasterSeeder::class);
            $seeder->setCommand($this);
            $seeder->run();
        } else {
            $this->call('db:seed', [
                '--class' => RaqibMasterSeeder::class,
                '--force' => true,
            ]);
        }

        return self::SUCCESS;
    }
}
