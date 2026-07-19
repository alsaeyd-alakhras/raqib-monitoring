<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\ValidatesPersonInput;
use App\Models\Center;
use App\Models\Department;
use App\Models\Person;
use App\Models\RoleUser;
use App\Models\Section;
use App\Models\User;
use App\Services\RoleAbilitiesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class DirectoryController extends Controller
{
    use ValidatesPersonInput;

    public function __construct(
        private readonly RoleAbilitiesService $roleAbilities
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorizeDirectoryView();

        if ($request->ajax()) {
            $rows = $this->buildDirectoryRows($request);

            if ($request->column_filters) {
                $rows = $this->applyColumnFilters($rows, $request->column_filters);
            }

            $rows = $this->applySort($rows, $request->sort_column, $request->sort_direction);

            return DataTables::of($rows)
                ->addIndexColumn()
                ->make(true);
        }

        return view('dashboard.directory.index');
    }

    public function data(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function getFilterOptions(Request $request, string $column): JsonResponse
    {
        $this->authorizeDirectoryView();

        $rows = $this->buildDirectoryRows($request);

        if ($request->active_filters) {
            $rows = $this->applyColumnFilters($rows, $request->active_filters);
        }

        $filterable = ['name', 'role_label', 'org_label', 'username', 'is_active_label', 'link_type_label'];

        if (! in_array($column, $filterable, true)) {
            return response()->json([]);
        }

        $options = $rows->pluck($column)
            ->map(fn ($value) => $value === null || $value === '' ? '—' : (string) $value)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return response()->json($options);
    }

    public function roleAbilities(?string $role = null): JsonResponse
    {
        $this->authorizeDirectoryView();

        if ($role === null || $role === '') {
            return response()->json(['abilities' => [], 'map' => $this->roleAbilities->all()]);
        }

        return response()->json([
            'abilities' => $this->roleAbilities->forRole($role),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if (auth()->user()?->can('create', Person::class)) {
            // allowed
        } elseif (auth()->user()?->can('create', User::class)) {
            // allowed for user-only
        } else {
            abort(403);
        }

        return view('dashboard.directory.create', $this->sharedFormMeta() + [
            'record' => null,
            'recordKey' => null,
            'person' => new Person,
            'user' => new User,
            'recordType' => old('record_mode', request('mode') === 'user_only' ? 'user_only' : 'linked'),
            'selectedAbilities' => old('abilities', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $recordMode = $request->input('record_mode', 'linked');

        if ($recordMode === 'user_only') {
            $this->authorize('create', User::class);
        } else {
            $this->authorize('create', Person::class);
        }

        return $this->persistRecord($request, null, null);
    }

    public function edit(string $record): View|RedirectResponse
    {
        [$type, $id] = $this->parseRecordKey($record);

        if ($type === 'person') {
            $person = Person::with(['user.roles', 'department', 'section'])->findOrFail($id);
            $this->ensurePersonVisible($person);

            if ($this->sectionManagerEditingSelf($person)) {
                return redirect()
                    ->route('dashboard.profile.settings')
                    ->with('info', 'لتعديل بياناتك الشخصية استخدم الملف الشخصي من القائمة العلوية.');
            }

            $this->authorize('update', Person::class);

            $user = $person->user ?? new User;
            $recordType = $person->user_id ? 'linked' : 'person_only';
        } else {
            $this->authorize('update', User::class);
            $user = User::with('roles')->findOrFail($id);
            $person = new Person;
            $recordType = 'user_only';
        }

        $selectedAbilities = $user->exists
            ? $user->roles()->pluck('role_name')->toArray()
            : [];

        return view('dashboard.directory.edit', $this->sharedFormMeta($person->exists ? $person : null) + [
            'record' => $record,
            'recordKey' => $record,
            'person' => $person,
            'user' => $user,
            'recordType' => old('record_mode', $recordType),
            'selectedAbilities' => old('abilities', $selectedAbilities),
        ]);
    }

    public function update(Request $request, string $record): RedirectResponse
    {
        [$type, $id] = $this->parseRecordKey($record);

        if ($type === 'person') {
            $person = Person::findOrFail($id);
            $this->ensurePersonVisible($person);
            $this->authorize('update', Person::class);
            $user = $person->user;
        } else {
            $this->authorize('update', User::class);
            $user = User::findOrFail($id);
            $person = null;
        }

        return $this->persistRecord($request, $person, $user);
    }

    public function destroy(string $record): RedirectResponse|JsonResponse
    {
        [$type, $id] = $this->parseRecordKey($record);

        if ($type === 'person') {
            $this->authorize('delete', Person::class);
            $this->ensurePersonCanDelete();
            $person = Person::findOrFail($id);
            $this->ensurePersonVisible($person);
            $person->delete();
        } else {
            $this->authorize('delete', User::class);
            User::findOrFail($id)->delete();
        }

        if (request()->ajax()) {
            return response()->json(['message' => 'تم الحذف بنجاح.']);
        }

        return redirect()
            ->route('dashboard.directory.index')
            ->with('success', 'تم الحذف بنجاح.');
    }

    private function authorizeDirectoryView(): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->can('view', Person::class) || $user?->can('view', User::class),
            403
        );
    }

    /** @return array<string, mixed> */
    private function sharedFormMeta(?Person $person = null): array
    {
        $currentPerson = auth()->user()?->person;
        $isSectionManager = $this->isSectionManagerActor();

        $departmentOptions = Department::with('center')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => (object) [
                'id' => $department->id,
                'name' => $department->name . ($department->center ? ' - ' . $department->center->name : ''),
            ]);

        $roleLabels = ['' => Person::ORDINARY_STAFF_LABEL] + Person::roleLabels();
        if ($isSectionManager) {
            $roleLabels = array_intersect_key($roleLabels, array_flip(['project_manager', 'coordinator']));
        }

        $selectedSection = $person?->section_id
            ? Section::with('department.center')->find($person->section_id)
            : ($isSectionManager ? Section::with('department.center')->find($currentPerson->section_id) : null);

        return [
            'roleLabels' => $roleLabels,
            'rolesRequiringDepartment' => Person::rolesRequiringDepartment(),
            'rolesRequiringSection' => Person::rolesRequiringSection(),
            'centers' => Center::orderBy('name')->get(),
            'departments' => $departmentOptions,
            'roleAbilitiesMap' => $this->roleAbilities->all(),
            'canManageUsers' => auth()->user()?->can('update', User::class) ?? false,
            'canCreateUsers' => auth()->user()?->can('create', User::class) ?? false,
            'departmentsByCenterUrl' => route('dashboard.departments.by-center', ['center' => '__ID__']),
            'sectionsByDepartmentUrl' => route('dashboard.sections.by-department', ['department' => '__ID__']),
            'roleAbilitiesUrl' => route('dashboard.directory.role-abilities', ['role' => '__ROLE__']),
            'selectedCenterId' => old('center_id', $selectedSection?->department?->center_id),
            'selectedDepartmentId' => old('department_id', $selectedSection?->department_id ?? $person?->department_id),
            'selectedSectionId' => old('section_id', $selectedSection?->id ?? ($isSectionManager ? $currentPerson->section_id : null)),
            'lockSectionForSectionManager' => $isSectionManager,
            'limitedPersonFormForSectionManager' => $isSectionManager && $person !== null,
            'sectionManagerCreatingPerson' => $isSectionManager && $person === null,
        ];
    }

    private function buildDirectoryRows(Request $request): Collection
    {
        $actor = auth()->user();
        $rows = collect();

        if ($actor?->can('view', Person::class)) {
            $peopleQuery = Person::with(['user.roles', 'department', 'section'])
                ->visibleToUser($actor);

            if ($request->filled('role')) {
                if ($request->role === '_ordinary') {
                    $peopleQuery->where(function ($q) {
                        $q->whereNull('role')->orWhere('role', '');
                    });
                } else {
                    $peopleQuery->where('role', $request->role);
                }
            }

            if ($request->filled('department_id')) {
                $peopleQuery->where('department_id', $request->department_id);
            }

            if ($request->filled('section_id')) {
                $peopleQuery->where('section_id', $request->section_id);
            }

            if ($request->filled('link_type')) {
                match ($request->link_type) {
                    'linked' => $peopleQuery->whereNotNull('user_id'),
                    'person_only' => $peopleQuery->whereNull('user_id'),
                    default => null,
                };
            }

            if ($request->filled('is_active') && $request->is_active !== '') {
                $peopleQuery->whereHas('user', fn ($q) => $q->where('is_active', (bool) $request->is_active));
            }

            if ($request->filled('q')) {
                $term = '%' . $request->q . '%';
                $peopleQuery->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('user', fn ($uq) => $uq
                            ->where('username', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('name', 'like', $term));
                });
            }

            $rows = $peopleQuery->orderBy('name')->get()->map(fn (Person $person) => $this->personRow($person));
        }

        if ($actor?->can('view', User::class) && ! $this->isSectionManagerActor()) {
            $orphansQuery = User::with('roles')->whereDoesntHave('person');

            if ($request->filled('link_type') && $request->link_type !== 'user_only') {
                // skip orphans unless filter allows
            } elseif ($request->filled('link_type') && $request->link_type === 'user_only') {
                // include orphans
            } elseif ($request->filled('link_type') && in_array($request->link_type, ['linked', 'person_only'], true)) {
                $orphansQuery->whereRaw('1 = 0');
            }

            if ($request->filled('role') || $request->filled('department_id') || $request->filled('section_id')) {
                $orphansQuery->whereRaw('1 = 0');
            }

            if ($request->filled('is_active') && $request->is_active !== '') {
                $orphansQuery->where('is_active', (bool) $request->is_active);
            }

            if ($request->filled('q')) {
                $term = '%' . $request->q . '%';
                $orphansQuery->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            }

            $orphanRows = $orphansQuery->orderBy('name')->get()->map(fn (User $user) => $this->userOnlyRow($user));
            $rows = $rows->concat($orphanRows);
        }

        return $rows->sortBy('name_sort')->values();
    }

    /** @return array<string, mixed> */
    private function personRow(Person $person): array
    {
        $user = $person->user;

        return [
            'record_key' => 'person:' . $person->id,
            'name' => $person->name,
            'name_sort' => $person->name,
            'role_label' => $person->role_label,
            'org_label' => $this->orgLabel($person->department?->name, $person->section?->name),
            'username' => $user?->username ?? '—',
            'is_active_label' => $user ? ($user->is_active ? 'نشط' : 'معطل') : '—',
            'link_type_label' => $user ? 'مربوط' : 'بدون دخول',
            'can_edit' => auth()->user()?->can('update', Person::class) ?? false,
            'can_delete' => auth()->user()?->can('delete', Person::class) && ! $this->isSectionManagerActor(),
        ];
    }

    /** @return array<string, mixed> */
    private function userOnlyRow(User $user): array
    {
        return [
            'record_key' => 'user:' . $user->id,
            'name' => $user->name,
            'name_sort' => $user->name,
            'role_label' => $user->super_admin ? 'مدير النظام' : '—',
            'org_label' => '—',
            'username' => $user->username,
            'is_active_label' => $user->is_active ? 'نشط' : 'معطل',
            'link_type_label' => 'حساب فقط',
            'can_edit' => auth()->user()?->can('update', User::class) ?? false,
            'can_delete' => auth()->user()?->can('delete', User::class) ?? false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyColumnFilters(Collection $rows, array $filters): Collection
    {
        foreach ($filters as $field => $values) {
            if (! is_array($values) || isset($values['from']) || isset($values['to'])) {
                continue;
            }

            $allowed = array_values(array_filter($values, fn ($value) => ! in_array($value, ['الكل', 'all', 'All'], true)));

            if ($allowed === []) {
                continue;
            }

            $rows = $rows->filter(fn (array $row) => in_array((string) ($row[$field] ?? '—'), $allowed, true));
        }

        return $rows->values();
    }

    private function applySort(Collection $rows, ?string $column, ?string $direction): Collection
    {
        if (! $column || ! $direction) {
            return $rows;
        }

        $allowed = ['name', 'name_sort', 'role_label', 'org_label', 'username', 'is_active_label', 'link_type_label'];
        if (! in_array($column, $allowed, true)) {
            return $rows;
        }

        return $direction === 'desc'
            ? $rows->sortByDesc($column)->values()
            : $rows->sortBy($column)->values();
    }

    private function orgLabel(?string $department, ?string $section): string
    {
        $parts = array_filter([$department, $section]);

        return $parts ? implode(' / ', $parts) : '—';
    }

    private function persistRecord(Request $request, ?Person $person, ?User $user): RedirectResponse
    {
        $recordMode = $request->input('record_mode', $person?->user_id ? 'linked' : ($person ? 'person_only' : 'user_only'));
        $hasAccount = $request->boolean('has_account') || $recordMode === 'linked' || $recordMode === 'user_only';
        $isSectionManager = $this->isSectionManagerActor();
        $isSectionManagerUpdate = $isSectionManager && $person !== null;

        if ($recordMode === 'user_only') {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
            ]);
        }

        if ($recordMode !== 'user_only') {
            $oldRole = $person?->role;
            $personData = $this->validatePersonInput(
                $request,
                $person,
                $isSectionManager,
                $isSectionManagerUpdate
            );
        }

        $canManageUsers = auth()->user()?->can($user ? 'update' : 'create', User::class) ?? false;

        if ($hasAccount && $canManageUsers && $recordMode !== 'person_only') {
            $userRules = [
                'username' => ['required', 'string', 'max:255', 'unique:users,username,' . ($user?->id ?? 'NULL')],
                'email' => ['nullable', 'email', 'max:255'],
                'user_type' => ['required', 'in:admin,employee'],
                'is_active' => ['required', 'boolean'],
            ];

            if (! $user?->exists || $request->filled('password')) {
                $userRules['password'] = ['required', 'same:confirm_password'];
                $userRules['confirm_password'] = ['required', 'same:password'];
            }

            $request->validate($userRules, [
                'password.same' => 'كلمة المرور غير متطابقة',
                'confirm_password.same' => 'كلمة المرور غير متطابقة',
            ]);
        }

        DB::beginTransaction();

        try {
        if ($recordMode === 'user_only' || ($person === null && $user !== null && $recordMode !== 'linked')) {
                $user = $this->saveUser($request, $user ?? new User, $canManageUsers);
                if ($canManageUsers) {
                    $this->syncAbilitiesForUserOnly($user, $request);
                }
                DB::commit();

                return redirect()->route('dashboard.directory.index')->with('success', 'تم حفظ الحساب بنجاح.');
            }

            if ($person === null) {
                $person = new Person;
            }

            $person->fill($personData);
            $person->save();

            if ($hasAccount && $canManageUsers) {
                $user = $this->saveUser($request, $user ?? new User, true, $person);
                $person->update(['user_id' => $user->id]);
                $this->syncAbilitiesForUser($user, $request, $person->fresh(), $oldRole ?? null);
            } elseif ($person->user_id && ! $hasAccount) {
                $person->update(['user_id' => null]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('dashboard.directory.index')
            ->with('success', 'تم حفظ السجل بنجاح.');
    }

    private function saveUser(Request $request, User $user, bool $canManage, ?Person $person = null): User
    {
        if (! $canManage) {
            return $user;
        }

        $data = [
            'name' => $person?->name ?? $request->input('name'),
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'user_type' => $request->input('user_type', 'employee'),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        if ($user->exists) {
            $user->update($data);
        } else {
            $user = User::create($data);
        }

        if ($person) {
            $person->update(['name' => $data['name'], 'phone' => $data['phone']]);
        }

        return $user;
    }

    private function syncAbilitiesForUser(User $user, Request $request, Person $person, ?string $oldRole = null): void
    {
        if ($user->super_admin) {
            return;
        }

        $submitted = $request->input('abilities', []);
        $newRole = $person->role;

        if ($request->boolean('reset_role_abilities')) {
            $abilities = $this->roleAbilities->forRole($newRole);
        } elseif ($request->boolean('apply_role_abilities') || ($oldRole !== $newRole && ! $request->has('abilities'))) {
            $current = $user->roles()->pluck('role_name')->toArray();
            $abilities = $this->roleAbilities->mergeOnRoleChange($oldRole, $newRole, $current);
        } else {
            $abilities = is_array($submitted) ? $submitted : [];
            if ($abilities === [] && $newRole) {
                $abilities = $this->roleAbilities->forRole($newRole);
            }
        }

        if ($request->input('user_type', $user->user_type) === 'employee') {
            $abilities = array_values(array_unique(array_merge(
                ['aiddistributions.view', 'aiddistributions.create', 'aiddistributions.update'],
                $abilities
            )));
        }

        RoleUser::where('user_id', $user->id)->delete();

        foreach (array_unique($abilities) as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }
    }

    private function syncAbilitiesForUserOnly(User $user, Request $request): void
    {
        if ($user->super_admin) {
            return;
        }

        $abilities = $request->input('abilities', []);
        if (! is_array($abilities)) {
            $abilities = [];
        }

        if ($request->input('user_type', $user->user_type) === 'employee') {
            $abilities = array_values(array_unique(array_merge(
                ['aiddistributions.view', 'aiddistributions.create', 'aiddistributions.update'],
                $abilities
            )));
        }

        RoleUser::where('user_id', $user->id)->delete();

        foreach (array_unique($abilities) as $ability) {
            RoleUser::create([
                'role_name' => $ability,
                'user_id' => $user->id,
                'ability' => 'allow',
            ]);
        }
    }

    /** @return array{0: string, 1: int} */
    private function parseRecordKey(string $record): array
    {
        if (! str_contains($record, ':')) {
            abort(404);
        }

        [$type, $id] = explode(':', $record, 2);

        if (! in_array($type, ['person', 'user'], true)) {
            abort(404);
        }

        return [$type, (int) $id];
    }

    private function sectionManagerEditingSelf(Person $person): bool
    {
        if (! $this->isSectionManagerActor()) {
            return false;
        }

        return (int) auth()->user()?->person?->id === (int) $person->id;
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
