<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services;

use App\Domain\Checkout\DTOs\CouponResult;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Support\Money;

class CouponService
{
    /**
     * @param  list<int>  $itemIds
     * @param  list<int>  $categoryIds
     */
    public function validateAndCalculate(
        string $code,
        int $subtotalMinor,
        array $itemIds = [],
        array $categoryIds = [],
        ?string $customerPhoneE164 = null,
        ?int $eligibleSubtotalMinor = null
    ): CouponResult {
        $applicableSubtotal = $eligibleSubtotalMinor ?? $subtotalMinor;
        $normalizedCode = trim(strtoupper($code));

        if ($normalizedCode === '') {
            return CouponResult::failure(__('يرجى إدخال رمز الكوبون'));
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()
            ->where('code', $normalizedCode)
            ->first();

        if ($coupon === null || ! $coupon->is_active) {
            return CouponResult::failure(__('رمز الكوبون غير صالح أو غير موجود'));
        }

        if (! $coupon->hasStarted()) {
            return CouponResult::failure(__('هذا الكوبون لم يبدأ العمل به بعد'));
        }

        if ($coupon->isExpired()) {
            return CouponResult::failure(__('انتهت صلاحية هذا الكوبون'));
        }

        if ($coupon->isUsageLimitReached()) {
            return CouponResult::failure(__('تم استنفاد الحد الأقصى لاستخدام هذا الكوبون'));
        }

        if ($customerPhoneE164 !== null) {
            $phoneHash = hash('sha256', $customerPhoneE164);
            if ($coupon->isCustomerUsageLimitReached($phoneHash)) {
                return CouponResult::failure(__('لقد استنفدت الحد المسموح لك لاستخدام هذا الكوبون'));
            }
        }

        if ($coupon->min_order_value_minor !== null && $subtotalMinor < $coupon->min_order_value_minor) {
            $minEgp = number_format($coupon->min_order_value_minor / 100, 2);

            return CouponResult::failure(__('الحد الأدنى للطلب لتفعيل هذا الكوبون هو :min_egp ', ['min_egp' => $minEgp]).Money::symbol());
        }

        // Check restricted categories
        if (! empty($coupon->restricted_categories)) {
            $matchingCategories = array_intersect($coupon->restricted_categories, $categoryIds);
            if (empty($matchingCategories)) {
                return CouponResult::failure(__('هذا الكوبون غير متاح للأقسام الموجودة في سلتك'));
            }
        }

        // Check restricted products
        if (! empty($coupon->restricted_products)) {
            $matchingProducts = array_intersect($coupon->restricted_products, $itemIds);
            if (empty($matchingProducts)) {
                return CouponResult::failure(__('هذا الكوبون غير متاح للمنتجات المحددة في سلتك'));
            }
        }

        // Calculate discount
        if ($applicableSubtotal <= 0 && $coupon->type !== Coupon::TYPE_FREE_SHIPPING) {
            return CouponResult::failure(__('هذا الكوبون لا يسري على المنتجات المشمولة بعروض التخفيض الفوري (الفلاش سيل)'));
        }

        if ($coupon->type === Coupon::TYPE_PERCENTAGE) {
            $calculated = (int) round(($applicableSubtotal * $coupon->value_minor) / 100);
            if ($coupon->max_discount_minor !== null && $calculated > $coupon->max_discount_minor) {
                $calculated = $coupon->max_discount_minor;
            }
            $discount = min($calculated, $applicableSubtotal);

            return CouponResult::success($coupon, $discount, false);
        }

        if ($coupon->type === Coupon::TYPE_FIXED) {
            $discount = min($coupon->value_minor, $applicableSubtotal);

            return CouponResult::success($coupon, $discount, false);
        }

        if ($coupon->type === Coupon::TYPE_FREE_SHIPPING) {
            return CouponResult::success($coupon, 0, true);
        }

        return CouponResult::failure(__('نوع الكوبون غير مدعوم'));
    }

    public function recordRedemption(Coupon $coupon, ?int $orderId, string $customerPhoneE164, int $discountMinor): CouponRedemption
    {
        $phoneHash = hash('sha256', $customerPhoneE164);

        $coupon->increment('uses_count');

        /** @var CouponRedemption $redemption */
        $redemption = CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'order_id' => $orderId,
            'customer_phone_hash' => $phoneHash,
            'discount_minor' => $discountMinor,
            'created_at' => now(),
        ]);

        return $redemption;
    }
}
