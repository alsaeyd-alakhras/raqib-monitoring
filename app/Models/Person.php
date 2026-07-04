<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Person extends Model
{
    use HasFactory;

    public const ROLES = [
        'project_manager',
        'coordinator',
        'department_manager',
        'monitoring_director',
        'monitor',
        'general_management',
        'admin',
    ];

    protected $fillable = [
        'name',
        'role',
        'department_id',
        'user_id',
        'job_title',
        'organization',
        'phone',
    ];

    public static function roleLabels(): array
    {
        return [
            'project_manager' => 'مدير مشروع',
            'coordinator' => 'منسق',
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
        return ['project_manager', 'department_manager'];
    }

    public static function departmentHasManager(int $departmentId, ?int $exceptPersonId = null): bool
    {
        return self::query()
            ->where('role', 'department_manager')
            ->where('department_id', $departmentId)
            ->when($exceptPersonId, fn ($query) => $query->where('id', '!=', $exceptPersonId))
            ->exists();
    }

    public function getRoleLabelAttribute(): string
    {
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

    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
