<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Funder;
use Illuminate\Http\Request;

class FunderController extends Controller
{
    public function index()
    {
        $this->authorize('view', Funder::class);

        $funders = Funder::orderBy('name')->paginate(15);

        return view('dashboard.funders.index', compact('funders'));
    }

    public function create()
    {
        $this->authorize('create', Funder::class);

        return view('dashboard.funders.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Funder::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Funder::create($validated);

        return redirect()
            ->route('dashboard.funders.index')
            ->with('success', 'تم إضافة الجهة الممولة بنجاح.');
    }

    public function edit(Funder $funder)
    {
        $this->authorize('update', Funder::class);

        return view('dashboard.funders.edit', compact('funder'));
    }

    public function update(Request $request, Funder $funder)
    {
        $this->authorize('update', Funder::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $funder->update($validated);

        return redirect()
            ->route('dashboard.funders.index')
            ->with('success', 'تم تعديل الجهة الممولة بنجاح.');
    }

    public function destroy(Funder $funder)
    {
        $this->authorize('delete', Funder::class);

        $funder->delete();

        return redirect()
            ->route('dashboard.funders.index')
            ->with('success', 'تم حذف الجهة الممولة بنجاح.');
    }
}
