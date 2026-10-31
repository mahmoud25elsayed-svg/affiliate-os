<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services;

use App\Domain\Checkout\DTOs\SpamCheckResult;
use App\Support\Phone\PhoneNumber;
use Lunar\Models\Order;

class SpamProtectionService
{
    /**
     * @param  list<int>  $purchasableIds
     */
    public function check(string $customerPhoneE164, array $purchasableIds, int $windowMinutes = 5): SpamCheckResult
    {
        $phone = PhoneNumber::tryParse($customerPhoneE164);
        $phoneHash = $phone ? $phone->hash() : hash('sha256', $customerPhoneE164);

        /** @var Order|null $recentOrder */
        $recentOrder = Order::query()
            ->where('customer_phone_hash', $phoneHash)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->whereIn('status', ['pending_confirmation', 'confirmed', 'dispatched'])
            ->with('lines')
            ->latest('id')
            ->first();

        if ($recentOrder !== null) {
            $recentPurchasableIds = $recentOrder->lines
                ->pluck('purchasable_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            $matching = array_intersect($purchasableIds, $recentPurchasableIds);

            if (! empty($matching)) {
                return SpamCheckResult::duplicate(
                    previousOrderId: $recentOrder->id,
                    flags: ['duplicate_phone_and_items_within_5_minutes'],
                    reasonAr: __('طلب مكرر لنفس رقم الهاتف والمنتج خلال دقائق معدودة')
                );
            }
        }

        return SpamCheckResult::pass();
    }
}
