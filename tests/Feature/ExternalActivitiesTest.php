<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Department;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\User;
use Tests\TestCase;

class ExternalActivitiesTest extends TestCase
{
    private function orgDefaults(): array
    {
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();

        return compact('center', 'department');
    }

    private function monitorUser(): User
    {
        $user = User::where('username', 'demo_monitor')->first()
            ?? User::whereHas('person', fn ($q) => $q->where('role', 'monitor'))->first();

        $this->assertNotNull($user, 'Monitor user required — run demo seeders');

        if ($user->person && empty($user->person->phone)) {
            $user->person->update(['phone' => '0500000001']);
        }

        return $user->fresh();
    }

    private function directorUser(): User
    {
        $user = User::where('username', 'demo_mon_dir')->first()
            ?? User::where('username', 'mon_dir')->first();

        $this->assertNotNull($user, 'Monitoring director user required — run demo seeders');

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function externalPayload(Center $center, Department $department): array
    {
        return [
            'center_id' => $center->id,
            'department_id' => $department->id,
            'subject' => 'نشاط خارجي — اختبار ' . uniqid(),
            'notes' => 'ملاحظة ميدانية',
            'field_problem' => 0,
            'execution_value' => 85,
            'quality_value' => 80,
            'closure_value' => 75,
            'deduction_value' => 0,
            'activity_date' => now()->format('Y-m-d'),
            'activity_time' => '10:00',
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function putExternal(MonitoringActivity $activity, array $data)
    {
        return $this->post(route('dashboard.external-activities.update', $activity), $data);
    }

    public function test_monitor_external_activity_full_workflow(): void
    {
        $monitorUser = $this->monitorUser();
        $monitor = $monitorUser->person;
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);

        $this->actingAs($monitorUser);

        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save'])
            ->assertRedirect();

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();
        $this->assertTrue($activity->isExternal());
        $this->assertSame('in_progress', $activity->workflow_status);
        $this->assertSame((int) $monitor->id, (int) $activity->monitor_person_id);
        $this->assertStringStartsWith('MA-', $activity->reference_code);

        $updatedSubject = $payload['subject'] . ' - updated';

        $response = $this->putExternal($activity, array_merge($payload, [
            'subject' => $updatedSubject,
            'action' => 'save',
        ]));
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard.external-activities.edit', $activity));
        $response->assertSessionHas('success');

        $activity->refresh();
        $this->assertSame($updatedSubject, $activity->subject);

        $this->post(route('dashboard.external-activities.submit', $activity))
            ->assertRedirect(route('dashboard.monitoring-activities.show', $activity));

        $activity->refresh();
        $this->assertSame('pending_confirmation', $activity->workflow_status);
        $this->assertNotNull($activity->submitted_at);

        $director = $this->directorUser();
        $this->actingAs($director);

        $this->post(route('dashboard.external-activities.return', $activity), [
            'rejection_reason' => 'يرجى استكمال بيانات التقييم',
            'gap_owner' => 'monitor',
        ])->assertRedirect(route('dashboard.monitoring-activities.show', $activity));

        $activity->refresh();
        $this->assertSame('in_progress', $activity->workflow_status);
        $this->assertSame('يرجى استكمال بيانات التقييم', $activity->rejection_reason);

        $this->actingAs($monitorUser);

        $this->putExternal($activity, array_merge($payload, [
            'subject' => $updatedSubject . ' - resubmit',
            'closure_value' => 90,
            'action' => 'submit',
        ]))->assertRedirect(route('dashboard.monitoring-activities.show', $activity));

        $activity->refresh();
        $this->assertSame('pending_confirmation', $activity->workflow_status);
        $this->assertNull($activity->rejection_reason);

        $this->actingAs($director);

        $this->post(route('dashboard.external-activities.approve', $activity))
            ->assertRedirect(route('dashboard.monitoring-activities.show', $activity));

        $activity->refresh();
        $this->assertSame('completed', $activity->workflow_status);
        $this->assertTrue($activity->is_passage_complete);

        $activity->delete();
    }

    public function test_other_monitor_cannot_edit_external_activity(): void
    {
        $ownerUser = $this->monitorUser();
        $otherUser = User::whereHas('person', fn ($q) => $q->where('role', 'monitor'))
            ->where('id', '!=', $ownerUser->id)
            ->first();

        if (! $otherUser) {
            $this->markTestSkipped('Need a second monitor user');
        }

        if ($otherUser->person && empty($otherUser->person->phone)) {
            $otherUser->person->update(['phone' => '0500000002']);
        }

        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);

        $this->actingAs($ownerUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save']);

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();

        $this->actingAs($otherUser->fresh());

        $this->get(route('dashboard.external-activities.edit', $activity))->assertForbidden();

        $activity->delete();
    }

    public function test_monitor_cannot_edit_after_submit(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'submit']);

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();
        $this->assertSame('pending_confirmation', $activity->workflow_status);

        $this->get(route('dashboard.external-activities.edit', $activity))->assertForbidden();

        $activity->delete();
    }

    public function test_director_can_reject_external_activity_final(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'submit']);

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();

        $director = $this->directorUser();
        $this->actingAs($director);

        $this->post(route('dashboard.external-activities.reject', $activity), [
            'rejection_reason' => 'غير مقبول',
            'gap_owner' => 'other',
        ])->assertRedirect();

        $activity->refresh();
        $this->assertSame('rejected', $activity->workflow_status);

        $activity->delete();
    }

    public function test_source_type_labels_include_project_execution(): void
    {
        $labels = MonitoringActivity::sourceTypeLabels();

        $this->assertSame('مسار تنفيذ', $labels['project_execution']);
        $this->assertSame('خارجي', $labels['external']);
    }

    public function test_monitoring_director_home_shows_pending_confirmation_activity(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);
        $payload['subject'] = 'نشاط للاعتماد — ' . uniqid();

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'submit']);

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();
        $this->assertSame('pending_confirmation', $activity->workflow_status);

        $director = $this->directorUser();
        $this->actingAs($director);

        $this->get(route('dashboard.home'))
            ->assertOk()
            ->assertSee('أنشطة خارجية بانتظار اعتمادك')
            ->assertSee($activity->reference_code)
            ->assertSee($payload['subject']);

        $activity->delete();
    }

    public function test_project_execution_activity_show_redirects_to_execution_workflow(): void
    {
        $activity = MonitoringActivity::query()
            ->where('source_type', 'project_execution')
            ->whereNotNull('project_execution_id')
            ->with('projectExecution.project')
            ->first();

        if (! $activity?->projectExecution?->project) {
            $this->markTestSkipped('No linked project_execution activity in database');
        }

        $execution = $activity->projectExecution;
        $project = $execution->project;

        $director = $this->directorUser();
        $this->actingAs($director);

        $this->get(route('dashboard.monitoring-activities.show', $activity))
            ->assertRedirect(route('dashboard.projects.executions.show', [$project, $execution]));
    }
}
