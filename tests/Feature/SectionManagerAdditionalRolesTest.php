<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Department;
use App\Models\Person;
use App\Models\Project;
use App\Models\RoleUser;
use App\Models\Section;
use App\Models\User;
use App\Services\RoleAbilitiesService;
use App\Services\UserRoleAbilitiesSync;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SectionManagerAdditionalRolesTest extends TestCase
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
        $this->seed(\Database\Seeders\ConstantsSeeder::class);
    }

    /** @return array{center: Center, department: Department, section: Section} */
    private function createOrg(): array
    {
        $center = Center::create(['name' => 'مركز تجريبي']);
        $department = Department::create(['center_id' => $center->id, 'name' => 'دائرة تجريبية']);
        $section = Section::create(['department_id' => $department->id, 'name' => 'قسم تجريبي']);

        return compact('center', 'department', 'section');
    }

    private function makeSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin_sm_roles',
            'email' => 'super-sm-roles@test.local',
            'password' => 'password',
            'user_type' => 'admin',
            'is_active' => true,
            'super_admin' => true,
        ]);
    }

    public function test_admin_can_save_section_manager_with_project_manager_additional_role(): void
    {
        $admin = $this->makeSuperAdmin();
        ['center' => $center, 'department' => $department, 'section' => $section] = $this->createOrg();

        $this->actingAs($admin)
            ->post(route('dashboard.directory.store'), [
                'record_mode' => 'person_only',
                'name' => 'مدير قسم ومشروع',
                'role' => 'section_manager',
                'additional_roles' => ['project_manager'],
                'center_id' => $center->id,
                'department_id' => $department->id,
                'section_id' => $section->id,
            ])
            ->assertRedirect(route('dashboard.directory.index'));

        $person = Person::where('name', 'مدير قسم ومشروع')->first();
        $this->assertNotNull($person);
        $this->assertSame('section_manager', $person->role);
        $this->assertSame(['project_manager'], $person->additionalRoles());
        $this->assertSame('مدير قسم + مدير مشروع', $person->role_label);
    }

    public function test_additional_roles_rejected_for_non_section_manager(): void
    {
        $admin = $this->makeSuperAdmin();
        ['department' => $department] = $this->createOrg();

        $this->actingAs($admin)
            ->post(route('dashboard.directory.store'), [
                'record_mode' => 'person_only',
                'name' => 'مدير دائرة بأدوار إضافية',
                'role' => 'department_manager',
                'additional_roles' => ['project_manager'],
                'department_id' => $department->id,
            ])
            ->assertSessionHasErrors('additional_roles');
    }

    public function test_linked_user_gets_merged_abilities_from_primary_and_additional_roles(): void
    {
        $admin = $this->makeSuperAdmin();
        ['center' => $center, 'department' => $department, 'section' => $section] = $this->createOrg();

        $this->actingAs($admin)
            ->post(route('dashboard.directory.store'), [
                'record_mode' => 'linked',
                'has_account' => '1',
                'name' => 'مدير قسم مع حساب',
                'role' => 'section_manager',
                'additional_roles' => ['project_manager'],
                'center_id' => $center->id,
                'department_id' => $department->id,
                'section_id' => $section->id,
                'username' => 'sm_pm_user',
                'email' => 'sm-pm@test.local',
                'password' => 'password',
                'confirm_password' => 'password',
                'user_type' => 'employee',
                'is_active' => '1',
                'reset_role_abilities' => '1',
            ])
            ->assertRedirect(route('dashboard.directory.index'));

        $user = User::where('username', 'sm_pm_user')->firstOrFail();
        $assigned = RoleUser::where('user_id', $user->id)->pluck('role_name')->sort()->values()->all();

        $expected = app(RoleAbilitiesService::class)->forRoles(['section_manager', 'project_manager']);
        sort($expected);

        $this->assertEqualsCanonicalizing(
            array_merge($expected, ['aiddistributions.view', 'aiddistributions.create', 'aiddistributions.update']),
            $assigned
        );
        $this->assertContains('projects.approve_section', $assigned);
        $this->assertContains('projects.create', $assigned);
    }

    public function test_observer_resyncs_abilities_when_additional_roles_change(): void
    {
        ['department' => $department, 'section' => $section] = $this->createOrg();

        $user = User::create([
            'name' => 'مدير قسم',
            'username' => 'sm_only',
            'email' => 'sm-only@test.local',
            'password' => 'password',
            'user_type' => 'employee',
            'is_active' => true,
            'super_admin' => false,
        ]);

        $person = Person::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'role' => 'section_manager',
            'department_id' => $department->id,
            'section_id' => $section->id,
            'additional_roles' => [],
        ]);

        app(UserRoleAbilitiesSync::class)->syncFromRoles($user, $person->allRoles());

        $person->update(['additional_roles' => ['project_manager']]);

        $assigned = RoleUser::where('user_id', $user->id)->pluck('role_name')->all();
        $this->assertContains('projects.create', $assigned);
        $this->assertContains('projects.approve_section', $assigned);
    }

    public function test_section_manager_with_project_manager_role_sees_own_and_section_projects(): void
    {
        ['department' => $department, 'section' => $section] = $this->createOrg();
        $otherSection = Section::create(['department_id' => $department->id, 'name' => 'قسم آخر']);

        $smPm = Person::create([
            'name' => 'مدير قسم ومشروع',
            'role' => 'section_manager',
            'additional_roles' => ['project_manager'],
            'department_id' => $department->id,
            'section_id' => $section->id,
        ]);

        $otherPm = Person::create([
            'name' => 'مدير مشروع آخر',
            'role' => 'project_manager',
            'department_id' => $department->id,
            'section_id' => $section->id,
        ]);

        $ownProject = Project::create([
            'project_number' => 'P-SM-1',
            'project_name' => 'مشروع مدير القسم',
            'project_manager_id' => $smPm->id,
            'workflow_status' => 'draft',
            'uses_execution_tracks' => false,
        ]);

        $sectionProject = Project::create([
            'project_number' => 'P-SM-2',
            'project_name' => 'مشروع زميل القسم',
            'project_manager_id' => $otherPm->id,
            'workflow_status' => 'draft',
            'uses_execution_tracks' => false,
        ]);

        $outsidePm = Person::create([
            'name' => 'مدير خارج القسم',
            'role' => 'project_manager',
            'department_id' => $department->id,
            'section_id' => $otherSection->id,
        ]);

        $outsideProject = Project::create([
            'project_number' => 'P-SM-3',
            'project_name' => 'مشروع خارج القسم',
            'project_manager_id' => $outsidePm->id,
            'workflow_status' => 'draft',
            'uses_execution_tracks' => false,
        ]);

        $user = User::create([
            'name' => $smPm->name,
            'username' => 'sm_pm_visibility',
            'email' => 'sm-pm-vis@test.local',
            'password' => 'password',
            'user_type' => 'employee',
            'is_active' => true,
            'super_admin' => false,
        ]);
        $smPm->update(['user_id' => $user->id]);

        $visibleIds = Project::query()->visibleToUser($user)->pluck('id')->all();

        $this->assertContains($ownProject->id, $visibleIds);
        $this->assertContains($sectionProject->id, $visibleIds);
        $this->assertNotContains($outsideProject->id, $visibleIds);
    }

    public function test_section_manager_with_project_manager_appears_in_pm_and_coordinator_scopes(): void
    {
        ['department' => $department, 'section' => $section] = $this->createOrg();

        $person = Person::create([
            'name' => 'مدير قسم يدير مشروع',
            'role' => 'section_manager',
            'additional_roles' => ['project_manager'],
            'department_id' => $department->id,
            'section_id' => $section->id,
        ]);

        $this->assertTrue(
            Person::query()->whereKey($person->id)->hasRole('project_manager')->exists()
        );
        $this->assertTrue(
            Person::eligibleAsProjectManager()->whereKey($person->id)->exists()
        );
        $this->assertTrue(
            Person::query()->whereKey($person->id)->hasAnyRole(Project::coordinatorEligibleRoles())->exists()
        );
        $this->assertTrue(Project::personCanBeCoordinator($person));
    }

    public function test_section_manager_pm_can_create_project_as_self_manager(): void
    {
        ['department' => $department, 'section' => $section] = $this->createOrg();

        $user = User::create([
            'name' => 'مدير قسم ومشروع',
            'username' => 'sm_pm_create',
            'email' => 'sm-pm-create@test.local',
            'password' => 'password',
            'user_type' => 'employee',
            'is_active' => true,
            'super_admin' => false,
        ]);

        $person = Person::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'role' => 'section_manager',
            'additional_roles' => ['project_manager'],
            'department_id' => $department->id,
            'section_id' => $section->id,
        ]);

        foreach (app(RoleAbilitiesService::class)->forRoles($person->allRoles()) as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }

        $this->actingAs($user);

        $this->assertTrue($person->hasRole('project_manager'));
        $this->assertTrue($user->can('create', Project::class));
        $this->assertSame((int) $person->id, (int) $user->person->id);
    }
}
