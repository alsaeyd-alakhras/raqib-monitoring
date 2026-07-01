<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index()
    {
        $this->authorize('view', Person::class);

        $people = Person::with('user')
            ->orderBy('name')
            ->paginate(15);

        return view('dashboard.people.index', compact('people'));
    }

    public function create()
    {
        $this->authorize('create', Person::class);

        $users = User::orderBy('name')->get();

        return view('dashboard.people.create', compact('users'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Person::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        Person::create($validated);

        return redirect()
            ->route('dashboard.people.index')
            ->with('success', 'تم إضافة الشخص بنجاح.');
    }

    public function edit(Person $person)
    {
        $this->authorize('update', Person::class);

        $users = User::orderBy('name')->get();

        return view('dashboard.people.edit', compact('person', 'users'));
    }

    public function update(Request $request, Person $person)
    {
        $this->authorize('update', Person::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $person->update($validated);

        return redirect()
            ->route('dashboard.people.index')
            ->with('success', 'تم تعديل بيانات الشخص بنجاح.');
    }

    public function destroy(Person $person)
    {
        $this->authorize('delete', Person::class);

        $person->delete();

        return redirect()
            ->route('dashboard.people.index')
            ->with('success', 'تم حذف الشخص بنجاح.');
    }
}
