<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Department;
use App\Models\Person;
use App\Models\Section;
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

        $people = Person::with(['user', 'department', 'section'])
            ->visibleToUser(auth()->user())
            ->orderBy('name')
            ->paginate(15);

        $sectionManagerNotice = $this->sectionManagerPeopleNotice($people);

        return view('dashboard.people.index', compact('people', 'sectionManagerNotice'));
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
        $this->ensurePersonVisible($person);

        if ($this->sectionManagerEditingSelf($person)) {
            return redirect()
                ->route('dashboard.profile.settings')
                ->with('info', 'لتعديل بياناتك الشخصية استخدم الملف الشخصي من القائمة العلوية.');
        }

        return view('dashboard.people.edit', $this->formData($person) + ['person' => $person]);
    }

    public function update(Request $request, Person $person)
    {
        $this->authorize('update', Person::class);
        $this->ensurePersonVisible($person);

        if ($this->sectionManagerEditingSelf($person)) {
            return redirect()
                ->route('dashboard.profile.settings')
                ->with('info', 'لتعديل بياناتك الشخصية استخدم الملف الشخصي من القائمة العلوية.');
        }

        $validated = $this->validatePerson($request, $person);

        $person->update($validated);

        return redirect()
            ->route('dashboard.people.index')
            ->with('success', 'تم تعديل بيانات الشخص بنجاح.');
    }

    public function destroy(Person $person)
    {
        $this->authorize('delete', Person::class);
        $this->ensurePersonCanDelete();

        $person->delete();

        return redirect()
            ->route('dashboard.people.index')
            ->with('success', 'تم حذف الشخص بنجاح.');
    }

    private function formData(?Person $person = null): array
    {
        $currentPerson = auth()->user()?->person;
        $isSectionManager = $this->isSectionManagerActor();
        $isSectionManagerEdit = $isSectionManager && $person !== null;

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

        $occupiedSectionManagers = Person::query()
            ->where('role', 'section_manager')
            ->whereNotNull('section_id')
            ->when($person, fn ($query) => $query->where('id', '!=', $person->id))
            ->pluck('name', 'section_id')
            ->map(fn ($name, $sectionId) => ['id' => (int) $sectionId, 'manager' => $name])
            ->values();

        $roleLabels = ['' => Person::ORDINARY_STAFF_LABEL] + Person::roleLabels();
        if ($isSectionManager) {
            $roleLabels = array_intersect_key($roleLabels, array_flip(['project_manager', 'coordinator']));
        }

        $selectedSection = $person?->section_id
            ? Section::with('department.center')->find($person->section_id)
            : ($isSectionManager ? Section::with('department.center')->find($currentPerson->section_id) : null);

        return [
            'users' => User::orderBy('name')->get(),
            'centers' => Center::orderBy('name')->get(),
            'departments' => $departmentOptions,
            'roleLabels' => $roleLabels,
            'rolesRequiringDepartment' => Person::rolesRequiringDepartment(),
            'rolesRequiringSection' => Person::rolesRequiringSection(),
            'occupiedDepartmentManagers' => $occupiedDepartmentManagers,
            'occupiedSectionManagers' => $occupiedSectionManagers,
            'departmentsByCenterUrl' => route('dashboard.departments.by-center', ['center' => '__ID__']),
            'sectionsByDepartmentUrl' => route('dashboard.sections.by-department', ['department' => '__ID__']),
            'selectedCenterId' => old('center_id', $selectedSection?->department?->center_id),
            'selectedDepartmentId' => old('department_id', $selectedSection?->department_id ?? $person?->department_id),
            'selectedSectionId' => old('section_id', $selectedSection?->id ?? ($isSectionManager ? $currentPerson->section_id : null)),
            'lockSectionForSectionManager' => $isSectionManager,
            'limitedPersonFormForSectionManager' => $isSectionManagerEdit,
            'sectionManagerCreatingPerson' => $isSectionManager && $person === null,
        ];
    }

    private function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', Rule::in(Person::ROLES)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function validatePerson(Request $request, ?Person $except = null): array
    {
        $currentPerson = auth()->user()?->person;
        $isSectionManager = $this->isSectionManagerActor();
        $isSectionManagerUpdate = $isSectionManager && $except !== null;

        $rules = $this->validationRules();

        if ($isSectionManagerUpdate) {
            $rules = [
                'role' => ['required', 'string', Rule::in(['project_manager', 'coordinator'])],
                'job_title' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
            ];
        } elseif ($isSectionManager) {
            $rules['role'] = ['required', 'string', Rule::in(['project_manager', 'coordinator'])];
            $rules['section_id'] = ['required', 'exists:sections,id'];
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $except, $currentPerson, $isSectionManager, $isSectionManagerUpdate) {
            if ($isSectionManagerUpdate) {
                return;
            }

            $role = $request->input('role');
            $departmentId = $request->input('department_id');
            $sectionId = $request->input('section_id');

            if (empty($role)) {
                return;
            }

            if ($isSectionManager) {
                if ((int) $sectionId !== (int) $currentPerson->section_id) {
                    $validator->errors()->add('section_id', 'لا يمكنك إضافة أو تعديل أشخاص خارج قسمك.');
                }
            }

            if (in_array($role, Person::rolesRequiringDepartment(), true) && empty($departmentId)) {
                $validator->errors()->add(
                    'department_id',
                    'الدائرة إلزامية لدور «' . (Person::roleLabels()[$role] ?? $role) . '».'
                );
            }

            if (in_array($role, Person::rolesRequiringSection(), true) && empty($sectionId)) {
                $validator->errors()->add(
                    'section_id',
                    'القسم إلزامي لدور «' . (Person::roleLabels()[$role] ?? $role) . '».'
                );
            }

            if ($sectionId) {
                $section = Section::find($sectionId);

                if (! $section) {
                    $validator->errors()->add('section_id', 'القسم المحدد غير موجود.');
                } elseif ($departmentId && (int) $section->department_id !== (int) $departmentId) {
                    $validator->errors()->add('section_id', 'القسم المختار لا يتبع الدائرة المحددة.');
                }
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

            if ($role === 'section_manager' && $sectionId) {
                $existingManager = Person::query()
                    ->with('section')
                    ->where('role', 'section_manager')
                    ->where('section_id', $sectionId)
                    ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
                    ->first();

                if ($existingManager) {
                    $validator->errors()->add(
                        'section_id',
                        'القسم «' . ($existingManager->section?->name ?? '') . '» لديه مدير قسم بالفعل: ' . $existingManager->name . '.'
                    );
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if ($isSectionManagerUpdate && $except) {
            return array_merge($except->only([
                'name',
                'department_id',
                'section_id',
                'user_id',
                'organization',
            ]), $validated);
        }

        if (! empty($validated['section_id'])) {
            $section = Section::findOrFail($validated['section_id']);
            $validated['department_id'] = $section->department_id;
        } elseif (($validated['role'] ?? null) === 'department_manager') {
            $validated['section_id'] = null;
        } elseif (! in_array($validated['role'] ?? null, Person::rolesRequiringSection(), true)) {
            $validated['section_id'] = null;
        }

        if (empty($validated['role'])) {
            $validated['role'] = null;
        }

        if ($isSectionManager) {
            $validated['section_id'] = $currentPerson->section_id;
            $validated['department_id'] = $currentPerson->department_id;
        }

        return $validated;
    }

    private function isSectionManagerActor(): bool
    {
        $user = auth()->user();

        return $user?->person?->role === 'section_manager' && ! $user->super_admin;
    }

    private function sectionManagerEditingSelf(Person $person): bool
    {
        if (! $this->isSectionManagerActor()) {
            return false;
        }

        return (int) auth()->user()?->person?->id === (int) $person->id;
    }

    private function sectionManagerPeopleNotice($people): ?string
    {
        if (! $this->isSectionManagerActor()) {
            return null;
        }

        $actor = auth()->user()?->person?->loadMissing('section');
        $sectionName = $actor?->section?->name ?? 'قسمك';

        $teamCount = Person::query()
            ->where('section_id', $actor?->section_id)
            ->whereIn('role', ['project_manager', 'coordinator'])
            ->count();

        if ($teamCount > 0) {
            return null;
        }

        return 'لا يوجد مديرو مشروع أو منسقون مرتبطون بـ «' . $sectionName . '» حالياً. '
            . 'يمكنك إضافتهم من زر (+) أو اطلب من الأدمن مزامنة بيانات التجربة: php artisan db:seed --class=DemoUsersSeeder';
    }

    private function ensurePersonVisible(Person $person): void
    {
        if (auth()->user()?->super_admin) {
            return;
        }

        abort_unless($person->isVisibleToUser(auth()->user()), 403);
    }

    private function ensurePersonCanDelete(): void
    {
        if (auth()->user()?->person?->role === 'section_manager' && ! auth()->user()?->super_admin) {
            abort(403, 'مدير القسم لا يمكنه حذف الأشخاص.');
        }
    }
}
