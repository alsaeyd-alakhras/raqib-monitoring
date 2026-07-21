<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Person;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * إنشاء/تحديث حساب demo_sec فقط — لا يمس المشاريع ولا باقي المستخدمين.
 *
 * php artisan db:seed --class=DemoSecUserSeeder
 */
class DemoSecUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensurePeopleRoleCheckAllowsSecretariat();

        $projectsDeptId = Department::where('name', 'دائرة المشاريع والتسويق والإعلام')->value('id');

        if (! $projectsDeptId) {
            $projectsDeptId = Person::query()
                ->whereHas('user', fn ($q) => $q->where('username', 'demo_pm'))
                ->value('department_id');
        }

        if (! $projectsDeptId) {
            $this->command?->error('تعذّر تحديد دائرة المشاريع. تأكد من الهيكل التنظيمي أو وجود demo_pm.');

            return;
        }

        $password = SimpleDemoUsersSeeder::DEMO_PASSWORD;

        $user = User::updateOrCreate(
            ['username' => 'demo_sec'],
            [
                'name' => 'رنا — سكرتاريا مشاريع (تجريبية)',
                'email' => 'demo.sec@raqib.local',
                'phone' => '0599111222',
                'user_type' => 'employee',
                'is_active' => true,
                'super_admin' => false,
                'password' => Hash::make($password),
            ]
        );

        Person::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'رنا — سكرتاريا مشاريع (تجريبية)',
                'role' => 'project_secretariat',
                'department_id' => $projectsDeptId,
                'section_id' => null,
                'job_title' => 'سكرتاريا الدائرة',
                'phone' => '0599111222',
            ]
        );

        RoleUser::where('user_id', $user->id)->delete();

        foreach (['projects.view', 'projects.fill_secretariat'] as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }

        $this->command?->info('✓ demo_sec جاهز — كلمة المرور: ' . $password);
        $this->command?->line('  دائرة السكرتاريا (id): ' . $projectsDeptId);
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
