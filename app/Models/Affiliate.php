<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Finance\Services\AccountResolver;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $code
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password
 * @property string $status
 * @property string|null $status_reason
 * @property int $commission_rate
 * @property int|null $referred_by_id
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $email_verified_at
 * @property string|null $otp_code
 * @property Carbon|null $otp_expires_at
 * @property string|null $national_id_number
 * @property string|null $id_card_front_path
 * @property string|null $id_card_back_path
 * @property string $kyc_status
 * @property string|null $kyc_rejected_reason
 * @property Carbon|null $kyc_reviewed_at
 * @property int|null $kyc_reviewed_by
 * @property string|null $payout_method
 * @property array<string, mixed>|null $payout_details
 * @property Carbon|null $payouts_frozen_until
 * @property int|null $affiliate_level_id
 * @property int $quality_score
 * @property int $daily_order_limit
 * @property int $total_delivered_orders
 * @property int $delivery_rate_basis_points
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate|null $referredBy
 * @property-read Collection<int, Affiliate> $referrals
 * @property-read User|null $reviewer
 * @property-read Collection<int, KycAuditLog> $kycAuditLogs
 * @property-read AffiliateLevel|null $level
 * @property-read Collection<int, AffiliateQualityLog> $qualityLogs
 * @property-read Collection<int, AffiliateReferralReward> $referralRewardsEarned
 */
class Affiliate extends Authenticatable
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

    public const KYC_UNSUBMITTED = 'unsubmitted';

    public const KYC_PENDING = 'pending';

    public const KYC_APPROVED = 'approved';

    public const KYC_REJECTED = 'rejected';

    public const PAYOUT_METHOD_WALLET = 'wallet_phone';

    public const PAYOUT_METHOD_INSTAPAY = 'instapay';

    public const PAYOUT_METHOD_BANK = 'bank_account';

    protected $table = 'affiliates';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'email',
        'phone',
        'password',
        'status',
        'status_reason',
        'commission_rate',
        'referred_by_id',
        'phone_verified_at',
        'email_verified_at',
        'otp_code',
        'otp_expires_at',
        'national_id_number',
        'id_card_front_path',
        'id_card_back_path',
        'kyc_status',
        'kyc_rejected_reason',
        'kyc_reviewed_at',
        'kyc_reviewed_by',
        'payout_method',
        'payout_details',
        'payouts_frozen_until',
        'affiliate_level_id',
        'quality_score',
        'daily_order_limit',
        'total_delivered_orders',
        'delivery_rate_basis_points',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'commission_rate' => 'integer',
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'kyc_reviewed_at' => 'datetime',
            'payouts_frozen_until' => 'datetime',
            'payout_details' => 'encrypted:array',
            'quality_score' => 'integer',
            'daily_order_limit' => 'integer',
            'total_delivered_orders' => 'integer',
            'delivery_rate_basis_points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }

    /**
     * @return HasMany<Affiliate, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kyc_reviewed_by');
    }

    /**
     * @return HasMany<KycAuditLog, $this>
     */
    public function kycAuditLogs(): HasMany
    {
        return $this->hasMany(KycAuditLog::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'affiliate_id');
    }

    /**
     * @return HasMany<TrackingLink, $this>
     */
    public function trackingLinks(): HasMany
    {
        return $this->hasMany(TrackingLink::class, 'affiliate_id');
    }

    /**
     * @return HasMany<AffiliateClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class, 'affiliate_id');
    }

    /**
     * @return HasMany<PayoutRequest, $this>
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class, 'affiliate_id');
    }

    /**
     * @return BelongsTo<AffiliateLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(AffiliateLevel::class, 'affiliate_level_id');
    }

    /**
     * @return HasMany<AffiliateQualityLog, $this>
     */
    public function qualityLogs(): HasMany
    {
        return $this->hasMany(AffiliateQualityLog::class);
    }

    /**
     * @return HasMany<AffiliateReferralReward, $this>
     */
    public function referralRewardsEarned(): HasMany
    {
        return $this->hasMany(AffiliateReferralReward::class, 'referrer_id');
    }

    public function getTierBonusMinor(): int
    {
        return $this->level instanceof AffiliateLevel ? (int) $this->level->bonus_per_order_minor : 0;
    }

    public function getCommissionBonusPercent(): int
    {
        return $this->level instanceof AffiliateLevel ? (int) $this->level->commission_bonus_percent : 0;
    }

    public function getQualityScoreRating(): string
    {
        $score = $this->quality_score;

        return match (true) {
            $score >= 85 => 'ممتاز',
            $score >= 70 => 'جيد جداً',
            $score >= 50 => 'جيد',
            default => 'يحتاج تحسين',
        };
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function hasApprovedKyc(): bool
    {
        return $this->kyc_status === self::KYC_APPROVED;
    }

    public function isPayoutFrozen(): bool
    {
        return $this->payouts_frozen_until !== null && $this->payouts_frozen_until->isFuture();
    }

    public function canWithdraw(): bool
    {
        return $this->isActive()
            && $this->hasApprovedKyc()
            && ! $this->isPayoutFrozen()
            && ! $this->hasDebt();
    }

    public function getPendingBalance(): int
    {
        return (int) app(AccountResolver::class)->getAffiliatePendingAccount($this)->balance_minor;
    }

    public function getAvailableBalance(): int
    {
        return (int) app(AccountResolver::class)->getAffiliateAvailableAccount($this)->balance_minor;
    }

    public function getLockedBalance(): int
    {
        return (int) app(AccountResolver::class)->getAffiliateLockedAccount($this)->balance_minor;
    }

    public function getWithdrawnBalance(): int
    {
        return (int) app(AccountResolver::class)->getAffiliateWithdrawnAccount($this)->balance_minor;
    }

    public function hasDebt(): bool
    {
        return $this->getAvailableBalance() < 0;
    }

    public function getDebtMinor(): int
    {
        return $this->hasDebt() ? abs($this->getAvailableBalance()) : 0;
    }

    public function freezePayouts(int $hours = 24): void
    {
        $this->updateQuietly([
            'payouts_frozen_until' => Carbon::now()->addHours($hours),
        ]);
        $this->payouts_frozen_until = Carbon::now()->addHours($hours);
    }

    public function verifyPhone(): void
    {
        $this->updateQuietly([
            'phone_verified_at' => Carbon::now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);
    }
}
