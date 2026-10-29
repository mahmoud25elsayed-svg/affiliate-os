<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Lunar\Models\Order;

class OrderDocumentController extends Controller
{
    /**
     * Render or stream Arabic tax / commercial invoice for an order.
     */
    public function invoice(Request $request, string $reference): Response|View
    {
        /** @var Order $order */
        $order = Order::query()
            ->with(['lines.purchasable', 'shippingAddress'])
            ->where('reference', $reference)
            ->firstOrFail();

        $this->authorizeInvoiceAccess($request, $order);

        $settings = GeneralSettings::load();
        $tenant = tenant();

        if ($request->boolean('pdf')) {
            /** @var \Barryvdh\DomPDF\PDF $pdf */
            $pdf = Pdf::loadView('store.documents.invoice', [
                'order' => $order,
                'settings' => $settings,
                'tenant' => $tenant,
                'isPdf' => true,
            ]);

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"invoice-{$order->reference}.pdf\"",
            ]);
        }

        return view('store.documents.invoice', [
            'order' => $order,
            'settings' => $settings,
            'tenant' => $tenant,
            'isPdf' => false,
        ]);
    }

    /**
     * Render or stream internal courier shipping waybill (بوليصة شحن) in Arabic.
     */
    public function waybill(Request $request, string $reference): Response|View
    {
        /** @var Order $order */
        $order = Order::query()
            ->with(['lines.purchasable', 'shippingAddress'])
            ->where('reference', $reference)
            ->firstOrFail();

        $this->authorizeWaybillAccess($request, $order);

        $settings = GeneralSettings::load();
        $tenant = tenant();

        if ($request->boolean('pdf')) {
            /** @var \Barryvdh\DomPDF\PDF $pdf */
            $pdf = Pdf::loadView('store.documents.waybill', [
                'order' => $order,
                'settings' => $settings,
                'tenant' => $tenant,
                'isPdf' => true,
            ]);

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"waybill-{$order->reference}.pdf\"",
            ]);
        }

        return view('store.documents.waybill', [
            'order' => $order,
            'settings' => $settings,
            'tenant' => $tenant,
            'isPdf' => false,
        ]);
    }

    protected function authorizeWaybillAccess(Request $request, Order $order): void
    {
        $user = $request->user();
        $tenantId = tenant('id') ? (string) tenant('id') : null;

        if (! $user instanceof User || ($tenantId && $user->tenant_id !== $tenantId && ! $user->is_platform_admin)) {
            abort(403, 'غير مصرح بعرض بوليصة الشحن بدون تسجيل دخول كموظف للمتجر.');
        }

        if ($user->is_platform_admin) {
            return;
        }

        $allowedRoles = ['owner', 'manager', 'orders', 'call_center', 'support'];
        if (! $user->hasAnyRole($allowedRoles)) {
            abort(403, 'ليس لديك صلاحية عرض بوالص الشحن.');
        }
    }

    protected function authorizeInvoiceAccess(Request $request, Order $order): void
    {
        // 1. Signed route allows customer/courier to view invoice via direct link
        if ($request->hasValidSignature()) {
            return;
        }

        // 2. Staff user of the tenant or platform admin
        $user = $request->user();
        $tenantId = tenant('id') ? (string) tenant('id') : null;
        if ($user instanceof User && ($user->is_platform_admin || ! $tenantId || $user->tenant_id === $tenantId)) {
            return;
        }

        // 3. Authenticated customer who owns the order
        $customer = $request->user('customer');
        $ownerId = $order->getAttribute('storefront_customer_id');
        if ($customer && $ownerId && (int) $customer->getAuthIdentifier() === (int) $ownerId) {
            return;
        }

        abort(403, 'غير مصرح بعرض فاتورة هذا الطلب.');
    }
}
