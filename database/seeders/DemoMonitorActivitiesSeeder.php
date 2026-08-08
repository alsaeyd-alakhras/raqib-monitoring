<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Department;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\RoleUser;
use App\Models\User;
use App\Services\RoleAbilitiesService;
use Illuminate\Database\Seeder;

/**
 * أنشطة خارجية تجريبية لحساب demo_monitor — additive، لا يمسح البيانات.
 *
 * تشغيل: php artisan db:seed --class=DemoMonitorActivitiesSeeder
 */
class DemoMonitorActivitiesSeeder extends Seeder
{
    private const REF_PREFIX = 'MA-DEMO-MON-';

    public function run(): void
    {
        $user = User::where('username', 'demo_monitor')->first();

        if (! $user?->person) {
            $this->command?->warn('لم يُعثر على demo_monitor — شغّل SimpleDemoUsersSeeder أولاً.');

            return;
        }

        $this->ensureMonitorAccount($user);

        $center = Center::query()->orderBy('id')->first();
        $department = Department::query()->when($center, fn ($q) => $q->where('center_id', $center->id))->orderBy('id')->first();

        if (! $center || ! $department) {
            $this->command?->error('تعذّر العثور على مركز/دائرة — شغّل seeders الهيكل أولاً.');

            return;
        }

        $monitorPersonId = (int) $user->person->id;
        $directorUserId = User::query()
            ->whereHas('person', fn ($q) => $q->where('role', 'monitoring_director'))
            ->value('id');

        $scenarios = [
            [
                'suffix' => '01',
                'subject' => 'زيارة ميدانية — مخيم شمال غزة',
                'workflow_status' => 'in_progress',
                'notes' => 'نشاط تحت العمل — يتطلب تعبئة وإرسال.',
            ],
            [
                'suffix' => '02',
                'subject' => 'متابعة توزيع مساعدات — خان يونس',
                'workflow_status' => 'in_progress',
                'notes' => 'رُجع من مدير الرقابة — يرجى استكمال التقييم.',
                'rejection_reason' => 'يرجى رفع صورة أوضح للموقع واستكمال قيم الإغلاق.',
                'gap_owner' => 'monitor',
                'rejected_by' => $directorUserId,
                'rejected_at' => now()->subDays(1),
            ],
            [
                'suffix' => '03',
                'subject' => 'تقرير رقابي — مشروع إيواء مؤقت',
                'workflow_status' => 'pending_confirmation',
                'notes' => 'مُرسَل لمدير الرقابة — بانتظار الاعتماد.',
                'submitted_at' => now()->subHours(6),
                'submitted_by' => $user->id,
            ],
            [
                'suffix' => '04',
                'subject' => 'اجتماع متابعة — دائرة الرقابة',
                'workflow_status' => 'completed',
                'notes' => 'نشاط مكتمل للمرجعية.',
                'is_passage_complete' => true,
                'passage_completed_at' => now()->subDays(3),
                'passage_completed_by' => $directorUserId,
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($scenarios as $scenario) {
            $referenceCode = self::REF_PREFIX . $scenario['suffix'];

            if (MonitoringActivity::where('reference_code', $referenceCode)->exists()) {
                $skipped++;

                continue;
            }

            MonitoringActivity::create([
                'reference_code' => $referenceCode,
                'source_type' => 'external',
                'source_id' => null,
                'activity_role' => 'secondary',
                'center_id' => $center->id,
                'department_id' => $department->id,
                'monitor_person_id' => $monitorPersonId,
                'activity_date' => now()->subDays((int) $scenario['suffix']),
                'activity_time' => '10:30',
                'activity_type' => 'زيارة ميدانية',
                'subject' => $scenario['subject'],
                'notes' => $scenario['notes'],
                'field_problem' => false,
                'execution_value' => 80,
                'quality_value' => 85,
                'closure_value' => ($scenario['suffix'] === '02') ? 50 : 70,
                'deduction_value' => 0,
                'workflow_status' => $scenario['workflow_status'],
                'is_passage_complete' => (bool) ($scenario['is_passage_complete'] ?? false),
                'passage_completed_at' => $scenario['passage_completed_at'] ?? null,
                'passage_completed_by' => $scenario['passage_completed_by'] ?? null,
                'submitted_at' => $scenario['submitted_at'] ?? null,
                'submitted_by' => $scenario['submitted_by'] ?? null,
                'rejection_reason' => $scenario['rejection_reason'] ?? null,
                'rejected_by' => $scenario['rejected_by'] ?? null,
                'rejected_at' => $scenario['rejected_at'] ?? null,
                'gap_owner' => $scenario['gap_owner'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $created++;
        }

        $this->command?->info("أنشطة demo_monitor: {$created} جديد، {$skipped} موجود مسبقاً.");
        $this->command?->line('  حساب: demo_monitor / password');
        $this->command?->line('  رموز: ' . self::REF_PREFIX . '01 … ' . self::REF_PREFIX . '04');
    }

    private function ensureMonitorAccount(User $user): void
    {
        $user->person->update([
            'role' => 'monitor',
        ]);

        RoleUser::where('user_id', $user->id)->delete();

        foreach (app(RoleAbilitiesService::class)->forRole('monitor') as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }
    }
}
