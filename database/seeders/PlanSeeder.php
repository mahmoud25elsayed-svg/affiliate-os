<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'name_ar' => 'البداية',
                'name_en' => 'Starter',
                'description_ar' => 'مثالي للمتاجر الناشئة لاختبار الفكرة والانطلاق السريع مع شبكة مسوقين محدودة.',
                'description_en' => 'Ideal for early-stage stores to launch quickly with an affiliate starter network.',
                'price_monthly' => 29900, // 299.00 EGP
                'price_yearly' => 299000, // 2,990.00 EGP (2 months free)
                'trial_days' => 14,
                'limits' => [
                    'products' => 50,
                    'monthly_orders' => 100,
                    'affiliates' => 5,
                    'staff' => 2,
                    'storage_mb' => 500,
                ],
                'features' => [
                    'affiliate' => true,
                    'call_center' => false,
                    'courier' => false,
                    'cod_reconciliation' => false,
                    'multi_vendor' => false,
                    'custom_domain' => false,
                    'api' => false,
                    'ai' => false,
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'pro',
                'name_ar' => 'المحترف',
                'name_en' => 'Pro',
                'description_ar' => 'للمتاجر المتنامية مع شبكة أفلييت وفريق كول سنتر وربط شركات الشحن.',
                'description_en' => 'For growing stores with active affiliates, call center integration, and courier APIs.',
                'price_monthly' => 79900, // 799.00 EGP
                'price_yearly' => 799000, // 7,990.00 EGP
                'trial_days' => 14,
                'limits' => [
                    'products' => 500,
                    'monthly_orders' => 1500,
                    'affiliates' => 50,
                    'staff' => 10,
                    'storage_mb' => 5000,
                ],
                'features' => [
                    'affiliate' => true,
                    'call_center' => true,
                    'courier' => true,
                    'cod_reconciliation' => true,
                    'multi_vendor' => false,
                    'custom_domain' => true,
                    'api' => true,
                    'ai' => false,
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'enterprise',
                'name_ar' => 'المؤسسات',
                'name_en' => 'Enterprise',
                'description_ar' => 'أقصى طاقة تشغيلية بلا حدود، دعم متعدد البائعين وذكاء اصطناعي وأولوية دعم فني.',
                'description_en' => 'Unlimited scale with AI automation, multi-vendor support, and priority VIP support.',
                'price_monthly' => 199900, // 1,999.00 EGP
                'price_yearly' => 1999000, // 19,990.00 EGP
                'trial_days' => 14,
                'limits' => [
                    'products' => -1,
                    'monthly_orders' => -1,
                    'affiliates' => -1,
                    'staff' => -1,
                    'storage_mb' => -1,
                ],
                'features' => [
                    'affiliate' => true,
                    'call_center' => true,
                    'courier' => true,
                    'cod_reconciliation' => true,
                    'multi_vendor' => true,
                    'custom_domain' => true,
                    'api' => true,
                    'ai' => true,
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
