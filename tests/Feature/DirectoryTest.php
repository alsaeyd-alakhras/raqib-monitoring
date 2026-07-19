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
use Tests\TestCase;

class DirectoryTest extends TestCase
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
        $this->seed(\Database\Seeders\OrganizationalSeeder::class);
    }

    public function test_org_structure_page_redirects_from_legacy_centers_index(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('dashboard.centers.index'))
            ->assertRedirect(route('dashboard.org-structure.index'));
    }

    public function test_directory_lists_person_without_user_and_user_without_person(): void
    {
        $admin = $this->makeSuperAdmin();

        $personOnly = Person::create([
            'name' => 'منسق خارجي',
            'role' => 'coordinator',
            'organization' => 'جهة خارجية',
        ]);

        $userOnly = User::create([
            'name' => 'حساب نظام',
            'username' => 'sys_only',
            'email' => 'sysonly@test.local',
            'password' => 'password',
            'user_type' => 'admin',
            'is_active' => true,
            'super_admin' => false,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('dashboard.directory.index'), ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertContains($personOnly->name, $names);
        $this->assertContains($userOnly->name, $names);
    }

    public function test_role_abilities_endpoint_returns_template_for_coordinator(): void
    {
        $admin = $this->makeSuperAdmin();
        $expected = app(RoleAbilitiesService::class)->forRole('coordinator');

        $this->actingAs($admin)
            ->getJson(route('dashboard.directory.role-abilities', ['role' => 'coordinator']))
            ->assertOk()
            ->assertJson(['abilities' => $expected]);
    }

    public function test_creating_linked_person_applies_role_abilities(): void
    {
        $admin = $this->makeSuperAdmin();
        $expected = app(RoleAbilitiesService::class)->forRole('coordinator');

        $center = Center::create(['name' => 'مركز تجريبي']);
        $department = Department::create(['center_id' => $center->id, 'name' => 'دائرة تجريبية']);
        $section = Section::create(['department_id' => $department->id, 'name' => 'قسم تجريبي']);

        $this->actingAs($admin)
            ->post(route('dashboard.directory.store'), [
                'record_mode' => 'linked',
                'has_account' => '1',
                'name' => 'منسق تجريبي',
                'role' => 'coordinator',
                'center_id' => $center->id,
                'department_id' => $department->id,
                'section_id' => $section->id,
                'username' => 'coord_test',
                'password' => 'password',
                'confirm_password' => 'password',
                'user_type' => 'employee',
                'is_active' => '1',
            ])
            ->assertRedirect(route('dashboard.directory.index'));

        $user = User::where('username', 'coord_test')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->person);

        $assigned = RoleUser::where('user_id', $user->id)->pluck('role_name')->sort()->values()->all();
        $this->assertEqualsCanonicalizing(
            array_merge($expected, ['aiddistributions.view', 'aiddistributions.create', 'aiddistributions.update']),
            $assigned
        );
    }

    public function test_person_only_record_has_no_user(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post(route('dashboard.directory.store'), [
                'record_mode' => 'person_only',
                'name' => 'شخص بدون حساب',
                'role' => '',
            ])
            ->assertRedirect(route('dashboard.directory.index'));

        $person = Person::where('name', 'شخص بدون حساب')->first();
        $this->assertNotNull($person);
        $this->assertNull($person->user_id);
    }

    private function makeSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin_test',
            'email' => 'super@test.local',
            'password' => 'password',
            'user_type' => 'admin',
            'is_active' => true,
            'super_admin' => true,
        ]);
    }
}
