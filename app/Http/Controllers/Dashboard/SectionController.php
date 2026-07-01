<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(): View
    {
        $this->authorize('view', Section::class);

        $sections = Section::with(['department.center'])
            ->orderBy('name')
            ->paginate(15);

        return view('dashboard.sections.index', compact('sections'));
    }

    public function create(): View
    {
        $this->authorize('create', Section::class);

        $departments = Department::with('center')
            ->orderBy('name')
            ->get();

        return view('dashboard.sections.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Section::class);

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Section::create($validated);

        return redirect()
            ->route('dashboard.sections.index')
            ->with('success', 'تم إنشاء الشعبة بنجاح.');
    }

    public function edit(Section $section): View
    {
        $this->authorize('update', Section::class);

        $departments = Department::with('center')
            ->orderBy('name')
            ->get();

        return view('dashboard.sections.edit', compact('section', 'departments'));
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $this->authorize('update', Section::class);

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $section->update($validated);

        return redirect()
            ->route('dashboard.sections.index')
            ->with('success', 'تم تحديث الشعبة بنجاح.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        $this->authorize('delete', Section::class);

        $section->delete();

        return redirect()
            ->route('dashboard.sections.index')
            ->with('success', 'تم حذف الشعبة بنجاح.');
    }
}
