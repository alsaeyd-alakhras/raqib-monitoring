<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Center;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CenterController extends Controller
{
    public function index(): View
    {
        $this->authorize('view', Center::class);

        $centers = Center::orderBy('name')->paginate(15);

        return view('dashboard.centers.index', compact('centers'));
    }

    public function create(): View
    {
        $this->authorize('create', Center::class);

        return view('dashboard.centers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Center::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Center::create($validated);

        return redirect()
            ->route('dashboard.centers.index')
            ->with('success', 'تم إنشاء المركز بنجاح.');
    }

    public function edit(Center $center): View
    {
        $this->authorize('update', Center::class);

        return view('dashboard.centers.edit', compact('center'));
    }

    public function update(Request $request, Center $center): RedirectResponse
    {
        $this->authorize('update', Center::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $center->update($validated);

        return redirect()
            ->route('dashboard.centers.index')
            ->with('success', 'تم تحديث المركز بنجاح.');
    }

    public function destroy(Center $center): RedirectResponse
    {
        $this->authorize('delete', Center::class);

        $center->delete();

        return redirect()
            ->route('dashboard.centers.index')
            ->with('success', 'تم حذف المركز بنجاح.');
    }
}
