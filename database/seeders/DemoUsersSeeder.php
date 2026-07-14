<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Person;
use App\Models\RoleUser;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    /**
     * مستخدمون وهميون للاختبار — لا يمسّ super_admin.
     * تشغيل: php artisan db:seed --class=DemoUsersSeeder
     */
    public function run(): void
    {
        $departments = Department::pluck('id', 'name');

        $monitoringDeptId = $departments['دائرة الرقابة العامة'] ?? null;
        $projectsDeptId = $departments['دائرة المشاريع والتسويق والإعلام'] ?? null;
        $socialDeptId = $departments['دائرة التنمية الاجتماعية'] ?? null;
        $adminDeptId = $departments['دائرة الشؤون الإدارية'] ?? null;
        $financeDeptId = $departments['دائرة الشؤون المالية'] ?? null;
        $investDeptId = $departments['دائرة الاستثمار الخيري'] ?? null;

        $projectsSectionId = $this->firstSectionIdForDepartment($projectsDeptId);
        $socialSectionId = $this->firstSectionIdForDepartment($socialDeptId);
        $investSectionId = $this->firstSectionIdForDepartment($investDeptId);
        $financeSectionId = $this->firstSectionIdForDepartment($financeDeptId);
        $projectsMarketingSectionId = Section::where('department_id', $projectsDeptId)
            ->where('name', 'قسم المشاريع')
            ->value('id') ?? $projectsSectionId;
        $socialFamiliesSectionId = Section::where('department_id', $socialDeptId)
            ->where('name', 'قسم الأيتام والأسر')
            ->value('id') ?? $socialSectionId;

        $demoUsers = [
            // === مديرو مشروع (4) ===
            [
                'name' => 'أحمد الخطيب',
                'username' => 'pm_ahmad',
                'email' => 'pm.ahmad@raqib.demo',
                'role' => 'project_manager',
                'department_id' => $projectsDeptId,
                'section_id' => $projectsMarketingSectionId,
                'job_title' => 'مدير مشروع',
                'abilities' => ['projects.view', 'projects.create', 'projects.update'],
            ],
            [
                'name' => 'سارة المنصور',
                'username' => 'pm_sara',
                'email' => 'pm.sara@raqib.demo',
                'role' => 'project_manager',
                'department_id' => $socialDeptId,
                'section_id' => $socialFamiliesSectionId,
                'job_title' => 'مديرة مشروع',
                'abilities' => ['projects.view', 'projects.create', 'projects.update'],
            ],
            [
                'name' => 'خالد عوض',
                'username' => 'pm_khaled',
                'email' => 'pm.khaled@raqib.demo',
                'role' => 'project_manager',
                'department_id' => $investDeptId,
                'section_id' => $investSectionId,
                'job_title' => 'مدير مشروع',
                'abilities' => ['projects.view', 'projects.create', 'projects.update'],
            ],
            [
                'name' => 'نور الدين',
                'username' => 'pm_nour',
                'email' => 'pm.nour@raqib.demo',
                'role' => 'project_manager',
                'department_id' => $financeDeptId,
                'section_id' => $financeSectionId,
                'job_title' => 'مدير مشروع',
                'abilities' => ['projects.view', 'projects.create', 'projects.update'],
            ],

            // === منسقون (3) ===
            [
                'name' => 'ليلى حمدان',
                'username' => 'coord_layla',
                'email' => 'coord.layla@raqib.demo',
                'role' => 'coordinator',
                'department_id' => $projectsDeptId,
                'section_id' => $projectsMarketingSectionId,
                'job_title' => 'منسقة مشروع',
                'abilities' => ['projects.view', 'projects.fill_coordinator'],
            ],
            [
                'name' => 'يوسف إبراهيم',
                'username' => 'coord_youssef',
                'email' => 'coord.youssef@raqib.demo',
                'role' => 'coordinator',
                'department_id' => $socialDeptId,
                'section_id' => $socialFamiliesSectionId,
                'job_title' => 'منسق مشروع',
                'abilities' => ['projects.view', 'projects.fill_coordinator'],
            ],
            [
                'name' => 'رنا سليم',
                'username' => 'coord_rana',
                'email' => 'coord.rana@raqib.demo',
                'role' => 'coordinator',
                'department_id' => $investDeptId,
                'section_id' => $investSectionId,
                'job_title' => 'منسقة مشروع',
                'abilities' => ['projects.view', 'projects.fill_coordinator'],
            ],

            // === مديرو أقسام (2) ===
            [
                'name' => 'كريم أبو سالم',
                'username' => 'sm_projects',
                'email' => 'sm.projects@raqib.demo',
                'role' => 'section_manager',
                'department_id' => $projectsDeptId,
                'section_id' => $projectsMarketingSectionId,
                'job_title' => 'مدير قسم المشاريع',
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
                'name' => 'مها القواسمي',
                'username' => 'sm_social',
                'email' => 'sm.social@raqib.demo',
                'role' => 'section_manager',
                'department_id' => $socialDeptId,
                'section_id' => $socialFamiliesSectionId,
                'job_title' => 'مديرة قسم الأيتام والأسر',
                'abilities' => [
                    'projects.view',
                    'projects.approve_section',
                    'projects.reject',
                    'people.view',
                    'people.create',
                    'people.update',
                ],
            ],

            // === مديرو دوائر (5) ===
            [
                'name' => 'محمود الشريف',
                'username' => 'dm_projects',
                'email' => 'dm.projects@raqib.demo',
                'role' => 'department_manager',
                'department_id' => $projectsDeptId,
                'job_title' => 'مدير دائرة المشاريع',
                'abilities' => ['projects.view', 'projects.approve_department', 'projects.reject'],
            ],
            [
                'name' => 'فاطمة الزعانين',
                'username' => 'dm_social',
                'email' => 'dm.social@raqib.demo',
                'role' => 'department_manager',
                'department_id' => $socialDeptId,
                'job_title' => 'مديرة دائرة التنمية الاجتماعية',
                'abilities' => ['projects.view', 'projects.approve_department', 'projects.reject'],
            ],
            [
                'name' => 'عمر الجعبري',
                'username' => 'dm_admin',
                'email' => 'dm.admin@raqib.demo',
                'role' => 'department_manager',
                'department_id' => $adminDeptId,
                'job_title' => 'مدير دائرة الشؤون الإدارية',
                'abilities' => ['projects.view', 'projects.approve_department', 'projects.reject'],
            ],
            [
                'name' => 'هبة النجار',
                'username' => 'dm_finance',
                'email' => 'dm.finance@raqib.demo',
                'role' => 'department_manager',
                'department_id' => $financeDeptId,
                'job_title' => 'مديرة دائرة الشؤون المالية',
                'abilities' => ['projects.view', 'projects.approve_department', 'projects.reject'],
            ],
            [
                'name' => 'طارق أبو ستة',
                'username' => 'dm_invest',
                'email' => 'dm.invest@raqib.demo',
                'role' => 'department_manager',
                'department_id' => $investDeptId,
                'job_title' => 'مدير دائرة الاستثمار الخيري',
                'abilities' => ['projects.view', 'projects.approve_department', 'projects.reject'],
            ],

            // === مدير الرقابة العامة ===
            [
                'name' => 'د. حسام المغني',
                'username' => 'mon_dir',
                'email' => 'monitoring.director@raqib.demo',
                'role' => 'monitoring_director',
                'department_id' => $monitoringDeptId,
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

            // === مراقبون (4) ===
            [
                'name' => 'سمير نصار',
                'username' => 'monitor1',
                'email' => 'monitor1@raqib.local',
                'role' => 'monitor',
                'department_id' => $monitoringDeptId,
                'job_title' => 'مراقب ميداني',
                'abilities' => ['projects.view', 'projects.fill_monitor', 'monitoringactivities.view', 'monitoringactivities.update'],
            ],
            [
                'name' => 'إياد أبو عبدو',
                'username' => 'monitor2',
                'email' => 'monitor2@raqib.local',
                'role' => 'monitor',
                'department_id' => $monitoringDeptId,
                'job_title' => 'مراقب ميداني',
                'abilities' => ['projects.view', 'projects.fill_monitor', 'monitoringactivities.view', 'monitoringactivities.update'],
            ],
            [
                'name' => 'محمد حسن',
                'username' => 'monitor3',
                'email' => 'monitor3@raqib.local',
                'role' => 'monitor',
                'department_id' => $monitoringDeptId,
                'job_title' => 'مراقب ميداني',
                'abilities' => ['projects.view', 'projects.fill_monitor', 'monitoringactivities.view', 'monitoringactivities.update'],
            ],
            [
                'name' => 'وليد القدسي',
                'username' => 'monitor4',
                'email' => 'monitor4@raqib.demo',
                'role' => 'monitor',
                'department_id' => $monitoringDeptId,
                'job_title' => 'مراقب ميداني',
                'abilities' => ['projects.view', 'projects.fill_monitor', 'monitoringactivities.view', 'monitoringactivities.update'],
            ],

            // === الإدارة العامة ===
            [
                'name' => 'عبدالله النجار',
                'username' => 'gen_mgmt',
                'email' => 'general.mgmt@raqib.demo',
                'role' => 'general_management',
                'department_id' => null,
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

            // === أدمن نظام (دور وظيفي — ليس super_admin) ===
            [
                'name' => 'ريم عودة',
                'username' => 'sys_admin',
                'email' => 'admin@raqib.demo',
                'role' => 'admin',
                'department_id' => null,
                'job_title' => 'أدمن النظام',
                'abilities' => [
                    'users.view', 'users.create', 'users.update',
                    'people.view', 'people.create', 'people.update', 'people.delete',
                    'constants.view', 'constants.create', 'constants.update',
                    'centers.view', 'centers.create', 'centers.update',
                    'departments.view', 'departments.create', 'departments.update',
                    'sections.view', 'sections.create', 'sections.update',
                    'funders.view', 'funders.create', 'funders.update',
                    'checklist_admin.manage',
                ],
            ],
        ];

        foreach ($demoUsers as $data) {
            $this->seedDemoUser($data);
        }

        $this->reapplyDemoOrgAssignments($demoUsers);

        Person::whereNull('user_id')->delete();

        $this->command?->info('تم إنشاء/تحديث ' . count($demoUsers) . ' مستخدم وهمي (كلمة المرور: ' . self::DEMO_PASSWORD . ').');
        $this->command?->info('حساب super_admin الأساسي لم يُمس.');
    }

    private function seedDemoUser(array $data): void
    {
        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'username' => $data['username'],
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

        $this->syncAbilities($user, $data['abilities']);
    }

    /**
     * يُعيد مزامنة الدائرة/القسم لحسابات التجربة حتى بعد تحديثات قديمة على people.
     *
     * @param  array<int, array<string, mixed>>  $demoUsers
     */
    private function reapplyDemoOrgAssignments(array $demoUsers): void
    {
        foreach ($demoUsers as $data) {
            $user = User::query()
                ->where('username', $data['username'])
                ->orWhere('email', $data['email'])
                ->first();

            if (! $user?->person) {
                continue;
            }

            $user->person->update([
                'name' => $data['name'],
                'role' => $data['role'],
                'department_id' => $data['department_id'],
                'section_id' => $data['section_id'] ?? null,
                'job_title' => $data['job_title'],
            ]);

            $this->syncAbilities($user, $data['abilities']);
        }
    }

    private function firstSectionIdForDepartment(?int $departmentId): ?int
    {
        if (! $departmentId) {
            return null;
        }

        return Section::where('department_id', $departmentId)->orderBy('id')->value('id');
    }

    private function syncAbilities(User $user, array $abilities): void
    {
        RoleUser::where('user_id', $user->id)->delete();

        foreach (array_unique($abilities) as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }
    }
}
