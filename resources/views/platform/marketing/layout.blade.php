<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? __('AffiliateOS — نظام التجارة والتسويق بالعمولة') }}</title>
    <meta name="description" content="{{ $metaDescription ?? 'منصة التجارة الإلكترونية وإدارة شبكات المسوقين بالعمولة للتجار في الوطن العربي والعالم.' }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%234f46e5'/%3E%3Cpath d='M30 70 L50 25 L70 70 L55 70 L50 55 L45 70 Z' fill='%23ffffff'/%3E%3C/svg%3E">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $pageTitle ?? 'AffiliateOS' }}">
    <meta property="og:description" content="{{ $metaDescription ?? '' }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_EG' : 'en_US' }}">
    <meta property="og:site_name" content="AffiliateOS">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle ?? 'AffiliateOS' }}">
    <meta name="twitter:description" content="{{ $metaDescription ?? '' }}">

    <!-- Schema.org SoftwareApplication JSON-LD -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "SoftwareApplication",
      "name": "AffiliateOS",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "All",
      "inLanguage": "{{ app()->getLocale() }}",
      "offers": {
        "@@type": "Offer",
        "price": "499",
        "priceCurrency": "EGP"
      },
      "description": {!! json_encode(__('نظام تشغيل متكامل لإدارة المتاجر الإلكترونية وشبكات التسويق بالعمولة بنظام الدفع عند الاستلام في الوطن العربي.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            --accent: #06b6d4;
            --bg: #0b0f19;
            --surface: #111827;
            --surface-card: rgba(17, 24, 39, 0.7);
            --border: rgba(255, 255, 255, 0.1);
            --border-hover: rgba(255, 255, 255, 0.2);
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --success: #10b981;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', sans-serif; }
        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(79, 70, 229, 0.12) 0%, transparent 45%),
                radial-gradient(circle at 85% 85%, rgba(124, 58, 237, 0.12) 0%, transparent 45%);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; width: 100%; }

        /* Navigation */
        header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(11, 15, 25, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 0;
        }
        .nav-wrapper { display: flex; justify-content: space-between; align-items: center; }
        .logo {
            font-size: 1.6rem;
            font-weight: 900;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-links { display: flex; gap: 1.75rem; list-style: none; align-items: center; }
        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--text-main); }
        .nav-actions { display: flex; gap: 1rem; align-items: center; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1.4rem;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-primary {
            background: var(--primary-gradient);
            color: #fff;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.45);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--border-hover);
        }

        /* Hero & Sections */
        main { flex: 1; }
        section { padding: 4.5rem 0; }
        .section-header { text-align: center; max-width: 750px; margin: 0 auto 3.5rem; }
        .section-header .badge {
            display: inline-block;
            background: rgba(79, 70, 229, 0.15);
            color: #818cf8;
            border: 1px solid rgba(79, 70, 229, 0.3);
            border-radius: 50px;
            padding: 0.25rem 0.85rem;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .section-header h2 { font-size: 2.25rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; }
        .section-header p { color: var(--text-muted); font-size: 1.1rem; }

        /* Cards & Grid */
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.75rem; }
        .card {
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 2rem;
            transition: all 0.3s;
        }
        .card:hover {
            border-color: rgba(99, 102, 241, 0.4);
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
        }

        /* Footer */
        footer {
            background: #070a12;
            border-top: 1px solid var(--border);
            padding: 4rem 0 2rem;
            margin-top: auto;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 2.5rem;
            margin-bottom: 3rem;
        }
        .footer-col h4 { font-size: 1.05rem; font-weight: 700; margin-bottom: 1.25rem; color: #fff; }
        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 0.75rem; }
        .footer-col a { color: var(--text-muted); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
        .footer-col a:hover { color: #fff; }
        .footer-bottom {
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        @media (max-width: 900px) {
            .nav-links { display: none; }
            .footer-grid { grid-template-columns: 1fr; }
            .section-header h2 { font-size: 1.8rem; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header>
        <div class="container nav-wrapper">
            <a href="{{ route('platform.marketing.home') }}" class="logo">
                <span>AffiliateOS</span>
            </a>
            <ul class="nav-links">
                <li><a href="{{ route('platform.marketing.home') }}" class="{{ request()->routeIs('platform.marketing.home') ? 'active' : '' }}">{{ __('الرئيسية') }}</a></li>
                <li><a href="{{ route('platform.marketing.features') }}" class="{{ request()->routeIs('platform.marketing.features') ? 'active' : '' }}">{{ __('الميزات') }}</a></li>
                <li><a href="{{ route('platform.marketing.pricing') }}" class="{{ request()->routeIs('platform.marketing.pricing') ? 'active' : '' }}">{{ __('الأسعار') }}</a></li>
                <li><a href="{{ route('platform.marketing.faq') }}" class="{{ request()->routeIs('platform.marketing.faq') ? 'active' : '' }}">{{ __('الأسئلة الشائعة') }}</a></li>
                <li><a href="{{ route('platform.marketing.blog') }}" class="{{ request()->routeIs('platform.marketing.blog*') ? 'active' : '' }}">{{ __('المدونة') }}</a></li>
                <li><a href="{{ route('platform.marketing.contact') }}" class="{{ request()->routeIs('platform.marketing.contact') ? 'active' : '' }}">{{ __('تواصل معنا') }}</a></li>
            </ul>
            <div class="nav-actions">
                <a href="?lang={{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}" class="btn btn-secondary">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</a>
                <a href="/login" class="btn btn-secondary">{{ __('تسجيل الدخول') }}</a>
                <a href="{{ route('onboarding.register') }}" class="btn btn-primary">{{ __('ابدأ مجاناً') }}</a>
            </div>
        </div>
    </header>

    <main>
        @if(session('success'))
            <div class="container" style="margin-top: 1.5rem;">
                <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success); color: #34d399; padding: 1rem 1.5rem; border-radius: 8px; font-weight: 600;">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <a href="{{ route('platform.marketing.home') }}" class="logo" style="margin-bottom: 1rem;">AffiliateOS</a>
                    <p style="color: var(--text-muted); font-size: 0.95rem; max-width: 380px;">
                        {{ __('نظام تشغيل متكامل للتجارة الإلكترونية والتسويق بالعمولة. مصمم خصيصاً لمواجهة تحديات الدفع عند الاستلام، وتنسيق شركات الشحن، وضمان عدالة تسوية العمولات في السوق المصري.') }}
                    </p>
                </div>
                <div class="footer-col">
                    <h4>{{ __('المنصة') }}</h4>
                    <ul>
                        <li><a href="{{ route('platform.marketing.features') }}">{{ __('الميزات والقدرات') }}</a></li>
                        <li><a href="{{ route('platform.marketing.pricing') }}">{{ __('خطط الأسعار') }}</a></li>
                        <li><a href="{{ route('onboarding.register') }}">{{ __('إنشاء متجر جديد') }}</a></li>
                        <li><a href="{{ route('health.check') }}">{{ __('حالة النظام والجاهزية') }}</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>{{ __('المصادر والدعم') }}</h4>
                    <ul>
                        <li><a href="{{ route('platform.marketing.blog') }}">{{ __('المدونة والمقالات') }}</a></li>
                        <li><a href="{{ route('platform.marketing.faq') }}">{{ __('الأسئلة الشائعة') }}</a></li>
                        <li><a href="{{ route('platform.marketing.contact') }}">{{ __('فريق المبيعات والدعم') }}</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>{{ __('الامتثال والشروط') }}</h4>
                    <ul>
                        <li><a href="{{ route('legal.terms') }}">{{ __('شروط الخدمة للتجار') }}</a></li>
                        <li><a href="{{ route('legal.dpa') }}">{{ __('اتفاقية معالجة البيانات (DPA)') }}</a></li>
                        <li><a href="{{ route('legal.affiliate-terms') }}">{{ __('شروط برنامج الأفلييت') }}</a></li>
                        <li><a href="{{ route('legal.privacy') }}">{{ __('سياسة الخصوصية') }}</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>{{ __('© 2026 AffiliateOS. جميع الحقوق محفوظة. يدعم 22 دولة عربية وأهم الأسواق العالمية.') }}</p>
                <p style="display: flex; gap: 1rem;">
                    <span>{{ __('🛡️ عزل تام لبيانات المستأجرين') }}</span>
                    <span>{{ __('⚡ بنية تحتية سريعة') }}</span>
                </p>
            </div>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
