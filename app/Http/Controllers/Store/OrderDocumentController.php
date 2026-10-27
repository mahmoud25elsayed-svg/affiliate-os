<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
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
}
