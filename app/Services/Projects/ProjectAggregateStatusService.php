<?php

namespace App\Services\Projects;

use App\Models\Project;

class ProjectAggregateStatusService
{
    public function refresh(Project $project): void
    {
        if (! $project->uses_execution_tracks) {
            return;
        }

        $executions = $project->executions()->where('is_active', true)->get();

        if ($executions->isEmpty()) {
            return;
        }

        $allComplete = $executions->every(fn ($execution) => $execution->workflow_status === 'passage_complete');
        $anyRejected = $executions->contains(fn ($execution) => $execution->workflow_status === 'rejected');

        $nextStatus = match (true) {
            $allComplete => 'passage_complete',
            $anyRejected && $executions->every(fn ($e) => in_array($e->workflow_status, ['passage_complete', 'rejected'], true)) => 'executions_in_progress',
            default => 'executions_in_progress',
        };

        if ($project->workflow_status !== $nextStatus) {
            $project->update(['workflow_status' => $nextStatus]);
        }
    }

    /**
     * @return array{total: int, complete: int, in_progress: int, rejected: int, label: string}
     */
    public function summary(Project $project): array
    {
        return $this->summaryFromCollection(
            $project->executions()->where('is_active', true)->get()
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ProjectExecution>  $executions
     * @return array{total: int, complete: int, in_progress: int, rejected: int, label: string}
     */
    public function summaryFromCollection($executions): array
    {
        $total = $executions->count();
        $complete = $executions->where('workflow_status', 'passage_complete')->count();
        $rejected = $executions->where('workflow_status', 'rejected')->count();
        $inProgress = $total - $complete - $rejected;

        return [
            'total' => $total,
            'complete' => $complete,
            'in_progress' => $inProgress,
            'rejected' => $rejected,
            'label' => $total > 0 ? "{$complete}/{$total}" : '—',
        ];
    }
}
