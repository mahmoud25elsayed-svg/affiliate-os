<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Services;

use App\Models\Affiliate;
use App\Models\CourierAccount;
use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Models\TenantShippingRate;
use App\Support\Settings\GeneralSettings;
use Lunar\Models\Product;

class MerchantOnboardingProgressService
{
    /**
     * Calculate onboarding checklist progress and steps for the given tenant.
     *
     * @return array{
     *     completion_percentage: int,
     *     completed_steps_count: int,
     *     total_steps_count: int,
     *     is_completed: bool,
     *     steps: array<string, array{
     *         id: string,
     *         title: string,
     *         description: string,
     *         completed: bool,
     *         action_url: string,
     *         icon: string
     *     }>
     * }
     */
    public function getProgress(Tenant $tenant): array
    {
        return $tenant->run(function () use ($tenant) {
            $tenantId = (string) $tenant->id;

            // 1. Logo & Branding check
            $hasLogo = false;

            try {
                $settings = GeneralSettings::load();
                $hasLogo = ! empty($settings->logo_url);
            } catch (\Throwable) {
                $hasLogo = false;
            }

            // 2. First Product check
            $hasProduct = Product::where('tenant_id', $tenantId)->exists();

            // 3. Shipping Setup check
            $hasShipping = TenantShippingRate::where('tenant_id', $tenantId)->exists()
                || CourierAccount::where('tenant_id', $tenantId)->exists();

            // 4. Payment Gateways / COD check
            $hasPayments = TenantPaymentGateway::where('tenant_id', $tenantId)->exists()
                || ($tenant->data['cod_enabled'] ?? true); // COD is enabled by default in settings

            // 5. Affiliate Program check
            $hasAffiliates = Affiliate::where('tenant_id', $tenantId)->exists()
                || ($tenant->data['affiliate_program_active'] ?? false);

            $steps = [
                'store_logo' => [
                    'id' => 'store_logo',
                    'title' => __('شعار وهوية المتجر'),
                    'description' => __('قم برفع شعار متجرك وتحديد الألوان الأساسية لعلامتك التجارية.'),
                    'completed' => $hasLogo,
                    'action_url' => '/store/settings/theme',
                    'icon' => '🎨',
                ],
                'first_product' => [
                    'id' => 'first_product',
                    'title' => __('إضافة أول منتج'),
                    'description' => __('أضف منتجك الأول مع تحديد سعر البيع وسعر الجملة وعمولة المسوق.'),
                    'completed' => $hasProduct,
                    'action_url' => '/store/products/create',
                    'icon' => '📦',
                ],
                'shipping_setup' => [
                    'id' => 'shipping_setup',
                    'title' => __('ضبط إعدادات الشحن والمحافظات'),
                    'description' => __('حدد أسعار الشحن للمحافظات أو اربط حسابك في بوسطة أو أوتو.'),
                    'completed' => $hasShipping,
                    'action_url' => '/store/settings/shipping',
                    'icon' => '🚚',
                ],
                'payment_gateways' => [
                    'id' => 'payment_gateways',
                    'title' => __('خيارات وبوابات الدفع'),
                    'description' => __('فعّل خيار الدفع عند الاستلام (COD) أو بوابات الدفع الإلكتروني.'),
                    'completed' => $hasPayments,
                    'action_url' => '/store/settings/payments',
                    'icon' => '💳',
                ],
                'affiliate_program' => [
                    'id' => 'affiliate_program',
                    'title' => __('تفعيل برنامج الأفلييت'),
                    'description' => __('حدد نسب العمولات الافتراضية وشروط سحب الأرباح لجذب المسوقين.'),
                    'completed' => $hasAffiliates,
                    'action_url' => '/store/affiliates/settings',
                    'icon' => '🤝',
                ],
            ];

            $completedCount = count(array_filter($steps, fn ($step) => $step['completed']));
            $totalCount = count($steps);
            $percentage = (int) round(($completedCount / $totalCount) * 100);

            return [
                'completion_percentage' => $percentage,
                'completed_steps_count' => $completedCount,
                'total_steps_count' => $totalCount,
                'is_completed' => $percentage === 100,
                'steps' => $steps,
            ];
        });
    }
}
