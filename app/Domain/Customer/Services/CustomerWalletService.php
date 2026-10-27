<?php

declare(strict_types=1);

namespace App\Domain\Customer\Services;

use App\Domain\Customer\Contracts\CustomerWalletServiceInterface;
use App\Models\Customer as AppCustomer;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use Lunar\Models\Customer as LunarCustomer;

class CustomerWalletService implements CustomerWalletServiceInterface
{
    public function getWallet(AppCustomer|LunarCustomer|int $customer): CustomerWallet
    {
        $customerId = is_int($customer) ? $customer : (int) $customer->id;

        /** @var CustomerWallet $wallet */
        $wallet = CustomerWallet::firstOrCreate(
            ['customer_id' => $customerId],
            ['balance_minor' => 0, 'currency' => 'EGP', 'is_active' => true]
        );

        return $wallet;
    }

    public function getBalance(AppCustomer|LunarCustomer|int $customer): int
    {
        return $this->getWallet($customer)->balance_minor;
    }

    public function credit(AppCustomer|LunarCustomer|int $customer, int $amountMinor, string $type, ?string $referenceId = null, ?string $notes = null): CustomerWalletTransaction
    {
        return $this->getWallet($customer)->credit($amountMinor, $type, $referenceId, $notes);
    }

    public function debit(AppCustomer|LunarCustomer|int $customer, int $amountMinor, string $type, ?string $referenceId = null, ?string $notes = null): CustomerWalletTransaction
    {
        return $this->getWallet($customer)->debit($amountMinor, $type, $referenceId, $notes);
    }
}
