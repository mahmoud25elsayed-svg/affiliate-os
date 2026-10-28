<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

use App\Models\Account;

readonly class LedgerLineData
{
    public function __construct(
        public Account $account,
        public int $amountMinor
    ) {}
}
