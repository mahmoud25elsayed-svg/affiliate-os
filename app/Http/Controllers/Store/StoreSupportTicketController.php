<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCannedResponse;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StoreSupportTicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    public function index(Request $request): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $status = $request->query('status');
        $priority = $request->query('priority');
        $department = $request->query('department');
        $level = $request->query('level', Ticket::LEVEL_STORE);

        $query = Ticket::query()
            ->where('tenant_id', $tenant->id)
            ->where('level', $level)
            ->with(['assignedTo', 'affiliate', 'customer', 'latestMessage'])
            ->latest();

        if ($status === 'breached') {
            $query->breachedSla();
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
        } else {
            $query->open();
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($department) {
            $query->where('department', $department);
        }

        $tickets = $query->paginate(20)->withQueryString();
        $metrics = $this->ticketService->getMetrics($tenant, $level);

        return view('store.support.index', [
            'tenant' => $tenant,
            'tickets' => $tickets,
            'metrics' => $metrics,
            'currentLevel' => $level,
            'currentStatus' => $status,
        ]);
    }

    public function show(int $id): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Ticket $ticket */
        $ticket = Ticket::where('tenant_id', $tenant->id)
            ->with(['messages.user', 'messages.affiliate', 'messages.customer', 'assignedTo', 'order', 'payoutRequest'])
            ->findOrFail($id);

        $cannedResponses = TicketCannedResponse::where('tenant_id', $tenant->id)
            ->active()
            ->get();

        $staffUsers = User::where('tenant_id', $tenant->id)->get();

        return view('store.support.show', [
            'tenant' => $tenant,
            'ticket' => $ticket,
            'cannedResponses' => $cannedResponses,
            'staffUsers' => $staffUsers,
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Ticket $ticket */
        $ticket = Ticket::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'is_internal_note' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $isInternalNote = $request->boolean('is_internal_note');
        $senderType = $ticket->level === Ticket::LEVEL_PLATFORM
            ? TicketMessage::SENDER_STAFF
            : TicketMessage::SENDER_STAFF;

        $this->ticketService->addReply(
            ticket: $ticket,
            message: $validated['message'],
            senderType: $senderType,
            user: $user,
            isInternalNote: $isInternalNote
        );

        $msg = $isInternalNote ? __('تم إضافة الملاحظة الداخلية بنجاح.') : __('تم إرسال الرد بنجاح.');

        return redirect()->route('store.support.show', $ticket->id)->with('success', $msg);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Ticket $ticket */
        $ticket = Ticket::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,pending_customer,pending_agent,resolved,closed'],
        ]);

        $this->ticketService->updateStatus($ticket, $validated['status']);

        return redirect()->route('store.support.show', $ticket->id)
            ->with('success', __('تم تحديث حالة التذكرة إلى [:status].', ['status' => $validated['status']]));
    }

    public function assign(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var Ticket $ticket */
        $ticket = Ticket::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var User $agent */
        $agent = User::where('tenant_id', $tenant->id)->findOrFail($validated['assigned_to_user_id']);

        $this->ticketService->assign($ticket, $agent);

        return redirect()->route('store.support.show', $ticket->id)
            ->with('success', __('تم تعيين التذكرة للموظف [:name].', ['name' => $agent->name]));
    }

    public function createPlatformTicket(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'in:technical,finance,general'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $ticket = $this->ticketService->createPlatformTicket(
            tenant: $tenant,
            merchantUser: $user,
            data: $validated
        );

        return redirect()->route('store.support.show', $ticket->id)
            ->with('success', __('تم فتح تذكرة دعم مع إدارة المنصة بنجاح.'));
    }
}
