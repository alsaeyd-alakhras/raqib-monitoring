<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\ChecklistItem;
use App\Models\Constant;
use App\Models\Currency;
use App\Models\Department;
use App\Models\Funder;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectChecklistValue;
use App\Models\ProjectExecution;
use App\Models\Section;
use App\Models\User;
use App\Services\Projects\ProjectAggregateStatusService;
use App\Services\Projects\ProjectExecutionSpawner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * مشاريع تجريبية ثابتة لاختبار كل دور يدوياً — لا تمس مشاريع P-* الموجودة.
 *
 * تشغيل: php artisan db:seed --class=DemoProjectsSeeder
 * يتطلب DemoUsersSeeder (يُستدعى تلقائياً إن لم يوجد mon_dir).
 */
class DemoProjectsSeeder extends Seeder
{
    private const DEMO_PREFIX = 'DEMO-';

    public function run(): void
    {
        if (! User::where('username', 'mon_dir')->exists()) {
            $this->command?->warn('DemoUsersSeeder غير موجود — جاري تشغيله...');
            $this->call(DemoUsersSeeder::class);
        }

        $ctx = $this->buildContext();
        if ($ctx === null) {
            $this->command?->error('تعذّر تجهيز سياق المشاريع (مركز/دائرة/ممول). شغّل RaqibMasterSeeder أولاً.');

            return;
        }

        $scenarios = $this->scenarios($ctx);
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($scenarios, &$created, &$skipped) {
            foreach ($scenarios as $scenario) {
                if (Project::where('project_number', $scenario['number'])->exists()) {
                    $skipped++;

                    continue;
                }

                $this->seedScenario($scenario);
                $created++;
            }
        });

        $this->command?->info("DemoProjectsSeeder: {$created} مشروع جديد، {$skipped} موجود مسبقاً (تخطّي).");
        $this->command?->line('  أرقام: ' . implode(', ', array_column($scenarios, 'number')));
        $this->command?->line('  كلمة مرور الحسابات: password');
    }

    /** @return array<string, mixed>|null */
    private function buildContext(): ?array
    {
        $center = Center::first();
        $department = Department::where('center_id', $center?->id)->first();
        $section = Section::where('department_id', $department?->id)->first();

        if (! $center || ! $department || ! $section) {
            return null;
        }

        $offices = json_decode((string) Constant::where('key', 'association_offices')->value('value'), true);
        if (! is_array($offices) || $offices === []) {
            $offices = ['مكتب غزة', 'مكتب خانيونس', 'مكتب رفح'];
        }

        $projectTypes = json_decode((string) Constant::where('key', 'project_types')->value('value'), true);
        $currency = Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'دولار', 'value' => 1, 'value_to_ils' => 3.70]
        );

        return [
            'center' => $center,
            'department' => $department,
            'section' => $section,
            'funder' => Funder::first() ?? Funder::create(['name' => 'ممول تجريبي']),
            'procurement' => Person::first(),
            'currency' => $currency,
            'project_type' => is_array($projectTypes) ? ($projectTypes[0] ?? 'إغاثي') : 'إغاثي',
            'offices' => $offices,
            'admin_id' => User::where('super_admin', true)->value('id') ?? User::value('id'),
            'pm' => $this->personByUsername('pm_ahmad'),
            'coord' => $this->personByUsername('coord_layla'),
            'monitor' => $this->personByUsername('monitor1'),
            'mon_dir' => $this->personByUsername('mon_dir'),
        ];
    }

    /** @param array<string, mixed> $ctx @return list<array<string, mixed>> */
    private function scenarios(array $ctx): array
    {
        $regions = [
            ['name' => $ctx['offices'][0], 'beneficiaries' => 100, 'execution_site' => 'موقع شمال'],
            ['name' => $ctx['offices'][1] ?? $ctx['offices'][0], 'beneficiaries' => 100, 'execution_site' => 'موقع جنوب'],
        ];

        return [
            [
                'number' => self::DEMO_PREFIX . '01',
                'name' => '[تجربة] مسودة — مدير مشروع',
                'project_status' => 'draft',
                'tracks' => false,
                'note' => 'pm_ahmad — إنشاء/تعديل/إرسال للسكرتاريا',
            ],
            [
                'number' => self::DEMO_PREFIX . '02',
                'name' => '[تجربة] بانتظار السكرتاريا',
                'project_status' => 'pending_secretariat',
                'tracks' => false,
                'note' => 'sec_hana — تعبئة الرقم والصورة',
            ],
            [
                'number' => self::DEMO_PREFIX . '03',
                'name' => '[تجربة] مسارات — منسق (منطقتان)',
                'project_status' => 'executions_in_progress',
                'tracks' => true,
                'regions' => $regions,
                'execution_statuses' => ['pending_coordinator', 'pending_coordinator'],
                'note' => 'coord_layla + mon_dir يرى الكل',
            ],
            [
                'number' => self::DEMO_PREFIX . '04',
                'name' => '[تجربة] مسار — مدير قسم',
                'project_status' => 'executions_in_progress',
                'tracks' => true,
                'regions' => [$regions[0]],
                'execution_statuses' => ['pending_section_manager'],
                'with_coordinator_checklist' => true,
                'note' => 'sm_projects — موافقة قسم',
            ],
            [
                'number' => self::DEMO_PREFIX . '05',
                'name' => '[تجربة] مسار — مدير دائرة',
                'project_status' => 'executions_in_progress',
                'tracks' => true,
                'regions' => [$regions[0]],
                'execution_statuses' => ['pending_dept_manager'],
                'with_coordinator_checklist' => true,
                'note' => 'dm_projects — موافقة دائرة',
            ],
            [
                'number' => self::DEMO_PREFIX . '06',
                'name' => '[تجربة] مسار — مدير الرقابة (تعيين مراقب)',
                'project_status' => 'executions_in_progress',
                'tracks' => true,
                'regions' => [$regions[0]],
                'execution_statuses' => ['pending_monitoring_manager'],
                'with_coordinator_checklist' => true,
                'note' => 'mon_dir — إعداد المراقبة',
            ],
            [
                'number' => self::DEMO_PREFIX . '07',
                'name' => '[تجربة] مسار — مراقب يعمل',
                'project_status' => 'executions_in_progress',
                'tracks' => true,
                'regions' => [$regions[0]],
                'execution_statuses' => ['monitoring_in_progress'],
                'with_coordinator_checklist' => true,
                'with_monitor' => true,
                'with_monitoring_activity' => true,
                'note' => 'monitor1 — تعبئة قائمة المراقب',
            ],
            [
                'number' => self::DEMO_PREFIX . '08',
                'name' => '[تجربة] مسار — تأكيد مرور',
                'project_status' => 'executions_in_progress',
                'tracks' => true,
                'regions' => [$regions[0]],
                'execution_statuses' => ['pending_monitoring_confirmation'],
                'with_coordinator_checklist' => true,
                'with_monitor' => true,
                'with_monitoring_activity' => true,
                'monitoring_activity_status' => 'pending_confirmation',
                'note' => 'mon_dir — تأكيد المرور',
            ],
            [
                'number' => self::DEMO_PREFIX . '09',
                'name' => '[تجربة] مسار — تم المرور',
                'project_status' => 'executions_in_progress',
                'tracks' => true,
                'regions' => [$regions[0]],
                'execution_statuses' => ['passage_complete'],
                'with_coordinator_checklist' => true,
                'with_monitor' => true,
                'with_monitoring_activity' => true,
                'monitoring_activity_status' => 'completed',
                'is_passage_complete' => true,
                'note' => 'عرض فقط — جميع الأدوار',
            ],
            [
                'number' => self::DEMO_PREFIX . '10',
                'name' => '[تجربة] مشروع واحد — مراقبة (بدون مسارات)',
                'project_status' => 'pending_monitoring_manager',
                'tracks' => false,
                'with_coordinator_checklist' => true,
                'note' => 'mon_dir — مشروع legacy بدون execution tracks',
            ],
        ];
    }

    /** @param array<string, mixed> $scenario */
    private function seedScenario(array $scenario): void
    {
        /** @var array<string, mixed> $ctx */
        $ctx = $this->buildContext() ?? [];

        $project = Project::create([
            'project_name' => $scenario['name'],
            'project_number' => $scenario['number'],
            'project_type' => $ctx['project_type'],
            'funder_id' => $ctx['funder']->id,
            'procurement_rep_id' => $ctx['procurement']?->id,
            'project_manager_id' => $ctx['pm']?->id,
            'coordinator_id' => $ctx['coord']?->id,
            'center_id' => $ctx['center']->id,
            'department_id' => $ctx['department']->id,
            'section_id' => $ctx['section']->id,
            'planned_start_date' => now()->toDateString(),
            'planned_end_date' => now()->addMonths(3)->toDateString(),
            'execution_start_date' => now()->toDateString(),
            'target_beneficiaries' => 200,
            'execution_zones' => count($scenario['regions'] ?? [1]),
            'execution_regions' => $this->regionsWithCoordinators(
                $scenario['regions'] ?? [
                    ['name' => $ctx['offices'][0], 'beneficiaries' => 200, 'execution_site' => 'موقع عام'],
                ],
                $ctx
            ),
            'estimated_duration' => '3 أشهر',
            'currency_id' => $ctx['currency']->id,
            'project_budget' => 10000,
            'revenue_amount' => 0,
            'net_amount' => 10000,
            'exchange_rate' => 3.70,
            'execution_amount_ils' => 37000,
            'workflow_status' => $scenario['project_status'],
            'uses_execution_tracks' => (bool) ($scenario['tracks'] ?? false),
            'created_by' => $ctx['admin_id'],
            'updated_by' => $ctx['admin_id'],
        ]);

        if ($project->usesExecutionTracks()) {
            app(ProjectExecutionSpawner::class)->syncFromRegions($project, $ctx['admin_id']);
            $executions = $project->executions()->orderBy('sort_order')->get();
            $statuses = $scenario['execution_statuses'] ?? ['pending_coordinator'];

            foreach ($executions as $index => $execution) {
                $status = $statuses[$index] ?? $statuses[0];
                $this->applyExecutionState($execution, $status, $scenario, $ctx);
            }

            app(ProjectAggregateStatusService::class)->refresh($project->fresh());
        } elseif (! empty($scenario['with_coordinator_checklist'])) {
            $this->seedChecklist($project, 'ready', 'ready');
            $project->update(['coordinator_readiness_pct' => 85.0]);
        }

        if (! empty($scenario['note'])) {
            $this->command?->line("  + {$scenario['number']}: {$scenario['note']}");
        }
    }

    /** @param array<string, mixed> $scenario @param array<string, mixed> $ctx */
    private function applyExecutionState(
        ProjectExecution $execution,
        string $status,
        array $scenario,
        array $ctx,
    ): void {
        $updates = ['workflow_status' => $status];

        if (! empty($scenario['with_monitor']) && $ctx['monitor']) {
            $updates['monitor_person_id'] = $ctx['monitor']->id;
        }

        if (in_array($status, ['pending_section_manager', 'pending_dept_manager', 'pending_monitoring_manager', 'monitoring_in_progress', 'pending_monitoring_confirmation', 'passage_complete'], true)) {
            $updates['coordinator_readiness_pct'] = 90.0;
        }

        if (in_array($status, ['monitoring_in_progress', 'pending_monitoring_confirmation', 'passage_complete'], true)) {
            $updates['monitor_readiness_pct'] = 88.0;
            $updates['monitoring_method'] = 'ميداني';
            $updates['monitoring_stage'] = 'أثناء التنفيذ';
            $updates['monitoring_date'] = now()->toDateString();
        }

        $execution->update($updates);

        if (! empty($scenario['with_coordinator_checklist'])) {
            $this->seedChecklist($execution->project, 'ready', $status === 'passage_complete' ? 'ready' : null, $execution);
        }

        if (! empty($scenario['with_monitoring_activity'])) {
            $execution->loadMissing('project');
            $code = 'DEMO-MA-' . str_pad((string) $execution->id, 4, '0', STR_PAD_LEFT) . '-' . substr(md5($execution->id . $scenario['number']), 0, 6);

            $activity = MonitoringActivity::create([
                'reference_code' => $code,
                'source_type' => 'project',
                'source_id' => $execution->project_id,
                'project_execution_id' => $execution->id,
                'activity_role' => 'primary',
                'center_id' => $execution->project->center_id,
                'department_id' => $execution->project->department_id,
                'section_id' => $execution->project->section_id,
                'monitor_person_id' => $execution->monitor_person_id,
                'workflow_status' => $scenario['monitoring_activity_status'] ?? 'in_progress',
                'execution_value' => 90,
                'quality_value' => 85,
                'closure_value' => 80,
                'deduction_value' => 0,
                'is_passage_complete' => (bool) ($scenario['is_passage_complete'] ?? false),
                'created_by' => $ctx['admin_id'],
                'updated_by' => $ctx['admin_id'],
            ]);

            $execution->update(['primary_monitoring_activity_id' => $activity->id]);
        }
    }

    private function seedChecklist(
        Project $project,
        string $coordinatorValue = 'ready',
        ?string $monitorValue = null,
        ?ProjectExecution $execution = null,
    ): void {
        $items = ChecklistItem::query()
            ->where('is_active', true)
            ->whereHas('group', fn ($q) => $q->where('is_active', true))
            ->pluck('id');

        foreach ($items as $itemId) {
            ProjectChecklistValue::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'checklist_item_id' => $itemId,
                    'project_execution_id' => $execution?->id,
                ],
                array_filter([
                    'coordinator_value' => $coordinatorValue,
                    'monitor_value' => $monitorValue,
                ], fn ($v) => $v !== null)
            );
        }
    }

    /** @param  list<array<string, mixed>>  $regions
     * @param  array<string, mixed>  $ctx
     * @return list<array<string, mixed>>
     */
    private function regionsWithCoordinators(array $regions, array $ctx): array
    {
        return array_values(array_map(function (array $region, int $index) use ($ctx) {
            if (filled($region['coordinator_mode'] ?? null)) {
                return $region;
            }

            $region['coordinator_mode'] = $index === 0 ? 'person' : 'self';
            $region['coordinator_id'] = $index === 0 ? $ctx['coord']?->id : null;
            $region['coordinator_external_name'] = null;

            return $region;
        }, $regions, array_keys($regions)));
    }

    private function personByUsername(string $username): ?Person
    {
        $user = User::where('username', $username)->first();

        return $user?->person ?? Person::whereHas('user', fn ($q) => $q->where('username', $username))->first();
    }
}
