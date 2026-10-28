<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Models\Account;
use App\Models\Affiliate;
use InvalidArgumentException;
use Lunar\Models\Customer;

class AccountResolver
{
    public const KIND_PENDING = 'pending';

    public const KIND_AVAILABLE = 'available';

    public const KIND_LOCKED = 'locked';

    public const KIND_WITHDRAWN = 'withdrawn';

    public const KIND_REVENUE = 'revenue';

    public const KIND_COMMISSION_EXPENSE = 'commission_expense';

    public const KIND_BONUS_EXPENSE = 'bonus_expense';

    public const KIND_COD_RECEIVABLE = 'cod_receivable';

    public const KIND_WALLET = 'wallet';

    public const KIND_LOYALTY = 'loyalty';

    public const KIND_PAYOUT_CLEARING = 'payout_clearing';

    public const KIND_BANK = 'bank';

    public const KIND_SHIPPING_EXPENSE = 'shipping_expense';

    public const KIND_DISCREPANCY = 'discrepancy';

    /**
     * Resolve or create an affiliate account.
     */
    public function getAffiliateAccount(Affiliate $affiliate, string $kind): Account
    {
        $allowed = [
            self::KIND_PENDING => 'أرباح معلقة',
            self::KIND_AVAILABLE => 'أرباح متاحة للسحب',
            self::KIND_LOCKED => 'أرباح محجوزة للسحب',
            self::KIND_WITHDRAWN => 'إجمالي المسحوبات',
        ];

        if (! array_key_exists($kind, $allowed)) {
            throw new InvalidArgumentException("نوع حساب مسوق غير صالح: {$kind}");
        }

        $code = "aff:{$affiliate->id}:{$kind}";
        $name = "{$allowed[$kind]} - مسوق #{$affiliate->id} ({$affiliate->name})";

        $tenantId = tenant('id') ? (string) tenant('id') : $affiliate->tenant_id;

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'owner_type' => Affiliate::class,
                'owner_id' => $affiliate->id,
                'kind' => $kind,
                'name' => $name,
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getAffiliatePendingAccount(Affiliate $affiliate): Account
    {
        return $this->getAffiliateAccount($affiliate, self::KIND_PENDING);
    }

    public function getAffiliateAvailableAccount(Affiliate $affiliate): Account
    {
        return $this->getAffiliateAccount($affiliate, self::KIND_AVAILABLE);
    }

    public function getAffiliateLockedAccount(Affiliate $affiliate): Account
    {
        return $this->getAffiliateAccount($affiliate, self::KIND_LOCKED);
    }

    public function getAffiliateWithdrawnAccount(Affiliate $affiliate): Account
    {
        return $this->getAffiliateAccount($affiliate, self::KIND_WITHDRAWN);
    }

    public function getStoreRevenueAccount(): Account
    {
        $code = 'store:revenue';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_REVENUE,
                'name' => 'إيرادات المبيعات العامة للمتجر',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getStoreCommissionExpenseAccount(): Account
    {
        $code = 'store:commission_expense';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_COMMISSION_EXPENSE,
                'name' => 'مصروفات عمولات وأرباح المسوقين',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getStoreBonusExpenseAccount(): Account
    {
        $code = 'store:bonus_expense';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_BONUS_EXPENSE,
                'name' => 'مصروفات مكافآت وبونص المسوقين والإحالات',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getPlatformCodReceivableAccount(): Account
    {
        $code = 'platform:cod_receivable';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_COD_RECEIVABLE,
                'name' => 'متحصلات COD معلقة لدى شركات الشحن',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getStorePayoutClearingAccount(): Account
    {
        $code = 'store:payout_clearing';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_PAYOUT_CLEARING,
                'name' => 'حساب وسيط لتسوية سحوبات المسوقين',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getStoreBankAccount(): Account
    {
        $code = 'store:bank';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_BANK,
                'name' => 'حساب البنك والتحصيلات للمتجر',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getStoreShippingExpenseAccount(): Account
    {
        $code = 'store:shipping_expense';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_SHIPPING_EXPENSE,
                'name' => 'مصروفات شحن وتوصيل الطلبات',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getStoreDiscrepancyExpenseAccount(): Account
    {
        $code = 'store:cod_discrepancy';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_DISCREPANCY,
                'name' => 'فروقات تحصيلات ومصاريف الشحن',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getCustomerWalletAccount(Customer $customer): Account
    {
        $code = "customer:{$customer->id}:wallet";
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'owner_type' => Customer::class,
                'owner_id' => $customer->id,
                'kind' => self::KIND_WALLET,
                'name' => "محفظة رصيد العميل #{$customer->id}",
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getCustomerLoyaltyAccount(Customer $customer): Account
    {
        $code = "customer:{$customer->id}:loyalty";
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'owner_type' => Customer::class,
                'owner_id' => $customer->id,
                'kind' => self::KIND_LOYALTY,
                'name' => "نقاط ولاء العميل #{$customer->id}",
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getStoreLoyaltyExpenseAccount(): Account
    {
        $code = 'store:loyalty_expense';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_LOYALTY,
                'name' => 'مصروفات نقاط ومكافآت ولاء العملاء',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }

    public function getStoreCustomerWalletLiabilityAccount(): Account
    {
        $code = 'store:customer_wallet_liability';
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Account $account */
        $account = Account::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'code' => $code,
            ],
            [
                'kind' => self::KIND_WALLET,
                'name' => 'التزامات أرصدة ومحافظ العملاء',
                'currency' => 'EGP',
                'balance_minor' => 0,
            ]
        );

        return $account;
    }
}
