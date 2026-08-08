<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MonitoringActivity;
use App\Models\Project;
use App\Models\ProjectExecution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** @var array<int, string> */
    private const MONITORING_PIPELINE_STATUSES = [
        'pending_monitoring_manager',
        'monitoring_in_progress',
        'pending_monitoring_confirmation',
    ];

    public function index(): View
    {
        $user = auth()->user();
        $person = $user?->person;
        $role = $person?->role ?? ($user?->super_admin ? 'super_admin' : 'guest');

        $projectsQuery = Project::query()->visibleToUser($user)->with(['projectManager', 'currency']);
        $visibleProjects = (clone $projectsQuery)->get();

        $executionDashboardRoles = ['coordinator', 'monitor', 'section_manager', 'department_manager', 'monitoring_director'];
        $usesExecutionDashboard = in_array($role, $executionDashboardRoles, true)
            || ($user?->canOverseeExecutions() ?? false);

        $visibleExecutions = collect();
        $executionStats = null;
        $actionExecutions = collect();

        if ($usesExecutionDashboard && $user?->can('view', ProjectExecution::class)) {
            $visibleExecutions = ProjectExecution::query()
                ->where('is_active', true)
                ->visibleToUser($user)
                ->with(['project.projectManager', 'coordinator', 'monitorPerson'])
                ->orderByDesc('updated_at')
                ->get();

            $executionStats = $this->buildExecutionStats($visibleExecutions, $role);
            $actionExecutions = $visibleExecutions
                ->filter(fn (ProjectExecution $execution) => $execution->needsActionFromPerson($person))
                ->values();
        }

        $stats = $usesExecutionDashboard && $executionStats
            ? $executionStats
            : $this->buildStats($visibleProjects, $role);

        $actionProjects = match (true) {
            ! $usesExecutionDashboard => $visibleProjects
                ->filter(fn (Project $project) => $project->needsActionFromPerson($person)),
            $user?->isMonitoringDirector() => $visibleProjects
                ->filter(fn (Project $project) => ! $project->usesExecutionTracks()
                    && $project->needsActionFromPerson($person)),
            $role === 'monitor' => $visibleProjects
                ->filter(fn (Project $project) => ! $project->usesExecutionTracks()
                    && $project->needsActionFromPerson($person)),
            default => collect(),
        };

        $monitoringStats = null;
        if ($user?->can('view', MonitoringActivity::class)) {
            $activitiesQuery = MonitoringActivity::query();
            if ($person?->role === 'monitor' && ! $user->super_admin) {
                $activitiesQuery->where('monitor_person_id', $person->id);
            }
            $pendingConfirmationQuery = (clone $activitiesQuery)->where('workflow_status', 'pending_confirmation');
            if ($user?->isMonitoringDirector()) {
                $pendingConfirmationQuery = MonitoringActivity::query()->pendingDirectorApproval();
            }

            $monitoringStats = [
                'total' => (clone $activitiesQuery)->count(),
                'pending_confirmation' => $pendingConfirmationQuery->count(),
                'in_progress' => (clone $activitiesQuery)->where('workflow_status', 'in_progress')->count(),
            ];
        }

        $isMonitoringDirector = $user?->isMonitoringDirector() ?? false;

        $monitorHome = null;
        if ($role === 'monitor' && ! $user?->super_admin && $person) {
            if ($visibleExecutions->isEmpty()) {
                $visibleExecutions = ProjectExecution::query()
                    ->where('is_active', true)
                    ->visibleToUser($user)
                    ->with(['project.projectManager', 'coordinator', 'monitorPerson'])
                    ->orderByDesc('updated_at')
                    ->get();
            }

            $monitorHome = $this->buildMonitorHomeData($user, $person, $visibleProjects, $visibleExecutions);
        }

        $monitoringDirectorHome = $isMonitoringDirector
            ? $this->buildMonitoringDirectorHomeData($user, $visibleProjects, $visibleExecutions)
            : null;

        return view('dashboard.index', [
            'role' => $role,
            'person' => $person,
            'isMonitoringDirector' => $isMonitoringDirector,
            'stats' => $stats,
            'actionProjects' => $actionProjects,
            'actionExecutions' => $actionExecutions,
            'visibleExecutions' => $visibleExecutions,
            'usesExecutionDashboard' => $usesExecutionDashboard,
            'monitoringStats' => $monitoringStats,
            'monitoringDirectorHome' => $monitoringDirectorHome,
            'monitorHome' => $monitorHome,
            'statusLabels' => Project::workflowStatusLabels(),
            'executionStatusLabels' => ProjectExecution::workflowStatusLabels(),
        ]);
    }

    /**
     * @param  Collection<int, Project>  $visibleProjects
     * @param  Collection<int, ProjectExecution>  $visibleExecutions
     * @return array{
     *     pipelineExecutions: Collection<int, ProjectExecution>,
     *     activeSingleProjects: Collection<int, Project>,
     *     executionTrackProjects: Collection<int, Project>
     * }
     */
    private function buildMonitoringDirectorHomeData(
        ?\App\Models\User $user,
        Collection $visibleProjects,
        Collection $visibleExecutions,
    ): array {
        $pipelineExecutions = $visibleExecutions
            ->sort(function (ProjectExecution $a, ProjectExecution $b) {
                $aPriority = in_array($a->workflow_status, self::MONITORING_PIPELINE_STATUSES, true) ? 0 : 1;
                $bPriority = in_array($b->workflow_status, self::MONITORING_PIPELINE_STATUSES, true) ? 0 : 1;

                if ($aPriority !== $bPriority) {
                    return $aPriority <=> $bPriority;
                }

                return ($b->updated_at?->getTimestamp() ?? 0) <=> ($a->updated_at?->getTimestamp() ?? 0);
            })
            ->values();

        $activeSingleProjects = $visibleProjects
            ->filter(fn (Project $project) => ! $project->usesExecutionTracks()
                && in_array($project->workflow_status, self::MONITORING_PIPELINE_STATUSES, true))
            ->sortByDesc('updated_at')
            ->values();

        $executionTrackProjects = Project::query()
            ->visibleToUser($user)
            ->where('uses_execution_tracks', true)
            ->whereHas('executions', fn ($query) => $query->where('is_active', true))
            ->withCount([
                'executions as active_executions_count' => fn ($query) => $query->where('is_active', true),
                'executions as pipeline_executions_count' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('workflow_status', self::MONITORING_PIPELINE_STATUSES),
            ])
            ->with('projectManager')
            ->orderByDesc('updated_at')
            ->get();

        return [
            'pipelineExecutions' => $pipelineExecutions,
            'activeSingleProjects' => $activeSingleProjects,
            'executionTrackProjects' => $executionTrackProjects,
            'pendingApprovalActivities' => MonitoringActivity::query()
                ->pendingDirectorApproval()
                ->with(['monitorPerson', 'center', 'department'])
                ->orderByDesc('submitted_at')
                ->orderByDesc('updated_at')
                ->get(),
            'pendingApprovalCount' => MonitoringActivity::query()
                ->pendingDirectorApproval()
                ->count(),
        ];
    }

    /**
     * @param  Collection<int, Project>  $visibleProjects
     * @param  Collection<int, ProjectExecution>  $visibleExecutions
     * @return array{
     *     actionActivities: Collection<int, MonitoringActivity>,
     *     returnedActivities: Collection<int, MonitoringActivity>,
     *     myCreatedActivities: Collection<int, MonitoringActivity>,
     *     pendingDirectorActivities: Collection<int, MonitoringActivity>,
     *     actionProjects: Collection<int, Project>,
     *     actionExecutions: Collection<int, ProjectExecution>,
     *     myExecutions: Collection<int, ProjectExecution>,
     *     myProjects: Collection<int, Project>
     * }
     */
    private function buildMonitorHomeData(
        ?\App\Models\User $user,
        ?\App\Models\Person $person,
        Collection $visibleProjects,
        Collection $visibleExecutions,
    ): array {
        $personId = (int) $person->id;
        $userId = (int) $user->id;

        $assignedQuery = MonitoringActivity::query()
            ->assignedToMonitor($personId)
            ->with(['monitorPerson', 'center', 'department']);

        $actionActivities = (clone $assignedQuery)
            ->where('workflow_status', 'in_progress')
            ->where(function (Builder $query) {
                $query->where('source_type', 'external')
                    ->orWhere('activity_role', 'secondary');
            })
            ->orderByDesc('updated_at')
            ->get();

        $returnedActivities = (clone $assignedQuery)
            ->where('workflow_status', 'in_progress')
            ->whereNotNull('rejected_at')
            ->orderByDesc('rejected_at')
            ->get();

        $myCreatedActivities = MonitoringActivity::query()
            ->where('source_type', 'external')
            ->where('created_by', $userId)
            ->with(['monitorPerson', 'center', 'department'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $pendingDirectorActivities = (clone $assignedQuery)
            ->where('workflow_status', 'pending_confirmation')
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->get();

        $actionProjects = $visibleProjects
            ->filter(fn (Project $project) => ! $project->usesExecutionTracks()
                && $project->needsActionFromPerson($person))
            ->values();

        $actionExecutions = $visibleExecutions
            ->filter(fn (ProjectExecution $execution) => $execution->needsActionFromPerson($person))
            ->values();

        $myProjects = $visibleProjects
            ->filter(fn (Project $project) => ! $project->usesExecutionTracks()
                && (int) $project->monitor_person_id === $personId)
            ->sortByDesc('updated_at')
            ->values();

        return [
            'actionActivities' => $actionActivities,
            'returnedActivities' => $returnedActivities,
            'myCreatedActivities' => $myCreatedActivities,
            'pendingDirectorActivities' => $pendingDirectorActivities,
            'actionProjects' => $actionProjects,
            'actionExecutions' => $actionExecutions,
            'myExecutions' => $visibleExecutions,
            'myProjects' => $myProjects,
        ];
    }

    public function refreshDashboardCache()
    {
        return back()->with('success', 'تم تحديث البيانات.');
    }

    /** @param Collection<int, ProjectExecution> $executions */
    private function buildExecutionStats(Collection $executions, ?string $role): array
    {
        $base = [
            'total' => $executions->count(),
            'pending_fill' => $executions->whereIn('workflow_status', ['pending_coordinator', 'coordinator_filling'])->count(),
            'pending_section' => $executions->where('workflow_status', 'pending_section_manager')->count(),
            'pending_dept' => $executions->where('workflow_status', 'pending_dept_manager')->count(),
            'pending_monitoring' => $executions->where('workflow_status', 'pending_monitoring_manager')->count(),
            'monitoring' => $executions->where('workflow_status', 'monitoring_in_progress')->count(),
            'pending_confirmation' => $executions->where('workflow_status', 'pending_monitoring_confirmation')->count(),
            'complete' => $executions->where('workflow_status', 'passage_complete')->count(),
            'rejected' => $executions->where('workflow_status', 'rejected')->count(),
        ];

        return match ($role) {
            'coordinator' => [
                'label' => 'مساراتي كمنسق',
                'cards' => [
                    ['title' => 'مُسندة لي', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'بانتظار تعبئتي', 'value' => $base['pending_fill'], 'class' => 'warning'],
                    ['title' => 'بانتظار مدير القسم', 'value' => $base['pending_section'], 'class' => 'info'],
                    ['title' => 'بانتظار مدير الدائرة', 'value' => $base['pending_dept'], 'class' => 'info'],
                    ['title' => 'قيد الرقابة', 'value' => $base['pending_monitoring'] + $base['monitoring'] + $base['pending_confirmation'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'monitor' => [
                'label' => 'مساراتي كمراقب',
                'cards' => [
                    ['title' => 'مُسندة لي', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'قيد التعبئة', 'value' => $base['monitoring'], 'class' => 'warning'],
                    ['title' => 'بانتظار مدير الرقابة', 'value' => $base['pending_confirmation'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'section_manager' => [
                'label' => 'مسارات قسمي',
                'cards' => [
                    ['title' => 'إجمالي المسارات', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'بانتظار موافقتي', 'value' => $base['pending_section'], 'class' => 'warning'],
                    ['title' => 'عند مدير الدائرة', 'value' => $base['pending_dept'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'department_manager' => [
                'label' => 'مسارات دائرتي',
                'cards' => [
                    ['title' => 'إجمالي المسارات', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'بانتظار موافقتي', 'value' => $base['pending_dept'], 'class' => 'warning'],
                    ['title' => 'عند الرقابة', 'value' => $base['pending_monitoring'] + $base['monitoring'] + $base['pending_confirmation'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'monitoring_director' => [
                'label' => 'دورة الرقابة — المسارات',
                'cards' => [
                    ['title' => 'بانتظار تعيين مراقب', 'value' => $base['pending_monitoring'], 'class' => 'warning'],
                    ['title' => 'قيد المراقبة', 'value' => $base['monitoring'], 'class' => 'info'],
                    ['title' => 'بانتظار تأكيد المرور', 'value' => $base['pending_confirmation'], 'class' => 'primary'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            default => [
                'label' => 'مسارات التنفيذ',
                'cards' => [
                    ['title' => 'إجمالي المسارات', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'قيد التنفيذ', 'value' => $base['total'] - $base['complete'] - $base['rejected'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                    ['title' => 'مرفوضة', 'value' => $base['rejected'], 'class' => 'danger'],
                ],
            ],
        };
    }

    /** @param \Illuminate\Support\Collection<int, Project> $projects */
    private function buildStats($projects, ?string $role): array
    {
        $base = [
            'total' => $projects->count(),
            'draft' => $projects->where('workflow_status', 'draft')->count(),
            'secretariat' => $projects->where('workflow_status', 'pending_secretariat')->count(),
            'coordinator' => $projects->whereIn('workflow_status', ['pending_coordinator', 'coordinator_filling'])->count(),
            'pending_pm' => $projects->where('workflow_status', 'pending_project_manager')->count(),
            'pending_section' => $projects->where('workflow_status', 'pending_section_manager')->count(),
            'pending_dept' => $projects->where('workflow_status', 'pending_dept_manager')->count(),
            'pending_monitoring' => $projects->where('workflow_status', 'pending_monitoring_manager')->count(),
            'monitoring' => $projects->where('workflow_status', 'monitoring_in_progress')->count(),
            'pending_confirmation' => $projects->where('workflow_status', 'pending_monitoring_confirmation')->count(),
            'complete' => $projects->where('workflow_status', 'passage_complete')->count(),
            'rejected' => $projects->where('workflow_status', 'rejected')->count(),
        ];

        return match ($role) {
            'project_manager' => [
                'label' => 'مشاريعي',
                'cards' => [
                    ['title' => 'إجمالي مشاريعي', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'مسودات', 'value' => $base['draft'], 'class' => 'secondary'],
                    ['title' => 'عند السكرتاريا', 'value' => $base['secretariat'], 'class' => 'info'],
                    ['title' => 'بانتظار المنسق', 'value' => $base['coordinator'], 'class' => 'info'],
                    ['title' => 'بانتظار مراجعتي', 'value' => $base['pending_pm'], 'class' => 'warning'],
                    ['title' => 'قيد المراقبة', 'value' => $base['monitoring'], 'class' => 'warning'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                    ['title' => 'مرفوضة نهائياً', 'value' => $base['rejected'], 'class' => 'danger'],
                ],
            ],
            'coordinator' => [
                'label' => 'مشاريعي كمنسق',
                'cards' => [
                    ['title' => 'مُسندة لي', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'بانتظار تعبئتي', 'value' => $base['coordinator'], 'class' => 'warning'],
                    ['title' => 'بانتظار مدير المشروع', 'value' => $base['pending_pm'], 'class' => 'info'],
                    ['title' => 'بانتظار مدير القسم', 'value' => $base['pending_section'], 'class' => 'info'],
                    ['title' => 'بانتظار مدير الدائرة', 'value' => $base['pending_dept'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'section_manager' => [
                'label' => 'مشاريع قسمي',
                'cards' => [
                    ['title' => 'إجمالي القسم', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'بانتظار موافقتي', 'value' => $base['pending_section'], 'class' => 'warning'],
                    ['title' => 'عند مدير الدائرة', 'value' => $base['pending_dept'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'department_manager' => [
                'label' => 'مشاريع دائرتي',
                'cards' => [
                    ['title' => 'إجمالي الدائرة', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'بانتظار موافقتي', 'value' => $base['pending_dept'], 'class' => 'warning'],
                    ['title' => 'عند الرقابة', 'value' => $base['pending_monitoring'] + $base['monitoring'] + $base['pending_confirmation'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'monitoring_director' => [
                'label' => 'دورة الرقابة',
                'cards' => [
                    ['title' => 'بانتظار تعيين مراقب', 'value' => $base['pending_monitoring'], 'class' => 'warning'],
                    ['title' => 'قيد المراقبة', 'value' => $base['monitoring'], 'class' => 'info'],
                    ['title' => 'بانتظار تأكيد المرور', 'value' => $base['pending_confirmation'], 'class' => 'primary'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'monitor' => [
                'label' => 'مهامي كمراقب',
                'cards' => [
                    ['title' => 'مُسندة لي', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'قيد التعبئة', 'value' => $base['monitoring'], 'class' => 'warning'],
                    ['title' => 'بانتظار مدير الرقابة', 'value' => $base['pending_confirmation'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'project_secretariat' => [
                'label' => 'سكرتاريا الدائرة',
                'cards' => [
                    ['title' => 'بانتظار تعبئتي', 'value' => $base['secretariat'], 'class' => 'warning'],
                    ['title' => 'بانتظار المنسق', 'value' => $base['coordinator'], 'class' => 'info'],
                    ['title' => 'قيد الدورة', 'value' => $base['pending_pm'] + $base['pending_section'] + $base['pending_dept'] + $base['pending_monitoring'] + $base['monitoring'], 'class' => 'primary'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                ],
            ],
            'general_management' => [
                'label' => 'نظرة عامة',
                'cards' => [
                    ['title' => 'إجمالي المشاريع', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'قيد التنفيذ', 'value' => $base['secretariat'] + $base['coordinator'] + $base['pending_pm'] + $base['pending_section'] + $base['pending_dept'] + $base['pending_monitoring'] + $base['monitoring'], 'class' => 'info'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                    ['title' => 'مرفوضة', 'value' => $base['rejected'], 'class' => 'danger'],
                ],
            ],
            default => [
                'label' => 'نظرة عامة',
                'cards' => [
                    ['title' => 'إجمالي المشاريع', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'مسودات', 'value' => $base['draft'], 'class' => 'secondary'],
                    ['title' => 'مكتملة', 'value' => $base['complete'], 'class' => 'success'],
                    ['title' => 'مرفوضة', 'value' => $base['rejected'], 'class' => 'danger'],
                ],
            ],
        };
    }
}
