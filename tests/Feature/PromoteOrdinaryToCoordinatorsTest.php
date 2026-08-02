<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Department;
use App\Models\Person;
use App\Models\RoleUser;
use App\Models\Section;
use App\Models\User;
use App\Services\RoleAbilitiesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PromoteOrdinaryToCoordinatorsTest extends TestCase
{
    private function useSqliteMemory(): void
    {
        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', ':memory:');
        $this->app['config']->set('database.connections.sqlite.foreign_key_constraints', true);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqliteMemory();
        $this->artisan('migrate:fresh', ['--force' => true]);
        $this->deletePromoteReport();
    }

    private function deletePromoteReport(): void
    {
        $reportPath = storage_path(config('raqib.promote_coordinators_report_path'));

        if (File::exists($reportPath)) {
            File::delete($reportPath);
        }
    }

    public function test_promotes_ordinary_employee_with_section_to_coordinator(): void
    {
        [$section] = $this->makeOrg();

        $user = $this->makeEmployeeUser('ordinary_with_section');
        $person = Person::create([
            'user_id' => $user->id,
            'name' => 'موظف عادي',
            'role' => null,
            'department_id' => $section->department_id,
            'section_id' => $section->id,
        ]);

        $this->artisan('raqib:promote-ordinary-to-coordinators')
            ->assertExitCode(0);

        $person->refresh();
        $this->assertSame('coordinator', $person->role);

        $expected = app(RoleAbilitiesService::class)->forRole('coordinator');
        $assigned = RoleUser::where('user_id', $user->id)->pluck('role_name')->sort()->values()->all();

        $this->assertEqualsCanonicalizing(
            array_merge($expected, ['aiddistributions.view', 'aiddistributions.create', 'aiddistributions.update']),
            $assigned
        );
    }

    public function test_skips_ordinary_employee_without_section(): void
    {
        [$section] = $this->makeOrg();

        $user = $this->makeEmployeeUser('ordinary_no_section');
        $person = Person::create([
            'user_id' => $user->id,
            'name' => 'موظف بدون قسم',
            'role' => null,
            'department_id' => $section->department_id,
            'section_id' => null,
        ]);

        $this->artisan('raqib:promote-ordinary-to-coordinators')
            ->assertExitCode(0);

        $person->refresh();
        $this->assertNull($person->role);
        $this->assertSame(0, RoleUser::where('user_id', $user->id)->count());
    }

    public function test_does_not_change_project_manager(): void
    {
        [$section] = $this->makeOrg();

        $user = $this->makeEmployeeUser('project_manager_user');
        $person = Person::create([
            'user_id' => $user->id,
            'name' => 'مدير مشروع',
            'role' => 'project_manager',
            'department_id' => $section->department_id,
            'section_id' => $section->id,
        ]);

        $this->artisan('raqib:promote-ordinary-to-coordinators')
            ->assertExitCode(0);

        $person->refresh();
        $this->assertSame('project_manager', $person->role);
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        [$section] = $this->makeOrg();

        $user = $this->makeEmployeeUser('dry_run_user');
        $person = Person::create([
            'user_id' => $user->id,
            'name' => 'موظف معاينة',
            'role' => null,
            'department_id' => $section->department_id,
            'section_id' => $section->id,
        ]);

        $this->deletePromoteReport();

        $this->artisan('raqib:promote-ordinary-to-coordinators', ['--dry-run' => true])
            ->assertExitCode(0);

        $person->refresh();
        $this->assertNull($person->role);
        $this->assertSame(0, RoleUser::where('user_id', $user->id)->count());
        $this->assertFalse(
            File::exists(storage_path(config('raqib.promote_coordinators_report_path')))
        );
    }

    public function test_writes_report_on_execution(): void
    {
        [$section] = $this->makeOrg();

        $user = $this->makeEmployeeUser('report_user');
        Person::create([
            'user_id' => $user->id,
            'name' => 'موظف للتقرير',
            'role' => null,
            'department_id' => $section->department_id,
            'section_id' => $section->id,
        ]);

        $reportPath = storage_path(config('raqib.promote_coordinators_report_path'));
        if (File::exists($reportPath)) {
            File::delete($reportPath);
        }

        $this->artisan('raqib:promote-ordinary-to-coordinators')
            ->assertExitCode(0);

        $this->assertTrue(File::exists($reportPath));

        $report = json_decode(File::get($reportPath), true);
        $this->assertSame(1, $report['promoted_count']);
        $this->assertFalse($report['dry_run']);
    }

    /**
     * @return array{0: Section}
     */
    private function makeOrg(): array
    {
        $center = Center::create(['name' => 'مركز تجريبي']);
        $department = Department::create(['center_id' => $center->id, 'name' => 'دائرة تجريبية']);
        $section = Section::create(['department_id' => $department->id, 'name' => 'قسم تجريبي']);

        return [$section];
    }

    private function makeEmployeeUser(string $username): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@test.local',
            'password' => 'password',
            'user_type' => 'employee',
            'is_active' => true,
            'super_admin' => false,
        ]);
    }
}
