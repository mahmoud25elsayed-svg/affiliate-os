<?php

declare(strict_types=1);

namespace App\Support\Billing\Services;

use App\Models\SubscriptionInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfGenerator
{
    public function generate(SubscriptionInvoice $invoice): string
    {
        ini_set('memory_limit', '512M');

        $pdf = Pdf::loadView('billing.invoice-pdf', [
            'invoice' => $invoice,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return (string) $pdf->output();
    }

    public function downloadResponse(SubscriptionInvoice $invoice): Response
    {
        $content = $this->generate($invoice);
        $filename = "invoice-{$invoice->invoice_number}.pdf";

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
