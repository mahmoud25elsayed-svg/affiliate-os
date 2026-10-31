@extends('platform.marketing.layout')

@section('content')
<section style="padding: 5rem 0 3rem; text-align: center;">
    <div class="container" style="max-width: 850px;">
        <span class="badge">{{ __('خطط أسعار مدروسة') }}</span>
        <h1 style="font-size: 2.75rem; font-weight: 900; margin-bottom: 1.25rem;">{{ __('أسعار شفافة وبسيطة بدون عمولات خفية') }}</h1>
        <p style="font-size: 1.15rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 2rem;">
            {{ __('استثمر في البنية التحتية لمتجرك وشبكة مسوقيك. جميع الخطط تأتي مع فترة تجربة مجانية لمدة 14 يوماً ودعم فني مخصص.') }}
        </p>

        <!-- Billing Toggle -->
        <div style="display: inline-flex; align-items: center; gap: 1rem; background: var(--surface); padding: 0.4rem 0.6rem; border-radius: 50px; border: 1px solid var(--border);">
            <button id="toggleMonthly" class="btn" style="border-radius: 50px; padding: 0.4rem 1.25rem; font-size: 0.9rem; background: var(--primary); color: #fff;" onclick="setBilling('monthly')">{{ __('الدفع شهرياً') }}</button>
            <button id="toggleYearly" class="btn" style="border-radius: 50px; padding: 0.4rem 1.25rem; font-size: 0.9rem; background: transparent; color: var(--text-muted);" onclick="setBilling('yearly')">
                {{ __('الدفع سنوياً') }} <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 20px; margin-right: 0.25rem;">{{ __('خصم 20% (شهرين مجاناً)') }}</span>
            </button>
        </div>
    </div>
</section>

<!-- Plan Cards -->
<section style="padding-top: 1rem;">
    <div class="container">
        <div class="grid-3">
            @foreach($plans as $plan)
                <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; {{ $plan->slug === 'pro' ? 'border-color: #6366f1; background: rgba(99, 102, 241, 0.08); position: relative;' : '' }}">
                    @if($plan->slug === 'pro')
                        <div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--primary-gradient); color: #fff; padding: 0.25rem 1rem; border-radius: 50px; font-size: 0.8rem; font-weight: 700;">
                            {{ __('الخيار الأكثر شعبية') }}
                        </div>
                    @endif
                    <div>
                        <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">{{ app()->getLocale() === 'en' && $plan->name_en ? $plan->name_en : $plan->name_ar }}</h3>
                        <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; min-height: 48px;">{{ $plan->description_ar }}</p>

                        <div style="margin-bottom: 2rem;">
                            <div class="price-monthly-box">
                                <span style="font-size: 2.75rem; font-weight: 900; color: #fff;">{{ number_format($plan->price_monthly / 100, 0) }}</span>
                                <span style="color: var(--text-muted); font-weight: 600;">{{ __('جنيه / شهرياً') }}</span>
                            </div>
                            <div class="price-yearly-box" style="display: none;">
                                <span style="font-size: 2.75rem; font-weight: 900; color: #fff;">{{ number_format(($plan->price_yearly / 12) / 100, 0) }}</span>
                                <span style="color: var(--text-muted); font-weight: 600;">{{ __('جنيه / شهر (تدفع سنوياً)') }}</span>
                            </div>
                        </div>

                        <div style="border-top: 1px solid var(--border); padding-top: 1.5rem; margin-bottom: 1.75rem;">
                            <p style="font-weight: 700; margin-bottom: 1rem; font-size: 0.95rem; color: #818cf8;">{{ __('حدود ومواصفات الخطة:') }}</p>
                            <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.95rem; color: var(--text-main);">
                                <li>📦 {{ $plan->getLimit('products') == -1 ? __('منتجات غير محدودة') : __('حتى ') . number_format($plan->getLimit('products')) . __(' منتج') }}</li>
                                <li>🛍️ {{ $plan->getLimit('monthly_orders') == -1 ? __('طلبات شهرية غير محدودة') : __('حتى ') . number_format($plan->getLimit('monthly_orders')) . __(' طلب شهرياً') }}</li>
                                <li>👥 {{ $plan->getLimit('affiliates') == -1 ? __('مسوقين غير محدودين') : __('حتى ') . number_format($plan->getLimit('affiliates')) . __(' مسوق معتمد') }}</li>
                                <li>📞 {{ $plan->getLimit('staff') == -1 ? __('موظفي كول سنتر غير محدود') : __('حتى ') . number_format($plan->getLimit('staff') ?: 5) . __(' موظفي كول سنتر') }}</li>
                            </ul>
                        </div>
                    </div>

                    <a href="{{ route('onboarding.register') }}" class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-secondary' }}" style="width: 100%; padding: 0.8rem 1rem;">
                        {{ __('ابدأ تجربة') }} {{ app()->getLocale() === 'en' && $plan->name_en ? $plan->name_en : $plan->name_ar }} {{ __('مجاناً') }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Detailed Comparison Table -->
<section>
    <div class="container">
        <div class="section-header">
            <span class="badge">{{ __('المقارنة الكاملة') }}</span>
            <h2>{{ __('مقارنة تفصيلية بين إمكانيات وميزات الخطط') }}</h2>
            <p>{{ __('اختر الخطة المناسبة بدقة استناداً إلى احتياجات متجرك ومستوى نموه.') }}</p>
        </div>

        <div style="overflow-x: auto; background: var(--surface-card); border: 1px solid var(--border); border-radius: 14px;">
            <table style="width: 100%; border-collapse: collapse; text-align: right; min-width: 650px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border); background: rgba(255, 255, 255, 0.02);">
                        <th style="padding: 1.25rem 1.5rem; font-weight: 700; color: #fff;">{{ __('الميزة / الإمكانية') }}</th>
                        @foreach($plans as $plan)
                            <th style="padding: 1.25rem 1.5rem; font-weight: 800; color: #818cf8; text-align: center;">{{ app()->getLocale() === 'en' && $plan->name_en ? $plan->name_en : $plan->name_ar }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody style="font-size: 0.95rem; color: var(--text-muted);">
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1rem 1.5rem; color: var(--text-main); font-weight: 600;">{{ __('عدد المنتجات المتاحة') }}</td>
                        @foreach($plans as $plan)
                            <td style="padding: 1rem 1.5rem; text-align: center;">{{ $plan->getLimit('products') == -1 ? __('غير محدود') : number_format($plan->getLimit('products')) }}</td>
                        @endforeach
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1rem 1.5rem; color: var(--text-main); font-weight: 600;">{{ __('سقف الطلبات الشهرية') }}</td>
                        @foreach($plans as $plan)
                            <td style="padding: 1rem 1.5rem; text-align: center;">{{ $plan->getLimit('monthly_orders') == -1 ? __('غير محدود') : number_format($plan->getLimit('monthly_orders')) }}</td>
                        @endforeach
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1rem 1.5rem; color: var(--text-main); font-weight: 600;">{{ __('عدد المسوقين المسجلين') }}</td>
                        @foreach($plans as $plan)
                            <td style="padding: 1rem 1.5rem; text-align: center;">{{ $plan->getLimit('affiliates') == -1 ? __('غير محدود') : number_format($plan->getLimit('affiliates')) }}</td>
                        @endforeach
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1rem 1.5rem; color: var(--text-main); font-weight: 600;">{{ __('دفتر الأستاذ المحاسبي المزدوج') }}</td>
                        @foreach($plans as $plan)
                            <td style="padding: 1rem 1.5rem; text-align: center; color: var(--success);">{{ __('✓ متضمن') }}</td>
                        @endforeach
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1rem 1.5rem; color: var(--text-main); font-weight: 600;">{{ __('نظام الكول سنتر وتأكيد COD') }}</td>
                        @foreach($plans as $plan)
                            <td style="padding: 1rem 1.5rem; text-align: center; color: var(--success);">{{ __('✓ متضمن') }}</td>
                        @endforeach
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1rem 1.5rem; color: var(--text-main); font-weight: 600;">{{ __('ربط دومين مخصص (Custom Domain)') }}</td>
                        @foreach($plans as $plan)
                            <td style="padding: 1rem 1.5rem; text-align: center;">
                                {!! $plan->hasFeature('custom_domain') ? '<span style="color: var(--success);">✓ متاح</span>' : '<span style="color: #6b7280;">—</span>' !!}
                            </td>
                        @endforeach
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1rem 1.5rem; color: var(--text-main); font-weight: 600;">{{ __('واجهات التطبيقات البرمجية REST API') }}</td>
                        @foreach($plans as $plan)
                            <td style="padding: 1rem 1.5rem; text-align: center;">
                                {!! $plan->hasFeature('api') ? '<span style="color: var(--success);">✓ متاح</span>' : '<span style="color: #6b7280;">—</span>' !!}
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td style="padding: 1rem 1.5rem; color: var(--text-main); font-weight: 600;">{{ __('فترة التجربة المجانية') }}</td>
                        @foreach($plans as $plan)
                            <td style="padding: 1rem 1.5rem; text-align: center; color: #818cf8; font-weight: 700;">{{ __('14 يوماً') }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

@push('scripts')
<script>
    function setBilling(period) {
        const monthlyBtn = document.getElementById('toggleMonthly');
        const yearlyBtn = document.getElementById('toggleYearly');
        const monthlyBoxes = document.querySelectorAll('.price-monthly-box');
        const yearlyBoxes = document.querySelectorAll('.price-yearly-box');

        if (period === 'yearly') {
            monthlyBtn.style.background = 'transparent';
            monthlyBtn.style.color = 'var(--text-muted)';
            yearlyBtn.style.background = 'var(--primary)';
            yearlyBtn.style.color = '#fff';

            monthlyBoxes.forEach(el => el.style.display = 'none');
            yearlyBoxes.forEach(el => el.style.display = 'block');
        } else {
            yearlyBtn.style.background = 'transparent';
            yearlyBtn.style.color = 'var(--text-muted)';
            monthlyBtn.style.background = 'var(--primary)';
            monthlyBtn.style.color = '#fff';

            monthlyBoxes.forEach(el => el.style.display = 'block');
            yearlyBoxes.forEach(el => el.style.display = 'none');
        }
    }
</script>
@endpush
@endsection
