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
    public function index(): View
    {
        $this->authorize('view', Department::class);

        $departments = Department::with('center')
            ->orderBy('name')
            ->paginate(15);

        return view('dashboard.departments.index', compact('departments'));
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        $centers = Center::orderBy('name')->get();

        return view('dashboard.departments.create', compact('centers'));
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
            ->route('dashboard.departments.index')
            ->with('success', 'تم إنشاء القسم بنجاح.');
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', Department::class);

        $centers = Center::orderBy('name')->get();

        return view('dashboard.departments.edit', compact('department', 'centers'));
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
            ->route('dashboard.departments.index')
            ->with('success', 'تم تحديث القسم بنجاح.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', Department::class);

        $department->delete();

        return redirect()
            ->route('dashboard.departments.index')
            ->with('success', 'تم حذف القسم بنجاح.');
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
