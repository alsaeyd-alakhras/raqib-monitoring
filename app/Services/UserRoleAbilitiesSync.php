<?php

namespace App\Services;

use App\Models\RoleUser;
use App\Models\User;

class UserRoleAbilitiesSync
{
    /** @var array<int, string> */
    private const LEGACY_EMPLOYEE_ABILITIES = [
        'aiddistributions.view',
        'aiddistributions.create',
        'aiddistributions.update',
    ];

    public function __construct(
        private readonly RoleAbilitiesService $roleAbilities,
    ) {}

    public function syncFromRole(User $user, ?string $role, bool $includeLegacyEmployee = true): void
    {
        if ($user->super_admin) {
            return;
        }

        $abilities = $this->roleAbilities->forRole($role);

        if ($includeLegacyEmployee && $user->user_type === 'employee') {
            $abilities = array_values(array_unique(array_merge(
                self::LEGACY_EMPLOYEE_ABILITIES,
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
}
