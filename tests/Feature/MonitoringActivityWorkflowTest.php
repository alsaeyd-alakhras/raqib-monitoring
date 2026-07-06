<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Department;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use Tests\TestCase;

class MonitoringActivityWorkflowTest extends TestCase
{
    private function monitorUserFor(Person $monitor): User
    {
        if ($monitor->user_id) {
            return User::findOrFail($monitor->user_id);
        }

        $user = User::first();
        $monitor->update(['user_id' => $user->id]);

        foreach (['projects.view', 'projects.fill_monitor', 'monitoringactivities.view', 'monitoringactivities.update'] as $ability) {
            $user->roles()->firstOrCreate(['role_name' => $ability]);
        }

        return $user->fresh();
    }

    private function orgDefaults(): array
    {
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();

        return compact('center', 'department');
    }

    public function test_external_activity_monitor_workflow(): void
    {
        $director = User::first();
        $director->super_admin = 1;
        $this->actingAs($director);

        $monitor = Person::withRole('monitor')->first();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();

        $this->post(route('dashboard.monitoring-activities.store'), [
            'source_type' => 'external',
            'center_id' => $center->id,
            'department_id' => $department->id,
            'monitor_person_id' => $monitor->id,
            'subject' => 'نشاط خارجي اختباري',
            'notes' => 'ملاحظة ميدانية',
            'field_problem' => 0,
            'deduction_value' => 0,
            'workflow_status' => 'pending_monitor',
            'is_passage_complete' => 0,
        ])->assertRedirect(route('dashboard.monitoring-activities.index'));

        $activity = MonitoringActivity::where('subject', 'نشاط خارجي اختباري')->firstOrFail();
        $this->assertSame('secondary', $activity->activity_role);
        $this->assertSame('external', $activity->source_type);
        $this->assertSame('in_progress', $activity->workflow_status);
        $this->assertSame((int) $monitor->id, (int) $activity->monitor_person_id);

        $monitorUser = $this->monitorUserFor($monitor);
        $this->actingAs($monitorUser);

        $this->put(route('dashboard.monitoring-activities.update', $activity), [
            'subject' => 'نشاط خارجي — بعد المراقب',
            'notes' => 'تمت الزيارة',
            'field_problem' => 0,
            'execution_value' => 80,
            'quality_value' => 75,
            'closure_value' => 70,
            'deduction_value' => 0,
        ])->assertRedirect(route('dashboard.monitoring-activities.show', $activity));

        $activity->refresh();
        $this->assertSame('in_progress', $activity->workflow_status);
        $this->assertSame('نشاط خارجي — بعد المراقب', $activity->subject);

        $this->post(route('dashboard.monitoring-activities.submit-to-director', $activity))
            ->assertRedirect(route('dashboard.monitoring-activities.show', $activity));

        $activity->refresh();
        $this->assertSame('pending_confirmation', $activity->workflow_status);

        $this->actingAs($director);

        $this->post(route('dashboard.monitoring-activities.confirm-passage', $activity))
            ->assertRedirect();

        $activity->refresh();
        $this->assertSame('completed', $activity->workflow_status);
        $this->assertTrue($activity->is_passage_complete);

        $activity->delete();
    }

    public function test_secondary_activity_from_project_note_workflow(): void
    {
        $director = User::first();
        $director->super_admin = 1;
        $this->actingAs($director);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $monitor = Person::withRole('monitor')->first();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();

        $project = Project::create([
            'project_name' => 'مشروع نشاط تابع ' . uniqid(),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
            'monitor_person_id' => $monitor->id,
            'workflow_status' => 'monitoring_in_progress',
            'monitor_notes' => ['ملاحظة للمتابعة'],
            'created_by' => $director->id,
            'updated_by' => $director->id,
        ]);

        $primary = MonitoringActivity::create([
            'reference_code' => 'MP-TEST-' . uniqid(),
            'source_type' => 'project',
            'source_id' => $project->id,
            'activity_role' => 'primary',
            'center_id' => $center->id,
            'department_id' => $department->id,
            'monitor_person_id' => $monitor->id,
            'subject' => $project->project_name,
            'field_problem' => false,
            'workflow_status' => 'in_progress',
            'is_passage_complete' => false,
            'created_by' => $director->id,
            'updated_by' => $director->id,
        ]);

        $project->update(['primary_monitoring_activity_id' => $primary->id]);

        $this->post(route('dashboard.monitoring-activities.store'), [
            'source_type' => 'project',
            'source_id' => $project->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
            'monitor_person_id' => $monitor->id,
            'subject' => 'متابعة ملاحظة مراقب',
            'notes' => 'ملاحظة للمتابعة',
            'field_problem' => 0,
            'deduction_value' => 0,
            'workflow_status' => 'pending_monitor',
            'is_passage_complete' => 0,
        ])->assertRedirect(route('dashboard.monitoring-activities.index'));

        $secondary = MonitoringActivity::where('subject', 'متابعة ملاحظة مراقب')->firstOrFail();
        $this->assertSame('secondary', $secondary->activity_role);
        $this->assertSame('in_progress', $secondary->workflow_status);

        $this->get(route('dashboard.monitoring-activities.show', $primary))
            ->assertOk()
            ->assertSee($secondary->reference_code)
            ->assertSee('متابعة ملاحظة مراقب');

        $monitorUser = $this->monitorUserFor($monitor);
        $this->actingAs($monitorUser);

        $this->post(route('dashboard.monitoring-activities.submit-to-director', $secondary))
            ->assertRedirect(route('dashboard.monitoring-activities.show', $secondary));

        $secondary->refresh();
        $project->refresh();
        $this->assertSame('pending_confirmation', $secondary->workflow_status);
        $this->assertSame('monitoring_in_progress', $project->workflow_status);

        $this->actingAs($director);
        $this->post(route('dashboard.monitoring-activities.confirm-passage', $secondary))->assertRedirect();

        $secondary->refresh();
        $project->refresh();
        $this->assertSame('completed', $secondary->workflow_status);
        $this->assertSame('monitoring_in_progress', $project->workflow_status);

        $this->put(route('dashboard.monitoring-activities.update', $primary), [
            'source_type' => 'project',
            'source_id' => $project->id,
            'center_id' => $center->id,
            'department_id' => $department->id,
            'monitor_person_id' => $monitor->id,
            'subject' => $primary->subject,
            'field_problem' => 0,
            'deduction_value' => 0,
            'workflow_status' => 'in_progress',
            'is_passage_complete' => 0,
        ])->assertRedirect();

        $project->update(['primary_monitoring_activity_id' => null]);
        $secondary->delete();
        $primary->delete();
        $project->delete();
    }

    public function test_monitor_cannot_edit_primary_activity_via_activity_form(): void
    {
        $director = User::first();
        $monitor = Person::withRole('monitor')->first();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();

        $activity = MonitoringActivity::create([
            'reference_code' => 'MP-LOCK-' . uniqid(),
            'source_type' => 'external',
            'activity_role' => 'primary',
            'center_id' => $center->id,
            'department_id' => $department->id,
            'monitor_person_id' => $monitor->id,
            'subject' => 'نشاط أساسي وهمي',
            'field_problem' => false,
            'workflow_status' => 'in_progress',
            'is_passage_complete' => false,
            'created_by' => $director->id,
            'updated_by' => $director->id,
        ]);

        $monitorUser = $this->monitorUserFor($monitor);
        $this->actingAs($monitorUser);

        $this->put(route('dashboard.monitoring-activities.update', $activity), [
            'subject' => 'محاولة تعديل',
            'field_problem' => 0,
            'deduction_value' => 0,
        ])->assertForbidden();

        $activity->delete();
    }
}
