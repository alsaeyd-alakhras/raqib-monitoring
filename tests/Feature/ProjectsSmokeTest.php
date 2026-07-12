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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $center = Center::firstOrFail();
        $department = Department::where('center_id', $center->id)->firstOrFail();
        $section = Section::where('department_id', $department->id)->first()
            ?? Section::create([
                'department_id' => $department->id,
                'name' => 'قسم تجريبي',
            ]);

        $pm = Person::withRole('project_manager')
            ->where('section_id', $section->id)
            ->first()
            ?? Person::withRole('project_manager')->first()
            ?? Person::first();

        $this->alignPersonToSection($pm, $section);

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
            'execution_start_date' => '2026-02-01',
            'location' => 'موقع تجريبي',
            'target_beneficiaries' => 100,
            'execution_zones' => 2,
            'execution_region_names' => ['منطقة شمال', 'منطقة جنوب'],
            'estimated_duration' => '6 أشهر',
            'allocated_budget' => 50000,
        ], $overrides);
    }

    private function alignPersonToSection(Person $person, Section $section): void
    {
        if ((int) $person->section_id !== (int) $section->id) {
            $person->update([
                'section_id' => $section->id,
                'department_id' => $section->department_id,
            ]);
        }
    }

    private function ensureSectionManagerForSection(Section $section): Person
    {
        $manager = Person::where('role', 'section_manager')
            ->where('section_id', $section->id)
            ->whereNotNull('user_id')
            ->first();

        if ($manager) {
            return $manager;
        }

        $user = User::create([
            'name' => 'مدير قسم اختبار ' . $section->id,
            'username' => 'sm_test_' . $section->id,
            'email' => 'sm.test.' . $section->id . '@raqib.test',
            'user_type' => 'employee',
            'is_active' => true,
            'super_admin' => false,
            'password' => bcrypt('password'),
        ]);

        foreach ([
            'projects.view',
            'projects.approve_section',
            'projects.reject',
            'people.view',
            'people.create',
            'people.update',
        ] as $ability) {
            $user->roles()->create([
                'role_name' => $ability,
                'ability' => 'allow',
            ]);
        }

        return Person::create([
            'name' => $user->name,
            'role' => 'section_manager',
            'section_id' => $section->id,
            'department_id' => $section->department_id,
            'user_id' => $user->id,
            'job_title' => 'مدير قسم اختبار',
        ]);
    }

    private function ensureDepartmentManagerForDepartment(int $departmentId): Person
    {
        $manager = Person::where('role', 'department_manager')
            ->where('department_id', $departmentId)
            ->whereNotNull('user_id')
            ->first();

        if ($manager) {
            return $manager;
        }

        $user = User::create([
            'name' => 'مدير دائرة اختبار ' . $departmentId,
            'username' => 'dm_test_' . $departmentId,
            'email' => 'dm.test.' . $departmentId . '@raqib.test',
            'user_type' => 'employee',
            'is_active' => true,
            'super_admin' => false,
            'password' => bcrypt('password'),
        ]);

        foreach (['projects.view', 'projects.approve_department', 'projects.reject'] as $ability) {
            $user->roles()->create([
                'role_name' => $ability,
                'ability' => 'allow',
            ]);
        }

        return Person::create([
            'name' => $user->name,
            'role' => 'department_manager',
            'department_id' => $departmentId,
            'user_id' => $user->id,
            'job_title' => 'مدير دائرة اختبار',
        ]);
    }

    /** @return array<string, mixed> */
    private function sampleProjectPostData(array $overrides = []): array
    {
        return array_merge($this->sampleProjectFields($overrides), [
            'allocation_image' => UploadedFile::fake()->image('allocation.jpg'),
        ]);
    }

    /** @return array<int, array{value: string, person_name?: string}> */
    private function fullChecklist(string $value = 'ready', bool $deferClosureDocs = true): array
    {
        $checklist = [];

        foreach (ChecklistItem::where('is_active', true)->get() as $item) {
            $itemValue = ($deferClosureDocs && $item->has_file_field) ? 'not_ready' : $value;
            $entry = ['value' => $itemValue];

            if ($item->has_person_field && in_array($itemValue, ['ready', 'partial'], true)) {
                $entry['person_name'] = 'شخص تجريبي';
            }

            $checklist[$item->id] = $entry;
        }

        return $checklist;
    }

    /** @return array<string, mixed> */
    private function closureDocsFormData(string $value = 'ready', bool $withFiles = true): array
    {
        $data = ['closure_docs' => []];

        foreach (Project::closureDocumentItemIds() as $itemId) {
            $entry = ['value' => $value];

            if ($value === 'ready') {
                $entry['person_name'] = 'شخص إغلاق';

                if ($withFiles) {
                    $entry['attachment'] = UploadedFile::fake()->create("closure-{$itemId}.pdf", 100, 'application/pdf');
                }
            }

            $data['closure_docs'][$itemId] = $entry;
        }

        return $data;
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
        $section = Section::findOrFail($this->sampleProjectFields()['section_id']);
        $this->alignPersonToSection($pm, $section);
        if ($coordinator) {
            $this->alignPersonToSection($coordinator, $section);
        }
        $monitor = Person::withRole('monitor')->first() ?? Person::skip(2)->first() ?? $coordinator;
        $center = Center::first();
        $department = Department::where('center_id', $center->id)->first();

        $projectName = 'مشروع اختبار شامل ' . uniqid();

        // 1) create draft
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $projectName,
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
        ]))->assertRedirect();

        $project = Project::where('project_name', $projectName)->firstOrFail();
        $this->assertSame('draft', $project->workflow_status);
        $this->assertMatchesRegularExpression('/^P-\d+$/', (string) $project->project_number);
        $this->assertStringStartsWith('projects/' . $project->project_number . '/', (string) $project->allocation_image_path);
        $this->assertStringContainsString('allocation.', (string) $project->allocation_image_path);

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
        $this->assertNotNull($project->coordinator_readiness_pct);
        $this->assertLessThan(100.0, (float) $project->coordinator_readiness_pct);

        // 4) submit to project manager, section manager, dept manager, approve
        $this->post(route('dashboard.projects.submit-to-project-manager', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame('pending_project_manager', $project->workflow_status);

        $this->post(route('dashboard.projects.submit-to-section-manager', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame('pending_section_manager', $project->workflow_status);

        $this->post(route('dashboard.projects.approve-section', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame('pending_dept_manager', $project->workflow_status);
        $this->assertNotNull($project->section_manager_approved_at);

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
        $this->assertSame('2026-02-01', $project->monitoring_date?->format('Y-m-d'));

        $activity = MonitoringActivity::find($project->primary_monitoring_activity_id);
        $this->assertMatchesRegularExpression('/^MP-\d+$/', (string) $activity->reference_code);

        // 6) monitor-work isolated screen
        $this->get(route('dashboard.projects.monitor-work', $project))->assertStatus(200);

        $checklistMonitor = $this->fullChecklist('partial', false);
        $this->post(route('dashboard.projects.fill-monitor', $project), [
            'checklist' => $checklistMonitor,
            'monitor_notes_text' => "ملاحظة إيجابية 1\nملاحظة إيجابية 2",
            'monitor_negative_notes_text' => "ملاحظة سلبية 1",
        ])->assertRedirect();

        $project->refresh();
        $activity->refresh();
        $this->assertEquals(50.0, (float) $project->monitor_readiness_pct);
        $this->assertEquals(50.0, (float) $activity->execution_value);
        $this->assertSame(['ملاحظة إيجابية 1', 'ملاحظة إيجابية 2'], $project->monitor_notes);
        $this->assertSame(['ملاحظة سلبية 1'], $project->monitor_negative_notes);
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

        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => 'مشروع منسق خارجي',
            'project_number_seq' => $this->nextProjectNumberSeq(),
            'coordinator_mode' => 'external',
            'coordinator_external_name' => 'منسق خارجي تجريبي',
        ]))->assertRedirect();

        $externalProject = Project::where('project_name', 'مشروع منسق خارجي')->firstOrFail();
        $this->assertSame('external', $externalProject->coordinatorMode());

        $this->post('/projects', $this->sampleProjectPostData([
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

        $this->put(route('dashboard.projects.update', $selfProject), $this->sampleProjectPostData([
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

        $this->post('/projects', $this->sampleProjectPostData([
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

        $this->post('/projects', $this->sampleProjectPostData([
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

        $this->post(route('dashboard.projects.submit-to-section-manager', $project))
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('pending_section_manager', $project->workflow_status);

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
        $deptManager = $this->ensureDepartmentManagerForDepartment((int) $pm->department_id);

        $otherDeptManager = Person::where('role', 'department_manager')
            ->where('department_id', '!=', $pm->department_id)
            ->whereNotNull('user_id')
            ->first();

        if (! $otherDeptManager) {
            $otherDepartment = Department::where('id', '!=', $pm->department_id)->firstOrFail();
            $otherDeptManager = $this->ensureDepartmentManagerForDepartment((int) $otherDepartment->id);
        }

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
        $deptManager = $this->ensureDepartmentManagerForDepartment((int) $pm->department_id);

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

    public function test_section_manager_sees_only_section_projects(): void
    {
        $section = Section::firstOrFail();
        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $this->alignPersonToSection($pm, $section);
        $sectionManager = $this->ensureSectionManagerForSection($section);

        $otherSection = Section::where('id', '!=', $section->id)->firstOrFail();

        $visibleProject = Project::create([
            'project_name' => 'مشروع قسم مدير القسم',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(20000, 29999)),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'center_id' => $section->department?->center_id,
            'department_id' => $section->department_id,
            'section_id' => $section->id,
            'workflow_status' => 'pending_section_manager',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $hiddenProject = Project::create([
            'project_name' => 'مشروع قسم آخر',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(30000, 39999)),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'center_id' => $otherSection->department?->center_id,
            'department_id' => $otherSection->department_id,
            'section_id' => $otherSection->id,
            'workflow_status' => 'pending_section_manager',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $this->actingAs($sectionManager->user);
        $this->get('/projects')->assertOk()->assertSee('projects-table');

        $this->getJson('/projects', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonFragment(['project_name' => 'مشروع قسم مدير القسم'])
            ->assertJsonMissing(['project_name' => 'مشروع قسم آخر']);

        $this->get(route('dashboard.projects.show', $visibleProject))->assertOk();
        $this->get(route('dashboard.projects.show', $hiddenProject))->assertForbidden();

        $visibleProject->delete();
        $hiddenProject->delete();
    }

    public function test_section_manager_people_scoped_create_update(): void
    {
        $section = Section::firstOrFail();
        $sectionManager = $this->ensureSectionManagerForSection($section);
        $otherSection = Section::where('id', '!=', $section->id)->firstOrFail();
        $otherPerson = Person::create([
            'name' => 'شخص خارج القسم',
            'role' => 'coordinator',
            'section_id' => $otherSection->id,
            'department_id' => $otherSection->department_id,
        ]);

        $this->actingAs($sectionManager->user);

        $this->post(route('dashboard.people.store'), [
            'name' => 'منسق جديد في القسم',
            'role' => 'coordinator',
            'section_id' => $section->id,
            'department_id' => $section->department_id,
            'center_id' => $section->department?->center_id,
        ])->assertRedirect(route('dashboard.people.index'));

        $this->assertDatabaseHas('people', [
            'name' => 'منسق جديد في القسم',
            'role' => 'coordinator',
            'section_id' => $section->id,
        ]);

        $created = Person::where('name', 'منسق جديد في القسم')->firstOrFail();

        $this->get(route('dashboard.people.edit', $otherPerson))->assertForbidden();

        $this->put(route('dashboard.people.update', $created), [
            'role' => 'project_manager',
            'job_title' => 'مدير مشروع محدّث',
            'phone' => '0599000001',
        ])->assertRedirect(route('dashboard.people.index'));

        $created->refresh();
        $this->assertSame('project_manager', $created->role);
        $this->assertSame('منسق جديد في القسم', $created->name);
        $this->assertSame('مدير مشروع محدّث', $created->job_title);
        $this->assertSame('0599000001', $created->phone);

        $this->get(route('dashboard.people.edit', $sectionManager))
            ->assertRedirect(route('dashboard.profile.settings'));

        $created->delete();
        $otherPerson->delete();
    }

    public function test_section_manager_reject_options(): void
    {
        $section = Section::firstOrFail();
        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $this->alignPersonToSection($pm, $section);
        $sectionManager = $this->ensureSectionManagerForSection($section);

        $project = Project::create([
            'project_name' => 'مشروع رفض مدير قسم',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + 700),
            'project_manager_id' => $pm->id,
            'coordinator_id' => $pm->id,
            'section_id' => $section->id,
            'department_id' => $section->department_id,
            'workflow_status' => 'pending_section_manager',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]);

        $this->actingAs($sectionManager->user);

        $this->post(route('dashboard.projects.reject', $project), [
            'rejection_reason' => 'نقص في المستندات',
            'gap_owner' => 'section_manager',
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

    public function test_project_manager_must_belong_to_project_section(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $fields = $this->sampleProjectFields();
        $section = Section::findOrFail($fields['section_id']);
        $otherSection = Section::where('id', '!=', $section->id)->firstOrFail();

        $wrongPm = Person::create([
            'name' => 'مدير مشروع قسم خاطئ',
            'role' => 'project_manager',
            'section_id' => $otherSection->id,
            'department_id' => $otherSection->department_id,
        ]);

        $response = $this->from('/projects/create')
            ->post('/projects', $this->sampleProjectPostData([
                'project_name' => 'مشروع قسم خاطئ ' . uniqid(),
                'project_number_seq' => $this->nextProjectNumberSeq() + 45000,
                'project_manager_id' => $wrongPm->id,
                'section_id' => $section->id,
                'department_id' => $section->department_id,
                'center_id' => $section->department?->center_id,
                'coordinator_mode' => 'self',
            ]));

        $response->assertSessionHasErrors('project_manager_id');
        $this->assertDatabaseMissing('projects', ['project_manager_id' => $wrongPm->id]);

        $wrongPm->delete();
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
        $this->put(route('dashboard.projects.update', $project), $this->sampleProjectPostData([
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
                'execution_start_date',
                'allocation_image',
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
        $this->post('/projects', $this->sampleProjectPostData([
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
        $deptManager = $this->ensureDepartmentManagerForDepartment((int) $pm->department_id);

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

    public function test_monitoring_director_sees_merged_checklist(): void
    {
        $director = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->first();
        $this->assertNotNull($director);

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $coordinator = Person::withRole('coordinator')->first() ?? Person::skip(1)->first();
        $monitor = Person::withRole('monitor')->first() ?? Person::skip(2)->first() ?? $coordinator;

        $project = Project::create(array_merge($this->sampleProjectFields(), [
            'project_name' => 'مشروع دمج قائمة التحقق',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(20000, 29999)),
            'coordinator_id' => $coordinator->id,
            'monitor_person_id' => $monitor->id,
            'workflow_status' => 'pending_monitoring_confirmation',
            'coordinator_readiness_pct' => 100,
            'monitor_readiness_pct' => 80,
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]));

        $this->actingAs($director->user);
        $this->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertSee('قائمة التحقق — المنسق والمراقب')
            ->assertDontSee('قائمة التحقق — عمود المنسق')
            ->assertDontSee('قائمة التحقق — عمود المراقب');

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_checklist_person_name_required_when_ready_or_partial(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $personFieldItem = ChecklistItem::where('is_active', true)
            ->where('has_person_field', true)
            ->first();

        $this->assertNotNull($personFieldItem, 'Expected at least one checklist item with person field.');

        $pm = Person::withRole('project_manager')->first() ?? Person::first();
        $coordinator = Person::withRole('coordinator')->first() ?? Person::skip(1)->first();

        $project = Project::create(array_merge($this->sampleProjectFields(), [
            'project_name' => 'مشروع التحقق من اسم الشخص',
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(30000, 39999)),
            'coordinator_id' => $coordinator->id,
            'workflow_status' => 'coordinator_filling',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));

        $checklist = $this->fullChecklist('ready');
        unset($checklist[$personFieldItem->id]['person_name']);

        $this->from(route('dashboard.projects.show', $project))
            ->post(route('dashboard.projects.fill-coordinator', $project), [
                'checklist' => $checklist,
            ])
            ->assertRedirect(route('dashboard.projects.show', $project))
            ->assertSessionHasErrors("checklist.{$personFieldItem->id}.person_name");

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_project_storage_folder_renames_when_number_changes(): void
    {
        Storage::fake('public');

        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $projectName = 'مشروع نقل مجلد التخزين ' . uniqid();

        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $projectName,
            'project_number_seq' => 77001,
            'coordinator_mode' => 'self',
        ]))->assertRedirect();

        $project = Project::where('project_name', $projectName)->firstOrFail();
        $this->assertSame('P-77001', $project->project_number);

        $oldPath = $project->allocation_image_path;
        Storage::disk('public')->assertExists($oldPath);
        Storage::disk('public')->assertExists('projects/P-77001');

        $this->put(route('dashboard.projects.update', $project), array_merge($this->sampleProjectFields([
            'project_name' => $projectName,
            'project_number_seq' => 77002,
            'coordinator_mode' => 'self',
        ]), [
            'coordinator_id' => $project->coordinator_id,
        ]))->assertRedirect(route('dashboard.projects.show', $project));

        $project->refresh();
        $this->assertSame('P-77002', $project->project_number);
        $this->assertStringStartsWith('projects/P-77002/', (string) $project->allocation_image_path);
        Storage::disk('public')->assertExists($project->allocation_image_path);
        Storage::disk('public')->assertMissing('projects/P-77001');
        Storage::disk('public')->assertMissing($oldPath);

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_closure_docs_upload_increases_readiness(): void
    {
        Storage::fake('public');

        $coordinator = Person::where('role', 'coordinator')->whereNotNull('user_id')->first();
        $this->assertNotNull($coordinator);

        $project = Project::create(array_merge($this->sampleProjectFields(), [
            'project_name' => 'مشروع مستندات إغلاق ' . uniqid(),
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(40000, 49999)),
            'coordinator_id' => $coordinator->id,
            'workflow_status' => 'monitoring_in_progress',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]));

        foreach ($this->fullChecklist('ready') as $itemId => $entry) {
            ProjectChecklistValue::updateOrCreate(
                ['project_id' => $project->id, 'checklist_item_id' => $itemId],
                ['coordinator_value' => $entry['value'], 'person_name' => $entry['person_name'] ?? null]
            );
        }

        $project->recalculateReadiness();
        $beforePct = (float) $project->fresh()->coordinator_readiness_pct;

        $this->actingAs($coordinator->user);
        $this->post(route('dashboard.projects.fill-closure-docs', $project), $this->closureDocsFormData())
            ->assertRedirect();

        $project->refresh();
        $this->assertGreaterThan($beforePct, (float) $project->coordinator_readiness_pct);

        foreach (Project::closureDocumentItemIds() as $itemId) {
            $row = ProjectChecklistValue::where('project_id', $project->id)
                ->where('checklist_item_id', $itemId)
                ->first();
            $this->assertSame('ready', $row->coordinator_value);
            $this->assertNotNull($row->attachment_path);
            Storage::disk('public')->assertExists($row->attachment_path);
        }

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_closure_docs_late_upload_uses_late_score(): void
    {
        Storage::fake('public');

        $coordinator = Person::where('role', 'coordinator')->whereNotNull('user_id')->first();
        $this->assertNotNull($coordinator);

        $project = Project::create(array_merge($this->sampleProjectFields([
            'planned_end_date' => '2020-01-01',
        ]), [
            'project_name' => 'مشروع تأخر إغلاق ' . uniqid(),
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(50000, 59999)),
            'coordinator_id' => $coordinator->id,
            'workflow_status' => 'passage_complete',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]));

        foreach (ChecklistItem::where('is_active', true)->get() as $item) {
            ProjectChecklistValue::updateOrCreate(
                ['project_id' => $project->id, 'checklist_item_id' => $item->id],
                [
                    'coordinator_value' => $item->has_file_field ? 'not_ready' : 'ready',
                    'person_name' => $item->has_person_field && ! $item->has_file_field ? 'شخص' : null,
                ]
            );
        }

        $this->actingAs($coordinator->user);
        $this->post(route('dashboard.projects.fill-closure-docs', $project), $this->closureDocsFormData())
            ->assertRedirect();

        $project->refresh();
        $lateScore = Project::closureLateScore();
        $totalHr = ChecklistItem::whereHas('group', fn ($q) => $q->where('name', 'الموارد البشرية'))->where('is_active', true)->count();
        $this->assertGreaterThan(0, $totalHr);

        $expectedHrWeight = (4 * 1.0) + (3 * $lateScore);
        $expectedHrPct = round(($expectedHrWeight / $totalHr) * 100, 2);
        $breakdown = $project->readinessBreakdown();
        $hrRow = collect($breakdown['groups'])->firstWhere('name', 'الموارد البشرية');
        $this->assertNotNull($hrRow);
        $this->assertEqualsWithDelta($expectedHrPct, (float) $hrRow['coordinator_pct'], 0.01);

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_section_manager_sees_closure_docs_but_monitor_does_not(): void
    {
        Storage::fake('public');

        $section = Section::findOrFail($this->sampleProjectFields()['section_id']);
        $sectionManager = $this->ensureSectionManagerForSection($section);
        $coordinator = Person::withRole('coordinator')->whereNotNull('user_id')->first();
        $monitor = Person::withRole('monitor')->whereNotNull('user_id')->first();
        $this->assertNotNull($coordinator);
        $this->assertNotNull($monitor);

        $project = Project::create(array_merge($this->sampleProjectFields(), [
            'project_name' => 'مشروع رؤية مستندات ' . uniqid(),
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(60000, 69999)),
            'coordinator_id' => $coordinator->id,
            'monitor_person_id' => $monitor->id,
            'workflow_status' => 'monitoring_in_progress',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]));

        $this->actingAs($coordinator->user);
        $this->post(route('dashboard.projects.fill-closure-docs', $project), $this->closureDocsFormData())
            ->assertRedirect();

        $attachmentName = ProjectChecklistValue::where('project_id', $project->id)
            ->whereNotNull('attachment_original_name')
            ->value('attachment_original_name');
        $this->assertNotNull($attachmentName);

        $this->actingAs($sectionManager->user);
        $this->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertSee('مستندات الإغلاق — الموارد البشرية')
            ->assertSee($attachmentName);

        $this->actingAs($monitor->user);
        $this->get(route('dashboard.projects.monitor-work', $project))
            ->assertOk()
            ->assertDontSee('مستندات الإغلاق — الموارد البشرية')
            ->assertDontSee($attachmentName);

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

    public function test_project_index_shows_closure_docs_attachment_count(): void
    {
        Storage::fake('public');

        $coordinator = Person::where('role', 'coordinator')->whereNotNull('user_id')->first();
        $this->assertNotNull($coordinator);

        $project = Project::create(array_merge($this->sampleProjectFields(), [
            'project_name' => 'مشروع فهرس مستندات ' . uniqid(),
            'project_number' => 'P-' . ($this->nextProjectNumberSeq() + random_int(60000, 69999)),
            'coordinator_id' => $coordinator->id,
            'workflow_status' => 'monitoring_in_progress',
            'created_by' => User::first()->id,
            'updated_by' => User::first()->id,
        ]));

        $summary = $project->fresh()->closureAttachmentSummary();
        $this->assertSame(3, $summary['total']);
        $this->assertSame(0, $summary['attached']);
        $this->assertFalse($summary['complete']);
        $this->assertSame('0/3', $summary['label']);

        $this->actingAs($coordinator->user);
        $this->post(route('dashboard.projects.fill-closure-docs', $project), $this->closureDocsFormData())
            ->assertRedirect();

        $project->refresh();
        $completeSummary = $project->closureAttachmentSummary();
        $this->assertSame(3, $completeSummary['attached']);
        $this->assertTrue($completeSummary['complete']);
        $this->assertSame('مكتمل', $completeSummary['label']);

        $response = $this->getJson(route('dashboard.projects.index'), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
        $rows = collect($response->json('data'));
        $row = $rows->firstWhere('id', $project->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['closure_docs_complete']);
        $this->assertSame('مكتمل', $row['closure_docs_label']);
        $this->assertSame(3, $row['closure_docs_attached']);

        ProjectChecklistValue::where('project_id', $project->id)->delete();
        $project->delete();
    }

}
