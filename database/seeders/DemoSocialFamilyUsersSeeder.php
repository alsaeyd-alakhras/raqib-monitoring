<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Person;
use App\Models\RoleUser;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * حسابات تجريبية كاملة لدائرة التنمية الاجتماعية — قسم الأيتام والأسر.
 * لاختبار عزل السكرتاريا بين الدوائر (مع demo_sec / demo_pm لدائرة المشاريع).
 *
 * php artisan db:seed --class=DemoSocialFamilyUsersSeeder
 */
class DemoSocialFamilyUsersSeeder extends Seeder
{
    public const DEPT_NAME = 'دائرة التنمية الاجتماعية';

    public const SECTION_NAME = 'قسم الأيتام والأسر';

    public function run(): void
    {
        $this->ensurePeopleRoleCheckAllowsSecretariat();

        $socialDeptId = Department::where('name', self::DEPT_NAME)->value('id');
        $sectionId = Section::query()
            ->where('department_id', $socialDeptId)
            ->where('name', self::SECTION_NAME)
            ->value('id');

        if (! $socialDeptId || ! $sectionId) {
            $this->command?->error('تعذّر إيجاد «' . self::DEPT_NAME . '» أو «' . self::SECTION_NAME . '». شغّل migrate/seed للهيكل التنظيمي أولاً.');

            return;
        }

        $password = SimpleDemoUsersSeeder::DEMO_PASSWORD;

        $demoUsers = [
            [
                'name' => 'سارة — مديرة مشروع أيتام (تجريبية)',
                'username' => 'demo_social_pm',
                'email' => 'demo.social.pm@raqib.local',
                'phone' => '0599333001',
                'role' => 'project_manager',
                'department_id' => $socialDeptId,
                'section_id' => $sectionId,
                'job_title' => 'مديرة مشروع',
                'abilities' => SimpleDemoUsersSeeder::DEMO_PM_ABILITIES,
            ],
            [
                'name' => 'يوسف — منسق أيتام (تجريبي)',
                'username' => 'demo_social_coord',
                'email' => 'demo.social.coord@raqib.local',
                'phone' => '0599333002',
                'role' => 'coordinator',
                'department_id' => $socialDeptId,
                'section_id' => $sectionId,
                'job_title' => 'منسق مشروع',
                'abilities' => ['projects.view', 'projects.fill_coordinator'],
            ],
            [
                'name' => 'لينا — سكرتاريا أيتام (تجريبية)',
                'username' => 'demo_social_sec',
                'email' => 'demo.social.sec@raqib.local',
                'phone' => '0599333003',
                'role' => 'project_secretariat',
                'department_id' => $socialDeptId,
                'section_id' => null,
                'job_title' => 'سكرتاريا الدائرة',
                'abilities' => ['projects.view', 'projects.fill_secretariat'],
            ],
            [
                'name' => 'مها — مديرة قسم أيتام (تجريبية)',
                'username' => 'demo_social_sm',
                'email' => 'demo.social.sm@raqib.local',
                'phone' => '0599333004',
                'role' => 'section_manager',
                'department_id' => $socialDeptId,
                'section_id' => $sectionId,
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
            [
                'name' => 'فاطمة — مديرة دائرة (تجريبية)',
                'username' => 'demo_social_dm',
                'email' => 'demo.social.dm@raqib.local',
                'phone' => '0599333005',
                'role' => 'department_manager',
                'department_id' => $socialDeptId,
                'section_id' => null,
                'job_title' => 'مديرة دائرة التنمية الاجتماعية',
                'abilities' => ['projects.view', 'projects.approve_department', 'projects.reject'],
            ],
        ];

        foreach ($demoUsers as $data) {
            $this->seedDemoUser($data, $password);
        }

        $this->command?->newLine();
        $this->command?->info('═══ دائرة التنمية الاجتماعية — قسم الأيتام والأسر (كلمة المرور: ' . $password . ') ═══');
        $this->command?->table(
            ['الدور', 'اسم المستخدم', 'الدائرة'],
            collect($demoUsers)->map(fn (array $u) => [
                Person::roleLabels()[$u['role']] ?? $u['role'],
                $u['username'],
                self::DEPT_NAME,
            ])->all()
        );
        $this->command?->line('قارن مع demo_sec / demo_pm (دائرة المشاريع) — كل سكرتاريا يرى مشاريع دائرة مدير المشروع فقط.');
    }

    /** @param  array<string, mixed>  $data */
    private function seedDemoUser(array $data, string $password): void
    {
        $user = User::updateOrCreate(
            ['username' => $data['username']],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'user_type' => 'employee',
                'is_active' => true,
                'super_admin' => false,
                'password' => Hash::make($password),
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

        RoleUser::where('user_id', $user->id)->delete();

        foreach (array_unique($data['abilities']) as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }
    }

    private function ensurePeopleRoleCheckAllowsSecretariat(): void
    {
        if (! Schema::hasTable('people') || Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        try {
            DB::statement('ALTER TABLE people DROP CHECK chk_people_role');
        } catch (\Throwable) {
            try {
                DB::statement('ALTER TABLE people DROP CONSTRAINT chk_people_role');
            } catch (\Throwable) {
                //
            }
        }

        $roleList = implode("','", Person::ROLES);
        DB::statement("ALTER TABLE people ADD CONSTRAINT chk_people_role CHECK (role IS NULL OR role IN ('{$roleList}'))");
    }
}
