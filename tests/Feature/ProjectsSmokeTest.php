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

        $pm = Person::first();
        $coordinator = Person::skip(1)->first();
        $monitor = Person::skip(2)->first() ?? $coordinator;
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();

        // 1) create draft
        $this->post('/projects', [
            'project_name' => 'مشروع اختبار شامل',
            'project_manager_id' => $pm->id,
            'coordinator_id' => $coordinator->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
        ])->assertRedirect();

        $project = Project::where('project_name', 'مشروع اختبار شامل')->firstOrFail();
        $this->assertSame('draft', $project->workflow_status);

        $this->get(route('dashboard.projects.show', $project))->assertStatus(200);
        $this->get(route('dashboard.projects.edit', $project))->assertStatus(200);

        // 2) submit to coordinator
        $this->post(route('dashboard.projects.submit-to-coordinator', $project))->assertRedirect();
        $project->refresh();
        $this->assertContains($project->workflow_status, ['pending_coordinator', 'coordinator_filling']);

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
        $this->assertSame('MP-1', $activity->reference_code);

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

        // 7) monitor confirms completion -> pending_confirmation
        $this->post(route('dashboard.projects.confirm-monitoring', $project))->assertRedirect();
        $activity->refresh();
        $this->assertSame('pending_confirmation', $activity->workflow_status);

        // 8) monitoring manager confirms passage -> completed
        $this->post(route('dashboard.monitoring-activities.confirm-passage', $activity))->assertRedirect();
        $activity->refresh();
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
}
