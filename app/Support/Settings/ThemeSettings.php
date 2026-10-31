<?php

declare(strict_types=1);

namespace App\Support\Settings;

class ThemeSettings extends Settings
{
    public string $theme_id = 'modern'; // 'modern' or 'boutique'

    public bool $white_label_enabled = false;

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

    public bool $show_features_badges = true;

    public bool $show_featured_categories = true;

    public bool $show_featured_products = true;

    public bool $show_offers_banner = true;

    public bool $show_latest_products = true;

    /**
     * @var array<string>
     */
    public array $sections_order = [
        'hero_banner',
        'features_badges',
        'featured_categories',
        'featured_products',
        'offers_banner',
        'latest_products',
    ];

    /**
     * @var array<array{label: string, url: string, target?: string}>
     */
    public array $navigation_links = [
        ['label' => 'الرئيسية', 'url' => '/'],
        ['label' => 'كل المنتجات', 'url' => '/products'],
        ['label' => 'العروض والخصومات', 'url' => '/products?sale=1'],
        ['label' => 'من نحن', 'url' => '/about'],
        ['label' => 'تتبع طلبك', 'url' => '/account/orders'],
    ];

    /**
     * @var array<string, string>
     */
    public array $footer_socials = [
        'facebook' => '',
        'instagram' => '',
        'tiktok' => '',
        'whatsapp' => '',
    ];

    /**
     * @var array<array{icon: string, title: string, subtitle: string}>
     */
    public array $features_badges = [
        ['icon' => 'truck', 'title' => 'شحن سريع وموثوق', 'subtitle' => 'توصيل لجميع محافظات مصر خلال 2-4 أيام عمل'],
        ['icon' => 'shield', 'title' => 'معاينة قبل الاستلام', 'subtitle' => 'حق فحص المنتج والدفع نقدًا عند الاستلام'],
        ['icon' => 'refresh', 'title' => 'استبدال واسترجاع سهل', 'subtitle' => 'ضمان استرجاع مجاني خلال 14 يومًا من الشراء'],
        ['icon' => 'headset', 'title' => 'خدمة عملاء مميزة', 'subtitle' => 'فريق متخصص لمساعدتك ومتابعة طلبك خطوة بخطوة'],
    ];

    public ?string $custom_css = null;

    public string $about_us = 'متجرنا يقدم لك تشكيلة مميزة من المنتجات العالية الجودة بأفضل الأسعار وخدمة عملاء على مدار الساعة.';

    public string $terms_and_conditions = 'تطبق الشروط والأحكام القياسية على كافة المشتريات والطلبات داخل جمهورية مصر العربية.';

    public string $privacy_policy = 'نلتزم بحماية خصوصية بياناتك ومعلوماتك الشخصية ولن يتم مشاركتها مع أي طرف خارجي غير معني بتنفيذ طلبك.';

    public string $shipping_and_returns = 'يتم الشحن لكافة محافظات مصر خلال 2-4 أيام عمل، مع إمكانية المعاينة عند الاستلام والاسترجاع خلال 14 يومًا.';

    public static function group(): string
    {
        return 'theme';
    }
}
