@extends('platform.marketing.layout')

@section('content')
<section style="padding: 5rem 0 3rem; text-align: center;">
    <div class="container" style="max-width: 850px;">
        <span class="badge">{{ __('جولة الميزات الفنية') }}</span>
        <h1 style="font-size: 2.75rem; font-weight: 900; margin-bottom: 1.25rem;">{{ __('معمارية متكاملة مصممة لنمو تجارتك') }}</h1>
        <p style="font-size: 1.15rem; color: var(--text-muted); line-height: 1.7;">
            {{ __('استكشف بالتفصيل كيف تساعدك وحدات النظام في إدارة المتجر، إسناد مبيعات الأفلييت، تأكيد وتوريد طلبات COD، وضمان دقة الحسابات.') }}
        </p>
    </div>
</section>

<section style="padding-top: 1rem;">
    <div class="container" style="display: flex; flex-direction: column; gap: 4rem;">

        <!-- Feature 1: Storefront & Lunar -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 3rem; align-items: center;">
            <div>
                <span style="color: #818cf8; font-weight: 700; font-size: 0.95rem;">{{ __('01. واجهة المتجر وتجربة الشراء') }}</span>
                <h2 style="font-size: 2rem; font-weight: 800; margin: 0.5rem 0 1rem;">{{ __('محرك Lunar فائق السرعة') }}</h2>
                <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1.5rem;">
                    {{ __('تم بناء واجهات المتاجر بالاعتماد على محرك Lunar Commerce مع دعم كامل للأجهزة المحمولة وشبكات الجيل الرابع. يتميز المتجر بـ ETag Http Caching لتسريع التصفح والاستجابة في أقل من 50 ملي ثانية حتى أثناء ذروة الحمل الإعلاني.') }}
                </p>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.75rem; color: var(--text-main);">
                    <li>{{ __('✓ تصفح سريع للمنتجات والكتالوج بتكشيش ذكي') }}</li>
                    <li>{{ __('✓ صفحة هبوط مخصصة لكل منتج بمعدل تحويل عالٍ') }}</li>
                    <li>{{ __('✓ نموذج شراء مختصر يتحقق من أرقام الموبايل لكل دولة') }}</li>
                </ul>
            </div>
            <div class="card" style="padding: 2.5rem; text-align: center; border-color: rgba(99, 102, 241, 0.3);">
                <div style="font-size: 4rem; margin-bottom: 1rem;">⚡</div>
                <h3 style="font-size: 1.25rem; font-weight: 700;">{{ __('زمن استجابة < 50ms') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem;">{{ __('جاهزية كاملة لروابط المشاهير وحملات تيك توك وسناب شات.') }}</p>
            </div>
        </div>

        <!-- Feature 2: Last-Click Attribution -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 3rem; align-items: center;">
            <div class="card" style="padding: 2.5rem; text-align: center; border-color: rgba(6, 182, 212, 0.3); order: 2;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🎯</div>
                <h3 style="font-size: 1.25rem; font-weight: 700;">{{ __('شفافية مطلقة للمسوق') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem;">{{ __('لوحة تحكم مباشرة لكل مسوق لمتابعة نقراته وطلباته وأرباحه لحظياً.') }}</p>
            </div>
            <div style="order: 1;">
                <span style="color: var(--accent); font-weight: 700; font-size: 0.95rem;">{{ __('02. التسويق بالعمولة') }}</span>
                <h2 style="font-size: 2rem; font-weight: 800; margin: 0.5rem 0 1rem;">{{ __('إسناد النقرة الأخيرة وبوابة الأفلييت') }}</h2>
                <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1.5rem;">
                    {{ __('نظام تتبع قوي يعتمد على ملفات تعريف ارتباط صالحة لـ 30 يوماً مع إسناد شفاف للمبيعات. يتمكن كل مسوق من إنشاء روابط مخصصة، ربط بيكسلات إعلاناته الخاصة (Meta & TikTok)، والاطلاع على رصيده المحتجز والمتاح للسحب.') }}
                </p>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.75rem; color: var(--text-main);">
                    <li>{{ __('✓ تتبع دقيق للنقرات وتحليل مصادر الزيارات (UTM)') }}</li>
                    <li>{{ __('✓ مستويات للمسوقين (Bronze, Silver, Gold) مع عمولات متدرجة') }}</li>
                    <li>{{ __('✓ سحب أرباح لحظي عبر فودافون كاش وإنستاباي والحسابات البنكية') }}</li>
                </ul>
            </div>
        </div>

        <!-- Feature 3: Double-Entry Ledger -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 3rem; align-items: center;">
            <div>
                <span style="color: var(--success); font-weight: 700; font-size: 0.95rem;">{{ __('03. السلامة المالية') }}</span>
                <h2 style="font-size: 2rem; font-weight: 800; margin: 0.5rem 0 1rem;">{{ __('دفتر أستاذ محاسبي مزدوج (Double-Entry Ledger)') }}</h2>
                <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1.5rem;">
                    {{ __('لا نعتمد على حقول أرصدة بسيطة قابلة للتلف. كل معاملة مالية (استحقاق عمولة، تسليم طلب، خصم مرتجع، سداد سحب) تسجل كقيد محاسبي مزدوج متوازن. يضمن النظام مطابقة ليلية آلية تكشف أي فارق قرش واحد على الفور.') }}
                </p>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.75rem; color: var(--text-main);">
                    <li>{{ __('✓ قيود مدينة ودائنة متوازنة لجميع الحسابات') }}</li>
                    <li>{{ __('✓ عزل صارم بين الأرباح المعلقة والمتاحة للسحب') }}</li>
                    <li>{{ __('✓ فحص ومطابقة ليلية عبر `ledger:reconcile`') }}</li>
                </ul>
            </div>
            <div class="card" style="padding: 2.5rem; text-align: center; border-color: rgba(16, 185, 129, 0.3);">
                <div style="font-size: 4rem; margin-bottom: 1rem;">⚖️</div>
                <h3 style="font-size: 1.25rem; font-weight: 700;">{{ __('مطابقة مالية 100%') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem;">{{ __('سجل مالي تاريخي لا يقبل التعديل أو الحذف، لحماية حقوق التاجر والمسوق.') }}</p>
            </div>
        </div>

        <!-- Feature 4: Call Center & Logistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 3rem; align-items: center;">
            <div class="card" style="padding: 2.5rem; text-align: center; border-color: rgba(236, 72, 153, 0.3); order: 2;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📞</div>
                <h3 style="font-size: 1.25rem; font-weight: 700;">{{ __('خفض نسب الارتجاع') }}</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem;">{{ __('توزيع آلي للمكالمات وتأكيد العناوين قبل شحن البضائع.') }}</p>
            </div>
            <div style="order: 1;">
                <span style="color: #ec4899; font-weight: 700; font-size: 0.95rem;">{{ __('04. العمليات والتنفيذ') }}</span>
                <h2 style="font-size: 2rem; font-weight: 800; margin: 0.5rem 0 1rem;">{{ __('إدارة الكول سنتر والربط اللوجستي') }}</h2>
                <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1.5rem;">
                    {{ __('نظام متقدم لتأكيد طلبات الـ COD قبل تجهيزها. توزيع عادل على ممثلي خدمة العملاء مع قفل آمن للطلبات يمنع اتصال موظفين بنفس العميل، مع سجل كامل لمحاولات الاتصال وأسباب الإلغاء.') }}
                </p>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.75rem; color: var(--text-main);">
                    <li>{{ __('✓ إصدار فوري لبوالص الشحن عبر بوابات الربط') }}</li>
                    <li>{{ __('✓ تتبع تلقائي للشحنات وتحديث حالة الطلب عبر Webhooks') }}</li>
                    <li>{{ __('✓ إدارة ومطابقة توريدات التحصيل (COD Remittance)') }}</li>
                </ul>
            </div>
        </div>

    </div>
</section>

<!-- Bottom CTA -->
<section style="text-align: center; padding: 5rem 0 3rem;">
    <div class="container">
        <h2>{{ __('اكتشف قوة النظام في إدارة تجارتك بنفسك') }}</h2>
        <p style="color: var(--text-muted); margin: 1rem 0 2rem;">{{ __('ابدأ تجربتك المجانية دون الحاجة لبطاقة ائتمانية واستمتع بجميع الميزات لمدة 14 يوماً.') }}</p>
        <a href="{{ route('onboarding.register') }}" class="btn btn-primary" style="padding: 0.85rem 2.2rem; font-size: 1.05rem;">
            {{ __('إنشاء متجر جديد الآن') }}
        </a>
    </div>
</section>
@endsection
