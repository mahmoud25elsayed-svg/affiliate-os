<?php

declare(strict_types=1);

namespace App\Domain\CallCenter\Jobs;

use App\Domain\CallCenter\Settings\CallCenterSettings;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Support\Enums\ReasonCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;

class ProcessExhaustedCallAttemptsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OrderTransitionService $transitionService): int
    {
        $settings = CallCenterSettings::load();
        if (! $settings->shouldAutoUnreachable()) {
            return 0;
        }

        $maxAttempts = $settings->getMaxCallAttempts();

        $exhaustedOrders = Order::query()
            ->where('status', OrderStatus::PENDING_CONFIRMATION->value)
            ->where('call_attempts_count', '>=', $maxAttempts)
            ->get();

        $count = 0;
        $actor = OrderActor::system();

        foreach ($exhaustedOrders as $order) {
            $transitionService->transition(
                order: $order,
                to: OrderStatus::UNREACHABLE,
                reason: ReasonCode::CUSTOMER_UNREACHABLE,
                actor: $actor,
                meta: ['attempts' => $order->call_attempts_count],
                note: "تعذر الوصول للعميل تلقائياً بعد استنفاد الحد الأقصى للمحاولات ({$maxAttempts})."
            );

            $order->update([
                'assigned_to' => null,
                'locked_until' => null,
                'next_attempt_at' => null,
            ]);

            $count++;
        }

        return $count;
    }
}
