<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Person extends Model
{
    use HasFactory;

    public const ROLES = [
        'project_manager',
        'project_secretariat',
        'coordinator',
        'section_manager',
        'department_manager',
        'monitoring_director',
        'monitor',
        'general_management',
        'admin',
    ];

    /** @var list<string> */
    public const ADDITIONAL_ROLES = [
        'project_manager',
        'coordinator',
    ];

    public const ORDINARY_STAFF_LABEL = 'موظف عادي';

    protected $fillable = [
        'name',
        'role',
        'additional_roles',
        'department_id',
        'section_id',
        'user_id',
        'job_title',
        'organization',
        'phone',
        'alternate_phone',
    ];

    protected function casts(): array
    {
        return [
            'additional_roles' => 'array',
        ];
    }

    public static function roleLabels(): array
    {
        return [
            'project_manager' => 'مدير مشروع',
            'project_secretariat' => 'سكرتاريا الدائرة',
            'coordinator' => 'منسق',
            'section_manager' => 'مدير قسم',
            'department_manager' => 'مدير دائرة',
            'monitoring_director' => 'مدير الرقابة العامة',
            'monitor' => 'مراقب',
            'general_management' => 'الإدارة العامة',
            'admin' => 'أدمن النظام',
        ];
    }

    /** @return list<string> */
    public static function allowedAdditionalRolesFor(?string $primaryRole): array
    {
        return $primaryRole === 'section_manager'
            ? self::ADDITIONAL_ROLES
            : [];
    }

    /** أدوار تتطلب انتماءً لدائرة عند الحفظ */
    public static function rolesRequiringDepartment(): array
    {
        return ['department_manager'];
    }

    /** أدوار تتطلب انتماءً لقسم عند الحفظ */
    public static function rolesRequiringSection(): array
    {
        return ['section_manager', 'project_manager', 'coordinator'];
    }

    public static function departmentHasManager(int $departmentId, ?int $exceptPersonId = null): bool
    {
        return self::query()
            ->where('role', 'department_manager')
            ->where('department_id', $departmentId)
            ->when($exceptPersonId, fn ($query) => $query->where('id', '!=', $exceptPersonId))
            ->exists();
    }

    public static function sectionHasManager(int $sectionId, ?int $exceptPersonId = null): bool
    {
        return self::query()
            ->where('role', 'section_manager')
            ->where('section_id', $sectionId)
            ->when($exceptPersonId, fn ($query) => $query->where('id', '!=', $exceptPersonId))
            ->exists();
    }

    public static function monitoringDirector(?int $exceptPersonId = null): ?Person
    {
        return self::query()
            ->where('role', 'monitoring_director')
            ->when($exceptPersonId, fn ($query) => $query->where('id', '!=', $exceptPersonId))
            ->first();
    }

    /** @return list<string> */
    public static function rolesLimitedToOneGlobally(): array
    {
        return ['monitoring_director'];
    }

    public function primaryRole(): ?string
    {
        $role = $this->role;

        return ($role === null || $role === '') ? null : $role;
    }

    /** @return list<string> */
    public function additionalRoles(): array
    {
        $roles = $this->additional_roles ?? [];

        return array_values(array_filter(
            is_array($roles) ? $roles : [],
            fn ($role) => is_string($role) && $role !== ''
        ));
    }

    /** @return list<string> */
    public function allRoles(): array
    {
        $roles = [];

        if ($primary = $this->primaryRole()) {
            $roles[] = $primary;
        }

        foreach ($this->additionalRoles() as $role) {
            if (! in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->allRoles(), true);
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function getRoleLabelAttribute(): string
    {
        $labels = self::roleLabels();
        $parts = [];

        if ($primary = $this->primaryRole()) {
            $parts[] = $labels[$primary] ?? $primary;
        }

        foreach ($this->additionalRoles() as $role) {
            if ($role !== $primary) {
                $parts[] = $labels[$role] ?? $role;
            }
        }

        if ($parts === []) {
            return self::ORDINARY_STAFF_LABEL;
        }

        return implode(' + ', $parts);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->hasRole($role);
    }

    public function scopeHasRole(Builder $query, string $role): Builder
    {
        return $query->where(function (Builder $inner) use ($role) {
            $inner->where('role', $role)
                ->orWhereJsonContains('additional_roles', $role);
        });
    }

    public function scopeHasAnyRole(Builder $query, array $roles): Builder
    {
        return $query->where(function (Builder $outer) use ($roles) {
            $outer->whereRaw('0 = 1');

            foreach ($roles as $role) {
                $outer->orWhere(function (Builder $inner) use ($role) {
                    $inner->where('role', $role)
                        ->orWhereJsonContains('additional_roles', $role);
                });
            }
        });
    }

    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->super_admin) {
            return $query;
        }

        $person = $user->person;

        if (! $person) {
            return $query->whereRaw('1 = 0');
        }

        if ($person->hasRole('section_manager')) {
            return $person->section_id
                ? $query->where('section_id', $person->section_id)
                : $query->whereRaw('1 = 0');
        }

        if ($person->primaryRole() === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function isVisibleToUser(?User $user): bool
    {
        if (! $user || $user->super_admin) {
            return true;
        }

        $person = $user->person;

        if (! $person) {
            return false;
        }

        if ($person->hasRole('section_manager')) {
            return $person->section_id
                && (int) $this->section_id === (int) $person->section_id;
        }

        if ($person->primaryRole() === null) {
            return (int) $this->user_id === (int) $user->id;
        }

        return true;
    }
}
