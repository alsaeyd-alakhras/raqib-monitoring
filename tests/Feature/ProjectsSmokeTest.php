<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\ChecklistItem;
use App\Models\Constant;
use App\Models\Department;
use App\Models\Funder;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectChecklistValue;
use App\Models\Section;
use App\Models\User;
use Tests\TestCase;

class ProjectsSmokeTest extends TestCase
{
    private function nextProjectNumberSeq(): int
    {
        return Project::sequenceFromProjectNumber(Project::generateProjectNumber()) ?? 1;
    }

    /** @return array<string, mixed> */
    private function sampleProjectFields(array $overrides = []): array
    {
        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $center = Center::firstOrFail();
        $department = Department::where('center_id', $center->id)->firstOrFail();
        $section = Section::where('department_id', $department->id)->first()
            ?? Section::create([
                'department_id' => $department->id,
                'name' => 'قسم تجريبي',
            ]);
        $funder = Funder::first()
            ?? Funder::create(['name' => 'ممول تجريبي']);
        $procurementRep = Person::firstOrFail();
        $projectTypes = json_decode((string) Constant::where('key', 'project_types')->value('value'), true);

        return array_merge([
            'project_manager_id' => $pm->id,
            'project_type' => is_array($projectTypes) ? ($projectTypes[0] ?? 'مشروع') : 'مشروع',
            'funder_id' => $funder->id,
            'procurement_rep_id' => $procurementRep->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
            'section_id' => $section->id,
            'planned_start_date' => '2026-01-01',
            'planned_end_date' => '2026-06-30',
            'location' => 'موقع تجريبي',
            'target_beneficiaries' => 100,
            'execution_zones' => 2,
            'estimated_duration' => '6 أشهر',
            'allocated_budget' => 50000,
        ], $overrides);
    }

    /** @return array<int, array{value: string}> */
    private function fullChecklist(string $value = 'ready'): array
    {
        $checklist = [];

        foreach (ChecklistItem::where('is_active', true)->get() as $item) {
            $checklist[$item->id] = ['value' => $value];
        }

        return $checklist;
    }

    public function test_project_and_checklist_admin_pages_render(): void
    {
        $user = User::first();
        $user->super_admin = 1;

        $this->actingAs($user);

        $this->get('/projects')->assertStatus(200);
        $this->get('/projects/create')->assertStatus(200);
        $this->get('/checklist-admin')->assertStatus(200);
    }

    public function test_full_project_workflow_end_to_end(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $coordinator = Person::withRole('coordinator')->first() ?? Person::skip(1)->first();
        $monitor = Person::withRole('monitor')->first() ?? Person::skip(2)->first() ?? $coordinator;
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();

        $projectName = 'مشروع اختبار شامل ' . uniqid();

        // 1) create draft
        $this->post('/projects', $this->sampleProjectFields([
            'project_name' => $projectName,
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
        ]))->assertRedirect();

        $project = Project::where('project_name', $projectName)->firstOrFail();
        $this->assertSame('draft', $project->workflow_status);
        $this->assertMatchesRegularExpression('/^P-\d+$/', (string) $project->project_number);

        $this->get(route('dashboard.projects.show', $project))->assertStatus(200);
        $this->get(route('dashboard.projects.edit', $project))->assertStatus(200);

        // 2) submit to coordinator
        $this->post(route('dashboard.projects.submit-to-coordinator', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame('pending_coordinator', $project->workflow_status);

        // 3) fill coordinator checklist
        $this->post(route('dashboard.projects.fill-coordinator', $project), ['checklist' => $this->fullChecklist()])
            ->assertRedirect();
        $project->refresh();
        $this->assertSame('coordinator_filling', $project->workflow_status);
        $this->assertEquals(100.0, (float) $project->coordinator_readiness_pct);

        // 4) submit to project manager, then to dept manager, approve
        $this->post(route('dashboard.projects.submit-to-project-manager', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame('pending_project_manager', $project->workflow_status);

        $this->post(route('dashboard.projects.submit-to-dept-manager', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame('pending_dept_manager', $project->workflow_status);

        $this->post(route('dashboard.projects.approve-department', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame('pending_monitoring_manager', $project->workflow_status);

        // 5) assign monitor -> generates monitoring_activity
        $this->post(route('dashboard.projects.assign-monitor', $project), [
            'monitor_person_id' => $monitor->id,
        ])->assertRedirect();
        $project->refresh();
        $this->assertSame('monitoring_in_progress', $project->workflow_status);
        $this->assertNotNull($project->primary_monitoring_activity_id);

        $activity = MonitoringActivity::find($project->primary_monitoring_activity_id);
        $this->assertMatchesRegularExpression('/^MP-\d+$/', (string) $activity->reference_code);

        // 6) monitor-work isolated screen
        $this->get(route('dashboard.projects.monitor-work', $project))->assertStatus(200);

        $checklistMonitor = $this->fullChecklist('partial');
        $this->post(route('dashboard.projects.fill-monitor', $project), [
            'checklist' => $checklistMonitor,
            'monitor_notes_text' => "ملاحظة1\nملاحظة2",
        ])->assertRedirect();

        $project->refresh();
        $activity->refresh();
        $this->assertEquals(50.0, (float) $project->monitor_readiness_pct);
        $this->assertEquals(50.0, (float) $activity->execution_value);
        $this->assertSame(['ملاحظة1', 'ملاحظة2'], $project->monitor_notes);
        $this->assertSame('in_progress', $activity->workflow_status);

        // 7) monitor submits to monitoring director
        $this->post(route('dashboard.projects.confirm-monitoring', $project))->assertRedirect();
        $project->refresh();
        $activity->refresh();
        $this->assertSame('pending_monitoring_confirmation', $project->workflow_status);
        $this->assertSame('pending_confirmation', $activity->workflow_status);

        // 8) monitoring manager confirms passage -> project completed
        $this->post(route('dashboard.projects.confirm-passage', $project))->assertRedirect();
        $project->refresh();
        $activity->refresh();
        $this->assertSame('passage_complete', $project->workflow_status);
        $this->assertSame('completed', $activity->workflow_status);
        $this->assertTrue($activity->is_passage_complete);

        // cleanup (avoid polluting shared dev database across test runs)
        $project->update(['primary_monitoring_activity_id' => null]);
        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
        $activity->delete();
    }

    public function test_reject_and_reroute_flow(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::first();
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();

        $project = Project::create([
            'project_name' => 'مشروع رفض اختباري',
            'project_manager_id' => $pm->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
            'workflow_status' => 'pending_dept_manager',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->post(route('dashboard.projects.reject', $project), [
            'rejection_reason' => 'نقص في المستندات',
            'gap_owner' => 'coordinator',
            'return_target' => 'reject_final',
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('rejected', $project->workflow_status);
        $this->assertNotNull($project->rejected_at);
        $this->assertSame('نقص في المستندات', $project->rejection_reason);

        $this->post(route('dashboard.projects.reroute', $project), [
            'workflow_status' => 'coordinator_filling',
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('coordinator_filling', $project->workflow_status);

        $this->get(route('dashboard.projects.show', $project))->assertStatus(200);

        $project->delete();
    }

    public function test_reject_final_flow(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::first();
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();

        $project = Project::create([
            'project_name' => 'مشروع رفض نهائي',
            'project_manager_id' => $pm->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
            'workflow_status' => 'pending_dept_manager',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->post(route('dashboard.projects.reject', $project), [
            'rejection_reason' => 'رفض نهائي',
            'gap_owner' => 'coordinator',
            'return_target' => 'reject_final',
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('rejected', $project->workflow_status);

        $project->delete();
    }

    public function test_checklist_admin_group_and_item_management(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $this->post(route('dashboard.checklist-admin.groups.store'), [
            'name' => 'مجموعة اختبار',
        ])->assertRedirect();

        $group = \App\Models\ChecklistGroup::where('name', 'مجموعة اختبار')->firstOrFail();

        $this->post(route('dashboard.checklist-admin.items.store'), [
            'group_id' => $group->id,
            'name' => 'بند اختبار',
            'has_person_field' => '1',
        ])->assertRedirect();

        $item = \App\Models\ChecklistItem::where('name', 'بند اختبار')->firstOrFail();
        $this->assertTrue((bool) $item->has_person_field);

        $this->post(route('dashboard.checklist-admin.items.toggle', $item))->assertRedirect();
        $this->assertFalse((bool) $item->fresh()->is_active);

        $this->post(route('dashboard.checklist-admin.groups.toggle', $group))->assertRedirect();
        $this->assertFalse((bool) $group->fresh()->is_active);

        $item->delete();
        $group->delete();
    }

    public function test_project_coordinator_modes_and_checklist_reset(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $coordinator = Person::withRole('coordinator')->first() ?? Person::skip(1)->first();
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();
        $itemId = \App\Models\ChecklistItem::where('is_active', true)->value('id');

        $this->post('/projects', $this->sampleProjectFields([
            'project_name' => 'مشروع منسق خارجي',
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'coordinator_mode' => 'external',
            'coordinator_external_name' => 'منسق خارجي تجريبي',
        ]))->assertRedirect();

        $externalProject = Project::where('project_name', 'مشروع منسق خارجي')->firstOrFail();
        $this->assertSame('external', $externalProject->coordinatorMode());

        $this->post('/projects', $this->sampleProjectFields([
            'project_name' => 'مشروع منسق ذاتي',
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'coordinator_mode' => 'self',
        ]))->assertRedirect();

        $selfProject = Project::where('project_name', 'مشروع منسق ذاتي')->firstOrFail();
        $this->assertTrue($selfProject->isSelfCoordinator());
        $this->assertMatchesRegularExpression('/^P-\d+$/', (string) $selfProject->project_number);

        if ($itemId) {
            ProjectChecklistValue::updateOrCreate(
                ['project_id' => $selfProject->id, 'checklist_item_id' => $itemId],
                ['coordinator_value' => 'ready']
            );
        }

        $this->put(route('dashboard.projects.update', $selfProject), $this->sampleProjectFields([
            'project_name' => $selfProject->project_name,
            'project_number_seq' => Project::sequenceFromProjectNumber($selfProject->project_number),
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
        ]))->assertRedirect();

        $selfProject->refresh();
        $this->assertSame('person', $selfProject->coordinatorMode());
        $this->assertNull(
            $selfProject->checklistValues()->whereNotNull('coordinator_value')->value('coordinator_value')
        );

        ProjectChecklistValue::where('project_id', $selfProject->id)->delete();
        $externalProject->delete();
        $selfProject->delete();
    }

    public function test_project_number_unique_on_update(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();

        $first = Project::create([
            'project_name' => 'مشروع رقم 1',
            'project_number' => 'P-9001',
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $second = Project::create([
            'project_name' => 'مشروع رقم 2',
            'project_number' => 'P-9002',
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->from(route('dashboard.projects.edit', $second))
            ->put(route('dashboard.projects.update', $second), [
                'project_name' => $second->project_name,
                'project_number_seq' => 9001,
                'project_manager_id' => $pm->id,
                'coordinator_mode' => 'self',
                'center_id' => $center->id,
                'department_id' => $department->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('project_number_seq');

        $first->delete();
        $second->delete();
    }

    public function test_external_coordinator_fill_records_filled_by(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();
        $itemId = \App\Models\ChecklistItem::where('is_active', true)->value('id');

        $this->post('/projects', $this->sampleProjectFields([
            'project_name' => 'مشروع تعبئة خارجي',
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'coordinator_mode' => 'external',
            'coordinator_external_name' => 'منسق خارجي',
        ]))->assertRedirect();

        $project = Project::where('project_name', 'مشروع تعبئة خارجي')->firstOrFail();

        $this->post(route('dashboard.projects.fill-coordinator', $project), [
            'fill_on_behalf' => '1',
            'checklist' => $this->fullChecklist(),
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame($user->id, $project->coordinator_filled_by);

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_submit_to_project_manager_requires_saved_coordinator_fill(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $coordinator = Person::withRole('coordinator')->first() ?? Person::skip(1)->first();
        $projectName = 'مشروع تحقق تعبئة المنسق ' . uniqid();

        $this->post('/projects', $this->sampleProjectFields([
            'project_name' => $projectName,
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
        ]))->assertRedirect();

        $project = Project::where('project_name', $projectName)->firstOrFail();

        $this->post(route('dashboard.projects.submit-to-coordinator', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame('pending_coordinator', $project->workflow_status);

        $project->update(['workflow_status' => 'coordinator_filling']);

        $this->from(route('dashboard.projects.show', $project))
            ->post(route('dashboard.projects.submit-to-project-manager', $project))
            ->assertRedirect(route('dashboard.projects.show', $project))
            ->assertSessionHasErrors('coordinator');

        $project->refresh();
        $this->assertSame('coordinator_filling', $project->workflow_status);

        $this->post(route('dashboard.projects.fill-coordinator', $project), [
            'checklist' => $this->fullChecklist(),
        ])->assertRedirect();

        $this->post(route('dashboard.projects.submit-to-project-manager', $project))
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('pending_project_manager', $project->workflow_status);

        $this->post(route('dashboard.projects.submit-to-dept-manager', $project))
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('pending_dept_manager', $project->workflow_status);

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_generate_project_number_fills_sequence_gaps(): void
    {
        $user = User::first();
        $pm = Person::withRole('project_manager')->first() ?? Person::first();

        Project::create([
            'project_name' => 'gap 1',
            'project_number' => 'P-99801',
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Project::create([
            'project_name' => 'gap 3',
            'project_number' => 'P-99803',
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $expected = 1;
        foreach (Project::usedProjectNumberSequence() as $used) {
            if ($used > $expected) {
                break;
            }
            if ($used === $expected) {
                $expected++;
            }
        }

        $this->assertSame('P-' . $expected, Project::generateProjectNumber());

        Project::whereIn('project_number', ['P-99801', 'P-99803'])->delete();
    }

    public function test_check_project_number_endpoint(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();

        $project = Project::create([
            'project_name' => 'check number',
            'project_number' => 'P-8801',
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->getJson(route('dashboard.projects.check-project-number', [
            'project_number_seq' => 8801,
            'except_id' => $project->id,
        ]))
            ->assertOk()
            ->assertJson(['available' => true, 'valid' => true]);

        $this->getJson(route('dashboard.projects.check-project-number', [
            'project_number_seq' => 8801,
        ]))
            ->assertOk()
            ->assertJson(['available' => false, 'valid' => true]);

        $project->delete();
    }

    public function test_department_manager_sees_projects_for_project_manager_department(): void
    {
        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $deptManager = Person::where('role', 'department_manager')
            ->where('department_id', $pm->department_id)
            ->whereNotNull('user_id')
            ->first();

        $this->assertNotNull($deptManager, 'Expected a department manager for the project manager department.');

        $otherDeptManager = Person::where('role', 'department_manager')
            ->where('department_id', '!=', $pm->department_id)
            ->whereNotNull('user_id')
            ->first();

        $project = Project::create([
            'project_name' => 'مشروع لمدير الدائرة',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(10000, 99999)),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'pending_dept_manager',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $this->actingAs($deptManager->user);
        $this->get('/projects')
            ->assertOk()
            ->assertSee('projects-table');

        $this->getJson('/projects', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonFragment(['project_name' => 'مشروع لمدير الدائرة']);

        $this->get(route('dashboard.projects.show', $project))
            ->assertOk();

        if ($otherDeptManager) {
            $this->actingAs($otherDeptManager->user);
            $this->get('/projects')
                ->assertOk()
                ->assertSee('projects-table');

            $this->getJson('/projects', ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()
                ->assertJsonMissing(['project_name' => 'مشروع لمدير الدائرة']);

            $this->get(route('dashboard.projects.show', $project))
                ->assertForbidden();
        }

        $project->delete();
    }

    public function test_department_manager_reject_uses_role_specific_gap_owner_options(): void
    {
        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $deptManager = Person::where('role', 'department_manager')
            ->where('department_id', $pm->department_id)
            ->whereNotNull('user_id')
            ->first();

        $this->assertNotNull($deptManager);

        $project = Project::create([
            'project_name' => 'مشروع رفض مدير دائرة',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + 600),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'pending_dept_manager',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $this->actingAs($deptManager->user);

        $this->post(route('dashboard.projects.reject', $project), [
            'rejection_reason' => 'نقص في المستندات',
            'gap_owner' => 'department_manager',
            'return_target' => 'return_project_manager',
        ])->assertSessionHasErrors('gap_owner');

        $this->post(route('dashboard.projects.reject', $project), [
            'rejection_reason' => 'نقص في المستندات',
            'gap_owner' => 'project_manager',
            'return_target' => 'return_coordinator',
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('coordinator_filling', $project->workflow_status);
        $this->assertSame('return_coordinator', $project->return_target);

        $project->delete();
    }

    public function test_project_datatable_ajax_delete_uses_model_id(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();

        $project = Project::create([
            'project_name' => 'مشروع حذف datatable ' . uniqid(),
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + 9000),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->deleteJson(route('dashboard.projects.destroy', $project), [
            '_token' => csrf_token(),
        ])
            ->assertOk()
            ->assertJson(['message' => 'تم حذف المشروع بنجاح.']);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_monitoring_director_rbac_on_project(): void
    {
        $directorPerson = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->first();
        $this->assertNotNull($directorPerson, 'Expected a monitoring director demo user.');

        $director = User::findOrFail($directorPerson->user_id);
        foreach ([
            'projects.view',
            'projects.update',
            'projects.reject',
            'monitoringactivities.set_monitoring_info',
            'monitoringactivities.assign_monitor',
            'monitoringactivities.confirm_completion',
        ] as $ability) {
            $director->roles()->firstOrCreate(['role_name' => $ability]);
        }

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $coordinator = Person::withRole('coordinator')->first() ?? Person::skip(1)->first();
        $monitor = Person::withRole('monitor')->first() ?? Person::skip(2)->first() ?? $coordinator;
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();

        $project = Project::create([
            'project_name' => 'مشروع RBAC مدير الرقابة ' . uniqid(),
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(8000, 8999)),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $coordinator->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
            'workflow_status' => 'pending_monitoring_manager',
            'coordinator_readiness_pct' => 100,
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        foreach (\App\Models\ChecklistItem::where('is_active', true)->get() as $item) {
            ProjectChecklistValue::create([
                'project_id' => $project->id,
                'checklist_item_id' => $item->id,
                'coordinator_value' => 'ready',
            ]);
        }

        $this->actingAs($director);

        $this->get(route('dashboard.projects.edit', $project))
            ->assertOk()
            ->assertDontSee('ثالثاً — قائمة تحقق المنسق');
        $this->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertSee('إعداد المراقبة')
            ->assertSee('قائمة التحقق — عمود المنسق')
            ->assertSee('عرض فقط');

        $updatedName = 'مشروع محدّث من مدير الرقابة ' . uniqid();
        $this->put(route('dashboard.projects.update', $project), $this->sampleProjectFields([
            'project_name' => $updatedName,
            'project_number_seq' => Project::sequenceFromProjectNumber($project->project_number),
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
        ]))->assertRedirect(route('dashboard.projects.show', $project));

        $project->refresh();
        $this->assertSame($updatedName, $project->project_name);
        $this->assertSame((int) $coordinator->id, (int) $project->coordinator_id);

        $checklist = $this->fullChecklist('not_ready');
        $this->post(route('dashboard.projects.fill-coordinator', $project), ['checklist' => $checklist])
            ->assertForbidden();

        $this->post(route('dashboard.projects.fill-monitor', $project), [
            'checklist' => $checklist,
        ])->assertForbidden();

        $monitorUser = $monitor->user_id
            ? User::findOrFail($monitor->user_id)
            : tap(User::first(), function ($user) use ($monitor) {
                $monitor->update(['user_id' => $user->id]);
            });

        foreach (['projects.view', 'projects.fill_monitor'] as $ability) {
            $monitorUser->roles()->firstOrCreate(['role_name' => $ability]);
        }

        $monitorProject = Project::create([
            'project_name' => 'مشروع RBAC مراقب ' . uniqid(),
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(9000, 9999)),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $coordinator->id,
            'monitor_person_id' => $monitor->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
            'workflow_status' => 'monitoring_in_progress',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $this->actingAs($monitorUser);
        $this->get(route('dashboard.projects.show', $monitorProject))
            ->assertRedirect(route('dashboard.projects.monitor-work', $monitorProject));

        $this->get(route('dashboard.projects.monitor-work', $monitorProject))
            ->assertOk()
            ->assertDontSee('قائمة التحقق — عمود المنسق');

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
        ProjectChecklistValue::where('project_id', $monitorProject->id)->delete();
        $monitorProject->delete();
    }

    public function test_project_create_requires_all_fields(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $this->from('/projects/create')
            ->post('/projects', [
                'project_name' => 'مشروع ناقص',
                'project_number_seq' => $this->nextProjectNumberSeq(),
                'coordinator_mode' => 'self',
            ])
            ->assertRedirect('/projects/create')
            ->assertSessionHasErrors([
                'project_type',
                'funder_id',
                'procurement_rep_id',
                'center_id',
                'department_id',
                'section_id',
            ]);
    }

    public function test_coordinator_with_user_blocks_project_manager_fill(): void
    {
        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $coordinator = Person::withRole('coordinator')->whereNotNull('user_id')->first()
            ?? tap(Person::withRole('coordinator')->first() ?? Person::skip(1)->first(), function (Person $person) {
                $person->update(['user_id' => User::first()->id]);
            });

        $pmUser = $pm->user_id
            ? User::findOrFail($pm->user_id)
            : tap(User::skip(1)->first() ?? User::first(), function (User $user) use ($pm) {
                $pm->update(['user_id' => $user->id]);
            });

        foreach (['projects.view', 'projects.create', 'projects.update', 'projects.fill_coordinator'] as $ability) {
            $pmUser->roles()->firstOrCreate(['role_name' => $ability]);
        }

        $this->actingAs($pmUser);

        $projectName = 'مشروع منسق بحساب ' . uniqid();
        $this->post('/projects', $this->sampleProjectFields([
            'project_name' => $projectName,
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
        ]))->assertRedirect();

        $project = Project::where('project_name', $projectName)->firstOrFail();
        $project->update(['workflow_status' => 'coordinator_filling']);

        $this->post(route('dashboard.projects.fill-coordinator', $project), [
            'fill_on_behalf' => '1',
            'checklist' => $this->fullChecklist(),
        ])->assertForbidden();

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_unset_checklist_items_count_as_not_ready(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $project = Project::create([
            'project_name' => 'مشروع جاهزية ' . uniqid(),
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(7000, 7999)),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $items = ChecklistItem::where('is_active', true)->take(2)->get();
        $this->assertGreaterThanOrEqual(1, $items->count());

        if ($items->count() >= 2) {
            ProjectChecklistValue::create([
                'project_id' => $project->id,
                'checklist_item_id' => $items[0]->id,
                'coordinator_value' => 'ready',
            ]);
        } else {
            ProjectChecklistValue::create([
                'project_id' => $project->id,
                'checklist_item_id' => $items[0]->id,
                'coordinator_value' => 'ready',
            ]);
        }

        $project->recalculateReadiness();
        $project->refresh();

        $this->assertNotNull($project->coordinator_readiness_pct);
        $this->assertLessThan(100.0, (float) $project->coordinator_readiness_pct);

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_rejection_creates_history_record(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $project = Project::create(array_merge($this->sampleProjectFields(), [
            'project_name' => 'مشروع سجل رفض',
            'workflow_status' => 'pending_dept_manager',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));

        $this->post(route('dashboard.projects.reject', $project), [
            'rejection_reason' => 'نقص في المستندات',
            'gap_owner' => 'coordinator',
            'return_target' => 'return_coordinator',
        ])->assertRedirect();

        $project->refresh();
        $this->assertDatabaseHas('project_rejections', [
            'project_id' => $project->id,
            'rejection_reason' => 'نقص في المستندات',
            'return_target' => 'return_coordinator',
            'workflow_status_before' => 'pending_dept_manager',
            'workflow_status_after' => 'coordinator_filling',
        ]);

        $project->rejections()->delete();
        $project->delete();
    }

    public function test_coordinator_sees_rejection_history_when_returned(): void
    {
        $coordinator = Person::where('role', 'coordinator')->whereNotNull('user_id')->first();
        $this->assertNotNull($coordinator);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $deptManager = Person::where('role', 'department_manager')
            ->where('department_id', $pm->department_id)
            ->whereNotNull('user_id')
            ->first();
        $this->assertNotNull($deptManager);

        $project = Project::create([
            'project_name' => 'مشروع رفض للمنسق',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + 700),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $coordinator->id,
            'workflow_status' => 'pending_dept_manager',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $this->actingAs($deptManager->user);
        $this->post(route('dashboard.projects.reject', $project), [
            'rejection_reason' => 'قائمة التحقق ناقصة',
            'gap_owner' => 'coordinator',
            'return_target' => 'return_coordinator',
        ])->assertRedirect();

        $this->actingAs($coordinator->user);
        $response = $this->get(route('dashboard.projects.show', $project));
        $response->assertOk();
        $response->assertSee('سجل الرفض والإرجاع');
        $response->assertSee('قائمة التحقق ناقصة');
        $response->assertSee('أُرجِع المشروع للمراجعة');

        $project->rejections()->delete();
        $project->delete();
    }

    public function test_monitor_does_not_see_rejection_history(): void
    {
        $monitor = Person::where('role', 'monitor')->whereNotNull('user_id')->first();
        $this->assertNotNull($monitor);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $coordinator = Person::where('role', 'coordinator')->whereNotNull('user_id')->first();
        $this->assertNotNull($coordinator);

        $project = Project::create([
            'project_name' => 'مشروع رفض للمراقب',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + 800),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $coordinator->id,
            'monitor_person_id' => $monitor->id,
            'workflow_status' => 'pending_dept_manager',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);
        $this->post(route('dashboard.projects.reject', $project), [
            'rejection_reason' => 'سبب سري للإدارة',
            'gap_owner' => 'coordinator',
            'return_target' => 'return_coordinator',
        ])->assertRedirect();

        $this->actingAs($monitor->user);
        $response = $this->get(route('dashboard.projects.show', $project));
        $response->assertOk();
        $response->assertDontSee('سجل الرفض والإرجاع');
        $response->assertDontSee('سبب سري للإدارة');

        $project->rejections()->delete();
        $project->delete();
    }

    public function test_coordinator_does_not_see_monitoring_status_panel(): void
    {
        $coordinator = Person::where('role', 'coordinator')->whereNotNull('user_id')->first();
        $this->assertNotNull($coordinator);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $monitor = Person::where('role', 'monitor')->whereNotNull('user_id')->first();
        $this->assertNotNull($monitor);

        $project = Project::create([
            'project_name' => 'مشروع اختبار المنسق والمراقبة',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(10000, 19999)),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $coordinator->id,
            'monitor_person_id' => $monitor->id,
            'monitoring_method' => 'ميداني',
            'monitoring_stage' => 'أثناء التنفيذ',
            'monitoring_date' => '2026-07-08',
            'workflow_status' => 'monitoring_in_progress',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $this->actingAs($coordinator->user);
        $this->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertDontSee('حالة المراقبة')
            ->assertDontSee('المراقب المعيّن');

        $director = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->first();
        $this->assertNotNull($director);

        $this->actingAs($director->user);
        $this->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertSee('حالة المراقبة')
            ->assertSee('المراقب المعيّن');

        $project->delete();
    }

    public function test_return_notice_cleared_after_coordinator_resubmits(): void
    {
        $coordinator = Person::where('role', 'coordinator')->whereNotNull('user_id')->first();
        $this->assertNotNull($coordinator);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();

        $project = Project::create([
            'project_name' => 'مشروع مسح إشعار الرفض',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(10000, 99999)),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $coordinator->id,
            'workflow_status' => 'coordinator_filling',
            'rejection_reason' => 'نقص سابق',
            'rejected_by' => User::first()->id,
            'rejected_at' => now(),
            'return_target' => 'return_coordinator',
            'gap_owner' => 'coordinator',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $this->actingAs($coordinator->user);
        $this->post(route('dashboard.projects.fill-coordinator', $project), [
            'checklist' => $this->fullChecklist('ready'),
        ])->assertRedirect();

        $this->post(route('dashboard.projects.submit-to-project-manager', $project))->assertRedirect();

        $project->refresh();
        $this->assertNull($project->rejection_reason);
        $this->assertNull($project->return_target);
        $this->assertSame('pending_project_manager', $project->workflow_status);

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

}
