<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Affiliate;

use App\Domain\Affiliate\Actions\SubmitAffiliateOrderAction;
use App\Domain\Webhooks\Services\WebhookDispatcher;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\ApiKey;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lunar\Models\Order;

class AffiliateOrderApiController extends Controller
{
    public function __construct(
        protected SubmitAffiliateOrderAction $submitAction,
        protected WebhookDispatcher $webhookDispatcher
    ) {}

    protected function resolveAffiliate(Request $request): Affiliate
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey && $apiKey->owner instanceof Affiliate) {
            return $apiKey->owner;
        }

        if ($request->has('affiliate_id')) {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::findOrFail((int) $request->input('affiliate_id'));

            return $affiliate;
        }

        /** @var Affiliate|null $firstAffiliate */
        $firstAffiliate = Affiliate::first();
        if ($firstAffiliate) {
            return $firstAffiliate;
        }

        abort(403, 'Affiliate identity required.');
    }

    public function index(Request $request): JsonResponse
    {
        $affiliate = $this->resolveAffiliate($request);
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $query = Order::query()
            ->where('affiliate_id', $affiliate->id)
            ->with(['lines.purchasable', 'shippingAddress'])
            ->latest();

        if ($request->has('status')) {
            $query->where('status', (string) $request->input('status'));
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

    public function show(Request $request, int $id): JsonResponse
    {
        $affiliate = $this->resolveAffiliate($request);

        /** @var Order $order */
        $order = Order::query()
            ->where('affiliate_id', $affiliate->id)
            ->with(['lines.purchasable', 'shippingAddress'])
            ->findOrFail($id);

        return response()->json([
            'data' => $order,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $affiliate = $this->resolveAffiliate($request);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_alt_phone' => ['nullable', 'string', 'max:30'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'string', 'max:50'],
            'flat' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'variant_id' => ['required', 'integer', 'exists:lunar_product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'selling_price_minor' => ['required', 'integer', 'min:100'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $order = $this->submitAction->execute($affiliate, [
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_alt_phone' => $validated['customer_alt_phone'] ?? null,
                'governorate_id' => (int) $validated['governorate_id'],
                'city' => $validated['city'],
                'address' => $validated['address'],
                'building_number' => $validated['building_number'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'flat' => $validated['flat'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'items' => [
                    [
                        'variant_id' => (int) $validated['variant_id'],
                        'quantity' => (int) $validated['quantity'],
                        'selling_price_minor' => (int) $validated['selling_price_minor'],
                    ],
                ],
                'utm_source' => $validated['utm_source'] ?? null,
                'utm_medium' => $validated['utm_medium'] ?? null,
                'utm_campaign' => $validated['utm_campaign'] ?? null,
                'utm_content' => $validated['utm_content'] ?? null,
            ]);

            // Dispatch webhook
            $this->webhookDispatcher->dispatch('order.created', [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'affiliate_id' => $affiliate->id,
                'total_minor' => (int) ($order->getRawOriginal('total') ?? 0),
                'status' => $order->status,
                'created_at' => now()->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'Affiliate order created successfully.',
                'data' => $order,
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'error' => 'Unprocessable Entity',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
