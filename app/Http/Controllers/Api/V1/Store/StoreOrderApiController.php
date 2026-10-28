<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Webhooks\Services\WebhookDispatcher;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use App\Support\Enums\ReasonCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lunar\Models\Order;

class StoreOrderApiController extends Controller
{
    public function __construct(
        protected OrderTransitionService $transitionService,
        protected WebhookDispatcher $webhookDispatcher
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $query = Order::query()
            ->with(['lines.purchasable.product', 'addresses'])
            ->latest();

        if ($request->has('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->has('affiliate_id')) {
            $query->where('affiliate_id', (int) $request->input('affiliate_id'));
        }

        $orders = $query->paginate($perPage);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        /** @var Order $order */
        $order = Order::with(['lines.purchasable.product', 'addresses'])
            ->findOrFail($id);

        return response()->json([
            'data' => $order,
        ]);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $statusEnum = OrderStatus::tryFrom($validated['status']);
        if (! $statusEnum) {
            return response()->json([
                'error' => 'Bad Request',
                'message' => "Invalid order status [{$validated['status']}].",
            ], 400);
        }

        /** @var Order $order */
        $order = Order::findOrFail($id);

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        $actor = ($apiKey && $apiKey->owner instanceof User)
            ? OrderActor::staff($apiKey->owner)
            : OrderActor::system();

        $reasonCode = ! empty($validated['reason'])
            ? ReasonCode::tryFrom($validated['reason'])
            : null;

        $fromStatus = $order->status;
        $order = $this->transitionService->transition(
            order: $order,
            to: $statusEnum,
            reason: $reasonCode,
            actor: $actor,
            meta: [],
            note: $validated['reason'] ?? null
        );

        // Dispatch outbound webhook
        $this->webhookDispatcher->dispatch('order.status_updated', [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'from_status' => $fromStatus,
            'to_status' => $order->status,
            'reason' => $validated['reason'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ]);

        if ($statusEnum === OrderStatus::DELIVERED) {
            $this->webhookDispatcher->dispatch('order.delivered', [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'total_minor' => (int) ($order->getRawOriginal('total') ?? 0),
                'delivered_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'message' => "Order transitioned to {$order->status} successfully.",
            'data' => $order,
        ]);
    }
}
