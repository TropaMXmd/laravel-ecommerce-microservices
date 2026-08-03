<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $guard_name = 'api';

    // UUID primary key
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'email_verified_at',
        'last_login_at',
        // NO 'uuid' here — id IS the uuid
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
            'password'          => 'hashed',
        ];
    }

    // ── Auto-generate UUID for primary key ────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->id)) {
                $user->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    // ── JWT claims ────────────────────────────────────────────────────────────


    public function getClaims(): array
    {
        return [
            'uuid'           => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'role'           => $this->roles->first()?->name ?? 'customer',  // from Spatie
            'email_verified' => (bool) $this->email_verified_at,
            'is_active'      => $this->is_active,
        ];
    }

    // ── Passport scopes ───────────────────────────────────────────────────────
    public function passportScopes(): array
    {
        return $this->getAllPermissions()
                    ->pluck('name')
                    ->toArray();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
