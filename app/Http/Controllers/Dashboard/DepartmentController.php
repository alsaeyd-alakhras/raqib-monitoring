<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $this->authorize('view', Department::class);

        return redirect()->route('dashboard.org-structure.index');
    }

    public function create(): RedirectResponse
    {
        $this->authorize('create', Department::class);

        return redirect()->route('dashboard.org-structure.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        $validated = $request->validate([
            'center_id' => ['required', 'exists:centers,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Department::create($validated);

        return redirect()
            ->route('dashboard.org-structure.index')
            ->with('success', 'تم إنشاء الدائرة بنجاح.');
    }

    public function edit(Department $department): RedirectResponse
    {
        $this->authorize('update', Department::class);

        return redirect()->route('dashboard.org-structure.index');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('update', Department::class);

        $validated = $request->validate([
            'center_id' => ['required', 'exists:centers,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department->update($validated);

        return redirect()
            ->route('dashboard.org-structure.index')
            ->with('success', 'تم تحديث الدائرة بنجاح.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', Department::class);

        $department->delete();

        return redirect()
            ->route('dashboard.org-structure.index')
            ->with('success', 'تم حذف الدائرة بنجاح.');
    }

    public function byCenter(Request $request): JsonResponse
    {
        $centerId = $request->route('center') ?? $request->center_id;

        $departments = Department::where('center_id', $centerId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($departments);
    }
}
