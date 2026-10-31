@extends('platform.marketing.layout')

@section('content')
<!-- Hero Section -->
<section style="padding: 6rem 0 4rem; text-align: center;">
    <div class="container" style="max-width: 900px;">
        <span class="badge" style="display: inline-block; background: rgba(79, 70, 229, 0.15); color: #818cf8; border: 1px solid rgba(79, 70, 229, 0.3); border-radius: 50px; padding: 0.35rem 1.25rem; font-size: 0.9rem; font-weight: 700; margin-bottom: 1.5rem;">
            {{ __('🚀 المنصة الأولى للتجارة الإلكترونية والتسويق بالعمولة في الوطن العربي') }}
        </span>
        <h1 style="font-size: 3.25rem; font-weight: 900; line-height: 1.25; margin-bottom: 1.5rem; letter-spacing: -0.5px;">
            {{ __('أطلق متجرك المستقل وابنِ') }} <span style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">{{ __('جيشاً من المسوقين بالعمولة') }}</span>
        </h1>
        <p style="font-size: 1.25rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 2.5rem; max-width: 780px; margin-left: auto; margin-right: auto;">
            {{ __('نظام تشغيل SaaS سحابي يجمع بين متجر Lunar التجاري فائق السرعة، إسناد العمولات الدقيق بنظام آخر نقرة، دفتر أستاذ محاسبي مزدوج، وتأكيد طلبات الـ COD وتوزيعها على شركات الشحن.') }}
        </p>
        <div style="display: flex; gap: 1.25rem; justify-content: center; flex-wrap: wrap; margin-bottom: 3.5rem;">
            <a href="{{ route('onboarding.register') }}" class="btn btn-primary" style="padding: 0.9rem 2.2rem; font-size: 1.1rem;">
                {{ __('ابدأ تجربتك المجانية لمدة 14 يوماً') }}
            </a>
            <a href="{{ route('platform.marketing.features') }}" class="btn btn-secondary" style="padding: 0.9rem 2rem; font-size: 1.1rem;">
                {{ __('استكشف الميزات والقدرات') }}
            </a>
        </div>
        <div style="display: flex; gap: 2.5rem; justify-content: center; color: var(--text-muted); font-size: 0.95rem; flex-wrap: wrap;">
            <span>{{ __('✓ لا تشترط بطاقة ائتمانية للتجربة') }}</span>
            <span>{{ __('✓ إعداد المتجر في 60 ثانية') }}</span>
            <span>{{ __('✓ جاهز للربط مع شركات الشحن المحلية') }}</span>
        </div>
    </div>
</section>

<!-- Stats / Highlights Section -->
<section style="background: rgba(255, 255, 255, 0.02); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 3rem 0;">
    <div class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; text-align: center;">
            <div>
                <h3 style="font-size: 2.75rem; font-weight: 900; color: #818cf8;">99.9%</h3>
                <p style="color: var(--text-muted); font-weight: 600;">{{ __('جاهزية واستقرار الخوادم') }}</p>
            </div>
            <div>
                <h3 style="font-size: 2.75rem; font-weight: 900; color: var(--accent);">&lt; 12%</h3>
                <p style="color: var(--text-muted); font-weight: 600;">{{ __('متوسط نسبة مرتجعات COD بعد التأكيد') }}</p>
            </div>
            <div>
                <h3 style="font-size: 2.75rem; font-weight: 900; color: var(--success);">100%</h3>
                <p style="color: var(--text-muted); font-weight: 600;">{{ __('دقة تسوية الدفاتر المحاسبية بدون فوارق') }}</p>
            </div>
            <div>
                <h3 style="font-size: 2.75rem; font-weight: 900; color: #ec4899;">27</h3>
                <p style="color: var(--text-muted); font-weight: 600;">{{ __('محافظة مغطاة بالشحن والتتبع اللحظي') }}</p>
            </div>
        </div>
    </div>
</section>

<!-- Core Features Section -->
<section>
    <div class="container">
        <div class="section-header">
            <span class="badge">{{ __('حلول شاملة') }}</span>
            <h2>{{ __('كل ما تحتاجه لإدارة تجارتك وشبكة مسوقيك في مكان واحد') }}</h2>
            <p>{{ __('صُممت منصة AffiliateOS خصيصاً للتغلب على معوقات التجارة الإلكترونية بنظام الدفع عند الاستلام وحل نزاعات المسوقين.') }}</p>
        </div>

        <div class="grid-3">
            <div class="card">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🛍️</div>
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 0.75rem;">{{ __('متجر مستقل فائق السرعة') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">{{ __('متجر مبني على محرك Lunar التجاري مع كاش ذكي، جاهز لاستقبال آلاف الزيارات الإعلانية وحملات تيك توك وسناب شات دون أي تباطؤ.') }}</p>
            </div>

            <div class="card">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🎯</div>
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 0.75rem;">{{ __('إسناد دقيق للعمولات (Last-Click)') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">{{ __('روابط تتبع مخصصة لكل مسوق مع بيكسلات إعلانات منفصلة، واحتساب آلي للعمولات وفق دورة التسليم وانتهاء فترة الاسترجاع.') }}</p>
            </div>

            <div class="card">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">⚖️</div>
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 0.75rem;">{{ __('دفتر أستاذ محاسبي مزدوج') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">{{ __('كل قرش في المنصة موثق بقيود مدينة ودائنة متوازنة تماماً. وداعاً للأخطاء الحسابية أو صرف عمولات شحنات ملغاة.') }}</p>
            </div>

            <div class="card">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">📞</div>
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 0.75rem;">{{ __('إدارة الكول سنتر وتأكيد الطلبات') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">{{ __('طابور ذكي لتوزيع الطلبات على فريق المبيعات، مع قفل لمنع تكرار الاتصال، وجدولة المتابعات لتقليل نسب الارتجاع إلى أدنى حد.') }}</p>
            </div>

            <div class="card">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🚚</div>
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 0.75rem;">{{ __('ربط مع شركات الشحن المحلية') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">{{ __('إصدار بوالص الشحن بضغطة زر، وتحديث حالات التسليم آلياً عبر Webhooks، ومطابقة توريدات مبالغ التحصيل COD دون عناء.') }}</p>
            </div>

            <div class="card">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🔒</div>
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 0.75rem;">{{ __('عزل أمني وسيادة للبيانات') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">{{ __('عزل كامل لبيانات متجرك ومخزونك وعملائك. تشفير قوي لبيانات الدفع والاتصال، مع امتثال كامل لقانون حماية البيانات المصري 151/2020.') }}</p>
            </div>
        </div>
    </div>
</section>

<!-- Pricing Preview Section -->
<section style="background: rgba(255, 255, 255, 0.02); border-top: 1px solid var(--border);">
    <div class="container">
        <div class="section-header">
            <span class="badge">{{ __('باقات واضحة وشفافة') }}</span>
            <h2>{{ __('خطط أسعار تناسب انطلاقتك وتنمو مع مبيعاتك') }}</h2>
            <p>{{ __('جميع الخطط تتضمن وصولاً كاملاً للمتجر، بوابة الأفلييت، الكول سنتر، ونظام الدفاتر المحاسبية.') }}</p>
        </div>

        <div class="grid-3">
            @foreach($plans as $plan)
                <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; {{ $plan->slug === 'pro' ? 'border-color: #6366f1; background: rgba(99, 102, 241, 0.08); position: relative;' : '' }}">
                    @if($plan->slug === 'pro')
                        <div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--primary-gradient); color: #fff; padding: 0.2rem 1rem; border-radius: 50px; font-size: 0.8rem; font-weight: 700;">
                            {{ __('الأكثر طلباً') }}
                        </div>
                    @endif
                    <div>
                        <h3 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem;">{{ app()->getLocale() === 'en' && $plan->name_en ? $plan->name_en : $plan->name_ar }}</h3>
                        <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; min-height: 48px;">{{ $plan->description_ar }}</p>
                        
                        <div style="margin-bottom: 2rem;">
                            <span style="font-size: 2.5rem; font-weight: 900; color: #fff;">{{ number_format($plan->price_monthly / 100, 0) }}</span>
                            <span style="color: var(--text-muted); font-weight: 600;">{{ __('جنيه / شهرياً') }}</span>
                        </div>

                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 2rem; color: var(--text-main); font-size: 0.95rem;">
                            <li>✓ {{ $plan->getLimit('products') == -1 ? __('منتجات غير محدودة') : __('حتى ') . number_format($plan->getLimit('products')) . __(' منتج') }}</li>
                            <li>✓ {{ $plan->getLimit('monthly_orders') == -1 ? __('طلبات شهرية غير محدودة') : __('حتى ') . number_format($plan->getLimit('monthly_orders')) . __(' طلب شهرياً') }}</li>
                            <li>✓ {{ $plan->getLimit('affiliates') == -1 ? __('مسوقين غير محدودين') : __('حتى ') . number_format($plan->getLimit('affiliates')) . __(' مسوق نشط') }}</li>
                            @if($plan->hasFeature('api'))
                                <li>{{ __('✓ واجهات برمجة التطبيقات REST API') }}</li>
                            @endif
                            @if($plan->hasFeature('custom_domain'))
                                <li>{{ __('✓ ربط دومين مخصص بمتجرك') }}</li>
                            @endif
                        </ul>
                    </div>

                    <a href="{{ route('onboarding.register') }}" class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-secondary' }}" style="width: 100%;">
                        {{ __('ابدأ تجربة الخطة مجاناً') }}
                    </a>
                </div>
            @endforeach
        </div>

        <div style="text-align: center; margin-top: 2.5rem;">
            <a href="{{ route('platform.marketing.pricing') }}" style="color: #818cf8; text-decoration: none; font-weight: 700; font-size: 1.05rem;">
                {{ __('عرض جدول المقارنة التفصيلي بين جميع الخطط ←') }}
            </a>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<section style="text-align: center; padding: 6rem 0;">
    <div class="container" style="max-width: 800px; background: var(--primary-gradient); padding: 4rem 2rem; border-radius: 20px; box-shadow: 0 20px 40px rgba(79, 70, 229, 0.3);">
        <h2 style="font-size: 2.4rem; font-weight: 900; color: #fff; margin-bottom: 1rem;">{{ __('جاهز لتحويل تجارتك الإلكترونية إلى منظومة احترافية؟') }}</h2>
        <p style="font-size: 1.15rem; color: rgba(255, 255, 255, 0.9); margin-bottom: 2rem;">
            {{ __('انضم إلى التجار الناجحين الذين يعتمدون على AffiliateOS لزيادة حجم مبيعاتهم وخفض تكاليف التشغيل.') }}
        </p>
        <a href="{{ route('onboarding.register') }}" class="btn" style="background: #fff; color: #4f46e5; padding: 1rem 2.5rem; font-size: 1.15rem; font-weight: 800;">
            {{ __('أنشئ متجرك الآن في دقيقة واحدة') }}
        </a>
    </div>
</section>
@endsection
