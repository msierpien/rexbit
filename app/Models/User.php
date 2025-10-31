<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
            'role' => \App\Enums\Role::class,
            'status' => \App\Enums\UserStatus::class,
        ];
    }

    /**
     * Determine if the user has the given role.
     */
    public function hasRole(\App\Enums\Role|string $role): bool
    {
        if ($role instanceof \App\Enums\Role) {
            return $this->role === $role;
        }

        return $this->role->value === $role;
    }

    /**
     * Determine if the user has the given status.
     */
    public function hasStatus(\App\Enums\UserStatus|string $status): bool
    {
        if ($status instanceof \App\Enums\UserStatus) {
            return $this->status === $status;
        }

        return $this->status->value === $status;
    }

    /**
     * User integrations relationship.
     */
    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }
}
