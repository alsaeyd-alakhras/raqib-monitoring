<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Department;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectChecklistValue;
use App\Models\User;
use Tests\TestCase;

class ProjectsSmokeTest extends TestCase
{
    private function nextProjectNumberSeq(): int
    {
        return Project::sequenceFromProjectNumber(Project::generateProjectNumber()) ?? 1;
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
        $this->post('/projects', [
            'project_name' => $projectName,
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
        ])->assertRedirect();

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
        $checklist = [];
        foreach (\App\Models\ChecklistItem::where('is_active', true)->get() as $item) {
            $checklist[$item->id] = ['value' => 'ready'];
        }
        $this->post(route('dashboard.projects.fill-coordinator', $project), ['checklist' => $checklist])
            ->assertRedirect();
        $project->refresh();
        $this->assertSame('coordinator_filling', $project->workflow_status);
        $this->assertEquals(100.0, (float) $project->coordinator_readiness_pct);

        // 4) submit to dept manager, approve
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

        $checklistMonitor = [];
        foreach (\App\Models\ChecklistItem::where('is_active', true)->get() as $item) {
            $checklistMonitor[$item->id] = ['value' => 'partial'];
        }
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

        $this->post('/projects', [
            'project_name' => 'مشروع منسق خارجي',
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'external',
            'coordinator_external_name' => 'منسق خارجي تجريبي',
            'center_id' => $center->id,
            'department_id' => $department->id,
        ])->assertRedirect();

        $externalProject = Project::where('project_name', 'مشروع منسق خارجي')->firstOrFail();
        $this->assertSame('external', $externalProject->coordinatorMode());

        $this->post('/projects', [
            'project_name' => 'مشروع منسق ذاتي',
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'self',
            'center_id' => $center->id,
            'department_id' => $department->id,
        ])->assertRedirect();

        $selfProject = Project::where('project_name', 'مشروع منسق ذاتي')->firstOrFail();
        $this->assertTrue($selfProject->isSelfCoordinator());
        $this->assertMatchesRegularExpression('/^P-\d+$/', (string) $selfProject->project_number);

        if ($itemId) {
            ProjectChecklistValue::updateOrCreate(
                ['project_id' => $selfProject->id, 'checklist_item_id' => $itemId],
                ['coordinator_value' => 'ready']
            );
        }

        $this->put(route('dashboard.projects.update', $selfProject), [
            'project_name' => $selfProject->project_name,
            'project_number_seq' => \App\Models\Project::sequenceFromProjectNumber($selfProject->project_number),
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
        ])->assertRedirect();

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

        $this->post('/projects', [
            'project_name' => 'مشروع تعبئة خارجي',
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'external',
            'coordinator_external_name' => 'منسق خارجي',
            'center_id' => $center->id,
            'department_id' => $department->id,
        ])->assertRedirect();

        $project = Project::where('project_name', 'مشروع تعبئة خارجي')->firstOrFail();

        $checklist = $itemId ? [$itemId => ['value' => 'ready']] : [];
        $this->post(route('dashboard.projects.fill-coordinator', $project), [
            'fill_on_behalf' => '1',
            'checklist' => $checklist,
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame($user->id, $project->coordinator_filled_by);

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_submit_to_department_requires_saved_coordinator_fill(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $coordinator = Person::withRole('coordinator')->first() ?? Person::skip(1)->first();
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();
        $projectName = 'مشروع تحقق تعبئة المنسق ' . uniqid();

        $this->post('/projects', [
            'project_name' => $projectName,
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
        ])->assertRedirect();

        $project = Project::where('project_name', $projectName)->firstOrFail();

        $this->post(route('dashboard.projects.submit-to-coordinator', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame('pending_coordinator', $project->workflow_status);

        $project->update(['workflow_status' => 'coordinator_filling']);

        $this->from(route('dashboard.projects.show', $project))
            ->post(route('dashboard.projects.submit-to-dept-manager', $project))
            ->assertRedirect(route('dashboard.projects.show', $project))
            ->assertSessionHasErrors('coordinator');

        $project->refresh();
        $this->assertSame('coordinator_filling', $project->workflow_status);

        $itemId = \App\Models\ChecklistItem::where('is_active', true)->value('id');
        if ($itemId) {
            $this->post(route('dashboard.projects.fill-coordinator', $project), [
                'fill_on_behalf' => '1',
                'checklist' => [
                    $itemId => ['value' => 'ready'],
                ],
            ])->assertRedirect();
        }

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
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + 500),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'workflow_status' => 'pending_dept_manager',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $this->actingAs($deptManager->user);
        $this->get('/projects')
            ->assertOk()
            ->assertSee('مشروع لمدير الدائرة');

        $this->get(route('dashboard.projects.show', $project))
            ->assertOk();

        if ($otherDeptManager) {
            $this->actingAs($otherDeptManager->user);
            $this->get('/projects')
                ->assertOk()
                ->assertDontSee('مشروع لمدير الدائرة');

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

}
