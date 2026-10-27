<?php

declare(strict_types=1);

namespace App\Support\Settings;

class ThemeSettings extends Settings
{
    public string $primary_color = '#4f46e5';

    public string $secondary_color = '#06b6d4';

    public string $accent_color = '#f59e0b';

    public string $font_family = 'Cairo';

    public ?string $logo_url = null;

    public ?string $favicon_url = null;

    public string $hero_headline = 'أفضل المنتجات العصرية بأعلى جودة';

    public string $hero_subheadline = 'تسوق بسهولة وسرعة مع ضمان أفضل الأسعار وتوصيل لجميع المحافظات';

    public ?string $hero_image_url = null;

    public string $hero_button_text = 'تسوق الآن';

    public string $hero_button_url = '/products';

    public bool $show_hero_banner = true;

    public bool $show_featured_categories = true;

    public bool $show_featured_products = true;

    public bool $show_offers_banner = true;

    public bool $show_latest_products = true;

    /**
     * @var array<string>
     */
    public array $sections_order = [
        'hero_banner',
        'featured_categories',
        'featured_products',
        'offers_banner',
        'latest_products',
    ];

    public string $about_us = 'متجرنا يقدم لك تشكيلة مميزة من المنتجات العالية الجودة بأفضل الأسعار وخدمة عملاء على مدار الساعة.';

    public string $terms_and_conditions = 'تطبق الشروط والأحكام القياسية على كافة المشتريات والطلبات داخل جمهورية مصر العربية.';

    public string $privacy_policy = 'نلتزم بحماية خصوصية بياناتك ومعلوماتك الشخصية ولن يتم مشاركتها مع أي طرف خارجي غير معني بتنفيذ طلبك.';

    public string $shipping_and_returns = 'يتم الشحن لكافة محافظات مصر خلال 2-4 أيام عمل، مع إمكانية المعاينة عند الاستلام والاسترجاع خلال 14 يومًا.';

    public static function group(): string
    {
        return 'theme';
    }
}
