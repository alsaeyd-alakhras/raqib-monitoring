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

    public const ORDINARY_STAFF_LABEL = 'موظف عادي';

    protected $fillable = [
        'name',
        'role',
        'department_id',
        'section_id',
        'user_id',
        'job_title',
        'organization',
        'phone',
    ];

    public static function roleLabels(): array
    {
        return [
            'project_manager' => 'مدير مشروع',
            'project_secretariat' => 'سكرتاريا المشاريع',
            'coordinator' => 'منسق',
            'section_manager' => 'مدير قسم',
            'department_manager' => 'مدير دائرة',
            'monitoring_director' => 'مدير الرقابة العامة',
            'monitor' => 'مراقب',
            'general_management' => 'الإدارة العامة',
            'admin' => 'أدمن النظام',
        ];
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

    public function getRoleLabelAttribute(): string
    {
        if ($this->role === null || $this->role === '') {
            return self::ORDINARY_STAFF_LABEL;
        }

        return self::roleLabels()[$this->role] ?? $this->role;
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
        return $query->where('role', $role);
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

        return match ($person->role) {
            'section_manager' => $person->section_id
                ? $query->where('section_id', $person->section_id)
                : $query->whereRaw('1 = 0'),
            null, '' => $query->whereRaw('1 = 0'),
            default => $query,
        };
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

        if ($person->role === 'section_manager') {
            return $person->section_id
                && (int) $this->section_id === (int) $person->section_id;
        }

        if ($person->role === null || $person->role === '') {
            return (int) $this->user_id === (int) $user->id;
        }

        return true;
    }
}
