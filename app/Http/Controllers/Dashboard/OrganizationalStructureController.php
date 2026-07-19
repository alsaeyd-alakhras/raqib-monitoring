<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Department;
use App\Models\MonitoringActivity;
use App\Models\Person;
use App\Models\Project;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationalStructureController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $this->authorizeOrgView();

        return view('dashboard.org-structure.index', [
            'canManageCenters' => auth()->user()?->can('create', Center::class) ?? false,
            'canManageDepartments' => auth()->user()?->can('create', Department::class) ?? false,
            'canManageSections' => auth()->user()?->can('create', Section::class) ?? false,
        ]);
    }

    public function tree(): JsonResponse
    {
        $this->authorizeOrgView();

        $centers = Center::with(['departments.sections'])
            ->orderBy('name')
            ->get()
            ->map(fn (Center $center) => [
                'id' => $center->id,
                'type' => 'center',
                'name' => $center->name,
                'children_count' => $center->departments->count(),
                'children' => $center->departments->sortBy('name')->values()->map(fn (Department $dept) => [
                    'id' => $dept->id,
                    'type' => 'department',
                    'name' => $dept->name,
                    'center_id' => $dept->center_id,
                    'children_count' => $dept->sections->count(),
                    'children' => $dept->sections->sortBy('name')->values()->map(fn (Section $section) => [
                        'id' => $section->id,
                        'type' => 'section',
                        'name' => $section->name,
                        'department_id' => $section->department_id,
                        'center_id' => $dept->center_id,
                        'children_count' => 0,
                    ]),
                ]),
            ]);

        return response()->json(['centers' => $centers]);
    }

    public function node(string $type, int $id): JsonResponse
    {
        $this->authorizeOrgView();

        return match ($type) {
            'center' => $this->centerNode($id),
            'department' => $this->departmentNode($id),
            'section' => $this->sectionNode($id),
            default => response()->json(['message' => 'نوع غير معروف.'], 404),
        };
    }

    public function store(Request $request): JsonResponse
    {
        $type = $request->input('type');

        return match ($type) {
            'center' => $this->storeCenter($request),
            'department' => $this->storeDepartment($request),
            'section' => $this->storeSection($request),
            default => response()->json(['message' => 'نوع غير معروف.'], 422),
        };
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        return match ($type) {
            'center' => $this->updateCenter($request, $id),
            'department' => $this->updateDepartment($request, $id),
            'section' => $this->updateSection($request, $id),
            default => response()->json(['message' => 'نوع غير معروف.'], 422),
        };
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        return match ($type) {
            'center' => $this->destroyCenter($id),
            'department' => $this->destroyDepartment($id),
            'section' => $this->destroySection($id),
            default => response()->json(['message' => 'نوع غير معروف.'], 422),
        };
    }

    private function authorizeOrgView(): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->can('view', Center::class)
            || $user?->can('view', Department::class)
            || $user?->can('view', Section::class),
            403
        );
    }

    private function centerNode(int $id): JsonResponse
    {
        $center = Center::withCount('departments')->findOrFail($id);

        return response()->json([
            'type' => 'center',
            'id' => $center->id,
            'name' => $center->name,
            'usage' => $this->usageForCenter($center->id),
            'children_count' => $center->departments_count,
        ]);
    }

    private function departmentNode(int $id): JsonResponse
    {
        $department = Department::with(['center'])->withCount('sections')->findOrFail($id);

        return response()->json([
            'type' => 'department',
            'id' => $department->id,
            'name' => $department->name,
            'center_id' => $department->center_id,
            'center_name' => $department->center?->name,
            'usage' => $this->usageForDepartment($department->id),
            'children_count' => $department->sections_count,
        ]);
    }

    private function sectionNode(int $id): JsonResponse
    {
        $section = Section::with(['department.center'])->findOrFail($id);

        return response()->json([
            'type' => 'section',
            'id' => $section->id,
            'name' => $section->name,
            'department_id' => $section->department_id,
            'department_name' => $section->department?->name,
            'center_id' => $section->department?->center_id,
            'center_name' => $section->department?->center?->name,
            'usage' => $this->usageForSection($section->id),
            'children_count' => 0,
        ]);
    }

    private function storeCenter(Request $request): JsonResponse
    {
        $this->authorize('create', Center::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $center = Center::create($validated);

        return response()->json([
            'message' => 'تم إنشاء المركز بنجاح.',
            'node' => [
                'id' => $center->id,
                'type' => 'center',
                'name' => $center->name,
                'children_count' => 0,
                'children' => [],
            ],
        ], 201);
    }

    private function storeDepartment(Request $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $validated = $request->validate([
            'center_id' => ['required', 'exists:centers,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department = Department::create($validated);

        return response()->json([
            'message' => 'تم إنشاء الدائرة بنجاح.',
            'node' => [
                'id' => $department->id,
                'type' => 'department',
                'name' => $department->name,
                'center_id' => $department->center_id,
                'children_count' => 0,
                'children' => [],
            ],
        ], 201);
    }

    private function storeSection(Request $request): JsonResponse
    {
        $this->authorize('create', Section::class);

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $section = Section::create($validated);
        $department = Department::find($section->department_id);

        return response()->json([
            'message' => 'تم إنشاء القسم بنجاح.',
            'node' => [
                'id' => $section->id,
                'type' => 'section',
                'name' => $section->name,
                'department_id' => $section->department_id,
                'center_id' => $department?->center_id,
                'children_count' => 0,
            ],
        ], 201);
    }

    private function updateCenter(Request $request, int $id): JsonResponse
    {
        $this->authorize('update', Center::class);

        $center = Center::findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $center->update($validated);

        return response()->json(['message' => 'تم تحديث المركز بنجاح.', 'name' => $center->name]);
    }

    private function updateDepartment(Request $request, int $id): JsonResponse
    {
        $this->authorize('update', Department::class);

        $department = Department::findOrFail($id);
        $validated = $request->validate([
            'center_id' => ['required', 'exists:centers,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $department->update($validated);

        return response()->json(['message' => 'تم تحديث الدائرة بنجاح.', 'name' => $department->name]);
    }

    private function updateSection(Request $request, int $id): JsonResponse
    {
        $this->authorize('update', Section::class);

        $section = Section::findOrFail($id);
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $section->update($validated);

        return response()->json(['message' => 'تم تحديث القسم بنجاح.', 'name' => $section->name]);
    }

    private function destroyCenter(int $id): JsonResponse
    {
        $this->authorize('delete', Center::class);

        $center = Center::withCount('departments')->findOrFail($id);

        if ($center->departments_count > 0) {
            return response()->json(['message' => 'لا يمكن حذف مركز يحتوي على دوائر.'], 422);
        }

        $usage = $this->usageForCenter($center->id);
        if ($this->hasUsage($usage)) {
            return response()->json(['message' => 'لا يمكن الحذف — المركز مستخدم في مشاريع أو أنشطة أو أشخاص.', 'usage' => $usage], 422);
        }

        $center->delete();

        return response()->json(['message' => 'تم حذف المركز بنجاح.']);
    }

    private function destroyDepartment(int $id): JsonResponse
    {
        $this->authorize('delete', Department::class);

        $department = Department::withCount('sections')->findOrFail($id);

        if ($department->sections_count > 0) {
            return response()->json(['message' => 'لا يمكن حذف دائرة تحتوي على أقسام.'], 422);
        }

        $usage = $this->usageForDepartment($department->id);
        if ($this->hasUsage($usage)) {
            return response()->json(['message' => 'لا يمكن الحذف — الدائرة مستخدمة في مشاريع أو أنشطة أو أشخاص.', 'usage' => $usage], 422);
        }

        $department->delete();

        return response()->json(['message' => 'تم حذف الدائرة بنجاح.']);
    }

    private function destroySection(int $id): JsonResponse
    {
        $this->authorize('delete', Section::class);

        $section = Section::findOrFail($id);
        $usage = $this->usageForSection($section->id);

        if ($this->hasUsage($usage)) {
            return response()->json(['message' => 'لا يمكن الحذف — القسم مستخدم في مشاريع أو أنشطة أو أشخاص.', 'usage' => $usage], 422);
        }

        $section->delete();

        return response()->json(['message' => 'تم حذف القسم بنجاح.']);
    }

    /** @return array<string, int> */
    private function usageForCenter(int $centerId): array
    {
        return [
            'projects' => Project::where('center_id', $centerId)->count(),
            'activities' => MonitoringActivity::where('center_id', $centerId)->count(),
            'people' => Person::whereHas('department', fn ($q) => $q->where('center_id', $centerId))->count(),
        ];
    }

    /** @return array<string, int> */
    private function usageForDepartment(int $departmentId): array
    {
        return [
            'projects' => Project::where('department_id', $departmentId)->count(),
            'activities' => MonitoringActivity::where('department_id', $departmentId)->count(),
            'people' => Person::where('department_id', $departmentId)->count(),
        ];
    }

    /** @return array<string, int> */
    private function usageForSection(int $sectionId): array
    {
        return [
            'projects' => Project::where('section_id', $sectionId)->count(),
            'activities' => MonitoringActivity::where('section_id', $sectionId)->count(),
            'people' => Person::where('section_id', $sectionId)->count(),
        ];
    }

    /** @param  array<string, int>  $usage */
    private function hasUsage(array $usage): bool
    {
        return array_sum($usage) > 0;
    }
}
