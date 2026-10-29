<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Models\ConsentLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Ticket;
use App\Models\TicketMessage;
use InvalidArgumentException;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;

class CustomerDataExportService
{
    /**
     * Export all personal and transactional data associated with a customer
     * in a structured, machine-readable format (Egyptian Law 151/2020 & GDPR).
     *
     * @return array<string, mixed>
     */
    public function exportCustomerData(Customer $customer): array
    {
        if (empty($customer->tenant_id)) {
            throw new InvalidArgumentException('Customer must belong to a valid tenant.');
        }

        // 1. Profile information
        $profile = [
            'id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone_e164,
            'is_active' => $customer->is_active,
            'phone_verified_at' => $customer->phone_verified_at?->toIso8601String(),
            'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
            'created_at' => $customer->created_at?->toIso8601String(),
            'anonymized_at' => $customer->anonymized_at?->toIso8601String(),
        ];

        // 2. Saved delivery addresses
        $addresses = [];
        /** @var CustomerAddress $addr */
        foreach ($customer->addresses as $addr) {
            $addresses[] = [
                'id' => $addr->id,
                'name' => $addr->name,
                'phone' => $addr->phone,
                'governorate_id' => $addr->governorate_id,
                'city' => $addr->city,
                'address_line' => $addr->address_line,
                'postal_code' => $addr->postal_code,
                'notes' => $addr->notes,
                'is_default' => $addr->is_default,
                'created_at' => $addr->created_at?->toIso8601String(),
            ];
        }

        // 3. Orders history
        $ordersList = [];
        /** @var Order $order */
        foreach ($customer->orders as $order) {
            $linesList = [];
            /** @var OrderLine $line */
            foreach ($order->lines as $line) {
                $linesList[] = [
                    'id' => $line->id,
                    'description' => (string) $line->description,
                    'quantity' => (int) $line->quantity,
                    'unit_price' => (int) ($line->getRawOriginal('unit_price') ?? 0),
                    'sub_total' => (int) ($line->getRawOriginal('sub_total') ?? 0),
                    'total' => (int) ($line->getRawOriginal('total') ?? 0),
                ];
            }

            $ordersList[] = [
                'id' => $order->id,
                'reference' => (string) $order->reference,
                'status' => (string) $order->status,
                'currency_code' => (string) $order->currency_code,
                'sub_total' => (int) ($order->getRawOriginal('sub_total') ?? 0),
                'discount_total' => (int) ($order->getRawOriginal('discount_total') ?? 0),
                'shipping_total' => (int) ($order->getRawOriginal('shipping_total') ?? 0),
                'total' => (int) ($order->getRawOriginal('total') ?? 0),
                'created_at' => $order->created_at?->toIso8601String(),
                'lines' => $linesList,
            ];
        }

        // 4. Support tickets and customer messages
        $ticketsList = [];
        $tickets = Ticket::where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->get();

        /** @var Ticket $ticket */
        foreach ($tickets as $ticket) {
            $messagesList = [];
            /** @var TicketMessage $msg */
            foreach ($ticket->messages as $msg) {
                $messagesList[] = [
                    'id' => $msg->id,
                    'message' => $msg->message,
                    'created_at' => $msg->created_at->toIso8601String(),
                ];
            }

            $ticketsList[] = [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'department' => $ticket->department,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'created_at' => $ticket->created_at->toIso8601String(),
                'messages' => $messagesList,
            ];
        }

        // 5. Consent history
        $consentsList = [];
        $consents = ConsentLog::where('tenant_id', $customer->tenant_id)
            ->where(function ($q) use ($customer) {
                $q->where('subject_identifier', (string) $customer->id);
                if ($customer->email) {
                    $q->orWhere('subject_identifier', $customer->email);
                }
                if ($customer->phone_e164) {
                    $q->orWhere('subject_identifier', $customer->phone_e164);
                }
            })
            ->latest('accepted_at')
            ->get();

        /** @var ConsentLog $log */
        foreach ($consents as $log) {
            $consentsList[] = [
                'document_type' => $log->document_type,
                'version' => $log->version,
                'ip_address' => $log->ip_address,
                'accepted_at' => $log->accepted_at->toIso8601String(),
            ];
        }

        return [
            'schema_version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'customer' => $profile,
            'addresses' => $addresses,
            'orders' => $ordersList,
            'support_tickets' => $ticketsList,
            'consent_history' => $consentsList,
        ];
    }
}
