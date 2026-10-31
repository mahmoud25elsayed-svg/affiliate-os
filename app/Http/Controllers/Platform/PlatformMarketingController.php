<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Analytics\Services\PlatformVisitorAnalyticsService;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformMarketingController extends Controller
{
    public function __construct(
        protected PlatformVisitorAnalyticsService $analyticsService
    ) {}

    /**
     * Home / Landing page with complete Arabic SEO and Schema.org markup.
     */
    public function home(): View
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('platform.marketing.home', [
            'plans' => $plans,
            'pageTitle' => __('AffiliateOS — نظام التجارة الإلكترونية وإدارة شبكات الأفلييت للتجار في الوطن العربي'),
            'metaDescription' => __('أنشئ متجرك الإلكتروني المستقل، وابنِ شبكة مسوقين بالعمولة بنظام الدفع عند الاستلام مع دفتر أستاذ محاسبي مزدوج وإدارة شحن متكاملة.'),
        ]);
    }

    /**
     * Platform Features Tour.
     */
    public function features(): View
    {
        return view('platform.marketing.features', [
            'pageTitle' => __('الميزات والحلول — AffiliateOS'),
            'metaDescription' => __('تعرف على إمكانيات AffiliateOS: متجر Lunar فائق السرعة، إسناد النقرة الأخيرة، دفتر الأستاذ المالي، كول سنتر التوزيع، وربط شركات الشحن المصرية.'),
        ]);
    }

    /**
     * Dynamic Pricing & Plan Comparison (fetched from plans table).
     */
    public function pricing(): View
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('platform.marketing.pricing', [
            'plans' => $plans,
            'pageTitle' => __('خطط الأسعار ومقارنة الباقات — AffiliateOS'),
            'metaDescription' => __('اختر الخطة المناسبة لحجم تجارتك. باقات شهرية وسنوية تبدأ من 499 ج.م مع تجربة مجانية لجميع الخطط.'),
        ]);
    }

    /**
     * Frequently Asked Questions (FAQ).
     */
    public function faq(): View
    {
        return view('platform.marketing.faq', [
            'pageTitle' => __('الأسئلة الشائعة — AffiliateOS'),
            'metaDescription' => __('إجابات شاملة عن كل ما يخص بدء التجارة، شحن COD، تسوية مستحقات المسوقين، الدومينات المخصصة، والتحويلات المالية.'),
        ]);
    }

    /**
     * E-Commerce & Affiliate Marketing Blog.
     */
    public function blog(): View
    {
        $articles = $this->getBlogArticles();

        return view('platform.marketing.blog', [
            'articles' => $articles,
            'pageTitle' => __('مدونة التجارة والتسويق بالعمولة — AffiliateOS'),
            'metaDescription' => __('أحدث المقالات والاستراتيجيات لتوسيع تجارتك الإلكترونية وإدارة شبكات المسوقين بالعمولة في الأسواق العربية.'),
        ]);
    }

    /**
     * View Single Blog Article.
     */
    public function blogPost(string $slug): View
    {
        $articles = $this->getBlogArticles();
        $article = collect($articles)->firstWhere('slug', $slug);

        if (! $article) {
            abort(404, __('المقال غير موجود.'));
        }

        return view('platform.marketing.blog_post', [
            'article' => $article,
            'pageTitle' => __(':title — مدونة AffiliateOS', ['title' => $article['title']]),
            'metaDescription' => $article['excerpt'],
        ]);
    }

    /**
     * Contact Page.
     */
    public function contact(): View
    {
        return view('platform.marketing.contact', [
            'pageTitle' => __('تواصل معنا واستشر خبراء المنصة — AffiliateOS'),
            'metaDescription' => __('فريق الدعم الفني والمستشارين جاهز للرد على استفساراتك ومساعدتك في إطلاق متجرك الإلكتروني وشبكة المسوقين.'),
        ]);
    }

    /**
     * Submit Contact Inquiry.
     */
    public function submitContact(Request $request): RedirectResponse
    {
        $ip = $request->ip() ?? '127.0.0.1';
        $rateKey = 'contact_submit:'.$ip;

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            throw ValidationException::withMessages([
                'email' => [__('تم تجاوز الحد المسموح للرسائل. يرجى الانتظار :seconds ثانية.', ['seconds' => $seconds])],
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:20'],
            'business_type' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        RateLimiter::hit($rateKey, 3600);

        // Record privacy-friendly conversion
        $this->analyticsService->recordConversion($request, 'contact_submitted');

        return redirect()->route('platform.marketing.contact')
            ->with('success', __('شكراً لتواصلك معنا! استلمنا رسالتك وسيتواصل معك أحد مستشاري التجارة بالمنصة خلال 24 ساعة.'));
    }

    /**
     * View Platform Legal Agreements & Policies (O-7).
     */
    public function legal(string $type): View
    {
        $map = [
            'terms' => [
                'file' => 'merchant-terms.md',
                'title' => __('شروط وأحكام الخدمة للتجار — AffiliateOS'),
                'desc' => __('شروط وأحكام استخدام منصة AffiliateOS وخدمات التجارة السحابية وإدارة المسوقين.'),
            ],
            'privacy' => [
                'file' => 'privacy-policy.md',
                'title' => __('سياسة الخصوصية وحماية البيانات — AffiliateOS'),
                'desc' => __('سياسة الخصوصية وجمع ومعالجة البيانات وفق القانون المصري رقم 151 لسنة 2020.'),
            ],
            'dpa' => [
                'file' => 'data-processing-agreement.md',
                'title' => __('اتفاقية معالجة البيانات (DPA) — AffiliateOS'),
                'desc' => __('اتفاقية معالجة البيانات الشخصية لرواد الأعمال والمتاجر الشريكة.'),
            ],
            'affiliate-terms' => [
                'file' => 'affiliate-terms.md',
                'title' => __('شروط وأحكام برنامج الأفلييت — AffiliateOS'),
                'desc' => __('القواعد والشروط المنظمة لبرامج التسويق بالعمولة واحتساب نسب العمولات والأرباح.'),
            ],
        ];

        if (! isset($map[$type])) {
            abort(404, __('الوثيقة القانونية غير موجودة.'));
        }

        $filePath = base_path('docs/legal/'.$map[$type]['file']);
        $markdown = file_exists($filePath) ? (string) file_get_contents($filePath) : __('# الوثيقة قيد التحديث');
        $html = Str::markdown($markdown);

        return view('platform.marketing.legal', [
            'content' => $html,
            'pageTitle' => $map[$type]['title'],
            'metaDescription' => $map[$type]['desc'],
        ]);
    }

    /**
     * Dynamic XML Sitemap for SEO (SM-09).
     */
    public function sitemap(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $now = now()->toAtomString();

        $urls = [
            ['loc' => $baseUrl, 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => "{$baseUrl}/features", 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => "{$baseUrl}/pricing", 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => "{$baseUrl}/register/merchant", 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['loc' => "{$baseUrl}/faq", 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => "{$baseUrl}/blog", 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => "{$baseUrl}/contact", 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => "{$baseUrl}/terms", 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => "{$baseUrl}/privacy", 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => "{$baseUrl}/dpa", 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => "{$baseUrl}/affiliate-terms", 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        foreach ($this->getBlogArticles() as $article) {
            $urls[] = [
                'loc' => "{$baseUrl}/blog/{$article['slug']}",
                'priority' => '0.7',
                'changefreq' => 'monthly',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$u['loc']}</loc>\n";
            $xml .= "    <lastmod>{$now}</lastmod>\n";
            $xml .= "    <changefreq>{$u['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$u['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getBlogArticles(): array
    {
        return [
            [
                'slug' => 'cod-ecommerce-egypt-scaling-guide',
                'title' => __('الدليل الشامل للتوسع في تجارة الدفع عند الاستلام (COD) في مصر'),
                'excerpt' => __('كيف تدير سيولة التحصيل، وتخفض نسب الارتجاع من 35% إلى أقل من 12% باستخدام تأكيد الطلبات عبر الكول سنتر.'),
                'date' => '2026-10-25',
                'reading_time' => __('6 دقائق'),
                'author' => __('فريق استشارات AffiliateOS'),
                'content' => __('يعتبر نموذج الدفع عند الاستلام شريان التجارة الإلكترونية في الشرق الأوسط ومصر بشكل خاص. للنجاح في هذا النموذج، تحتاج إلى بنية محكمة تربط بين سرعة التأكيد الهاتفية وعزل العمولات المحاسبية.'),
            ],
            [
                'slug' => 'double-entry-ledger-affiliate-networks',
                'title' => __('لماذا تحتاج كل شبكة تسويق بالعمولة إلى دفتر أستاذ محاسبي مزدوج؟'),
                'excerpt' => __('تجنب أخطاء تكرار احتساب الأرباح واختفاء الفوارق المالية عند إلغاء الشحنات عبر المعمارية المحاسبية المزدوجة.'),
                'date' => '2026-10-20',
                'reading_time' => __('5 دقائق'),
                'author' => __('المهندس المالي للمنصة'),
                'content' => __('في شبكات التسويق بالعمولة، تحدث الفوضى عندما ترتبط أرباح المسوق بسجلات أحادية القيد. باستخدام دفتر الأستاذ المزدوج، يمثل كل استحقاق قيداً متوازناً يمنع أي فارق سنت واحد.'),
            ],
            [
                'slug' => 'last-click-attribution-vs-multi-touch',
                'title' => __('نموذج إسناد النقرة الأخيرة (Last-Click): لماذا يفضله المسوقون في مصر؟'),
                'excerpt' => __('مقارنة فنية بين نماذج الإسناد وكيف تضمن شفافية احتساب أرباح الحملات الإعلانية على تيك توك وميتا.'),
                'date' => '2026-10-15',
                'reading_time' => __('4 دقائق'),
                'author' => __('قسم أداء التسويق'),
                'content' => __('الشفافية هي العامل الحاسم في جذب كبار المسوقين. نموذج النقرة الأخيرة يمنح المسوق ثقة كاملة بأن جهوده الترويجية المباشرة هي التي تحصد العمولة المستحقة دون التباس.'),
            ],
        ];
    }
}
