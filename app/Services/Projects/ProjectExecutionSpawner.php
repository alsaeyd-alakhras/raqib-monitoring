<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectChecklistValue;
use App\Models\ProjectExecution;
use Illuminate\Support\Str;

class ProjectExecutionSpawner
{
    /**
     * @return list<ProjectExecution>
     */
    public function syncFromRegions(Project $project, ?int $actorUserId = null): array
    {
        $project->loadMissing('projectManager');
        $regions = $this->regionsForSpawn($project);
        $existing = $project->executions()->get()->keyBy('region_key');
        $created = [];

        foreach ($regions as $index => $region) {
            $regionKey = $region['key'];
            $execution = $existing->get($regionKey);
            $coordinator = $this->resolveRegionCoordinator($region, $project);

            if ($execution) {
                $updates = [
                    'region_name' => $region['name'],
                    'region_beneficiaries' => $region['beneficiaries'],
                    'region_execution_site' => $region['execution_site'],
                    'nomination_responsibility' => $region['nomination_responsibility'] ?? null,
                    'sort_order' => $index,
                    'is_active' => true,
                ];

                if (in_array($execution->workflow_status, ['pending_coordinator', 'pending_section_manager'], true)) {
                    $coordinatorChanged = (int) ($execution->coordinator_id ?? 0) !== (int) ($coordinator['coordinator_id'] ?? 0)
                        || (string) ($execution->coordinator_external_name ?? '') !== (string) ($coordinator['coordinator_external_name'] ?? '');

                    $updates['coordinator_id'] = $coordinator['coordinator_id'];
                    $updates['coordinator_external_name'] = $coordinator['coordinator_external_name'];

                    if ($coordinatorChanged) {
                        $this->clearExecutionCoordinatorChecklist($execution);
                        $initialStatus = $this->initialWorkflowStatusForRegion($region, $project);
                        $updates['workflow_status'] = $initialStatus;
                        $updates['coordinator_submitted_at'] = $initialStatus === 'pending_coordinator' ? now() : null;
                        $updates['coordinator_submitted_by'] = $initialStatus === 'pending_coordinator' ? $actorUserId : null;
                        $updates['submitted_to_section_manager_at'] = $initialStatus === 'pending_section_manager' ? now() : null;
                        $updates['submitted_to_section_manager_by'] = $initialStatus === 'pending_section_manager' ? $actorUserId : null;
                        $updates['coordinator_filled_at'] = null;
                        $updates['coordinator_filled_by'] = null;
                        $updates['coordinator_readiness_pct'] = null;
                    }
                }

                $execution->update($updates);
                $created[] = $execution->fresh();

                continue;
            }

            $initialStatus = $this->initialWorkflowStatusForRegion($region, $project);

            $created[] = ProjectExecution::create([
                'project_id' => $project->id,
                'region_key' => $regionKey,
                'region_name' => $region['name'],
                'region_beneficiaries' => $region['beneficiaries'],
                'region_execution_site' => $region['execution_site'],
                'sort_order' => $index,
                'coordinator_id' => $coordinator['coordinator_id'],
                'coordinator_external_name' => $coordinator['coordinator_external_name'],
                'nomination_responsibility' => $region['nomination_responsibility'] ?? null,
                'workflow_status' => $initialStatus,
                'coordinator_submitted_at' => $initialStatus === 'pending_coordinator' ? now() : null,
                'coordinator_submitted_by' => $initialStatus === 'pending_coordinator' ? $actorUserId : null,
                'submitted_to_section_manager_at' => $initialStatus === 'pending_section_manager' ? now() : null,
                'submitted_to_section_manager_by' => $initialStatus === 'pending_section_manager' ? $actorUserId : null,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);
        }

        return $created;
    }

    /**
     * @return list<array{key: string, name: string, beneficiaries: ?int, execution_site: ?string, coordinator_mode: ?string, coordinator_id: ?int, coordinator_external_name: ?string}>
     */
    private function regionsForSpawn(Project $project): array
    {
        $display = $project->executionRegionsForDisplay();

        if ($display === []) {
            return [[
                'key' => 'default',
                'name' => filled($project->location) ? trim((string) $project->location) : 'تنفيذ عام',
                'beneficiaries' => $project->target_beneficiaries,
                'execution_site' => null,
                'coordinator_mode' => $project->coordinatorMode() === 'none' ? null : $project->coordinatorMode(),
                'coordinator_id' => $project->coordinator_id,
                'coordinator_external_name' => $project->coordinator_external_name,
            ]];
        }

        return array_values(array_map(function (array $region, int $index) {
            $name = trim((string) ($region['name'] ?? ''));

            return [
                'key' => 'region-' . ($index + 1) . '-' . Str::slug($name !== '' ? $name : 'region'),
                'name' => $name !== '' ? $name : 'منطقة ' . ($index + 1),
                'beneficiaries' => $region['beneficiaries'] ?? null,
                'execution_site' => $region['execution_site'] ?? null,
                'coordinator_mode' => $region['coordinator_mode'] ?? null,
                'coordinator_id' => $region['coordinator_id'] ?? null,
                'coordinator_external_name' => $region['coordinator_external_name'] ?? null,
                'nomination_responsibility' => $region['nomination_responsibility'] ?? null,
            ];
        }, $display, array_keys($display)));
    }

    /**
     * @param  array{coordinator_mode: ?string, coordinator_id: ?int, coordinator_external_name: ?string}  $region
     * @return array{coordinator_id: ?int, coordinator_external_name: ?string}
     */
    private function resolveRegionCoordinator(array $region, Project $project): array
    {
        $mode = $region['coordinator_mode'] ?? null;

        if ($mode === null) {
            return [
                'coordinator_id' => $project->coordinator_id,
                'coordinator_external_name' => $project->coordinator_external_name,
            ];
        }

        return match ($mode) {
            'self' => [
                'coordinator_id' => $project->project_manager_id,
                'coordinator_external_name' => null,
            ],
            'person' => [
                'coordinator_id' => isset($region['coordinator_id']) ? (int) $region['coordinator_id'] : null,
                'coordinator_external_name' => null,
            ],
            'external' => [
                'coordinator_id' => null,
                'coordinator_external_name' => filled($region['coordinator_external_name'] ?? null)
                    ? trim((string) $region['coordinator_external_name'])
                    : null,
            ],
            default => [
                'coordinator_id' => $project->coordinator_id,
                'coordinator_external_name' => $project->coordinator_external_name,
            ],
        };
    }

    private function initialWorkflowStatusForRegion(array $region, Project $project): string
    {
        return 'pending_coordinator';
    }

    public function spawnNewRegion(Project $project, array $region, int $sortOrder, ?int $actorUserId = null): ProjectExecution
    {
        $name = trim((string) ($region['name'] ?? ''));
        $regionKey = 'region-' . ($sortOrder + 1) . '-' . Str::slug($name !== '' ? $name : 'region') . '-' . Str::random(4);
        $spawnRegion = [
            'coordinator_mode' => $region['coordinator_mode'] ?? null,
            'coordinator_id' => $region['coordinator_id'] ?? null,
            'coordinator_external_name' => $region['coordinator_external_name'] ?? null,
        ];
        $coordinator = $this->resolveRegionCoordinator($spawnRegion, $project);
        $initialStatus = $this->initialWorkflowStatusForRegion($spawnRegion, $project);

        return ProjectExecution::create([
            'project_id' => $project->id,
            'region_key' => $regionKey,
            'region_name' => $name !== '' ? $name : 'منطقة ' . ($sortOrder + 1),
            'region_beneficiaries' => isset($region['beneficiaries']) && $region['beneficiaries'] !== ''
                ? (int) $region['beneficiaries']
                : null,
            'region_execution_site' => filled($region['execution_site'] ?? null)
                ? trim((string) $region['execution_site'])
                : null,
            'sort_order' => $sortOrder,
            'coordinator_id' => $coordinator['coordinator_id'],
            'coordinator_external_name' => $coordinator['coordinator_external_name'],
            'nomination_responsibility' => filled($region['nomination_responsibility'] ?? null)
                ? (string) $region['nomination_responsibility']
                : null,
            'workflow_status' => $initialStatus,
            'coordinator_submitted_at' => $initialStatus === 'pending_coordinator' ? now() : null,
            'coordinator_submitted_by' => $initialStatus === 'pending_coordinator' ? $actorUserId : null,
            'submitted_to_section_manager_at' => $initialStatus === 'pending_section_manager' ? now() : null,
            'submitted_to_section_manager_by' => $initialStatus === 'pending_section_manager' ? $actorUserId : null,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ]);
    }

    private function clearExecutionCoordinatorChecklist(ProjectExecution $execution): void
    {
        ProjectChecklistValue::where('project_id', $execution->project_id)
            ->where('project_execution_id', $execution->id)
            ->where(function ($query) {
                $query->whereNotNull('coordinator_value')
                    ->orWhereNotNull('person_name');
            })
            ->update([
                'coordinator_value' => null,
                'person_name' => null,
            ]);
    }
}
