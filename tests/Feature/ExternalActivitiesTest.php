<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Department;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\ProjectExecution;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $user = User::whereHas('person', fn ($q) => $q->where('role', 'monitor'))->first()
            ?? User::whereIn('username', ['monitor1', 'demo_monitor'])->first();

        $this->assertNotNull($user, 'Monitor user required — run demo seeders');

        if ($user->person && $user->person->role !== 'monitor') {
            $user->person->update(['role' => 'monitor']);
            $user = $user->fresh(['person']);
        }

        if ($user->person && empty($user->person->phone)) {
            $user->person->update(['phone' => '0500000001']);
        }

        return $user->fresh(['person']);
    }

    private function directorUser(): User
    {
        $user = User::where('username', 'demo_mon_dir')->first()
            ?? User::where('username', 'mon_dir')->first();

        $this->assertNotNull($user, 'Monitoring director user required — run demo seeders');

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function externalPayload(Center $center, Department $department, ?string $referenceCode = null): array
    {
        return [
            'reference_code' => $referenceCode ?? ('MA-TEST-' . uniqid()),
            'center_id' => $center->id,
            'department_id' => $department->id,
            'subject' => 'نشاط خارجي — اختبار ' . uniqid(),
            'notes' => 'ملاحظة ميدانية',
            'field_problem' => 0,
            'execution_value' => 80,
            'quality_value' => 85,
            'closure_value' => 60,
            'deduction_value' => 0,
            'activity_date' => now()->format('Y-m-d'),
            'activity_time' => '10:00',
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function putExternal(MonitoringActivity $activity, array $data)
    {
        return $this->post(route('dashboard.external-activities.update', $activity), $data + [
            'reference_code' => $data['reference_code'] ?? $activity->reference_code,
        ]);
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

        $this->get(route('dashboard.monitoring-activities.show', $activity))
            ->assertOk()
            ->assertSee($updatedSubject, false);

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
            'closure_value' => 100,
            'reference_code' => $activity->reference_code,
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

    public function test_external_activity_accepts_custom_reference_code(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $customCode = 'MA-CUSTOM-' . uniqid();
        $payload = $this->externalPayload($center, $department, $customCode);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save'])
            ->assertRedirect();

        $activity = MonitoringActivity::where('reference_code', $customCode)->firstOrFail();
        $this->assertSame($customCode, $activity->reference_code);

        $activity->delete();
    }

    public function test_external_activity_rejects_duplicate_reference_code(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $code = 'MA-DUP-' . uniqid();
        $payload = $this->externalPayload($center, $department, $code);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save']);

        $first = MonitoringActivity::where('reference_code', $code)->firstOrFail();

        $payload['subject'] = 'نشاط مكرر — ' . uniqid();
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save'])
            ->assertSessionHasErrors('reference_code');

        $first->delete();
    }

    public function test_external_activity_saves_extended_content_fields(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department) + [
            'detail' => 'زيارة ميدانية',
            'closure_date' => '2026-08-15',
            'positive_notes_text' => "ملاحظة إيجابية 1\nملاحظة إيجابية 2",
            'negative_notes_text' => "ملاحظة سلبية 1",
            'recommendations_text' => "توصية 1\nتوصية 2",
        ];

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save'])
            ->assertRedirect();

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();
        $this->assertSame('زيارة ميدانية', $activity->detail);
        $this->assertSame('2026-08-15', $activity->closure_date?->format('Y-m-d'));
        $this->assertSame(['ملاحظة إيجابية 1', 'ملاحظة إيجابية 2'], $activity->positive_notes);
        $this->assertSame(['ملاحظة سلبية 1'], $activity->negative_notes);
        $this->assertSame(['توصية 1', 'توصية 2'], $activity->recommendations);

        $activity->delete();
    }

    public function test_external_activity_stores_file_and_url_attachments(): void
    {
        Storage::fake('public');

        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + [
            'action' => 'save',
            'activity_attachment_urls' => ['https://example.com/report.pdf'],
            'activity_attachments' => [
                UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect();

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();
        $this->assertCount(2, $activity->attachmentsList());

        $this->post(route('dashboard.external-activities.delete-attachment', $activity), [
            'attachment_id' => $activity->attachmentsList()[0]['id'],
        ])->assertRedirect();

        $activity->refresh();
        $this->assertCount(1, $activity->attachmentsList());

        $activity->delete();
    }

    public function test_external_activity_scale_values_compute_kpi(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save']);

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();
        $this->assertSame(80.0, $activity->execution_value);
        $this->assertSame(85.0, $activity->quality_value);
        $this->assertSame(60.0, $activity->closure_value);
        $this->assertSame(0.0, $activity->deduction_value);
        $this->assertSame(75.5, $activity->kpi_value);
        $this->assertSame('جيد', $activity->kpi_rating);

        $activity->delete();
    }

    public function test_director_home_shows_project_manager_column_in_execution_tracks(): void
    {
        $execution = ProjectExecution::query()
            ->where('is_active', true)
            ->whereHas('project.projectManager')
            ->with('project.projectManager')
            ->first();

        if (! $execution?->project?->projectManager) {
            $this->markTestSkipped('No active execution with project manager in database');
        }

        $director = $this->directorUser();
        $this->actingAs($director);

        $this->get(route('dashboard.home'))
            ->assertOk()
            ->assertSee('مدير المشروع')
            ->assertSee($execution->project->projectManager->name);
    }

    public function test_external_activity_show_displays_image_inline(): void
    {
        Storage::fake('public');

        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + [
            'action' => 'save',
            'activity_attachments' => [
                UploadedFile::fake()->image('field-photo.jpg'),
            ],
        ])->assertRedirect();

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();
        $attachment = $activity->attachmentsList()[0] ?? null;
        $this->assertNotNull($attachment);
        $this->assertTrue($activity->attachmentIsImage($attachment));

        $imageUrl = $activity->attachmentRowUrl($attachment);
        $this->assertNotNull($imageUrl);

        $this->get(route('dashboard.monitoring-activities.show', $activity))
            ->assertOk()
            ->assertSee('<img', false)
            ->assertSee($imageUrl, false);

        $activity->delete();
    }

    public function test_returned_external_activity_has_monitor_action_flags(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save']);

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();
        $this->post(route('dashboard.external-activities.submit', $activity));

        $director = $this->directorUser();
        $this->actingAs($director);
        $this->post(route('dashboard.external-activities.return', $activity), [
            'rejection_reason' => 'يرجى إرفاق صورة أوضح',
            'gap_owner' => 'monitor',
        ]);

        $activity->refresh();
        $this->assertTrue($activity->wasReturnedToMonitor());
        $this->assertTrue($activity->needsActionFromMonitor($monitorUser));

        $this->actingAs($monitorUser);
        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('dashboard.monitoring-activities.index'));

        $response->assertOk();
        $rows = collect($response->json('data'));
        $row = $rows->firstWhere('id', $activity->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['needs_monitor_action']);
        $this->assertTrue($row['was_returned_to_monitor']);

        $activity->delete();
    }

    public function test_problem_closure_status_labels(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();

        $this->actingAs($monitorUser);

        $completePayload = $this->externalPayload($center, $department);
        $this->post(route('dashboard.external-activities.store'), $completePayload + ['action' => 'save'])
            ->assertRedirect();
        $completeActivity = MonitoringActivity::where('subject', $completePayload['subject'])->firstOrFail();
        $this->assertSame('complete', $completeActivity->problemClosureStatusKey());
        $this->assertSame('مكتمل', $completeActivity->problemClosureStatusLabel());

        $followUpPayload = array_merge($this->externalPayload($center, $department), [
            'field_problem' => 1,
            'deduction_value' => -10,
        ]);
        $this->post(route('dashboard.external-activities.store'), $followUpPayload + ['action' => 'save'])
            ->assertRedirect();
        $followUpActivity = MonitoringActivity::where('subject', $followUpPayload['subject'])->firstOrFail();
        $this->assertSame('in_follow_up', $followUpActivity->problemClosureStatusKey());
        $this->assertSame('قيد المتابعة', $followUpActivity->problemClosureStatusLabel());

        $closedPayload = array_merge($this->externalPayload($center, $department), [
            'field_problem' => 1,
            'deduction_value' => -10,
            'closure_date' => '2026-08-20',
        ]);
        $this->post(route('dashboard.external-activities.store'), $closedPayload + ['action' => 'save'])
            ->assertRedirect();
        $closedActivity = MonitoringActivity::where('subject', $closedPayload['subject'])->firstOrFail();
        $this->assertSame('closed', $closedActivity->problemClosureStatusKey());
        $this->assertSame('تم الإغلاق', $closedActivity->problemClosureStatusLabel());

        $completeActivity->delete();
        $followUpActivity->delete();
        $closedActivity->delete();
    }

    public function test_external_activity_clears_closure_date_when_no_field_problem(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = array_merge($this->externalPayload($center, $department), [
            'field_problem' => 1,
            'deduction_value' => -10,
            'closure_date' => '2026-08-15',
        ]);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save'])
            ->assertRedirect();

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();
        $this->assertSame('2026-08-15', $activity->closure_date?->format('Y-m-d'));

        $this->putExternal($activity, array_merge($payload, [
            'field_problem' => 0,
            'deduction_value' => 0,
            'action' => 'save',
        ]))->assertRedirect();

        $activity->refresh();
        $this->assertNull($activity->closure_date);
        $this->assertSame('complete', $activity->problemClosureStatusKey());

        $activity->delete();
    }

    public function test_monitoring_activities_index_includes_problem_closure_status(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = array_merge($this->externalPayload($center, $department), [
            'field_problem' => 1,
            'deduction_value' => -10,
        ]);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save'])
            ->assertRedirect();

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();

        $response = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('dashboard.monitoring-activities.index'));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $activity->id);
        $this->assertNotNull($row);
        $this->assertSame('قيد المتابعة', $row['problem_closure_status_label']);
        $this->assertSame('in_follow_up', $row['problem_closure_status_key']);

        $activity->delete();
    }

    public function test_monitor_home_shows_assigned_work_sections(): void
    {
        $monitorUser = $this->monitorUser();
        ['center' => $center, 'department' => $department] = $this->orgDefaults();
        $payload = $this->externalPayload($center, $department);

        $this->actingAs($monitorUser);
        $this->post(route('dashboard.external-activities.store'), $payload + ['action' => 'save']);

        $activity = MonitoringActivity::where('subject', $payload['subject'])->firstOrFail();

        $this->get(route('dashboard.home'))
            ->assertOk()
            ->assertSee('يتطلب إجراءك الآن', false)
            ->assertSee('أنشطة أضفتها', false)
            ->assertSee($activity->reference_code, false);

        $activity->delete();
    }
}
