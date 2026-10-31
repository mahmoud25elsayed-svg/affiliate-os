<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('AffiliateOS — منصة التجارة والتسويق بالعمولة للتجار') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --accent: #06b6d4;
            --bg: #090d16;
            --card-bg: rgba(18, 24, 38, 0.7);
            --border: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', sans-serif; }
        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(168, 85, 247, 0.15) 0%, transparent 40%);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-actions { display: flex; align-items: center; gap: 1rem; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-ghost { color: var(--text-main); border-color: var(--border); background: rgba(255, 255, 255, 0.03); }
        .btn-ghost:hover { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.2); }
        .btn-primary { background: var(--primary-gradient); color: #fff; box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 15px 30px -5px rgba(99, 102, 241, 0.6); }

        .hero {
            padding: 6rem 0 4rem;
            text-align: center;
            position: relative;
        }
        .badge {
            display: inline-block;
            padding: 0.4rem 1.25rem;
            background: rgba(99, 102, 241, 0.12);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #a5b4fc;
            margin-bottom: 1.75rem;
        }
        .hero h1 {
            font-size: clamp(2.25rem, 5vw, 4rem);
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            letter-spacing: -0.5px;
        }
        .hero h1 span {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            font-size: 1.25rem;
            color: var(--text-muted);
            max-width: 700px;
            margin: 0 auto 2.5rem;
        }
        .hero-actions { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            padding: 4rem 0 6rem;
        }
        .feature-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            padding: 2.5rem;
            backdrop-filter: blur(16px);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            border-color: rgba(99, 102, 241, 0.4);
        }
        .feature-icon {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 1rem;
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
        }
        .feature-title { font-size: 1.35rem; font-weight: 700; margin-bottom: 0.75rem; }
        .feature-desc { color: var(--text-muted); font-size: 0.95rem; }
        footer {
            border-top: 1px solid var(--border);
            padding: 2.5rem 0;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <span>⚡ AffiliateOS</span>
            </div>
            <div class="nav-actions">
                <a href="{{ route('onboarding.register') }}" class="btn btn-primary">{{ __('ابدأ مجاناً') }}</a>
            </div>
        </header>

        <section class="hero">
            <div class="badge">{{ __('🚀 الجيل الجديد من التجارة الإلكترونية والتسويق بالعمولة') }}</div>
            <h1>{{ __('متجرك الإلكتروني وشبكة مسوقيك') }} <br><span>{{ __('في منصة واحدة خلال دقيقة') }}</span></h1>
            <p>{{ __('حل متكامل مصمم خصيصاً للتجارة الإلكترونية في الوطن العربي. بدون تعقيد، مع عزل تام للبيانات، ونظام دفع وتسوية عمولات دقيق بالقرش.') }}</p>
            <div class="hero-actions">
                <a href="{{ route('onboarding.register') }}" class="btn btn-primary" style="padding: 1rem 2.25rem; font-size: 1.15rem;">{{ __('سجل متجرك الآن مجاناً ←') }}</a>
            </div>
        </section>

        <section class="features">
            <div class="feature-card">
                <div class="feature-icon">🏬</div>
                <h3 class="feature-title">{{ __('متجر مستقل لكل تاجر') }}</h3>
                <p class="feature-desc">{{ __('كتالوج متطور، إدارة المخزون، خيارات المنتجات، ونطاق فرعي فوري مع دعم كامل للعربية والإنجليزية و28 عملة.') }}</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🤝</div>
                <h3 class="feature-title">{{ __('برنامج تسويق بالعمولة مدمج') }}</h3>
                <p class="feature-desc">{{ __('روابط تتبع مخصصة لمسوقيك، احتساب دقيق للعمولات بدون كسور، وتسوية فورية لأرباح المسوقين.') }}</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3 class="feature-title">{{ __('عزل تام وأمان متقدم') }}</h3>
                <p class="feature-desc">{{ __('عزل كامل لبيانات متجرك وسجل التدقيق، مع حماية ثنائية (2FA) وأدوار وصلاحيات دقيقة لفريق عملك.') }}</p>
            </div>
        </section>

        <footer>
            <p>© {{ date('Y') }} {{ __('AffiliateOS. جميع الحقوق محفوظة.') }}</p>
        </footer>
    </div>
</body>
</html>
