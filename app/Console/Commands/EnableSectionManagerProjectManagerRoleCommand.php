<?php

namespace App\Console\Commands;

use App\Models\Person;
use Illuminate\Console\Command;

class EnableSectionManagerProjectManagerRoleCommand extends Command
{
    protected $signature = 'raqib:section-managers-enable-pm
                            {--dry-run : معاينة بدون حفظ — لا يغيّر قاعدة البيانات}
                            {--force : تنفيذ على الإنتاج بدون تأكيد تفاعلي}';

    protected $description = 'إضافة دور «مدير مشروع» الإضافي لكل مدير/ة قسم (ليظهر في قائمة مديري المشاريع)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('⚠️  وضع المعاينة فقط — لن يُحفظ أي شيء في قاعدة البيانات.');
        } elseif (app()->environment('production') && ! $force && ! $this->input->isInteractive()) {
            $this->error('على الإنتاج استخدم --force أو شغّل الأمر من طرفية تفاعلية.');

            return self::FAILURE;
        } elseif (app()->environment('production') && ! $force) {
            if (! $this->confirm('هل تريد تفعيل «مدير مشروع» لجميع مديري الأقسام على بيئة الإنتاج؟')) {
                $this->info('تم الإلغاء.');

                return self::SUCCESS;
            }
        }

        $people = Person::query()
            ->where('role', 'section_manager')
            ->orderBy('name')
            ->get();

        if ($people->isEmpty()) {
            $this->warn('لا يوجد مديرو/ات أقسام في النظام.');

            return self::SUCCESS;
        }

        $granted = 0;
        $already = 0;

        foreach ($people as $person) {
            if ($person->hasRole('project_manager')) {
                $already++;
                $this->line("  ✓ {$person->name} — لديه «مدير مشروع» مسبقاً");

                continue;
            }

            if ($dryRun) {
                $granted++;
                $this->line("  → {$person->name} — سيُضاف «مدير مشروع»");

                continue;
            }

            $roles = $person->additionalRoles();
            $roles[] = 'project_manager';
            $person->update(['additional_roles' => array_values(array_unique($roles))]);
            $granted++;
            $this->line("  ✓ {$person->name} — تم تفعيل «مدير مشروع»");
        }

        $this->newLine();
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}تم: {$granted} | كان مفعّلاً مسبقاً: {$already} | الإجمالي: {$people->count()}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️  لم يُحفظ شيء! لتطبيق التغييرات على القائمة شغّل:');
            $this->line('   php artisan raqib:section-managers-enable-pm --force');
            $this->line('   php artisan raqib:sync-role-abilities --section-managers');

            return self::SUCCESS;
        }

        $sectionManagersInDropdown = Person::query()
            ->eligibleAsProjectManager()
            ->where('role', 'section_manager')
            ->count();
        $totalInDropdown = Person::query()->eligibleAsProjectManager()->count();

        $this->newLine();
        $this->info("التحقق: {$sectionManagersInDropdown} مدير/ة قسم + {$totalInDropdown} إجمالاً في قائمة «مدير المشروع».");

        if ($granted > 0) {
            $this->info('→ يُنصح بتشغيل: php artisan raqib:sync-role-abilities --section-managers');
        }

        return self::SUCCESS;
    }
}
