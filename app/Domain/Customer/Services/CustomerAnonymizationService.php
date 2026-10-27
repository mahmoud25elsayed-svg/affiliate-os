<?php

declare(strict_types=1);

namespace App\Domain\Customer\Services;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

class CustomerAnonymizationService
{
    /**
     * Anonymize customer personal data while strictly preserving financial records.
     */
    public function anonymize(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customerId = $customer->id;

            // 1. Delete Wishlists & Addresses
            $customer->wishlists()->delete();
            $customer->addresses()->delete();

            // 2. Anonymize Orders PII while keeping financial totals & line items intact
            /** @var Collection<int, Order> $orders */
            $orders = Order::query()
                ->where('storefront_customer_id', $customerId)
                ->get();

            foreach ($orders as $order) {
                // Redact PII columns from order without touching sub_total, total, etc.
                $order->update([
                    'customer_phone_e164' => null,
                    'notes' => null,
                ]);

                // Anonymize Order Addresses (shipping & billing)
                OrderAddress::query()
                    ->where('order_id', $order->id)
                    ->update([
                        'first_name' => 'عميل',
                        'last_name' => 'محذوف',
                        'contact_phone' => null,
                        'contact_email' => null,
                        'line_one' => 'عنوان محذوف لخصوصية العميل',
                        'line_two' => null,
                        'line_three' => null,
                    ]);
            }

            // 3. Scrub Customer Record
            $customer->update([
                'first_name' => 'عميل محذوف',
                'last_name' => null,
                'email' => null,
                'phone_e164' => null,
                'password' => null,
                'is_active' => false,
                'anonymized_at' => now(),
            ]);

            // 4. Soft Delete Customer
            $customer->delete();

            // 5. Logout if this customer is currently authenticated
            if (Auth::guard('customer')->check() && Auth::guard('customer')->id() === $customerId) {
                Auth::guard('customer')->logout();
            }
        });
    }
}
