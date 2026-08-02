<?php

namespace App\Console\Commands;

use App\Services\PromoteOrdinaryToCoordinators;
use Illuminate\Console\Command;

class PromoteOrdinaryToCoordinatorsCommand extends Command
{
    protected $signature = 'raqib:promote-ordinary-to-coordinators {--dry-run : معاينة بدون حفظ}';

    protected $description = 'تحويل الموظفين العاديين (بدون دور) الذين لديهم قسم إلى منسقين';

    public function handle(PromoteOrdinaryToCoordinators $promoter): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('وضع المعاينة — لن يتم حفظ أي بيانات.');
        } elseif (app()->environment('production')) {
            if (! $this->confirm('هل أنت متأكد من تحويل الموظفين العاديين إلى منسقين على بيئة الإنتاج؟')) {
                $this->info('تم الإلغاء.');

                return self::SUCCESS;
            }
        }

        $report = $promoter->run($dryRun);

        $this->info(
            ($dryRun ? '[dry-run] ' : '')
            .'سيُحوَّل: '.$report['promoted_count']
            .' | يُتخطّى (بدون قسم): '.$report['skipped_no_section_count']
        );

        foreach ($report['promoted'] as $person) {
            $this->line('  → '.$person['name'].' ('.$person['username'].')');
        }

        if ($report['skipped_no_section_count'] > 0) {
            $this->newLine();
            $this->warn('متخطّون بدون section_id:');

            foreach ($report['skipped_no_section'] as $person) {
                $this->line('  ⊘ '.$person['name'].' ('.$person['username'].')');
            }
        }

        if (! $dryRun) {
            $this->info('→ storage/'.config('raqib.promote_coordinators_report_path'));
        }

        return self::SUCCESS;
    }
}
