<?php

declare(strict_types=1);

namespace App\Domain\Support\Services;

use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketService
{
    /**
     * Create a store-level ticket (Customer or Affiliate -> Merchant).
     *
     * @param  array{
     *     subject: string,
     *     message: string,
     *     priority?: string,
     *     department?: string,
     *     order_id?: int|null,
     *     payout_request_id?: int|null,
     *     attachments?: array<string, mixed>|null
     * }  $data
     */
    public function createStoreTicket(
        array $data,
        Tenant $tenant,
        ?User $creator = null,
        ?Affiliate $affiliate = null,
        ?Customer $customer = null
    ): Ticket {
        return DB::transaction(function () use ($data, $tenant, $creator, $affiliate, $customer) {
            $priority = $data['priority'] ?? Ticket::PRIORITY_MEDIUM;
            $department = $data['department'] ?? Ticket::DEPT_GENERAL;
            $slaDueAt = Ticket::calculateSlaDueAt($priority);

            /** @var Ticket $ticket */
            $ticket = Ticket::create([
                'tenant_id' => $tenant->id,
                'ticket_number' => Ticket::generateTicketNumber('TCK'),
                'level' => Ticket::LEVEL_STORE,
                'subject' => $data['subject'],
                'priority' => $priority,
                'status' => Ticket::STATUS_OPEN,
                'department' => $department,
                'created_by_user_id' => $creator?->id,
                'affiliate_id' => $affiliate?->id,
                'customer_id' => $customer?->id,
                'order_id' => $data['order_id'] ?? null,
                'payout_request_id' => $data['payout_request_id'] ?? null,
                'sla_due_at' => $slaDueAt,
            ]);

            $senderType = $affiliate ? TicketMessage::SENDER_AFFILIATE : ($customer ? TicketMessage::SENDER_CUSTOMER : TicketMessage::SENDER_STAFF);

            TicketMessage::create([
                'tenant_id' => $tenant->id,
                'ticket_id' => $ticket->id,
                'user_id' => $creator?->id,
                'affiliate_id' => $affiliate?->id,
                'customer_id' => $customer?->id,
                'sender_type' => $senderType,
                'message' => $data['message'],
                'is_internal_note' => false,
                'attachments' => $data['attachments'] ?? null,
            ]);

            return $ticket;
        });
    }

    /**
     * Create a platform-level ticket (Merchant -> Platform Support).
     *
     * @param  array{
     *     subject: string,
     *     message: string,
     *     priority?: string,
     *     department?: string,
     *     attachments?: array<string, mixed>|null
     * }  $data
     */
    public function createPlatformTicket(
        Tenant $tenant,
        User $merchantUser,
        array $data
    ): Ticket {
        return DB::transaction(function () use ($tenant, $merchantUser, $data) {
            $priority = $data['priority'] ?? Ticket::PRIORITY_MEDIUM;
            $department = $data['department'] ?? Ticket::DEPT_GENERAL;
            $slaDueAt = Ticket::calculateSlaDueAt($priority);

            /** @var Ticket $ticket */
            $ticket = Ticket::create([
                'tenant_id' => $tenant->id,
                'ticket_number' => Ticket::generateTicketNumber('PLT'),
                'level' => Ticket::LEVEL_PLATFORM,
                'subject' => $data['subject'],
                'priority' => $priority,
                'status' => Ticket::STATUS_OPEN,
                'department' => $department,
                'created_by_user_id' => $merchantUser->id,
                'sla_due_at' => $slaDueAt,
            ]);

            TicketMessage::create([
                'tenant_id' => $tenant->id,
                'ticket_id' => $ticket->id,
                'user_id' => $merchantUser->id,
                'sender_type' => TicketMessage::SENDER_STAFF,
                'message' => $data['message'],
                'is_internal_note' => false,
                'attachments' => $data['attachments'] ?? null,
            ]);

            return $ticket;
        });
    }

    /**
     * Add a reply or internal note to a ticket.
     *
     * @param  array<string, mixed>|null  $attachments
     */
    public function addReply(
        Ticket $ticket,
        string $message,
        string $senderType,
        ?User $user = null,
        ?Affiliate $affiliate = null,
        ?Customer $customer = null,
        bool $isInternalNote = false,
        ?array $attachments = null
    ): TicketMessage {
        return DB::transaction(function () use ($ticket, $message, $senderType, $user, $affiliate, $customer, $isInternalNote, $attachments) {
            $ticketMessage = TicketMessage::create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'user_id' => $user?->id,
                'affiliate_id' => $affiliate?->id,
                'customer_id' => $customer?->id,
                'sender_type' => $senderType,
                'message' => $message,
                'is_internal_note' => $isInternalNote,
                'attachments' => $attachments,
            ]);

            // If not an internal note, update ticket response metrics and status
            if (! $isInternalNote) {
                $isStaffReply = in_array($senderType, [TicketMessage::SENDER_STAFF, TicketMessage::SENDER_PLATFORM_ADMIN], true);

                if ($isStaffReply && $ticket->first_response_at === null) {
                    $ticket->first_response_at = now();
                }

                if ($isStaffReply) {
                    $ticket->status = Ticket::STATUS_PENDING_CUSTOMER;
                } else {
                    $ticket->status = Ticket::STATUS_PENDING_AGENT;
                }

                $ticket->save();
            }

            return $ticketMessage;
        });
    }

    /**
     * Update ticket status (e.g. resolve or close).
     */
    public function updateStatus(Ticket $ticket, string $status): Ticket
    {
        $ticket->status = $status;

        if ($status === Ticket::STATUS_RESOLVED && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
        } elseif ($status === Ticket::STATUS_CLOSED && ! $ticket->closed_at) {
            $ticket->closed_at = now();
        }

        $ticket->save();

        return $ticket;
    }

    /**
     * Assign ticket to a support staff member.
     */
    public function assign(Ticket $ticket, User $agent): Ticket
    {
        $ticket->assigned_to_user_id = $agent->id;
        $ticket->save();

        return $ticket;
    }

    /**
     * Get support SLA and workload metrics.
     *
     * @return array{
     *     open_count: int,
     *     breached_sla_count: int,
     *     resolved_today_count: int,
     *     avg_first_response_minutes: int,
     *     avg_resolution_minutes: int
     * }
     */
    public function getMetrics(?Tenant $tenant = null, string $level = 'store'): array
    {
        $query = Ticket::query()->where('level', $level);

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $openCount = (clone $query)->open()->count();
        $breachedSlaCount = (clone $query)->breachedSla()->count();
        $resolvedTodayCount = (clone $query)
            ->where('status', Ticket::STATUS_RESOLVED)
            ->where('resolved_at', '>=', now()->startOfDay())
            ->count();

        // Calculate Average First Response Time (minutes) for resolved or responded tickets
        $ticketsWithFirstResponse = (clone $query)
            ->whereNotNull('first_response_at')
            ->latest()
            ->take(100)
            ->get();

        $frtTotal = 0;
        $frtCount = $ticketsWithFirstResponse->count();
        foreach ($ticketsWithFirstResponse as $t) {
            $frtTotal += (int) $t->getFirstResponseTimeMinutes();
        }
        $avgFrt = $frtCount > 0 ? (int) round($frtTotal / $frtCount) : 0;

        // Calculate Average Resolution Time (minutes)
        $resolvedTickets = (clone $query)
            ->whereNotNull('resolved_at')
            ->latest()
            ->take(100)
            ->get();

        $resTotal = 0;
        $resCount = $resolvedTickets->count();
        foreach ($resolvedTickets as $t) {
            $resTotal += (int) $t->getResolutionTimeMinutes();
        }
        $avgResolution = $resCount > 0 ? (int) round($resTotal / $resCount) : 0;

        return [
            'open_count' => $openCount,
            'breached_sla_count' => $breachedSlaCount,
            'resolved_today_count' => $resolvedTodayCount,
            'avg_first_response_minutes' => $avgFrt,
            'avg_resolution_minutes' => $avgResolution,
        ];
    }
}
