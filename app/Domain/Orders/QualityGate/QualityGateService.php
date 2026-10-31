<?php

declare(strict_types=1);

namespace App\Domain\Orders\QualityGate;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\QualityGate\DTOs\QualityGateResult;
use App\Models\MerchantBlacklist;
use App\Support\Enums\ReasonCode;
use App\Support\Phone\PhoneNumber;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

class QualityGateService
{
    /**
     * @param  list<int>  $variantIds
     */
    public function evaluate(
        string $customerName,
        string $customerPhone,
        int $governorateId,
        string $city,
        string $address,
        array $variantIds = [],
        ?string $ipAddress = null
    ): QualityGateResult {
        $flags = [];
        $reasons = [];
        $riskScore = 0;
        $status = QualityGateResult::STATUS_PASS;

        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : null;

        // 1. Phone validation & normalization
        $phone = PhoneNumber::tryParse($customerPhone);
        if ($phone === null) {
            $flags[] = ReasonCode::INVALID_PHONE->value;
            $reasons[] = PhoneNumber::invalidMessage();
            $riskScore += 100;

            return new QualityGateResult(
                status: QualityGateResult::STATUS_REJECT,
                flags: $flags,
                reasons: $reasons,
                riskScore: $riskScore
            );
        }

        $normalizedPhone = $phone->e164;

        // 2. Merchant Blacklist Check
        if ($tenantId) {
            $blacklistQuery = MerchantBlacklist::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($normalizedPhone, $customerPhone, $customerName, $ipAddress) {
                    $query->where(function ($q) use ($normalizedPhone, $customerPhone) {
                        $q->where('type', MerchantBlacklist::TYPE_PHONE)
                            ->whereIn('value', [$normalizedPhone, $customerPhone]);
                    })->orWhere(function ($q) use ($customerName) {
                        $q->where('type', MerchantBlacklist::TYPE_NAME)
                            ->where('value', trim($customerName));
                    });

                    if (! empty($ipAddress)) {
                        $query->orWhere(function ($q) use ($ipAddress) {
                            $q->where('type', MerchantBlacklist::TYPE_IP)
                                ->where('value', trim($ipAddress));
                        });
                    }
                });

            /** @var MerchantBlacklist|null $blacklisted */
            $blacklisted = $blacklistQuery->first();
            if ($blacklisted) {
                $flags[] = ReasonCode::SUSPECTED_FRAUD->value;
                $reasons[] = __('العميل مدرج في القائمة السوداء للمتجر: ').($blacklisted->reason ?? __('عميل محظور'));
                $riskScore += 100;

                return new QualityGateResult(
                    status: QualityGateResult::STATUS_REJECT,
                    flags: $flags,
                    reasons: $reasons,
                    riskScore: $riskScore
                );
            }
        }

        // 3. Address Completeness Check
        $trimmedAddress = trim($address);
        $words = preg_split('/\s+/', $trimmedAddress);
        $wordCount = is_array($words) ? count($words) : 0;

        if (mb_strlen($trimmedAddress) < 10 || $wordCount < 2) {
            $flags[] = ReasonCode::INCOMPLETE_ADDRESS->value;
            $reasons[] = __('العنوان غير مكتمل أو غامض (أقل من 10 أحرف أو يفتقر لتفاصيل الشارع ورقم العقار).');
            $riskScore += 30;
            $status = QualityGateResult::STATUS_REVIEW;
        }

        // 4. Duplicate Order Detection (Phone + Same Variant within 7 Days)
        if ($tenantId && ! empty($variantIds)) {
            $sevenDaysAgo = Carbon::now()->subDays(7);

            $duplicateOrdersCount = Order::query()
                ->where('tenant_id', $tenantId)
                ->where('customer_phone_e164', $normalizedPhone)
                ->where('created_at', '>=', $sevenDaysAgo)
                ->whereHas('lines', function ($query) use ($variantIds) {
                    $query->whereIn('purchasable_id', $variantIds);
                })
                ->count();

            if ($duplicateOrdersCount >= 2) {
                $flags[] = ReasonCode::SUSPECTED_FRAUD->value;
                $reasons[] = __('اشتباه احتيال: تم تقديم أكثر من طلب لنفس المنتج ونفس العميل خلال آخر 7 أيام.');
                $riskScore += 80;
                $status = QualityGateResult::STATUS_REJECT;
            } elseif ($duplicateOrdersCount === 1) {
                $flags[] = ReasonCode::DUPLICATE_ORDER->value;
                $reasons[] = __('تنبيه تكرار: تم تقديم طلب سابق لنفس المنتج والعميل خلال آخر 7 أيام.');
                $riskScore += 40;
                $status = QualityGateResult::STATUS_REVIEW;
            }
        }

        // 5. Customer Reputation & Historical Return Rate
        if ($tenantId) {
            $pastOrders = Order::query()
                ->where('tenant_id', $tenantId)
                ->where('customer_phone_e164', $normalizedPhone)
                ->get();

            $totalPast = $pastOrders->count();
            if ($totalPast >= 2) {
                $returnsCount = $pastOrders->whereIn('status', [
                    OrderStatus::RETURNED->value,
                    OrderStatus::DELIVERY_FAILED->value,
                ])->count();

                $returnRate = $returnsCount / $totalPast;

                if ($totalPast >= 3 && $returnRate >= 0.75) {
                    $flags[] = ReasonCode::HIGH_RISK_REPUTATION->value;
                    $reasons[] = __('سمعة العميل عالية الخطورة: أكثر من 75% من طلبات العميل السابقة بالمتجر مرتجعة أو فاشلة التوصيل.');
                    $riskScore += 80;
                    $status = QualityGateResult::STATUS_REJECT;
                } elseif ($returnRate >= 0.50) {
                    $flags[] = ReasonCode::HIGH_RISK_REPUTATION->value;
                    $reasons[] = __('تنبيه مخاطرة: 50% أو أكثر من طلبات العميل السابقة بالمتجر لم يتم استلامها.');
                    $riskScore += 40;
                    if ($status !== QualityGateResult::STATUS_REJECT) {
                        $status = QualityGateResult::STATUS_REVIEW;
                    }
                }
            }
        }

        if (empty($flags)) {
            $reasons[] = __('تم اجتياز فحص الجودة بنجاح.');
        }

        return new QualityGateResult(
            status: $status,
            flags: $flags,
            reasons: $reasons,
            riskScore: $riskScore
        );
    }
}
