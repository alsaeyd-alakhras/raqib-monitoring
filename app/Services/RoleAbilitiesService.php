<?php

namespace App\Services;

class RoleAbilitiesService
{
    /** @var array<string, array<int, string>> */
    private array $map;

    /**
     * @param  array<string, array<int, string>>|null  $map
     */
    public function __construct(?array $map = null)
    {
        $this->map = $map ?? require base_path('data/role-abilities.php');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function all(): array
    {
        return $this->map;
    }

    /**
     * @return array<int, string>
     */
    public function forRole(?string $role): array
    {
        if (! $role) {
            return [];
        }

        return array_values(array_unique($this->map[$role] ?? []));
    }

    /**
     * دمج ذكي عند تغيير الدور: أساسيات الدور الجديد + أي صلاحيات إضافية خارج أساسيات الدور القديم.
     *
     * @param  array<int, string>  $currentAbilities
     * @return array<int, string>
     */
    public function mergeOnRoleChange(?string $oldRole, ?string $newRole, array $currentAbilities): array
    {
        $oldBase = $this->forRole($oldRole);
        $newBase = $this->forRole($newRole);
        $extras = array_values(array_diff($currentAbilities, $oldBase));

        return array_values(array_unique(array_merge($newBase, $extras)));
    }

    /**
     * @param  array<int, string>  $currentAbilities
     * @return array<int, string>
     */
    public function resetToRole(?string $role, array $currentAbilities = []): array
    {
        return $this->forRole($role);
    }
}
