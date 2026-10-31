<?php

declare(strict_types=1);

namespace App\Domain\Orders\DTOs;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

readonly class OrderActor
{
    public function __construct(
        public string $type,
        public ?string $id = null,
        public ?string $name = null,
        public ?string $role = null
    ) {}

    public static function system(): self
    {
        return new self(
            type: 'system',
            id: 'system',
            name: __('النظام الآلي')
        );
    }

    public static function staff(Authenticatable|User|int|string $user, ?string $name = null, ?string $role = null): self
    {
        if ($user instanceof User) {
            $id = (string) $user->id;
            $userName = $name ?? $user->name;
            $firstRole = $user->roles->first();
            $userRole = $role ?? (is_object($firstRole) && isset($firstRole->name) ? (string) $firstRole->name : 'staff');

            return new self(
                type: 'staff',
                id: $id,
                name: $userName,
                role: $userRole
            );
        }

        if ($user instanceof Authenticatable) {
            $id = (string) $user->getAuthIdentifier();
            $userName = $name ?? (isset($user->name) && is_string($user->name) ? $user->name : __('الموظف'));

            return new self(
                type: 'staff',
                id: $id,
                name: $userName,
                role: $role ?? 'staff'
            );
        }

        return new self(
            type: 'staff',
            id: (string) $user,
            name: $name,
            role: $role ?? 'staff'
        );
    }

    public static function customer(int|string|null $id = null, ?string $name = null): self
    {
        return new self(
            type: 'customer',
            id: $id !== null ? (string) $id : null,
            name: $name ?? __('العميل')
        );
    }

    public static function affiliate(int|string|null $id = null, ?string $name = null): self
    {
        return new self(
            type: 'affiliate',
            id: $id !== null ? (string) $id : null,
            name: $name ?? __('المسوق')
        );
    }

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    public function isStaff(): bool
    {
        return $this->type === 'staff';
    }

    public function isCustomer(): bool
    {
        return $this->type === 'customer';
    }

    public function isAffiliate(): bool
    {
        return $this->type === 'affiliate';
    }

    public function hasRole(string ...$roles): bool
    {
        if (! $this->isStaff()) {
            return false;
        }

        if ($this->role === null) {
            return false;
        }

        // Owner/Admin always passes
        if ($this->role === 'owner' || $this->role === 'admin' || $this->role === 'manager') {
            return true;
        }

        return in_array($this->role, $roles, true);
    }
}
