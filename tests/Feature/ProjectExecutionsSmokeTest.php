<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Project;
use App\Models\RoleUser;
use App\Models\User;

class ProjectExecutionsSmokeTest extends ProjectsSmokeTest
{
    public function test_multi_region_project_spawns_independent_executions(): void
    {
        $user = User::first();
        $user->super_admin = 1;
        $this->actingAs($user);

        $name = 'مشروع متعدد المناطق ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $this->assertSame(2, $project->executions()->count());
        $this->assertSame('executions_in_progress', $project->workflow_status);

        $executions = $project->executions()->orderBy('sort_order')->get();
        $this->assertSame('pending_coordinator', $executions[0]->workflow_status);
        $this->assertSame('pending_coordinator', $executions[1]->workflow_status);
        $this->assertTrue($executions[1]->isSelfCoordinator());

        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $executions[0])->assertRedirect();
        $this->postApproveSection($project, $executions[0])->assertRedirect();
        $this->postApproveDepartment($project, $executions[0])->assertRedirect();

        $monitor = Person::withRole('monitor')->firstOrFail();
        $this->postAssignMonitor($project, ['monitor_person_id' => $monitor->id], $executions[0])->assertRedirect();
        $this->postFillMonitor($project, ['checklist' => $this->fullChecklist('ready', false)], $executions[0])->assertRedirect();
        $this->postConfirmPassage($project, $executions[0])->assertRedirect();

        $project->refresh();
        $this->assertSame('executions_in_progress', $project->workflow_status);
        $this->assertSame('passage_complete', $executions[0]->fresh()->workflow_status);
        $this->assertNotSame('passage_complete', $executions[1]->fresh()->workflow_status);

        $project->executions()->delete();
        $project->delete();
    }

    public function test_multi_region_mixed_coordinators_spawn_and_visibility(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $coordinator = Person::withRole('coordinator')->whereNotNull('user_id')->firstOrFail();
        $coordUser = User::findOrFail($coordinator->user_id);
        $coordUser->update(['super_admin' => false]);
        $coordUser->update(['phone' => '0599' . str_pad((string) $coordUser->id, 7, '0', STR_PAD_LEFT)]);
        $coordUser->person?->update(['phone' => $coordUser->phone]);

        RoleUser::where('user_id', $coordUser->id)->delete();
        foreach (['projects.view', 'projects.fill_coordinator', 'projectexecutions.view', 'projectexecutions.fill_coordinator'] as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $coordUser->id,
                'ability' => 'allow',
            ]);
        }

        $pm = Person::withRole('project_manager')->firstOrFail();
        $fields = $this->sampleProjectFields();
        $regions = [
            array_merge($fields['execution_regions'][0], [
                'coordinator_mode' => 'person',
                'coordinator_id' => $coordinator->id,
            ]),
            array_merge($fields['execution_regions'][1], [
                'coordinator_mode' => 'self',
            ]),
        ];

        $name = 'مشروع منسقين مختلط ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'project_manager_id' => $pm->id,
            'execution_regions' => $regions,
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $executions = $project->executions()->orderBy('sort_order')->get();
        $this->assertCount(2, $executions);
        $this->assertSame((int) $coordinator->id, (int) $executions[0]->coordinator_id);
        $this->assertSame('pending_coordinator', $executions[0]->workflow_status);
        $this->assertSame((int) $pm->id, (int) $executions[1]->coordinator_id);
        $this->assertSame('pending_coordinator', $executions[1]->workflow_status);
        $this->assertTrue($executions[1]->isSelfCoordinator());

        $this->actingAs($coordUser->fresh());
        $this->get(route('dashboard.projects.executions.show', [$project, $executions[0]]))
            ->assertOk()
            ->assertSee('قائمة التحقق — عمود المنسق', false);
        $this->get(route('dashboard.projects.executions.show', [$project, $executions[1]]))
            ->assertForbidden();

        $pmUser = $pm->user_id ? User::findOrFail($pm->user_id) : User::first();
        $pmUser->update(['super_admin' => false]);
        $this->actingAs($pmUser->fresh());
        $this->get(route('dashboard.projects.executions.show', [$project, $executions[1]]))
            ->assertOk();

        $project->executions()->delete();
        $project->delete();
    }

    public function test_coordinator_can_access_assigned_execution_via_role_abilities(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $coordinator = Person::withRole('coordinator')->whereNotNull('user_id')->firstOrFail();
        $coordUser = User::findOrFail($coordinator->user_id);

        RoleUser::where('user_id', $coordUser->id)->delete();
        foreach (['projects.view', 'projects.fill_coordinator'] as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $coordUser->id,
                'ability' => 'allow',
            ]);
        }

        $coordUser->update(['super_admin' => false]);
        $coordUser->update(['phone' => '0599' . str_pad((string) $coordUser->id, 7, '0', STR_PAD_LEFT)]);
        $coordUser->person?->update(['phone' => $coordUser->phone]);

        $pm = Person::withRole('project_manager')->firstOrFail();
        $otherCoordinator = Person::withRole('coordinator')->where('id', '!=', $coordinator->id)->firstOrFail();

        $name = 'مشروع صلاحيات منسق ' . uniqid();
        $fields = $this->sampleProjectFields();

        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 2,
            'execution_regions' => $fields['execution_regions'],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $executions = $project->executions()->orderBy('sort_order')->get();
        $this->assertGreaterThanOrEqual(2, $executions->count());

        $executions[0]->update(['coordinator_id' => $coordinator->id]);
        $executions[1]->update(['coordinator_id' => $otherCoordinator->id]);

        $this->actingAs($coordUser->fresh());

        $this->assertTrue($coordUser->can('view', \App\Models\ProjectExecution::class));
        $this->assertTrue($coordUser->can('fill_coordinator', \App\Models\ProjectExecution::class));

        $this->get(route('dashboard.projects.executions.show', [$project, $executions[0]]))
            ->assertOk()
            ->assertSee('قائمة التحقق — عمود المنسق', false);

        $this->get(route('dashboard.projects.executions.show', [$project, $executions[1]]))
            ->assertForbidden();

        $this->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertSee($executions[0]->region_name, false)
            ->assertDontSee($executions[1]->region_name, false);

        $this->get(route('dashboard.home'))
            ->assertOk()
            ->assertSee('مساراتي كمنسق', false)
            ->assertSee('يتطلب إجراءك — المسارات', false);

        $project->executions()->delete();
        $project->delete();
    }

    public function test_coordinator_can_upload_closure_docs_after_execution_submit(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $coordinator = Person::withRole('coordinator')->whereNotNull('user_id')->firstOrFail();
        $coordUser = User::findOrFail($coordinator->user_id);
        $coordUser->update(['super_admin' => false]);
        $coordUser->update(['phone' => '0599' . str_pad((string) $coordUser->id, 7, '0', STR_PAD_LEFT)]);
        $coordUser->person?->update(['phone' => $coordUser->phone]);

        $name = 'مشروع مستندات مسار ' . uniqid();
        $fields = $this->sampleProjectFields();

        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $execution = $project->executions()->firstOrFail();
        $execution->update(['coordinator_id' => $coordinator->id]);

        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $execution)->assertRedirect();

        $execution->refresh();
        $this->assertSame('pending_section_manager', $execution->workflow_status);

        $this->actingAs($coordUser->fresh());

        $this->get(route('dashboard.projects.executions.show', [$project, $execution]))
            ->assertOk()
            ->assertSee('حفظ مستندات الإغلاق', false)
            ->assertSee('checklist-file-upload-btn', false);

        $closureData = ['closure_docs' => []];
        foreach (Project::closureDocumentItemIds() as $itemId) {
            $closureData['closure_docs'][$itemId] = [
                'value' => 'ready',
                'person_name' => 'شخص إغلاق',
                'attachment_type' => 'url',
                'attachment_url' => 'https://docs.example.com/execution-closure-' . $itemId,
            ];
        }

        $this->post(route('dashboard.projects.executions.fill-closure-docs', [$project, $execution]), $closureData)
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach (Project::closureDocumentItemIds() as $itemId) {
            $row = \App\Models\ProjectChecklistValue::query()
                ->where('project_id', $project->id)
                ->where('project_execution_id', $execution->id)
                ->where('checklist_item_id', $itemId)
                ->first();
            $this->assertSame('ready', $row->coordinator_value);
            $this->assertTrue($row->hasAttachment());
        }

        $project->executions()->delete();
        $project->delete();
    }

    public function test_monitoring_director_sees_execution_actions_on_home_and_setup_panel(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $director = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->firstOrFail();
        $directorUser = User::findOrFail($director->user_id);
        $directorUser->update(['super_admin' => false]);

        $fields = $this->sampleProjectFields();
        $coordinator = Person::withRole('coordinator')->firstOrFail();

        $name = 'مسار مدير الرقابة ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $execution = $this->primaryExecution($project);
        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $execution);
        $this->postApproveSection($project, $execution)->assertRedirect();
        $this->postApproveDepartment($project, $execution)->assertRedirect();

        $execution->refresh();
        $this->assertSame('pending_monitoring_manager', $execution->workflow_status);

        $this->actingAs($directorUser);

        $this->get(route('dashboard.home'))
            ->assertOk()
            ->assertSee('يتطلب إجراءك الآن')
            ->assertSee('مسارات التنفيذ')
            ->assertSee($execution->region_name)
            ->assertSee('بانتظار مدير الرقابة العامة');

        $this->get(route('dashboard.projects.executions.show', [$project, $execution]))
            ->assertOk()
            ->assertSee('إعداد المراقبة')
            ->assertSee('طريقة المراقبة')
            ->assertSee('مرحلة المراقبة')
            ->assertSee('تاريخ المراقبة')
            ->assertSee('حفظ وبدء المراقبة')
            ->assertSee('رفض المسار')
            ->assertDontSee('حفظ طريقة/مرحلة المراقبة');

        $project->executions()->delete();
        $project->delete();
    }

    public function test_assign_monitor_requires_method_stage_and_date(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $fields = $this->sampleProjectFields();
        $coordinator = Person::withRole('coordinator')->firstOrFail();
        $monitor = Person::withRole('monitor')->firstOrFail();

        $name = 'تحقق إعداد المراقبة ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $execution = $this->primaryExecution($project);
        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $execution);
        $this->postApproveSection($project, $execution)->assertRedirect();
        $this->postApproveDepartment($project, $execution)->assertRedirect();

        $this->post(route('dashboard.projects.executions.assign-monitor', [$project, $execution]), [
            'monitor_person_id' => $monitor->id,
        ])->assertSessionHasErrors(['monitoring_method', 'monitoring_stage', 'monitoring_date']);

        $project->executions()->delete();
        $project->delete();
    }

    public function test_monitor_work_execution_shows_notes_activity_and_readiness(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $fields = $this->sampleProjectFields();
        $coordinator = Person::withRole('coordinator')->firstOrFail();
        $monitor = Person::withRole('monitor')->whereNotNull('user_id')->firstOrFail();
        $monitorUser = User::findOrFail($monitor->user_id);
        $monitorUser->update(['phone' => '0599' . str_pad((string) $monitorUser->id, 7, '0', STR_PAD_LEFT)]);
        $monitor->update(['phone' => $monitorUser->phone]);

        $name = 'شاشة عمل المراقب ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $execution = $this->primaryExecution($project);
        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $execution);
        $this->postApproveSection($project, $execution)->assertRedirect();
        $this->postApproveDepartment($project, $execution)->assertRedirect();

        $this->postAssignMonitor($project, [
            'monitor_person_id' => $monitor->id,
        ], $execution)->assertRedirect();

        $execution->refresh();
        $this->assertSame('monitoring_in_progress', $execution->workflow_status);

        $this->actingAs($monitorUser);

        $this->get(route('dashboard.projects.executions.monitor-work', [$project, $execution]))
            ->assertOk()
            ->assertSee('قائمة التحقق — عمود المراقب', false)
            ->assertSee('checklist-overall-pct', false)
            ->assertSee('الملاحظات الميدانية', false)
            ->assertSee('بيانات النشاط المتبقية', false)
            ->assertSee('حفظ وإرسال لمدير الرقابة العامة', false)
            ->assertSee('checklist-readiness.js', false);

        $pm = Person::withRole('project_manager')->firstOrFail();
        $this->postFillMonitor($project, [
            'checklist' => $this->fullChecklist('partial', false),
            'field_problem' => 0,
            'quality_value' => 50,
            'closure_value' => 60,
            'deduction_value' => 0,
            'responsible_person_id' => $pm->id,
            'activity_date' => '2026-07-14',
            'activity_type' => 'تفتيش ميداني',
            'subject' => 'موضوع اختبار',
        ], $execution)->assertRedirect();

        $activity = $execution->fresh()->primaryMonitoringActivity;
        $this->assertNotNull($activity);
        $this->assertSame(50.0, (float) $activity->quality_value);
        $this->assertSame(60.0, (float) $activity->closure_value);
        $this->assertNotNull($activity->kpi_value);
        $this->assertStringNotContainsString('الجودة', $activity->verification_status);

        $project->executions()->delete();
        $project->delete();
    }

    public function test_monitoring_director_home_shows_active_projects_and_pipeline_executions(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $director = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->firstOrFail();
        $directorUser = User::findOrFail($director->user_id);
        $directorUser->update(['super_admin' => false]);

        $fields = $this->sampleProjectFields();
        $coordinator = Person::withRole('coordinator')->firstOrFail();

        $name = 'لوحة مدير الرقابة ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $execution = $this->primaryExecution($project);
        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $execution);
        $this->postApproveSection($project, $execution)->assertRedirect();
        $this->postApproveDepartment($project, $execution)->assertRedirect();

        $this->actingAs($directorUser);

        $this->get(route('dashboard.home'))
            ->assertOk()
            ->assertSee('مسارات التنفيذ')
            ->assertSee('المشاريع النشطة')
            ->assertSee('home-panel-active-projects', false)
            ->assertSee('home-panel-pipeline-executions', false)
            ->assertSee('raqib-home-panel-toggle', false)
            ->assertSee($execution->region_name)
            ->assertSee($name);

        $this->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertSee('متابعة')
            ->assertSee($execution->region_name);

        $project->executions()->delete();
        $project->delete();
    }

    public function test_monitoring_director_sees_all_tracks_on_show_master_before_his_stage(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $director = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->firstOrFail();
        $directorUser = User::findOrFail($director->user_id);
        $directorUser->update(['super_admin' => false]);

        $fields = $this->sampleProjectFields();
        $coordinator = Person::withRole('coordinator')->firstOrFail();

        $name = 'مسارات قبل مرحلة الرقابة ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 2,
            'execution_regions' => $fields['execution_regions'],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $executions = $project->executions()->orderBy('sort_order')->get();
        $this->assertSame('pending_coordinator', $executions[0]->workflow_status);

        $this->actingAs($directorUser);

        $this->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertSee($executions[0]->region_name)
            ->assertSee($executions[1]->region_name)
            ->assertSee('متابعة', false);

        $project->executions()->delete();
        $project->delete();
    }

    public function test_monitoring_director_can_open_execution_readonly_before_his_stage(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $director = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->firstOrFail();
        $directorUser = User::findOrFail($director->user_id);
        $directorUser->update(['super_admin' => false]);

        $fields = $this->sampleProjectFields();
        $coordinator = Person::withRole('coordinator')->firstOrFail();

        $name = 'قراءة مسار قبل الرقابة ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);
        $execution = $this->primaryExecution($project);

        $this->actingAs($directorUser);

        $this->get(route('dashboard.projects.executions.show', [$project, $execution]))
            ->assertOk()
            ->assertSee('سير العمل — المسار')
            ->assertDontSee('حفظ وبدء المراقبة');

        $project->executions()->delete();
        $project->delete();
    }

    public function test_project_manager_cannot_see_monitoring_setup_on_execution(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $pmUser = User::where('username', 'demo_pm')->first();
        if (! $pmUser) {
            $pm = Person::withRole('project_manager')->whereNotNull('user_id')->firstOrFail();
            $pmUser = User::findOrFail($pm->user_id);
        }
        $pmUser->update(['super_admin' => false]);
        RoleUser::where('user_id', $pmUser->id)->delete();
        foreach (\Database\Seeders\SimpleDemoUsersSeeder::DEMO_PM_ABILITIES as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $pmUser->id,
                'ability' => 'allow',
            ]);
        }

        RoleUser::create([
            'role_name' => 'monitoringactivities.assign_monitor',
            'user_id' => $pmUser->id,
            'ability' => 'allow',
        ]);

        $fields = $this->sampleProjectFields();
        $coordinator = Person::withRole('coordinator')->firstOrFail();
        $pm = $pmUser->person ?? Person::where('user_id', $pmUser->id)->firstOrFail();

        $name = 'PM لا يرى إعداد المراقبة ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);
        $execution = $this->primaryExecution($project);
        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $execution);
        $this->postApproveSection($project, $execution)->assertRedirect();
        $this->postApproveDepartment($project, $execution)->assertRedirect();

        $execution->refresh();
        $this->assertSame('pending_monitoring_manager', $execution->workflow_status);

        $this->actingAs($pmUser->fresh());

        $this->get(route('dashboard.projects.executions.show', [$project, $execution]))
            ->assertOk()
            ->assertDontSee('حفظ وبدء المراقبة')
            ->assertDontSee('إعداد المراقبة —');

        $this->post(route('dashboard.projects.executions.assign-monitor', [$project, $execution]), [
            'monitoring_method' => 'زيارة ميدانية',
            'monitoring_stage' => 'مرحلة التنفيذ',
            'monitor_person_id' => Person::withRole('monitor')->firstOrFail()->id,
            'monitoring_date' => now()->format('Y-m-d'),
        ])->assertForbidden();

        $project->executions()->delete();
        $project->delete();
    }

    public function test_show_master_auto_syncs_missing_execution_tracks_for_monitoring_director(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $director = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->firstOrFail();
        $directorUser = User::findOrFail($director->user_id);
        $directorUser->update(['super_admin' => false]);

        $fields = $this->sampleProjectFields();
        $coordinator = Person::withRole('coordinator')->firstOrFail();
        $pm = Person::withRole('project_manager')->firstOrFail();

        $name = 'إصلاح مسارات مفقودة ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 2,
            'execution_regions' => $fields['execution_regions'],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);

        $project->executions()->delete();
        $this->assertSame(0, $project->executions()->count());

        $this->actingAs($directorUser);

        $this->get(route('dashboard.projects.show', $project))
            ->assertOk()
            ->assertSee($fields['execution_regions'][0]['name'])
            ->assertSee($fields['execution_regions'][1]['name'])
            ->assertSee('متابعة', false);

        $this->assertSame(2, $project->fresh()->executions()->where('is_active', true)->count());

        $project->executions()->delete();
        $project->delete();
    }

    public function test_monitoring_director_abilities_do_not_grant_oversight_when_person_role_mismatched(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $director = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->firstOrFail();
        $directorUser = User::findOrFail($director->user_id);
        $directorUser->update(['super_admin' => false]);

        $director->update(['role' => 'coordinator']);
        app(\App\Services\UserRoleAbilitiesSync::class)->syncFromRole($directorUser, 'monitoring_director');

        try {
            $this->assertFalse($directorUser->fresh()->isMonitoringDirector());
            $this->assertFalse($directorUser->canOverseeExecutions());

            $fields = $this->sampleProjectFields();
            $coordinator = Person::withRole('coordinator')->where('id', '!=', $director->id)->firstOrFail();
            $pm = Person::withRole('project_manager')->firstOrFail();

            $name = 'دور منسق لا يمنح رؤية MD ' . uniqid();
            $this->post('/projects', $this->sampleProjectPostData([
                'project_name' => $name,
                'project_manager_id' => $pm->id,
                'coordinator_mode' => 'person',
                'coordinator_id' => $coordinator->id,
                'execution_zones' => 2,
                'execution_regions' => $fields['execution_regions'],
            ]))->assertRedirect();

            $project = Project::where('project_name', $name)->firstOrFail();
            $this->advanceProjectThroughSecretariat($project);

            $this->actingAs($directorUser->fresh());

            $this->get(route('dashboard.projects.show', $project))
                ->assertForbidden();

            $project->executions()->delete();
            $project->delete();
        } finally {
            $director->update(['role' => 'monitoring_director']);
            app(\App\Services\UserRoleAbilitiesSync::class)->syncFromRole($directorUser, 'monitoring_director');
        }
    }

    public function test_section_manager_can_reject_execution_from_show(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $section = \App\Models\Section::firstOrFail();
        $pm = Person::withRole('project_manager')->firstOrFail();
        $this->alignPersonToSection($pm, $section);
        $sectionManager = $this->ensureSectionManagerForSection($section);
        $coordinator = Person::withRole('coordinator')->firstOrFail();
        $fields = $this->sampleProjectFields();

        $name = 'رفض مسار مدير قسم ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);
        $execution = $this->primaryExecution($project);

        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $execution)->assertRedirect();

        $execution->refresh();
        $this->assertSame('pending_section_manager', $execution->workflow_status);

        $this->actingAs($sectionManager->user);

        $this->get(route('dashboard.projects.executions.show', [$project, $execution]))
            ->assertOk()
            ->assertSee('موافقة مدير القسم', false)
            ->assertSee('رفض المسار', false)
            ->assertSee('executionRejectModal', false);

        $this->post(route('dashboard.projects.executions.reject', [$project, $execution]), [
            'rejection_reason' => 'نقص في المستندات',
            'gap_owner' => 'project_manager',
            'return_target' => 'return_coordinator',
        ])->assertRedirect();

        $execution->refresh();
        $this->assertSame('pending_coordinator', $execution->workflow_status);
        $this->assertSame('return_coordinator', $execution->return_target);

        $project->executions()->delete();
        $project->delete();
    }

    public function test_department_manager_can_reject_execution_to_section_manager(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $pm = Person::withRole('project_manager')->firstOrFail();
        $deptManager = $this->ensureDepartmentManagerForDepartment((int) $pm->department_id);
        $coordinator = Person::withRole('coordinator')->firstOrFail();
        $fields = $this->sampleProjectFields();

        $name = 'رفض مسار مدير دائرة ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'project_manager_id' => $pm->id,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);
        $execution = $this->primaryExecution($project);

        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $execution);
        $this->postApproveSection($project, $execution)->assertRedirect();

        $execution->refresh();
        $this->assertSame('pending_dept_manager', $execution->workflow_status);

        $this->actingAs($deptManager->user);

        $this->get(route('dashboard.projects.executions.show', [$project, $execution]))
            ->assertOk()
            ->assertSee('موافقة مدير الدائرة', false)
            ->assertSee('رفض المسار', false);

        $this->post(route('dashboard.projects.executions.reject', [$project, $execution]), [
            'rejection_reason' => 'نقص في المستندات',
            'gap_owner' => 'project_manager',
            'return_target' => 'return_section_manager',
        ])->assertRedirect();

        $execution->refresh();
        $this->assertSame('pending_section_manager', $execution->workflow_status);
        $this->assertSame('return_section_manager', $execution->return_target);

        $project->executions()->delete();
        $project->delete();
    }

    public function test_monitoring_director_sees_merged_checklist_on_execution(): void
    {
        $admin = User::first();
        $admin->super_admin = 1;
        $this->actingAs($admin);

        $director = Person::where('role', 'monitoring_director')->whereNotNull('user_id')->firstOrFail();
        $directorUser = User::findOrFail($director->user_id);
        $directorUser->update(['super_admin' => false]);

        $fields = $this->sampleProjectFields();
        $coordinator = Person::withRole('coordinator')->firstOrFail();
        $monitor = Person::withRole('monitor')->firstOrFail();

        $name = 'دمج checklist مسار ' . uniqid();
        $this->post('/projects', $this->sampleProjectPostData([
            'project_name' => $name,
            'coordinator_mode' => 'person',
            'coordinator_id' => $coordinator->id,
            'execution_zones' => 1,
            'execution_regions' => [$fields['execution_regions'][0]],
        ]))->assertRedirect();

        $project = Project::where('project_name', $name)->firstOrFail();
        $this->advanceProjectThroughSecretariat($project);
        $execution = $this->primaryExecution($project);

        $this->postFillCoordinator($project, ['checklist' => $this->fullChecklist()], $execution);
        $this->postApproveSection($project, $execution)->assertRedirect();
        $this->postApproveDepartment($project, $execution)->assertRedirect();
        $this->postAssignMonitor($project, ['monitor_person_id' => $monitor->id], $execution)->assertRedirect();
        $pm = Person::withRole('project_manager')->firstOrFail();
        $this->postFillMonitor($project, [
            'checklist' => $this->fullChecklist('ready', false),
            'field_problem' => 0,
            'quality_value' => 50,
            'closure_value' => 60,
            'deduction_value' => 0,
            'responsible_person_id' => $pm->id,
            'activity_date' => '2026-07-14',
            'activity_type' => 'تفتيش ميداني',
            'subject' => 'موضوع اختبار',
        ], $execution)
            ->assertRedirect()
            ->assertSessionHas('success');
        $execution->refresh();
        $this->assertSame('pending_monitoring_confirmation', $execution->workflow_status);

        $this->actingAs($directorUser);

        $this->get(route('dashboard.projects.executions.show', [$project, $execution]))
            ->assertOk()
            ->assertSee('قائمة التحقق — المنسق والمراقب', false)
            ->assertDontSee('قائمة التحقق — عمود المنسق', false)
            ->assertDontSee('قائمة التحقق — عمود المراقب', false);

        $project->executions()->delete();
        $project->delete();
    }
}
