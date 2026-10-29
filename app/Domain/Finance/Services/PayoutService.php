<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Exceptions\InsufficientAffiliateBalanceException;
use App\Domain\Finance\Exceptions\InvalidPayoutStatusTransitionException;
use App\Domain\Finance\Exceptions\MakerCheckerViolationException;
use App\Domain\Finance\Exceptions\PayoutNotEligibleException;
use App\Models\Account;
use App\Models\Affiliate;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    public function __construct(
        protected Ledger $ledger,
        protected AccountResolver $accountResolver
    ) {}

    /**
     * Affiliate initiates a payout request.
     * Concurrency safe via pessimistic row-level locking on the available account.
     *
     * @param  array<string, mixed>|null  $customDetails
     */
    public function requestPayout(
        Affiliate $affiliate,
        int $amountMinor,
        ?string $paymentMethod = null,
        ?array $customDetails = null
    ): PayoutRequest {
        $settings = AffiliateProgramSettings::load();

        if (! $affiliate->canWithdraw()) {
            throw new PayoutNotEligibleException;
        }

        if ($amountMinor < $settings->min_payout_minor) {
            $minMajor = number_format($settings->min_payout_minor / 100, 2);

            throw new InsufficientAffiliateBalanceException("الحد الأدنى لطلب السحب هو {$minMajor} ج.م");
        }

        $method = $paymentMethod ?? $affiliate->payout_method ?? Affiliate::PAYOUT_METHOD_WALLET;
        $details = $customDetails ?? $affiliate->payout_details ?? [];

        return DB::transaction(function () use ($affiliate, $amountMinor, $method, $details) {
            $availableAccount = $this->accountResolver->getAffiliateAvailableAccount($affiliate);
            $lockedAccount = $this->accountResolver->getAffiliateLockedAccount($affiliate);

            // Pessimistic lock on available account to prevent race conditions
            /** @var Account $lockedAvailableAccount */
            $lockedAvailableAccount = Account::where('id', $availableAccount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAvailableAccount->balance_minor < $amountMinor) {
                throw new InsufficientAffiliateBalanceException;
            }

            $tenantId = tenant('id') ? (string) tenant('id') : 'central';
            $payoutRequestId = (string) Str::uuid();

            /** @var PayoutRequest $payoutRequest */
            $payoutRequest = PayoutRequest::create([
                'tenant_id' => $tenantId,
                'affiliate_id' => $affiliate->id,
                'amount_minor' => $amountMinor,
                'fee_minor' => 0,
                'net_amount_minor' => $amountMinor,
                'status' => PayoutRequest::STATUS_REQUESTED,
                'payment_method' => $method,
                'payout_details' => $details,
            ]);

            // Double entry ledger: available -> locked
            $this->ledger->post(new EntryData(
                type: 'payout_requested',
                idempotencyKey: "payout_req_{$payoutRequest->id}",
                description: "حجز رصيد سحب أرباح طلب #{$payoutRequest->id}",
                lines: [
                    new LedgerLineData(account: $lockedAvailableAccount, amountMinor: -$amountMinor),
                    new LedgerLineData(account: $lockedAccount, amountMinor: $amountMinor),
                ],
                refType: PayoutRequest::class,
                refId: (int) $payoutRequest->id
            ));

            return $payoutRequest;
        });
    }

    /**
     * Merchant staff approves the payout request (Maker step).
     */
    public function approvePayout(PayoutRequest $request, User $actor): PayoutRequest
    {
        // AF-17: Role check for payout approval
        if ($actor->hasRole('call_center') || $actor->hasRole('orders')) {
            throw new AuthorizationException('غير مصرح لك باعتماد طلبات السحب.');
        }

        if ($actor->roles()->exists() && ! $actor->isPlatformAdmin() && ! $actor->hasAnyRole(['owner', 'manager', 'finance', 'admin'])) {
            throw new AuthorizationException('غير مصرح لك باعتماد طلبات السحب.');
        }

        if (! $request->canBeApproved()) {
            throw new InvalidPayoutStatusTransitionException($request->status, PayoutRequest::STATUS_APPROVED);
        }

        $request->update([
            'status' => PayoutRequest::STATUS_APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        return $request->fresh() ?? $request;
    }

    /**
     * Merchant staff executes/pays the payout request (Checker step).
     * Enforces Maker-Checker separation for amounts exceeding threshold.
     */
    public function executePayout(
        PayoutRequest $request,
        User $actor,
        string $referenceNumber,
        ?string $receiptPath = null,
        ?string $twoFactorCode = null
    ): PayoutRequest {
        // AF-17: Role check and 2FA enforcement for payout execution
        if ($actor->hasRole('call_center') || $actor->hasRole('orders')) {
            throw new AuthorizationException('غير مصرح لك بتنفيذ طلبات السحب.');
        }

        if ($actor->roles()->exists() && ! $actor->isPlatformAdmin() && ! $actor->hasAnyRole(['owner', 'manager', 'finance', 'admin'])) {
            throw new AuthorizationException('غير مصرح لك بتنفيذ طلبات السحب.');
        }

        if ($actor->hasTwoFactorEnabled()) {
            if (empty($twoFactorCode) || ! $actor->verifyTwoFactorCode($twoFactorCode)) {
                throw ValidationException::withMessages([
                    'two_factor_code' => ['رمز التحقق بخطوتين غير صحيح أو مفقود.'],
                ]);
            }
        }

        if (! $request->canBeExecuted()) {
            throw new InvalidPayoutStatusTransitionException($request->status, PayoutRequest::STATUS_PAID);
        }

        $settings = AffiliateProgramSettings::load();

        if (
            $settings->requiresMakerChecker($request->amount_minor)
            && $request->approved_by !== null
            && (int) $request->approved_by === (int) $actor->id
        ) {
            throw new MakerCheckerViolationException;
        }

        return DB::transaction(function () use ($request, $actor, $referenceNumber, $receiptPath) {
            $affiliate = $request->affiliate;
            if (! $affiliate instanceof Affiliate) {
                throw new PayoutNotEligibleException('المسوق غير موجود');
            }
            $lockedAccount = $this->accountResolver->getAffiliateLockedAccount($affiliate);
            $withdrawnAccount = $this->accountResolver->getAffiliateWithdrawnAccount($affiliate);

            // Double entry: locked -> withdrawn
            $this->ledger->post(new EntryData(
                type: 'payout_executed',
                idempotencyKey: "payout_exec_{$request->id}",
                description: "تنفيذ تحويل أرباح طلب #{$request->id} برقم مرجعي {$referenceNumber}",
                lines: [
                    new LedgerLineData(account: $lockedAccount, amountMinor: -$request->amount_minor),
                    new LedgerLineData(account: $withdrawnAccount, amountMinor: $request->amount_minor),
                ],
                refType: PayoutRequest::class,
                refId: (int) $request->id,
                createdBy: $actor->id
            ));

            $request->update([
                'status' => PayoutRequest::STATUS_PAID,
                'paid_by' => $actor->id,
                'paid_at' => now(),
                'reference_number' => $referenceNumber,
                'receipt_path' => $receiptPath,
            ]);

            return $request->fresh() ?? $request;
        });
    }

    /**
     * Merchant staff rejects the payout request and reverses locked balance back to available.
     */
    public function rejectPayout(PayoutRequest $request, User $actor, string $reason): PayoutRequest
    {
        if (! $request->canBeRejected()) {
            throw new InvalidPayoutStatusTransitionException($request->status, PayoutRequest::STATUS_REJECTED);
        }

        return DB::transaction(function () use ($request, $actor, $reason) {
            $affiliate = $request->affiliate;
            if (! $affiliate instanceof Affiliate) {
                throw new PayoutNotEligibleException('المسوق غير موجود');
            }
            $lockedAccount = $this->accountResolver->getAffiliateLockedAccount($affiliate);
            $availableAccount = $this->accountResolver->getAffiliateAvailableAccount($affiliate);

            // Double entry reversal: locked -> available
            $this->ledger->post(new EntryData(
                type: 'payout_rejected',
                idempotencyKey: "payout_rej_{$request->id}",
                description: "رفض طلب سحب أرباح #{$request->id}: {$reason}",
                lines: [
                    new LedgerLineData(account: $lockedAccount, amountMinor: -$request->amount_minor),
                    new LedgerLineData(account: $availableAccount, amountMinor: $request->amount_minor),
                ],
                refType: PayoutRequest::class,
                refId: (int) $request->id,
                createdBy: $actor->id
            ));

            $request->update([
                'status' => PayoutRequest::STATUS_REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $request->fresh() ?? $request;
        });
    }

    /**
     * Affiliate cancels their own pending payout before review.
     */
    public function cancelPayout(PayoutRequest $request, Affiliate $affiliate): PayoutRequest
    {
        if ((int) $request->affiliate_id !== (int) $affiliate->id) {
            throw new InvalidPayoutStatusTransitionException($request->status, PayoutRequest::STATUS_CANCELLED);
        }

        if (! $request->isRequested()) {
            throw new InvalidPayoutStatusTransitionException($request->status, PayoutRequest::STATUS_CANCELLED);
        }

        return DB::transaction(function () use ($request) {
            $affiliate = $request->affiliate;
            if (! $affiliate instanceof Affiliate) {
                throw new PayoutNotEligibleException('المسوق غير موجود');
            }
            $lockedAccount = $this->accountResolver->getAffiliateLockedAccount($affiliate);
            $availableAccount = $this->accountResolver->getAffiliateAvailableAccount($affiliate);

            // Reversal: locked -> available
            $this->ledger->post(new EntryData(
                type: 'payout_cancelled',
                idempotencyKey: "payout_can_{$request->id}",
                description: "إلغاء المسوق لطلب سحب أرباح #{$request->id}",
                lines: [
                    new LedgerLineData(account: $lockedAccount, amountMinor: -$request->amount_minor),
                    new LedgerLineData(account: $availableAccount, amountMinor: $request->amount_minor),
                ],
                refType: PayoutRequest::class,
                refId: (int) $request->id
            ));

            $request->update([
                'status' => PayoutRequest::STATUS_CANCELLED,
            ]);

            return $request->fresh() ?? $request;
        });
    }
}
