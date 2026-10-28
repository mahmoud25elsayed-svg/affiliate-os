<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Settings;

use App\Support\Settings\Settings;

class AffiliateProgramSettings extends Settings
{
    /**
     * Commission model: 'markup' (سعر حر فوق الجملة), 'percentage' (نسبة مئوية), 'both'.
     */
    public string $commission_model = 'markup';

    /**
     * Default commission rate percentage when using percentage model.
     */
    public int $default_commission_rate = 10;

    /**
     * Minimum allowed affiliate commission in minor units (1000 = 10.00 EGP).
     */
    public int $min_commission_minor = 1000;

    /**
     * Maximum allowed affiliate commission in minor units (100000 = 1,000.00 EGP).
     */
    public int $max_commission_minor = 100000;

    /**
     * Minimum selling markup above wholesale price in minor units (2000 = 20.00 EGP).
     */
    public int $min_selling_markup_minor = 2000;

    /**
     * Maximum selling markup above wholesale price in minor units (200000 = 2,000.00 EGP).
     */
    public int $max_selling_markup_minor = 200000;

    /**
     * Number of days to hold commission before moving from pending to available.
     */
    public int $return_window_days = 14;

    /**
     * Minimum withdrawal balance in minor units (10000 = 100.00 EGP).
     */
    public int $min_payout_minor = 10000;

    /**
     * Payout amount threshold in minor units above which Maker-Checker dual authorization is mandatory (50000 = 500.00 EGP).
     */
    public int $maker_checker_threshold_minor = 50000;

    /**
     * Designated days of the week when payout requests are processed.
     *
     * @var list<string>
     */
    public array $payout_days = ['sunday', 'wednesday'];

    /**
     * Whether newly registered affiliates are automatically approved without merchant review.
     */
    public bool $auto_approve_registrations = false;

    /**
     * Whether the customer phone number is displayed to the affiliate in order details.
     */
    public bool $show_customer_phone_to_affiliate = false;

    /**
     * Default daily order quota limit for an affiliate.
     */
    public int $default_daily_order_limit = 50;

    /**
     * Commission settlement release mode: 'return_window', 'courier_remittance', 'later_of_both'.
     */
    public string $release_mode = 'later_of_both';

    /**
     * Security payout freeze duration in hours whenever payout accounts are edited.
     */
    public int $payout_freeze_hours_on_change = 24;

    /**
     * Attribution cookie validity window in days.
     */
    public int $attribution_window_days = 30;

    /**
     * Attribution model: 'last_click' or 'first_click'.
     */
    public string $attribution_model = 'last_click';

    /**
     * Disallow affiliate from earning commission on self-referrals (matching phone/email/IP).
     */
    public bool $prevent_self_referral = true;

    public static function group(): string
    {
        return 'affiliate_program';
    }

    public function isMarkupModel(): bool
    {
        return in_array($this->commission_model, ['markup', 'both'], true);
    }

    public function isPercentageModel(): bool
    {
        return in_array($this->commission_model, ['percentage', 'both'], true);
    }

    public function clampCommission(int $commissionMinor): int
    {
        return max($this->min_commission_minor, min($this->max_commission_minor, $commissionMinor));
    }

    public function clampMarkup(int $markupMinor): int
    {
        return max($this->min_selling_markup_minor, min($this->max_selling_markup_minor, $markupMinor));
    }

    public function isPayoutDay(?string $day = null): bool
    {
        $dayName = strtolower($day ?? now()->format('l'));

        return in_array($dayName, array_map('strtolower', $this->payout_days), true);
    }

    public function canRequestPayout(int $balanceMinor): bool
    {
        return $balanceMinor >= $this->min_payout_minor;
    }

    public function requiresMakerChecker(int $amountMinor): bool
    {
        return $amountMinor >= $this->maker_checker_threshold_minor;
    }

    public function shouldAutoApprove(): bool
    {
        return $this->auto_approve_registrations;
    }

    public function shouldShowCustomerPhone(): bool
    {
        return $this->show_customer_phone_to_affiliate;
    }

    public function getDailyOrderLimit(): int
    {
        return $this->default_daily_order_limit;
    }

    public function getReturnWindowDays(): int
    {
        return $this->return_window_days;
    }

    public function getReleaseMode(): string
    {
        return $this->release_mode;
    }

    public function getPayoutFreezeHours(): int
    {
        return $this->payout_freeze_hours_on_change;
    }

    public function getAttributionWindowDays(): int
    {
        return $this->attribution_window_days;
    }

    public function getAttributionModel(): string
    {
        return $this->attribution_model;
    }

    public function shouldPreventSelfReferral(): bool
    {
        return $this->prevent_self_referral;
    }
}
