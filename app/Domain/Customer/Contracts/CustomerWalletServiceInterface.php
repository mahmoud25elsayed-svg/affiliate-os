<?php

declare(strict_types=1);

namespace App\Domain\Customer\Contracts;

use App\Models\Customer as AppCustomer;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use Lunar\Models\Customer as LunarCustomer;

interface CustomerWalletServiceInterface
{
    public function getWallet(AppCustomer|LunarCustomer|int $customer): CustomerWallet;

    public function getBalance(AppCustomer|LunarCustomer|int $customer): int;

    public function credit(AppCustomer|LunarCustomer|int $customer, int $amountMinor, string $type, ?string $referenceId = null, ?string $notes = null): CustomerWalletTransaction;

    public function debit(AppCustomer|LunarCustomer|int $customer, int $amountMinor, string $type, ?string $referenceId = null, ?string $notes = null): CustomerWalletTransaction;
}
