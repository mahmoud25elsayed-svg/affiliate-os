<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Services;

use App\Domain\Promotions\Events\AbandonedCartDetected;
use App\Models\AbandonedCart;
use Illuminate\Support\Facades\Event;

class AbandonedCartService
{
    /**
     * Record or update an active cart session for abandonment tracking.
     *
     * @param  array<string, mixed>  $cartData
     */
    public function recordCart(
        string $sessionId,
        ?int $customerId,
        array $cartData,
        int $subtotalMinor,
        int $itemsCount,
        ?string $phone = null,
        ?string $name = null,
        ?string $email = null
    ): AbandonedCart {
        /** @var AbandonedCart $abandonedCart */
        $abandonedCart = AbandonedCart::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'customer_id' => $customerId,
                'customer_name' => $name,
                'phone_e164' => $phone,
                'email' => $email,
                'cart_data' => $cartData,
                'subtotal_minor' => $subtotalMinor,
                'items_count' => $itemsCount,
                'last_active_at' => now(),
            ]
        );

        return $abandonedCart;
    }

    /**
     * Scan carts that have been idle past the threshold and trigger reminder events.
     */
    public function scanAndNotifyAbandoned(int $minutesThreshold = 30): int
    {
        $cutoff = now()->subMinutes($minutesThreshold);

        $abandoned = AbandonedCart::query()
            ->whereNull('recovered_at')
            ->whereNull('reminded_at')
            ->where('last_active_at', '<=', $cutoff)
            ->where(function ($q) {
                $q->whereNotNull('phone_e164')
                    ->orWhereNotNull('email');
            })
            ->get();

        $count = 0;
        foreach ($abandoned as $cart) {
            $cart->markReminded();
            Event::dispatch(new AbandonedCartDetected($cart));
            $count++;
        }

        return $count;
    }

    /**
     * Recover an abandoned cart using its unique recovery token.
     */
    public function recoverByToken(string $token): ?AbandonedCart
    {
        /** @var AbandonedCart|null $cart */
        $cart = AbandonedCart::query()
            ->where('recovery_token', $token)
            ->first();

        if ($cart && ! $cart->isRecovered()) {
            $cart->markRecovered();
        }

        return $cart;
    }
}
