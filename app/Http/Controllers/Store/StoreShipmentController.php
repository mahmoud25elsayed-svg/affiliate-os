<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Fulfillment\Services\CourierManager;
use App\Domain\Fulfillment\Services\ShipmentDispatchService;
use App\Domain\Fulfillment\Services\ShipmentTrackingSyncService;
use App\Http\Controllers\Controller;
use App\Models\CourierAccount;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Lunar\Models\Order;

class StoreShipmentController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $status = $request->query('status');
        $search = $request->query('search');

        $query = Shipment::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['order', 'courierAccount'])
            ->orderByDesc('id');

        if ($status && ShipmentStatus::tryFrom((string) $status)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_phone', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('reference', 'like', "%{$search}%"));
            });
        }

        $shipments = $query->paginate(20)->withQueryString();

        // Calculate aggregate fulfillment statistics
        $stats = [
            'total' => Shipment::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->count(),
            'in_transit' => Shipment::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->whereIn('status', [ShipmentStatus::PICKED_UP->value, ShipmentStatus::IN_TRANSIT->value])->count(),
            'out_for_delivery' => Shipment::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('status', ShipmentStatus::OUT_FOR_DELIVERY->value)->count(),
            'delivered' => Shipment::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('status', ShipmentStatus::DELIVERED->value)->count(),
            'returning_or_returned' => Shipment::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->whereIn('status', [ShipmentStatus::RETURNING->value, ShipmentStatus::RETURNED->value])->count(),
        ];

        $courierAccounts = CourierAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->get();

        return view('store.shipments.index', [
            'shipments' => $shipments,
            'stats' => $stats,
            'courierAccounts' => $courierAccounts,
            'currentStatus' => $status,
            'search' => $search,
            'statuses' => ShipmentStatus::cases(),
        ]);
    }

    public function dispatch(Request $request, Order $order, ShipmentDispatchService $dispatchService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $courierAccountId = $request->input('courier_account_id');
        /** @var CourierAccount|null $courierAccount */
        $courierAccount = $courierAccountId ? CourierAccount::find($courierAccountId) : null;

        $shipment = $dispatchService->dispatchOrder($order, $courierAccount, $user);

        return redirect()->back()
            ->with('success', "تم تسليم الطلب #{$order->id} لشركة الشحن بنجاح برقم تتبع {$shipment->tracking_number}.");
    }

    public function show(Shipment $shipment, CourierManager $manager, ShipmentTrackingSyncService $syncService): View
    {
        // Query live tracking from courier driver
        if ($shipment->courierAccount) {
            $driver = $manager->driver($shipment->courierAccount);
            $tracking = $driver->trackShipment($shipment->tracking_number);

            if ($tracking->status !== $shipment->status) {
                $syncService->syncStatus($shipment, $tracking->status, $tracking->note);
                $shipment->refresh();
            }
        }

        return view('store.shipments.show', [
            'shipment' => $shipment->load(['order', 'courierAccount']),
        ]);
    }

    public function waybill(Shipment $shipment, CourierManager $manager): Response|RedirectResponse
    {
        if ($shipment->waybill_url) {
            return redirect()->away($shipment->waybill_url);
        }

        if ($shipment->courierAccount) {
            $driver = $manager->driver($shipment->courierAccount);
            $pdfContent = $driver->getWaybillPdf($shipment->tracking_number);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"waybill-{$shipment->tracking_number}.pdf\"",
            ]);
        }

        return redirect()->back()->with('error', 'بوليصة الشحن غير متوفرة لهذا الحساب.');
    }
}
