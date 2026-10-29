<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\PayoutRequest;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Lunar\Models\Order;

class AffiliateSupportController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    public function index(): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $tickets = Ticket::where('tenant_id', $tenant->id)
            ->where('affiliate_id', $affiliate->id)
            ->with(['latestMessage'])
            ->latest()
            ->paginate(15);

        return view('affiliate.support.index', [
            'tenant' => $tenant,
            'affiliate' => $affiliate,
            'tickets' => $tickets,
        ]);
    }

    public function create(): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $recentOrders = Order::where('tenant_id', $tenant->id)
            ->where('affiliate_id', $affiliate->id)
            ->latest()
            ->take(10)
            ->get();

        $recentPayouts = PayoutRequest::where('tenant_id', $tenant->id)
            ->where('affiliate_id', $affiliate->id)
            ->latest()
            ->take(5)
            ->get();

        return view('affiliate.support.create', [
            'tenant' => $tenant,
            'affiliate' => $affiliate,
            'recentOrders' => $recentOrders,
            'recentPayouts' => $recentPayouts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'in:orders,finance,shipping,technical,general'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
            'message' => ['required', 'string', 'max:5000'],
            'order_id' => ['nullable', 'integer'],
            'payout_request_id' => ['nullable', 'integer'],
        ]);

        $ticket = $this->ticketService->createStoreTicket(
            data: $validated,
            tenant: $tenant,
            affiliate: $affiliate
        );

        return redirect()->route('affiliate.support.show', $ticket->id)
            ->with('success', 'تم فتح تذكرة الدعم بنجاح وسيتم الرد عليك في أقرب وقت.');
    }

    public function show(int $id): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        /** @var Ticket $ticket */
        $ticket = Ticket::where('tenant_id', $tenant->id)
            ->where('affiliate_id', $affiliate->id)
            ->with(['order', 'payoutRequest'])
            ->findOrFail($id);

        // Security / Privacy: ONLY public messages, NEVER internal notes!
        $messages = $ticket->messages()
            ->publicMessages()
            ->with(['user', 'affiliate'])
            ->get();

        return view('affiliate.support.show', [
            'tenant' => $tenant,
            'affiliate' => $affiliate,
            'ticket' => $ticket,
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        /** @var Ticket $ticket */
        $ticket = Ticket::where('tenant_id', $tenant->id)
            ->where('affiliate_id', $affiliate->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->ticketService->addReply(
            ticket: $ticket,
            message: $validated['message'],
            senderType: TicketMessage::SENDER_AFFILIATE,
            affiliate: $affiliate,
            isInternalNote: false
        );

        return redirect()->route('affiliate.support.show', $ticket->id)
            ->with('success', 'تم إرسال ردك بنجاح.');
    }
}
