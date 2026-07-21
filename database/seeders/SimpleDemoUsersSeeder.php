<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Person;
use App\Models\Project;
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

    /** صلاحيات demo_pm — إنشاء/تعديل مشاريعه فقط، بدون تعبئة عمود المنسق */
    public const DEMO_PM_ABILITIES = [
        'projects.view',
        'projects.create',
        'projects.update',
    ];

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

        $socialDeptId = Department::where('name', DemoSocialFamilyUsersSeeder::DEPT_NAME)->value('id');
        $socialSectionId = Section::query()
            ->where('department_id', $socialDeptId)
            ->where('name', DemoSocialFamilyUsersSeeder::SECTION_NAME)
            ->value('id');

        $demoUsers = [
            [
                'name' => 'أحمد — مدير مشروع (تجريبي)',
                'username' => 'demo_pm',
                'email' => 'demo.pm@raqib.local',
                'role' => 'project_manager',
                'department_id' => $projectsDeptId,
                'section_id' => $projectsSectionId,
                'job_title' => 'مدير مشروع',
                'abilities' => self::DEMO_PM_ABILITIES,
            ],
            [
                'name' => 'رنا — سكرتاريا مشاريع (تجريبية)',
                'username' => 'demo_sec',
                'email' => 'demo.sec@raqib.local',
                'role' => 'project_secretariat',
                'department_id' => $projectsDeptId,
                'section_id' => null,
                'job_title' => 'سكرتاريا الدائرة',
                'phone' => '0599111222',
                'abilities' => ['projects.view', 'projects.fill_secretariat'],
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

        if ($socialDeptId && $socialSectionId) {
            $demoUsers = array_merge($demoUsers, [
                [
                    'name' => 'سارة — مديرة مشروع أيتام (تجريبية)',
                    'username' => 'demo_social_pm',
                    'email' => 'demo.social.pm@raqib.local',
                    'role' => 'project_manager',
                    'department_id' => $socialDeptId,
                    'section_id' => $socialSectionId,
                    'job_title' => 'مديرة مشروع',
                    'phone' => '0599333001',
                    'abilities' => self::DEMO_PM_ABILITIES,
                ],
                [
                    'name' => 'يوسف — منسق أيتام (تجريبي)',
                    'username' => 'demo_social_coord',
                    'email' => 'demo.social.coord@raqib.local',
                    'role' => 'coordinator',
                    'department_id' => $socialDeptId,
                    'section_id' => $socialSectionId,
                    'job_title' => 'منسق مشروع',
                    'phone' => '0599333002',
                    'abilities' => ['projects.view', 'projects.fill_coordinator'],
                ],
                [
                    'name' => 'لينا — سكرتاريا أيتام (تجريبية)',
                    'username' => 'demo_social_sec',
                    'email' => 'demo.social.sec@raqib.local',
                    'role' => 'project_secretariat',
                    'department_id' => $socialDeptId,
                    'section_id' => null,
                    'job_title' => 'سكرتاريا الدائرة',
                    'phone' => '0599333003',
                    'abilities' => ['projects.view', 'projects.fill_secretariat'],
                ],
                [
                    'name' => 'مها — مديرة قسم أيتام (تجريبية)',
                    'username' => 'demo_social_sm',
                    'email' => 'demo.social.sm@raqib.local',
                    'role' => 'section_manager',
                    'department_id' => $socialDeptId,
                    'section_id' => $socialSectionId,
                    'job_title' => 'مديرة قسم الأيتام والأسر',
                    'phone' => '0599333004',
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
                    'name' => 'فاطمة — مديرة دائرة (تجريبية)',
                    'username' => 'demo_social_dm',
                    'email' => 'demo.social.dm@raqib.local',
                    'role' => 'department_manager',
                    'department_id' => $socialDeptId,
                    'section_id' => null,
                    'job_title' => 'مديرة دائرة التنمية الاجتماعية',
                    'phone' => '0599333005',
                    'abilities' => ['projects.view', 'projects.approve_department', 'projects.reject'],
                ],
            ]);
        } else {
            $this->command?->warn('تخطّي حسابات الأيتام والأسر — لم يُعثر على دائرة التنمية الاجتماعية أو القسم.');
        }

        foreach ($demoUsers as $data) {
            $this->seedDemoUser($data);
        }

        $referencedPersonIds = collect()
            ->merge(Project::whereNotNull('coordinator_id')->pluck('coordinator_id'))
            ->merge(Project::whereNotNull('project_manager_id')->pluck('project_manager_id'))
            ->merge(Project::whereNotNull('monitor_person_id')->pluck('monitor_person_id'))
            ->unique()
            ->filter()
            ->all();

        Person::whereNull('user_id')
            ->when($referencedPersonIds !== [], fn ($q) => $q->whereNotIn('id', $referencedPersonIds))
            ->delete();

        $this->command?->newLine();
        $this->command?->info('═══ حسابات التجربة (كلمة المرور: ' . self::DEMO_PASSWORD . ') ═══');
        $this->command?->table(
            ['الدور', 'اسم المستخدم', 'الاسم', 'الصلاحيات'],
            collect($demoUsers)->map(fn (array $u) => [
                Person::roleLabels()[$u['role']] ?? $u['role'],
                $u['username'],
                $u['name'],
                implode(', ', $u['abilities']),
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
                'phone' => $data['phone'] ?? null,
            ]
        );

        if (! empty($data['phone'])) {
            $user->update(['phone' => $data['phone']]);
        }

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
