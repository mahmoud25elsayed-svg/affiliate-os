<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Support\Billing\Services\InvoicePdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceDownloadController extends Controller
{
    public function __invoke(Request $request, SubscriptionInvoice $invoice, InvoicePdfGenerator $generator): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        // Authorize: Platform Admin or store staff belonging to the invoice's tenant
        if (! $user->is_platform_admin && $user->tenant_id !== $invoice->tenant_id) {
            abort(403, __('غير مصرح لك بتحميل هذه الفاتورة.'));
        }

        return $generator->downloadResponse($invoice);
    }
}
