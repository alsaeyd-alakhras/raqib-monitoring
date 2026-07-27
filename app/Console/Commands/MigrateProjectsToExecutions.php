<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectChecklistValue;
use App\Models\ProjectExecution;
use App\Services\Projects\ProjectAggregateStatusService;
use App\Services\Projects\ProjectExecutionSpawner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateProjectsToExecutions extends Command
{
    protected $signature = 'projects:migrate-to-executions {--dry-run : Preview without writing}';

    protected $description = 'Migrate in-flight projects to execution tracks (one execution per project).';

    public function handle(ProjectExecutionSpawner $spawner, ProjectAggregateStatusService $aggregate): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $projects = Project::query()
            ->where('uses_execution_tracks', false)
            ->whereNotIn('workflow_status', ['draft', 'pending_secretariat'])
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No projects to migrate.');

            return self::SUCCESS;
        }

        foreach ($projects as $project) {
            $this->line("Project #{$project->id} ({$project->project_number}) — {$project->workflow_status}");

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($project, $spawner, $aggregate) {
                $executions = $spawner->syncFromRegions($project);
                $execution = $executions[0] ?? null;

                if (! $execution) {
                    return;
                }

                $execution->update([
                    'coordinator_id' => $project->coordinator_id,
                    'coordinator_external_name' => $project->coordinator_external_name,
                    'monitor_person_id' => $project->monitor_person_id,
                    'monitoring_date' => $project->monitoring_date,
                    'monitoring_method' => $project->monitoring_method,
                    'monitoring_stage' => $project->monitoring_stage,
                    'coordinator_readiness_pct' => $project->coordinator_readiness_pct,
                    'monitor_readiness_pct' => $project->monitor_readiness_pct,
                    'monitor_notes' => $project->monitor_notes,
                    'monitor_negative_notes' => $project->monitor_negative_notes,
                    'monitor_recommendations' => $project->monitor_recommendations,
                    'workflow_status' => $project->workflow_status === 'passage_complete'
                        ? 'passage_complete'
                        : ($project->workflow_status === 'executions_in_progress'
                            ? 'pending_coordinator'
                            : $project->workflow_status),
                    'primary_monitoring_activity_id' => $project->primary_monitoring_activity_id,
                    'coordinator_filled_at' => $project->coordinator_filled_at,
                    'coordinator_filled_by' => $project->coordinator_filled_by,
                    'submitted_to_section_manager_at' => $project->submitted_to_section_manager_at,
                    'submitted_to_section_manager_by' => $project->submitted_to_section_manager_by,
                    'section_manager_approved_at' => $project->section_manager_approved_at,
                    'section_manager_approved_by' => $project->section_manager_approved_by,
                    'dept_manager_approved_at' => $project->dept_manager_approved_at,
                    'dept_manager_approved_by' => $project->dept_manager_approved_by,
                    'monitoring_manager_received_at' => $project->monitoring_manager_received_at,
                    'monitoring_manager_received_by' => $project->monitoring_manager_received_by,
                    'monitor_submitted_at' => $project->monitor_submitted_at,
                    'monitor_submitted_by' => $project->monitor_submitted_by,
                ]);

                ProjectChecklistValue::query()
                    ->where('project_id', $project->id)
                    ->whereNull('project_execution_id')
                    ->update(['project_execution_id' => $execution->id]);

                $project->update([
                    'uses_execution_tracks' => true,
                    'workflow_status' => $project->workflow_status === 'passage_complete'
                        ? 'passage_complete'
                        : 'executions_in_progress',
                ]);

                $aggregate->refresh($project);
            });
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Migration complete.');

        return self::SUCCESS;
    }
}
