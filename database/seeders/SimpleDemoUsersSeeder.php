<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Person;
use App\Models\RoleUser;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * حسابات تجريبية بسيطة لشرح دورة المشروع — مستخدم واحد لكل دور رئيسي.
 *
 * تشغيل: php artisan raqib:demo --fresh
 * كلمة المرور لجميع الحسابات: password
 */
class SimpleDemoUsersSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $projectsDeptId = Department::where('name', 'دائرة المشاريع والتسويق والإعلام')->value('id');
        $monitoringDeptId = Department::where('name', 'دائرة الرقابة العامة')->value('id');

        $projectsSectionId = Section::query()
            ->where('department_id', $projectsDeptId)
            ->where('name', 'قسم المشاريع والتسويق')
            ->value('id');

        if (! $projectsDeptId || ! $projectsSectionId || ! $monitoringDeptId) {
            $this->command?->error('الهيكل التنظيمي غير جاهز. شغّل أولاً: php artisan raqib:demo --fresh');

            return;
        }

        $demoUsers = [
            [
                'name' => 'أحمد — مدير مشروع (تجريبي)',
                'username' => 'demo_pm',
                'email' => 'demo.pm@raqib.local',
                'role' => 'project_manager',
                'department_id' => $projectsDeptId,
                'section_id' => $projectsSectionId,
                'job_title' => 'مدير مشروع',
                'abilities' => ['projects.view', 'projects.create', 'projects.update', 'projects.fill_coordinator'],
            ],
            [
                'name' => 'ليلى — منسقة (تجريبية)',
                'username' => 'demo_coord',
                'email' => 'demo.coord@raqib.local',
                'role' => 'coordinator',
                'department_id' => $projectsDeptId,
                'section_id' => $projectsSectionId,
                'job_title' => 'منسقة مشروع',
                'abilities' => ['projects.view', 'projects.fill_coordinator'],
            ],
            [
                'name' => 'كريم — مدير قسم (تجريبي)',
                'username' => 'demo_sm',
                'email' => 'demo.sm@raqib.local',
                'role' => 'section_manager',
                'department_id' => $projectsDeptId,
                'section_id' => $projectsSectionId,
                'job_title' => 'مدير قسم المشاريع والتسويق',
                'abilities' => [
                    'projects.view',
                    'projects.approve_section',
                    'projects.reject',
                    'people.view',
                    'people.create',
                    'people.update',
                ],
            ],
            [
                'name' => 'محمود — مدير دائرة (تجريبي)',
                'username' => 'demo_dm',
                'email' => 'demo.dm@raqib.local',
                'role' => 'department_manager',
                'department_id' => $projectsDeptId,
                'section_id' => null,
                'job_title' => 'مدير دائرة المشاريع',
                'abilities' => ['projects.view', 'projects.approve_department', 'projects.reject'],
            ],
            [
                'name' => 'حسام — مدير الرقابة (تجريبي)',
                'username' => 'demo_mon_dir',
                'email' => 'demo.mondir@raqib.local',
                'role' => 'monitoring_director',
                'department_id' => $monitoringDeptId,
                'section_id' => null,
                'job_title' => 'مدير الرقابة العامة',
                'abilities' => [
                    'projects.view',
                    'projects.update',
                    'projects.reject',
                    'monitoringactivities.view',
                    'monitoringactivities.create',
                    'monitoringactivities.update',
                    'monitoringactivities.set_monitoring_info',
                    'monitoringactivities.assign_monitor',
                    'monitoringactivities.confirm_completion',
                    'monitoringactivities.edit_ratings',
                    'monitoringactivities.reject',
                ],
            ],
            [
                'name' => 'سمير — مراقب (تجريبي)',
                'username' => 'demo_monitor',
                'email' => 'demo.monitor@raqib.local',
                'role' => 'monitor',
                'department_id' => $monitoringDeptId,
                'section_id' => null,
                'job_title' => 'مراقب ميداني',
                'abilities' => [
                    'projects.view',
                    'projects.fill_monitor',
                    'monitoringactivities.view',
                    'monitoringactivities.update',
                ],
            ],
            [
                'name' => 'عبدالله — الإدارة العامة (تجريبي)',
                'username' => 'demo_gen',
                'email' => 'demo.gen@raqib.local',
                'role' => 'general_management',
                'department_id' => null,
                'section_id' => null,
                'job_title' => 'المدير العام',
                'abilities' => [
                    'projects.view',
                    'monitoringactivities.view',
                    'monitoringactivities.edit_ratings',
                    'people.view',
                    'funders.view',
                    'centers.view',
                    'departments.view',
                ],
            ],
        ];

        foreach ($demoUsers as $data) {
            $this->seedDemoUser($data);
        }

        Person::whereNull('user_id')->delete();

        $this->command?->newLine();
        $this->command?->info('═══ حسابات التجربة (كلمة المرور: ' . self::DEMO_PASSWORD . ') ═══');
        $this->command?->table(
            ['الدور', 'اسم المستخدم', 'الاسم'],
            collect($demoUsers)->map(fn (array $u) => [
                Person::roleLabels()[$u['role']] ?? $u['role'],
                $u['username'],
                $u['name'],
            ])->all()
        );
        $this->command?->info('للعودة للبيانات الحقيقية: php artisan raqib:setup --fresh');
    }

    private function seedDemoUser(array $data): void
    {
        $user = User::updateOrCreate(
            ['username' => $data['username']],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'user_type' => 'employee',
                'is_active' => true,
                'super_admin' => false,
                'password' => Hash::make(self::DEMO_PASSWORD),
            ]
        );

        Person::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $data['name'],
                'role' => $data['role'],
                'department_id' => $data['department_id'],
                'section_id' => $data['section_id'] ?? null,
                'job_title' => $data['job_title'],
            ]
        );

        RoleUser::where('user_id', $user->id)->delete();

        foreach (array_unique($data['abilities']) as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }
    }
}
