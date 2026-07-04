<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonController extends Controller
{
    public function index()
    {
        $this->authorize('view', Person::class);

        $people = Person::with(['user', 'department'])
            ->orderBy('name')
            ->paginate(15);

        return view('dashboard.people.index', compact('people'));
    }

    public function create()
    {
        $this->authorize('create', Person::class);

        return view('dashboard.people.create', $this->formData());
    }

    public function store(Request $request)
    {
        $this->authorize('create', Person::class);

        $validated = $this->validatePerson($request);

        Person::create($validated);

        return redirect()
            ->route('dashboard.people.index')
            ->with('success', 'تم إضافة الشخص بنجاح.');
    }

    public function edit(Person $person)
    {
        $this->authorize('update', Person::class);

        return view('dashboard.people.edit', $this->formData($person) + ['person' => $person]);
    }

    public function update(Request $request, Person $person)
    {
        $this->authorize('update', Person::class);

        $validated = $this->validatePerson($request, $person);

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

    private function formData(?Person $person = null): array
    {
        $departmentOptions = Department::with('center')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => (object) [
                'id' => $department->id,
                'name' => $department->name . ($department->center ? ' - ' . $department->center->name : ''),
            ]);

        $occupiedDepartmentManagers = Person::query()
            ->where('role', 'department_manager')
            ->whereNotNull('department_id')
            ->when($person, fn ($query) => $query->where('id', '!=', $person->id))
            ->pluck('name', 'department_id')
            ->map(fn ($name, $departmentId) => ['id' => (int) $departmentId, 'manager' => $name])
            ->values();

        return [
            'users' => User::orderBy('name')->get(),
            'departments' => $departmentOptions,
            'roleLabels' => Person::roleLabels(),
            'rolesRequiringDepartment' => Person::rolesRequiringDepartment(),
            'occupiedDepartmentManagers' => $occupiedDepartmentManagers,
        ];
    }

    private function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(Person::ROLES)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function validatePerson(Request $request, ?Person $except = null): array
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        $validator->after(function ($validator) use ($request, $except) {
            $role = $request->input('role');
            $departmentId = $request->input('department_id');

            if (in_array($role, Person::rolesRequiringDepartment(), true) && empty($departmentId)) {
                $validator->errors()->add(
                    'department_id',
                    'الدائرة إلزامية لدور «' . (Person::roleLabels()[$role] ?? $role) . '».'
                );
            }

            if ($role === 'department_manager' && $departmentId) {
                $existingManager = Person::query()
                    ->with('department')
                    ->where('role', 'department_manager')
                    ->where('department_id', $departmentId)
                    ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
                    ->first();

                if ($existingManager) {
                    $validator->errors()->add(
                        'department_id',
                        'الدائرة «' . ($existingManager->department?->name ?? '') . '» لديها مدير دائرة بالفعل: ' . $existingManager->name . '.'
                    );
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
