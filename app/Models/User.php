<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Services\RoleAbilitiesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'username',
        'last_activity',
        'avatar',
        'super_admin',
        'user_type',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(RoleUser::class, 'user_id', 'id');
    }

    public function person(): HasOne
    {
        return $this->hasOne(Person::class);
    }



    // Accessor
    public function getAvatarUrlAttribute() // $user->avatar-url
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return asset('imgs/user.jpg');
    }

    public function personRole(): ?string
    {
        return $this->person?->role;
    }

    public function hasPersonRole(string ...$roles): bool
    {
        $person = $this->person;

        if (! $person) {
            return false;
        }

        return $person->hasAnyRole($roles);
    }

    public function hasAbility(string $ability): bool
    {
        if ($this->roles->contains('role_name', $ability)) {
            return true;
        }

        $person = $this->person;

        if (! $person) {
            return false;
        }

        return in_array(
            $ability,
            app(RoleAbilitiesService::class)->forRoles($person->allRoles()),
            true
        );
    }

    /** مدير الرقابة — Person.role فقط (لا abilities). */
    public function isMonitoringDirector(): bool
    {
        return $this->hasPersonRole('monitoring_director');
    }

    /** رؤية كل مسارات التنفيذ للمتابعة — بالدور فقط. */
    public function canOverseeExecutions(): bool
    {
        if ($this->super_admin) {
            return true;
        }

        return $this->hasPersonRole('monitoring_director', 'general_management', 'admin');
    }
}
