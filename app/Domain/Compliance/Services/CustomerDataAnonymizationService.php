<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

class CustomerDataAnonymizationService
{
    /**
     * Anonymize and redact all personal identifiable information (PII) for a customer
     * while preserving financial, tax, and double-entry accounting integrity
     * (Egyptian Law 151/2020 Art. 18 & GDPR Art. 17).
     */
    public function anonymizeCustomer(Customer $customer): void
    {
        if (empty($customer->tenant_id)) {
            throw new InvalidArgumentException('Customer must belong to a valid tenant.');
        }

        DB::transaction(function () use ($customer) {
            $customerId = $customer->id;
            $tenantId = $customer->tenant_id;
            $anonymizedEmail = "anonymized_{$customerId}@deleted.local";
            $anonymizedPhone = '+200000000000';
            $anonymizedHash = hash('sha256', $anonymizedPhone);

            // 1. Redact Customer Addresses
            $customer->addresses()->update([
                'name' => __('مستهلك مجهول'),
                'phone' => $anonymizedPhone,
                'address_line' => __('عنوان مجهول (تم الحذف بناءً على طلب صاحب البيانات)'),
                'notes' => null,
                'postal_code' => null,
            ]);

            // 2. Redact Order Addresses associated with customer's orders
            $orderIds = Order::where('tenant_id', $tenantId)
                ->where('storefront_customer_id', $customerId)
                ->pluck('id');

            if ($orderIds->isNotEmpty()) {
                OrderAddress::whereIn('order_id', $orderIds)->update([
                    'first_name' => __('مستهلك'),
                    'last_name' => __('مجهول'),
                    'company_name' => null,
                    'line_one' => __('تم الحذف بناءً على طلب الخصوصية'),
                    'line_two' => null,
                    'line_three' => null,
                    'contact_phone' => $anonymizedPhone,
                    'contact_email' => $anonymizedEmail,
                    'delivery_instructions' => null,
                ]);
            }

            // 3. Redact Profile and Mark as Anonymized
            $customer->first_name = __('مستهلك');
            $customer->last_name = __('مجهول');
            $customer->email = $anonymizedEmail;
            $customer->phone_e164 = $anonymizedPhone;
            $customer->phone_hash = $anonymizedHash;
            $customer->password = null;
            $customer->remember_token = null;
            $customer->is_active = false;
            $customer->anonymized_at = now();
            $customer->save();

            // 4. Soft-delete the customer record
            $customer->delete();

            Log::info("Compliance: Customer #{$customerId} in tenant {$tenantId} has been successfully anonymized per Right to Erasure.");
        });
    }
}
