<?php

namespace Tests\Feature;

use App\Models\Constant;
use App\Models\Department;
use App\Models\Person;
use App\Models\Project;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\InteractsWithRaqibProjects;
use Tests\TestCase;

/**
 * تغطية المهمتين:
 * 1) إدخال السكرتاريا للمشاريع (entry_channel + handoff/start executions)
 * 2) نموذج مدير المشروع (قفل PM + منسق PM + رسائل الواجهة)
 */
class SecretariatEntryAndPmFormTest extends TestCase
{
    use InteractsWithRaqibProjects;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['raqib.projects.secretariat_entry_enabled' => true]);
    }

    /** @param  array<string, mixed>  $overrides */
    protected function storeProjectAsSecretariat(User $secUser, Person $pm, array $overrides = []): Project
    {
        $coordinator = Person::withRole('coordinator')->firstOrFail();
        $offices = json_decode((string) Constant::where('key', 'association_offices')->value('value'), true);
        $seq = $this->nextProjectNumberSeq() + random_int(70000, 79999);
        $projectName = 'اختبار سكرتاريا ' . uniqid();

        $fields = $this->sampleProjectFields(array_merge([
            'project_manager_id' => $pm->id,
            'execution_zones' => 1,
            'execution_regions' => [[
                'name' => $offices[0],
                'beneficiaries' => 100,
                'coordinator_mode' => 'person',
                'coordinator_id' => $coordinator->id,
            ]],
            'project_number_seq' => $seq,
            'allocation_image' => UploadedFile::fake()->image('allocation.jpg'),
        ], $overrides));

        $this->actingAs($secUser);
        $this->from(route('dashboard.projects.create'));
        $this->post(route('dashboard.projects.store'), array_merge($fields, [
            'project_name' => $projectName,
        ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return Project::where('project_name', $projectName)->firstOrFail();
    }

    // ─── المهمة 1: إدخال السكرتاريا ─────────────────────────────────────

    public function test_secretariat_can_open_create_page_when_entry_enabled(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $secUser = $this->secretariatUserForDepartment((int) $pm->department_id);

        $this->actingAs($secUser)
            ->get(route('dashboard.projects.create'))
            ->assertOk()
            ->assertSee('id="project-manager-id"', false);

        $this->deleteEphemeralUser($pmUser);
    }

    public function test_secretariat_create_page_forbidden_when_entry_disabled(): void
    {
        config(['raqib.projects.secretariat_entry_enabled' => false]);

        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $secUser = $this->secretariatUserForDepartment((int) $pm->department_id);

        $this->actingAs($secUser)
            ->get(route('dashboard.projects.create'))
            ->assertForbidden();

        $this->assertFalse(Project::userCanCreate($secUser));

        $this->deleteEphemeralUser($pmUser);
    }

    public function test_secretariat_store_records_entry_channel_and_creator(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $secUser = $this->secretariatUserForDepartment((int) $pm->department_id);

        $project = $this->storeProjectAsSecretariat($secUser, $pm);

        $this->assertSame(Project::ENTRY_CHANNEL_SECRETARIAT, $project->entry_channel);
        $this->assertSame((int) $secUser->id, (int) $project->created_by);
        $this->assertSame((int) $pm->id, (int) $project->project_manager_id);
        $this->assertTrue($project->isSecretariatEntry());
        $this->assertTrue($project->hasCompletedSecretariatPhase());

        $project->delete();
        $this->deleteEphemeralUser($pmUser);
    }

    public function test_secretariat_cannot_select_project_manager_from_other_department(): void
    {
        $deptA = Department::query()->orderBy('id')->firstOrFail();
        $deptB = Department::query()->where('id', '!=', $deptA->id)->firstOrFail();

        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $pm->update(['department_id' => $deptA->id]);

        $secUser = $this->secretariatUserForDepartment((int) $deptB->id);
        $seq = $this->nextProjectNumberSeq() + 71001;

        $fields = $this->sampleProjectFields([
            'project_manager_id' => $pm->id,
            'project_number_seq' => $seq,
            'allocation_image' => UploadedFile::fake()->image('allocation.jpg'),
        ]);

        $this->actingAs($secUser);
        $this->from(route('dashboard.projects.create'));
        $this->post(route('dashboard.projects.store'), array_merge($fields, [
            'project_name' => 'دائرة خاطئة ' . uniqid(),
        ]))->assertSessionHasErrors('project_manager_id');

        $this->deleteEphemeralUser($pmUser);
    }

    public function test_secretariat_handoff_requires_allocation_and_coordinator(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $secUser = $this->secretariatUserForDepartment((int) $pm->department_id);
        $project = $this->storeProjectAsSecretariat($secUser, $pm);

        $this->actingAs($secUser);
        $this->from(route('dashboard.projects.show', $project));
        $project->update([
            'project_number' => null,
            'allocation_image_path' => null,
        ]);
        $this->post(route('dashboard.projects.submit-handed-to-pm', $project))
            ->assertRedirect()
            ->assertSessionHasErrors('allocation');

        $regions = $project->execution_regions;
        $regions[0]['coordinator_mode'] = 'person';
        $regions[0]['coordinator_id'] = null;
        unset($regions[0]['coordinator_external_name']);
        $project->update([
            'project_number' => Project::formatFromSequence($this->nextProjectNumberSeq() + 71002),
            'allocation_image_path' => 'projects/test/allocation.jpg',
            'execution_regions' => $regions,
            'coordinator_id' => null,
            'coordinator_external_name' => null,
        ]);

        $this->post(route('dashboard.projects.submit-handed-to-pm', $project))
            ->assertRedirect()
            ->assertSessionHasErrors('execution_regions');

        $project->delete();
        $this->deleteEphemeralUser($pmUser);
    }

    public function test_secretariat_handoff_sets_handed_to_pm_fields(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $secUser = $this->secretariatUserForDepartment((int) $pm->department_id);
        $project = $this->storeProjectAsSecretariat($secUser, $pm);

        $this->actingAs($secUser);
        $this->post(route('dashboard.projects.submit-handed-to-pm', $project))->assertRedirect();

        $project->refresh();
        $this->assertNotNull($project->handed_to_pm_at);
        $this->assertSame((int) $secUser->id, (int) $project->handed_to_pm_by);
        $this->assertSame('draft', $project->workflow_status);

        $project->delete();
        $this->deleteEphemeralUser($pmUser);
    }

    public function test_secretariat_pm_cannot_start_executions_before_handoff(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $secUser = $this->secretariatUserForDepartment((int) $pm->department_id);
        $project = $this->storeProjectAsSecretariat($secUser, $pm);

        $this->actingAs($pmUser);
        $this->post(route('dashboard.projects.submit-and-start-executions', $project))
            ->assertForbidden();

        $project->delete();
        $this->deleteEphemeralUser($pmUser);
    }

    public function test_secretariat_direct_start_executions_skips_handoff(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $secUser = $this->secretariatUserForDepartment((int) $pm->department_id);
        $project = $this->storeProjectAsSecretariat($secUser, $pm);

        $this->actingAs($secUser);
        $this->post(route('dashboard.projects.submit-and-start-executions', $project))->assertRedirect();

        $project->refresh();
        $this->assertNull($project->handed_to_pm_at);
        $this->assertSame('executions_in_progress', $project->workflow_status);

        $project->delete();
        $this->deleteEphemeralUser($pmUser);
    }

    public function test_user_without_project_create_ability_cannot_open_create_page(): void
    {
        $coordinator = Person::withRole('coordinator')->firstOrFail();
        $user = User::create([
            'name' => 'منسق بدون إنشاء ' . uniqid(),
            'username' => 'coord_no_create_' . uniqid(),
            'email' => uniqid() . '@test.local',
            'user_type' => 'employee',
            'is_active' => true,
            'super_admin' => false,
            'password' => bcrypt('password'),
        ]);

        Person::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'role' => 'coordinator',
            'department_id' => $coordinator->department_id,
            'section_id' => $coordinator->section_id,
            'job_title' => 'منسق اختبار',
            'phone' => '0599111222',
        ]);

        $this->syncUserAbilities($user, ['projects.view']);

        $this->actingAs($user->fresh(['person']))
            ->get(route('dashboard.projects.create'))
            ->assertForbidden();

        $this->assertFalse(Project::userCanCreate($user->fresh(['person'])));

        $this->deleteEphemeralUser($user);
    }

    // ─── المهمة 2: نموذج مدير المشروع والمنسق ───────────────────────────

    public function test_project_manager_create_form_locks_project_manager_field(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);

        $this->actingAs($pmUser);
        $response = $this->get(route('dashboard.projects.create'));
        $response->assertOk();
        $response->assertSee($pm->name, false);
        $response->assertDontSee('id="project-manager-id"', false);
        $response->assertSee('name="project_manager_id"', false);

        $this->deleteEphemeralUser($pmUser);
    }

    public function test_project_manager_store_sets_project_manager_entry_channel(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $seq = $this->nextProjectNumberSeq() + 72001;

        $fields = $this->sampleProjectFields([
            'project_manager_id' => $pm->id,
            'project_number_seq' => $seq,
            'allocation_image' => UploadedFile::fake()->image('allocation.jpg'),
        ]);

        $this->actingAs($pmUser);
        $this->post(route('dashboard.projects.store'), array_merge($fields, [
            'project_name' => 'مشروع PM ' . uniqid(),
        ]))->assertRedirect();

        $project = Project::latest('id')->firstOrFail();
        $this->assertSame(Project::ENTRY_CHANNEL_PROJECT_MANAGER, $project->entry_channel);
        $this->assertSame((int) $pm->id, (int) $project->project_manager_id);
        $this->assertSame((int) $pmUser->id, (int) $project->created_by);

        $project->delete();
        $this->deleteEphemeralUser($pmUser);
    }

    public function test_project_manager_cannot_assign_different_project_manager_on_create(): void
    {
        [$pmAUser, $pmA] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        [, $pmB] = $this->createEphemeralProjectManager(['projects.view']);

        $fields = $this->sampleProjectFields([
            'project_manager_id' => $pmB->id,
            'project_number_seq' => $this->nextProjectNumberSeq() + 72002,
            'allocation_image' => UploadedFile::fake()->image('allocation.jpg'),
        ]);

        $this->actingAs($pmAUser);
        $this->from(route('dashboard.projects.create'));
        $this->post(route('dashboard.projects.store'), array_merge($fields, [
            'project_name' => 'محاولة PM آخر ' . uniqid(),
        ]))->assertSessionHasErrors('project_manager_id');

        $this->deleteEphemeralUser($pmAUser);
    }

    public function test_project_manager_create_form_shows_pm_coordinator_hint_not_secretariat_message(): void
    {
        [$pmUser] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);

        $this->actingAs($pmUser);
        $response = $this->get(route('dashboard.projects.create'));
        $response->assertOk();
        $response->assertSee('selfCoordinatorHint', false);
        $content = $response->getContent();
        $this->assertStringContainsString('\u0623\u0646\u062a \u0645\u062f\u064a\u0631 \u0627\u0644\u0645\u0634\u0631\u0648\u0639 \u0648\u0627\u0644\u0645\u0646\u0633\u0642', $content);
        $this->assertStringNotContainsString('\u0628\u0639\u062f \u062a\u0633\u0644\u064a\u0645 \u0627\u0644\u0645\u0634\u0631\u0648\u0639', $content);

        $this->deleteEphemeralUser($pmUser);
    }

    public function test_secretariat_create_form_shows_handoff_coordinator_hint(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $secUser = $this->secretariatUserForDepartment((int) $pm->department_id);

        $this->actingAs($secUser);
        $response = $this->get(route('dashboard.projects.create'));
        $response->assertOk();
        $response->assertSee('selfCoordinatorHint', false);
        $response->assertSee('\u0628\u0639\u062f \u062a\u0633\u0644\u064a\u0645 \u0627\u0644\u0645\u0634\u0631\u0648\u0639', false);
        $response->assertSee('id="project-manager-id"', false);

        $this->deleteEphemeralUser($pmUser);
    }

    public function test_create_form_lists_project_managers_in_coordinator_candidates(): void
    {
        [$pmUser] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);

        $this->actingAs($pmUser);
        $response = $this->get(route('dashboard.projects.create'));
        $response->assertOk();
        $response->assertSee('coordinators:', false);
        $response->assertSee('\u0645\u062f\u064a\u0631 \u0645\u0634\u0631\u0648\u0639)', false);

        $this->deleteEphemeralUser($pmUser);
    }

    public function test_assigned_project_manager_coordinator_can_view_execution_track(): void
    {
        [$pmCoordinatorUser, $pmCoordinator] = $this->createEphemeralProjectManager([
            'projects.view',
            'projects.fill_coordinator',
        ]);
        [$pmOwnerUser, $pmOwner] = $this->createEphemeralProjectManager([
            'projects.view',
            'projects.create',
            'projects.update',
        ]);

        $secUser = $this->secretariatUserForDepartment((int) $pmOwner->department_id);
        $project = $this->storeProjectAsSecretariat($secUser, $pmOwner, [
            'execution_regions' => [[
                'name' => json_decode((string) Constant::where('key', 'association_offices')->value('value'), true)[0],
                'beneficiaries' => 100,
                'coordinator_mode' => 'person',
                'coordinator_id' => $pmCoordinator->id,
            ]],
        ]);

        $this->actingAs($secUser);
        $this->post(route('dashboard.projects.submit-handed-to-pm', $project))->assertRedirect();

        $this->actingAs($pmOwnerUser);
        $this->post(route('dashboard.projects.submit-and-start-executions', $project))->assertRedirect();

        $project->refresh();
        $execution = $project->executions()->firstOrFail();

        $this->actingAs($pmCoordinatorUser);
        $this->get(route('dashboard.projects.executions.show', [$project, $execution]))
            ->assertOk();

        $this->assertTrue($execution->isVisibleToUser($pmCoordinatorUser));
        $this->assertSame((int) $pmCoordinator->id, (int) $execution->coordinator_id);

        $project->delete();
        $this->deleteEphemeralUser($pmCoordinatorUser);
        $this->deleteEphemeralUser($pmOwnerUser);
    }

    public function test_project_manager_without_coordinator_assignment_cannot_view_foreign_execution(): void
    {
        [$pmAUser] = $this->createEphemeralProjectManager(['projects.view', 'projects.fill_coordinator']);
        [$pmBUser, $pmB] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);

        $secUser = $this->secretariatUserForDepartment((int) $pmB->department_id);
        $project = $this->storeProjectAsSecretariat($secUser, $pmB);

        $this->actingAs($secUser);
        $this->post(route('dashboard.projects.submit-and-start-executions', $project))->assertRedirect();

        $execution = $project->fresh()->executions()->firstOrFail();

        $this->actingAs($pmAUser);
        $this->get(route('dashboard.projects.executions.show', [$project, $execution]))
            ->assertForbidden();

        $project->delete();
        $this->deleteEphemeralUser($pmAUser);
        $this->deleteEphemeralUser($pmBUser);
    }

    public function test_sync_role_abilities_command_updates_secretariat_role_users(): void
    {
        [$pmUser, $pm] = $this->createEphemeralProjectManager(['projects.view', 'projects.create', 'projects.update']);
        $secUser = $this->secretariatUserForDepartment((int) $pm->department_id);

        RoleUser::where('user_id', $secUser->id)->delete();
        RoleUser::create([
            'role_name' => 'projects.view',
            'user_id' => $secUser->id,
            'ability' => 'allow',
        ]);
        RoleUser::create([
            'role_name' => 'projects.fill_secretariat',
            'user_id' => $secUser->id,
            'ability' => 'allow',
        ]);

        $this->artisan('raqib:sync-role-abilities', [
            '--role' => 'project_secretariat',
        ])->assertSuccessful();

        $projectAbilities = RoleUser::where('user_id', $secUser->id)
            ->where('role_name', 'like', 'projects.%')
            ->pluck('role_name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'projects.create',
            'projects.update',
            'projects.view',
        ], $projectAbilities);
        $this->assertNull(
            RoleUser::where('user_id', $secUser->id)->where('role_name', 'projects.fill_secretariat')->first()
        );

        $this->deleteEphemeralUser($pmUser);
    }
}
