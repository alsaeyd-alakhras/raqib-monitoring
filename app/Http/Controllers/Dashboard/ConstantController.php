<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Constant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConstantController extends Controller
{
    public function index(): View
    {
        $this->authorize('view', Constant::class);

        $constants = Constant::get();

        return view('dashboard.pages.constants', compact('constants'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('update', Constant::class);

        foreach ($request->except('_token') as $key => $value) {
            Constant::updateOrCreate([
                'key' => $key,
            ], [
                'value' => $value,
            ]);
        }

        return redirect()->route('dashboard.constants.index')->with('success', 'تم تحديث القيم');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorize('update', Constant::class);

        if ($request->state_effectiveness) {
            Constant::findOrFail($request->state_effectiveness)->delete();
        }

        return redirect()->route('dashboard.constants.index')->with('danger', 'تم حذف القيمة المحددة');
    }
}
