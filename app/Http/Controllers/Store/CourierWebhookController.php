<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Fulfillment\Services\ShipmentTrackingSyncService;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class CourierWebhookController extends Controller
{
    public function handle(Request $request, string $driver, ShipmentTrackingSyncService $syncService): JsonResponse
    {
        // 1. Resolve Tracking Number & New Status from payload
        $payload = $request->all();
        $trackingNumber = (string) ($payload['trackingNumber'] ?? $payload['tracking_number'] ?? $payload['_id'] ?? '');

        if (empty($trackingNumber)) {
            return response()->json(['error' => 'رقم التتبع مفقود في بيانات الإشعار'], 422);
        }

        /** @var Shipment|null $shipment */
        $shipment = Shipment::query()
            ->where('tracking_number', $trackingNumber)
            ->with('courierAccount')
            ->first();

        if (! $shipment instanceof Shipment) {
            return response()->json(['error' => 'لم يتم العثور على شحنة مطابقة لهذا الرقم'], 404);
        }

        // 2. Validate webhook secret / signature if configured on account
        $account = $shipment->courierAccount;
        if ($account && ! empty($account->webhook_secret)) {
            $incomingSignature = $request->header('X-Webhook-Signature') ?? $request->header('X-Bosta-Signature');
            if ($incomingSignature !== $account->webhook_secret) {
                throw new UnauthorizedHttpException('Signature', 'توقيع الإشعار غير متطابق مع مفتاح الحساب السري.');
            }
        }

        // 3. Map status based on driver / payload
        $rawState = (string) ($payload['state'] ?? $payload['status'] ?? $payload['event'] ?? '');
        $newStatus = $this->resolveStatus($driver, $rawState, $payload);
        $note = (string) ($payload['note'] ?? $payload['message'] ?? $payload['reason'] ?? 'تحديث تلقائي عبر Webhook');

        // 4. Sync status with shipment & cascade to order state machine
        $syncService->syncStatus($shipment, $newStatus, $note);

        return response()->json([
            'success' => true,
            'tracking_number' => $trackingNumber,
            'shipment_status' => $newStatus->value,
            'order_status' => $shipment->order?->status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveStatus(string $driver, string $state, array $payload): ShipmentStatus
    {
        $normalized = strtoupper($state);

        // Check if directly a valid ShipmentStatus value
        $directStatus = ShipmentStatus::tryFrom(strtolower($state));
        if ($directStatus instanceof ShipmentStatus) {
            return $directStatus;
        }

        return match ($normalized) {
            'PICKED_UP', 'RECEIVED_AT_WAREHOUSE', 'PACKAGE_RECEIVED' => ShipmentStatus::PICKED_UP,
            'IN_TRANSIT', 'OUT_FOR_TRANSFER', 'HUB_TRANSFER' => ShipmentStatus::IN_TRANSIT,
            'WAITING_FOR_ROUTE', 'OUT_FOR_DELIVERY', 'DELIVERING' => ShipmentStatus::OUT_FOR_DELIVERY,
            'DELIVERED', 'COMPLETED', 'SUCCESS' => ShipmentStatus::DELIVERED,
            'FAILED', 'DELAYED', 'ATTEMPT_FAILED' => ShipmentStatus::FAILED_ATTEMPT,
            'RETURNING', 'RETURN_IN_PROGRESS', 'CANCELED_RETURN' => ShipmentStatus::RETURNING,
            'RETURNED', 'RETURNED_TO_BUSINESS', 'TERMINATED' => ShipmentStatus::RETURNED,
            'CANCELLED', 'CANCELED' => ShipmentStatus::CANCELLED,
            default => ShipmentStatus::IN_TRANSIT,
        };
    }
}
