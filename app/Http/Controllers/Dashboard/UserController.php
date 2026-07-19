<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\RoleUser;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    private const EMPLOYEE_DEFAULT_ABILITIES = [
        'aiddistributions.view',
        'aiddistributions.create',
        'aiddistributions.update',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view', User::class);

        return redirect()->route('dashboard.directory.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $this->authorize('create', User::class);

        return redirect()->route('dashboard.directory.create', ['mode' => 'user_only']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $request->validate([
            'name' => 'required',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|same:confirm_password',
            'confirm_password' => 'required|same:password',
            'user_type' => 'required|in:admin,employee',
            'is_active' => 'required|boolean',
        ], [
            'password.same' => 'كلمة المرور غير متطابقة',
            'confirm_password.same' => 'كلمة المرور غير متطابقة',
        ]);
        $abilities = $this->normalizeAbilitiesForUserType(
            $request->user_type,
            $request->input('abilities', [])
        );

        DB::beginTransaction();
        try {
            if ($request->has('avatar')) {
                $avatar = $request->file('avatar');
                $path = $avatar->store('avatars', 'public');
                $request->merge(['avatar' => $path]);
            }
            $user = User::create($request->all());
            foreach ($abilities as $role) {
                RoleUser::create([
                    'role_name' => $role,
                    'user_id' => $user->id,
                    'ability' => 'allow',
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->route('dashboard.directory.index')->with('success', 'تم اضافة مستخدم جديد');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {

        if (Auth::user()->id != $user->id && !Auth::user()->can('view', User::class)) {
            abort(403);
        }
        $profile = Auth::user()->id == $user->id && !Auth::user()->can('view', User::class) ? true : false;
        $logs = ActivityLog::where('user_id', $user->id)->orderBy('created_at', 'DESC')->paginate(20);
        return view('dashboard.users.show', compact('user', 'logs', 'profile'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function settings(Request $request)
    {
        $user = Auth::user();

        return view('dashboard.users.settings', compact('user'));
    }

    public function completePhone()
    {
        $user = Auth::user();

        if ($user->super_admin || ! empty($user->person?->phone)) {
            return redirect()->route('dashboard.home');
        }

        return view('dashboard.profile.complete-phone');
    }

    public function storeCompletePhone(Request $request)
    {
        $user = Auth::user();
        $person = $user->person;

        abort_unless($person, 404);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
        ]);

        $person->update([
            'phone' => $validated['phone'],
        ]);

        $user->update([
            'phone' => $validated['phone'],
        ]);

        return redirect()
            ->route('dashboard.home')
            ->with('success', 'تم حفظ رقم الجوال بنجاح.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $user)
    {
        $this->authorize('update', User::class);

        if ($user->person) {
            return redirect()->route('dashboard.directory.edit', 'person:' . $user->person->id);
        }

        return redirect()->route('dashboard.directory.edit', 'user:' . $user->id);
    }

    /**
     * Update the authenticated user's profile settings.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'name' => 'required',
            'username' => 'required|string|unique:users,username,' . $user->id,
            'email' => 'nullable|email',
            'phone' => ['nullable', 'string', 'max:50'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'required|same:confirm_password';
            $rules['confirm_password'] = 'required|same:password';
        }

        $validated = $request->validate($rules, [
            'password.same' => 'كلمة المرور غير متطابقة',
            'confirm_password.same' => 'كلمة المرور غير متطابقة',
        ]);

        DB::beginTransaction();
        try {
            $userOld = $user->toArray();
            $oldAvatar = $user->avatar;

            if ($request->hasFile('avatarUpload')) {
                if ($oldAvatar !== null) {
                    Storage::disk('public')->delete($oldAvatar);
                }
                $path = $request->file('avatarUpload')->store('avatars', 'public');
            } else {
                $path = $user->avatar;
            }

            $updateData = [
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $request->email,
                'phone' => $validated['phone'] ?? null,
                'avatar' => $path,
            ];

            if ($request->filled('password')) {
                $updateData['password'] = $request->password;
            }

            $user->update($updateData);

            if ($user->person) {
                $user->person->update([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'] ?? null,
                ]);
            }

            ActivityLogService::log(
                'Updated',
                'User',
                "تم تحديث الملف الشخصي : {$user->name}.",
                $userOld,
                $user->getChanges()
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('dashboard.profile.settings')->with('success', 'تم تحديث الملف الشخصي');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', User::class);
        $request->validate([
            'name' => 'required',
            'username' => 'required|string|unique:users,username,' . $user->id,
        ]);
        DB::beginTransaction();
        try {
            $userOld = $user->toArray();
            $oldAvatar = $user->avatar;
            if ($request->has('avatarUpload')) {
                if ($oldAvatar != null) {
                    Storage::disk('public')->delete($oldAvatar);
                }
                $avatar = $request->file('avatarUpload');
                $path = $avatar->store('avatars', 'public');
                $request->merge(['avatar' => $path]);
            }
            $avatar = $request->avatar ?? $user->avatar;
            if (isset($request->password)) {
                $user->update($request->all());
            }
            $nextUserType = $request->user_type ?? $user->user_type;
            $abilities = $this->normalizeAbilitiesForUserType(
                $nextUserType,
                $request->input('abilities', [])
            );

            $user->update([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'avatar' => $avatar ?? null,
                'user_type' => $nextUserType,
                'is_active' => $request->is_active ?? $user->is_active,
            ]);
            $this->syncUserAbilities($user, $abilities);
            ActivityLogService::log(
                'Updated',
                'User',
                "تم تحديث المستخدم : {$user->name}.",
                $userOld,
                $user->getChanges()
            );
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return redirect()->route('dashboard.directory.index')->with('success', 'تم تعديل المستخدم');
    }

    private function normalizeAbilitiesForUserType(string $userType, array $abilities): array
    {
        if ($userType === 'employee') {
            return array_values(array_unique(array_merge(self::EMPLOYEE_DEFAULT_ABILITIES, $abilities)));
        }

        return array_values(array_unique($abilities));
    }

    private function syncUserAbilities(User $user, array $abilities): void
    {
        $roleOld = RoleUser::where('user_id', $user->id)->pluck('role_name')->toArray();

        foreach ($roleOld as $role) {
            if (!in_array($role, $abilities, true)) {
                RoleUser::where('user_id', $user->id)->where('role_name', $role)->delete();
            }
        }

        foreach ($abilities as $role) {
            $roleRecord = RoleUser::where('user_id', $user->id)->where('role_name', $role)->first();
            if ($roleRecord === null) {
                RoleUser::create([
                    'role_name' => $role,
                    'user_id' => $user->id,
                    'ability' => 'allow',
                ]);
            } else {
                $roleRecord->update(['ability' => 'allow']);
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', User::class);
        if ($user->avatar != null) {
            Storage::disk('public')->delete($user->avatar);
        }
        $user->delete();
        return redirect()->route('dashboard.directory.index')->with('success', 'تم حذف المستخدم');
    }
}
