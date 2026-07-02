<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ChecklistGroup;
use App\Models\ChecklistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChecklistAdminController extends Controller
{
    public function index(): View
    {
        $this->authorize('checklist_admin.manage');

        $groups = ChecklistGroup::orderBy('order')
            ->with(['items' => fn ($q) => $q->orderBy('order')])
            ->get();

        return view('dashboard.checklist-admin.index', compact('groups'));
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $this->authorize('checklist_admin.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $validated['order'] = (ChecklistGroup::max('order') ?? 0) + 1;
        $validated['is_active'] = true;

        ChecklistGroup::create($validated);

        return back()->with('success', 'تمت إضافة المجموعة.');
    }

    public function updateGroup(Request $request, ChecklistGroup $group): RedirectResponse
    {
        $this->authorize('checklist_admin.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $group->update($validated);

        return back()->with('success', 'تم تحديث المجموعة.');
    }

    public function toggleGroup(ChecklistGroup $group): RedirectResponse
    {
        $this->authorize('checklist_admin.manage');

        $group->update(['is_active' => ! $group->is_active]);

        return back()->with('success', 'تم تحديث حالة المجموعة.');
    }

    public function moveGroup(Request $request, ChecklistGroup $group): RedirectResponse
    {
        $this->authorize('checklist_admin.manage');

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $sibling = ChecklistGroup::when(
            $direction === 'up',
            fn ($q) => $q->where('order', '<', $group->order)->orderBy('order', 'desc'),
            fn ($q) => $q->where('order', '>', $group->order)->orderBy('order', 'asc'),
        )->first();

        if ($sibling) {
            [$groupOrder, $siblingOrder] = [$group->order, $sibling->order];
            $group->update(['order' => $siblingOrder]);
            $sibling->update(['order' => $groupOrder]);
        }

        return back();
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->authorize('checklist_admin.manage');

        $validated = $request->validate([
            'group_id' => ['required', 'exists:checklist_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'has_person_field' => ['nullable', 'boolean'],
        ]);

        $validated['has_person_field'] = (bool) ($validated['has_person_field'] ?? false);
        $validated['order'] = (ChecklistItem::where('group_id', $validated['group_id'])->max('order') ?? 0) + 1;
        $validated['is_active'] = true;

        ChecklistItem::create($validated);

        return back()->with('success', 'تمت إضافة البند.');
    }

    public function updateItem(Request $request, ChecklistItem $item): RedirectResponse
    {
        $this->authorize('checklist_admin.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'has_person_field' => ['nullable', 'boolean'],
        ]);

        $validated['has_person_field'] = (bool) ($validated['has_person_field'] ?? false);

        $item->update($validated);

        return back()->with('success', 'تم تحديث البند.');
    }

    public function toggleItem(ChecklistItem $item): RedirectResponse
    {
        $this->authorize('checklist_admin.manage');

        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('success', 'تم تحديث حالة البند.');
    }

    public function moveItem(Request $request, ChecklistItem $item): RedirectResponse
    {
        $this->authorize('checklist_admin.manage');

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $sibling = ChecklistItem::where('group_id', $item->group_id)
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('order', '<', $item->order)->orderBy('order', 'desc'),
                fn ($q) => $q->where('order', '>', $item->order)->orderBy('order', 'asc'),
            )->first();

        if ($sibling) {
            [$itemOrder, $siblingOrder] = [$item->order, $sibling->order];
            $item->update(['order' => $siblingOrder]);
            $sibling->update(['order' => $itemOrder]);
        }

        return back();
    }
}
