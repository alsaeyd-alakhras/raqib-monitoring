<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Department;
use App\Models\Funder;
use App\Models\Person;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RaqibSetupTest extends TestCase
{
    private function useSqliteMemory(): void
    {
        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', ':memory:');
        $this->app['config']->set('database.connections.sqlite.foreign_key_constraints', true);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    public function test_raqib_setup_fresh_imports_org_funders_and_employees(): void
    {
        $excelPath = base_path('plans/بيانات2(2).xlsx');
        $this->assertFileExists($excelPath, 'ملف Excel مطلوب للاختبار: plans/بيانات2(2).xlsx');

        $this->useSqliteMemory();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);

        $this->artisan('raqib:setup', ['--fresh' => true])
            ->assertExitCode(0);

        $this->assertGreaterThanOrEqual(2, Center::count());
        $this->assertGreaterThanOrEqual(10, Department::count());
        $this->assertGreaterThanOrEqual(40, Funder::count());
        $this->assertGreaterThanOrEqual(180, Person::count());

        $departmentManagers = Person::query()
            ->where('role', 'department_manager')
            ->whereNotNull('department_id')
            ->get()
            ->groupBy('department_id');

        foreach ($departmentManagers as $departmentId => $managers) {
            $this->assertSame(
                1,
                $managers->count(),
                "الدائرة {$departmentId} لديها أكثر من مدير دائرة"
            );
        }

        $sectionManagers = Person::query()
            ->where('role', 'section_manager')
            ->whereNotNull('section_id')
            ->get()
            ->groupBy('section_id');

        foreach ($sectionManagers as $sectionId => $managers) {
            $this->assertSame(
                1,
                $managers->count(),
                "القسم {$sectionId} لديه أكثر من مدير قسم"
            );
        }

        $ordinary = Person::query()
            ->whereNull('role')
            ->whereHas('user')
            ->first();

        $this->assertNotNull($ordinary, 'يُتوقع وجود موظف عادي بدون دور');
        $this->assertSame(
            0,
            RoleUser::where('user_id', $ordinary->user_id)->count(),
            'الموظف العادي لا يجب أن يملك صلاحيات RoleUser'
        );

        $this->assertTrue(
            User::where('super_admin', true)->exists(),
            'يجب إنشاء super_admin من SuperAdminSeeder'
        );
    }

    public function test_raqib_setup_dry_run_does_not_persist_employee_users(): void
    {
        $excelPath = base_path('plans/بيانات2(2).xlsx');
        $this->assertFileExists($excelPath);

        $this->useSqliteMemory();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);

        $beforeUsers = User::where('super_admin', 0)->count();

        $this->artisan('raqib:setup', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame($beforeUsers, User::where('super_admin', 0)->count());
    }
}
