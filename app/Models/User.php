<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'phone_verified_at',
        'is_platform_admin',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_platform_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'platform') {
            return $this->isPlatformAdmin();
        }

        if ($panel->getId() === 'store') {
            if ($this->isPlatformAdmin() && session()->has('impersonator_id')) {
                return true;
            }

            return $this->tenant_id !== null;
        }

        return true;
    }

    /**
     * @return array<Tenant>
     */
    public function getTenants(Panel $panel): array
    {
        if ($this->isPlatformAdmin() && session()->has('impersonator_id')) {
            /** @var array<Tenant> $allTenants */
            $allTenants = Tenant::all()->all();

            return $allTenants;
        }

        if ($this->tenant_id) {
            /** @var array<Tenant> $myTenants */
            $myTenants = Tenant::where('id', $this->tenant_id)->get()->all();

            return $myTenants;
        }

        return [];
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isPlatformAdmin() && session()->has('impersonator_id')) {
            return true;
        }

        return $this->tenant_id === $tenant->getKey();
    }
}
