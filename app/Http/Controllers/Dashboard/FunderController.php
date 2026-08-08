<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\AppliesDataTableQueryFilters;
use App\Models\Funder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class FunderController extends Controller
{
    use AppliesDataTableQueryFilters;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('view', Funder::class);

        if ($request->ajax() || $request->wantsJson()) {
            $user = auth()->user();
            $query = Funder::query()->orderBy('name');

            if ($request->column_filters) {
                $this->applyDataTableColumnFilters($query, $request->column_filters, ['name']);
            }

            if ($request->sort_column === 'name' && in_array($request->sort_direction, ['asc', 'desc'], true)) {
                $query->reorder()->orderBy('name', $request->sort_direction);
            }

            return DataTables::eloquent($query)
                ->addIndexColumn()
                ->addColumn('can_edit', fn () => (bool) $user?->can('update', Funder::class))
                ->addColumn('can_delete', fn () => (bool) $user?->can('delete', Funder::class))
                ->make(true);
        }

        return view('dashboard.funders.index');
    }

    public function getFilterOptions(Request $request, string $column): JsonResponse
    {
        $this->authorize('view', Funder::class);

        if ($column !== 'name') {
            return response()->json([]);
        }

        $query = Funder::query()->orderBy('name');

        if ($request->active_filters) {
            $this->applyDataTableColumnFilters($query, $request->active_filters, ['name']);
        }

        $options = $query->pluck('name')
            ->map(fn ($value) => $value === null || $value === '' ? '—' : (string) $value)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return response()->json($options);
    }

    public function create(): View
    {
        $this->authorize('create', Funder::class);

        return view('dashboard.funders.create');
    }

    public function store(Request $request): RedirectResponse
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

    public function edit(Funder $funder): View
    {
        $this->authorize('update', Funder::class);

        return view('dashboard.funders.edit', compact('funder'));
    }

    public function update(Request $request, Funder $funder): RedirectResponse
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

    public function destroy(Funder $funder): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', Funder::class);

        $funder->delete();

        if (request()->ajax()) {
            return response()->json(['message' => 'تم حذف الجهة الممولة بنجاح.']);
        }

        return redirect()
            ->route('dashboard.funders.index')
            ->with('success', 'تم حذف الجهة الممولة بنجاح.');
    }
}
