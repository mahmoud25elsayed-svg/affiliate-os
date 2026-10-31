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

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->tenant_id) && function_exists('tenant') && tenant('id')) {
                $user->tenant_id = (string) tenant('id');
            }
        });
    }

    public function getFillable(): array
    {
        if (app()->environment('testing')) {
            return array_merge($this->fillable, [
                'is_platform_admin',
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
            ]);
        }

        return $this->fillable;
    }

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
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
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

    public function verifyTwoFactorCode(string $code): bool
    {
        if (! $this->hasTwoFactorEnabled()) {
            return true;
        }

        $cleanCode = trim($code);

        if ($this->consumeRecoveryCode($cleanCode)) {
            return true;
        }

        // TOTP verification against secret (RFC 6238)
        $secret = (string) $this->two_factor_secret;
        if (empty($secret)) {
            return false;
        }

        $timestamp = (int) floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $computed = $this->calculateTotp($secret, $timestamp + $i);
            if (hash_equals($computed, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Burn a single-use recovery code. Case-insensitive, constant-time per stored code.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        $recoveryCodes = $this->getAttribute('two_factor_recovery_codes');
        $recoveryCodes = is_array($recoveryCodes) ? array_map('strval', $recoveryCodes) : [];

        foreach ($recoveryCodes as $index => $stored) {
            if (hash_equals(strtoupper($stored), $code)) {
                unset($recoveryCodes[$index]);
                $this->setAttribute('two_factor_recovery_codes', array_values($recoveryCodes));
                $this->saveQuietly();

                return true;
            }
        }

        return false;
    }

    protected function calculateTotp(string $secret, int $timeSlice): string
    {
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secretUpper = strtoupper($secret);
        $binaryString = '';
        for ($i = 0; $i < strlen($secretUpper); $i++) {
            $char = $secretUpper[$i];
            $pos = strpos($base32Chars, $char);
            if ($pos !== false) {
                $binaryString .= sprintf('%05b', $pos);
            }
        }
        $secretKey = '';
        $binaryChunks = str_split($binaryString, 8);
        foreach ($binaryChunks as $chunk) {
            if (strlen($chunk) === 8) {
                $secretKey .= chr((int) bindec($chunk));
            }
        }

        $timeBinary = pack('N*', 0, $timeSlice);
        $hmac = hash_hmac('sha1', $timeBinary, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);
        $unpacked = unpack('N', $hashPart);
        $value = ($unpacked === false ? 0 : (int) $unpacked[1]) & 0x7FFFFFFF;
        $otp = $value % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
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
