<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MonitoringActivity;
use App\Models\Project;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $person = $user?->person;
        $role = $person?->role ?? ($user?->super_admin ? 'super_admin' : 'guest');

        $projectsQuery = Project::query()->visibleToUser($user);
        $visibleProjects = (clone $projectsQuery)->get();

        $stats = $this->buildStats($visibleProjects, $role);
        $actionProjects = $visibleProjects
            ->filter(fn (Project $project) => $project->needsActionFromPerson($person))
            ->take(10);

        $monitoringStats = null;
        if ($user?->can('view', MonitoringActivity::class)) {
            $activitiesQuery = MonitoringActivity::query();
            if ($person?->role === 'monitor' && ! $user->super_admin) {
                $activitiesQuery->where('monitor_person_id', $person->id);
            }
            $monitoringStats = [
                'total' => (clone $activitiesQuery)->count(),
                'pending_confirmation' => (clone $activitiesQuery)->where('workflow_status', 'pending_confirmation')->count(),
                'in_progress' => (clone $activitiesQuery)->where('workflow_status', 'in_progress')->count(),
            ];
        }

        return view('dashboard.index', [
            'role' => $role,
            'person' => $person,
            'stats' => $stats,
            'actionProjects' => $actionProjects,
            'monitoringStats' => $monitoringStats,
            'statusLabels' => Project::workflowStatusLabels(),
        ]);
    }

    public function refreshDashboardCache()
    {
        return back()->with('success', 'تم تحديث البيانات.');
    }

    /** @param \Illuminate\Support\Collection<int, Project> $projects */
    private function buildStats($projects, ?string $role): array
    {
        $base = [
            'total' => $projects->count(),
            'draft' => $projects->where('workflow_status', 'draft')->count(),
            'coordinator' => $projects->whereIn('workflow_status', ['pending_coordinator', 'coordinator_filling'])->count(),
            'pending_pm' => $projects->where('workflow_status', 'pending_project_manager')->count(),
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
                    ['title' => 'بانتظار مدير الدائرة', 'value' => $base['pending_dept'], 'class' => 'info'],
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
            'general_management' => [
                'label' => 'نظرة عامة',
                'cards' => [
                    ['title' => 'إجمالي المشاريع', 'value' => $base['total'], 'class' => 'primary'],
                    ['title' => 'قيد التنفيذ', 'value' => $base['coordinator'] + $base['pending_pm'] + $base['pending_dept'] + $base['pending_monitoring'] + $base['monitoring'], 'class' => 'info'],
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
