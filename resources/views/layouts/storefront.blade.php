@php
    $general = $general ?? \App\Support\Settings\GeneralSettings::load();
    $theme = $theme ?? \App\Support\Settings\ThemeSettings::load();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'en' ? 'ltr' : 'rtl' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $general->platform_name ?? 'متجرنا الإلكتروني')</title>
    <meta name="description" content="@yield('meta_description', $theme->hero_subheadline ?? 'تسوق أفضل المنتجات بأفضل الأسعار وتوصيل لكافة المحافظات')">
    <link rel="canonical" href="@yield('canonical_url', url()->current())">

    <!-- Hreflang Tags -->
    <link rel="alternate" hreflang="ar" href="{{ url()->current() }}?lang=ar">
    <link rel="alternate" hreflang="en" href="{{ url()->current() }}?lang=en">
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">

    <!-- Open Graph & Twitter Cards -->
    <meta property="og:title" content="@yield('title', $general->platform_name ?? 'متجرنا الإلكتروني')">
    <meta property="og:description" content="@yield('meta_description', $theme->hero_subheadline ?? 'تسوق أفضل المنتجات بأفضل الأسعار')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ $general->platform_name ?? 'متجرنا' }}">
    <meta property="og:image" content="@yield('og_image', $theme->logo_url ? url($theme->logo_url) : url('/images/og-default.jpg'))">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Favicon -->
    @if(!empty($theme->favicon_url))
        <link rel="icon" type="image/x-icon" href="{{ $theme->favicon_url }}">
    @endif

    <!-- Google Fonts -->
    @php
        $fontName = $theme->font_family ?? 'Cairo';
        $fontParam = str_replace(' ', '+', $fontName);
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family={{ $fontParam }}:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Dynamic Theme Styling -->
    <style>
        :root {
            --primary: {{ $theme->primary_color ?? '#4f46e5' }};
            --primary-hover: color-mix(in srgb, var(--primary) 85%, black);
            --primary-light: color-mix(in srgb, var(--primary) 12%, white);
            --secondary: {{ $theme->secondary_color ?? '#06b6d4' }};
            --accent: {{ $theme->accent_color ?? '#f59e0b' }};
            --font-family: '{{ $fontName }}', sans-serif;
        }
        body {
            font-family: var(--font-family);
        }
        .bg-primary { background-color: var(--primary); }
        .bg-primary:hover { background-color: var(--primary-hover); }
        .text-primary { color: var(--primary); }
        .border-primary { border-color: var(--primary); }
        .bg-primary-light { background-color: var(--primary-light); }
        .text-accent { color: var(--accent); }
        .bg-accent { background-color: var(--accent); }
    </style>

    @if(!empty($theme->custom_css))
    <style>
        {!! $theme->custom_css !!}
    </style>
    @endif

    <!-- Tailwind CSS (via CDN for standalone storefront runtime) -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: 'var(--primary-light)',
                            600: 'var(--primary)',
                            700: 'var(--primary-hover)',
                        }
                    }
                }
            }
        }
    </script>

    @yield('schema')
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-900 antialiased min-h-screen flex flex-col selection:bg-brand-600 selection:text-white">

    <!-- Top Announcement Bar -->
    <header class="bg-gray-900 text-gray-300 text-xs py-2 px-4 border-b border-gray-800">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <span>🚚 شحن سريع لكافة محافظات مصر</span>
                <span class="hidden sm:inline text-gray-600">|</span>
                <span class="hidden sm:inline">💳 الدفع عند الاستلام متاح</span>
            </div>
            <div class="flex items-center gap-4">
                @if(!empty($general->support_phone))
                    <a href="tel:{{ $general->support_phone }}" class="hover:text-white flex items-center gap-1">
                        <span>📞 {{ $general->support_phone }}</span>
                    </a>
                @endif
                <div class="flex items-center gap-2">
                    <a href="?lang=ar" class="{{ app()->getLocale() === 'ar' ? 'font-bold text-white' : 'hover:text-white' }}">عربي</a>
                    <span class="text-gray-600">/</span>
                    <a href="?lang=en" class="{{ app()->getLocale() === 'en' ? 'font-bold text-white' : 'hover:text-white' }}">EN</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Navigation Header -->
    <nav class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-gray-200 shadow-sm transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-4">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center gap-3">
                    <a href="{{ url('/') }}" class="flex items-center gap-2">
                        @if(!empty($theme->logo_url))
                            <img src="{{ $theme->logo_url }}" alt="{{ $general->platform_name }}" class="h-10 w-auto object-contain" loading="lazy">
                        @else
                            <span class="text-2xl font-black text-brand-600 tracking-tight">{{ $general->platform_name ?? 'متجرنا' }}</span>
                        @endif
                    </a>
                </div>

                <!-- Search Bar (Livewire) -->
                <div class="flex-1 max-w-xl hidden md:block">
                    <livewire:storefront.search-bar />
                </div>

                <!-- User & Cart Actions -->
                <div class="flex items-center gap-3">
                    @if(Auth::guard('customer')->check())
                        <a href="{{ route('storefront.account.orders') }}"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 hover:border-emerald-500 hover:text-emerald-700 text-xs font-semibold text-gray-700 bg-white transition-all shadow-sm">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>حسابي</span>
                        </a>
                    @else
                        <a href="{{ route('storefront.login') }}"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 hover:border-emerald-500 hover:text-emerald-700 text-xs font-semibold text-gray-700 bg-white transition-all shadow-sm">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            <span>دخول</span>
                        </a>
                    @endif
                    <livewire:storefront.cart-drawer />
                </div>
            </div>

            <!-- Navigation Links Bar -->
            <div class="hidden sm:flex items-center gap-6 py-2.5 text-sm font-medium border-t border-gray-100 overflow-x-auto scrollbar-none">
                @if(!empty($theme->navigation_links) && is_array($theme->navigation_links))
                    @foreach($theme->navigation_links as $nav)
                        <a href="{{ url($nav['url'] ?? '/') }}" target="{{ $nav['target'] ?? '_self' }}"
                           class="hover:text-brand-600 transition-colors whitespace-nowrap {{ request()->is(ltrim($nav['url'] ?? '', '/')) ? 'text-brand-600 font-bold' : 'text-gray-700' }}">
                            {{ $nav['label'] ?? '' }}
                        </a>
                    @endforeach
                @else
                    <a href="{{ url('/') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap {{ request()->is('/') ? 'text-brand-600 font-bold' : 'text-gray-700' }}">الرئيسية</a>
                    <a href="{{ url('/products') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap text-gray-700">كافة المنتجات</a>
                    <a href="{{ url('/about') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap text-gray-600">عن المتجر</a>
                    <a href="{{ url('/shipping') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap text-gray-600">الشحن والتوصيل</a>
                    <a href="{{ url('/terms') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap text-gray-600">الشروط والأحكام</a>
                @endif
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 mt-16 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <!-- Newsletter Subscription Banner (P14) -->
            <div class="mb-12 pb-12 border-b border-gray-800">
                <div class="bg-gradient-to-r from-gray-800/80 to-slate-800/80 rounded-2xl p-6 sm:p-8 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 border border-gray-700/60 shadow-lg">
                    <div class="space-y-1">
                        <span class="text-xs font-bold text-brand-600 bg-brand-50/10 px-2.5 py-0.5 rounded-full inline-block mb-1">📬 النشرة البريدية والعروض الحصرية</span>
                        <h3 class="text-xl sm:text-2xl font-black text-white">اشترك ليصلك جديد التخفيضات وكوبونات الخصم</h3>
                        <p class="text-xs sm:text-sm text-gray-400">انضم لأكثر من آلاف العملاء واستمتع بخصومات فورية وعروض الفلاش سيل أسبوعياً.</p>
                    </div>
                    <div class="w-full lg:max-w-md">
                        @if(session('newsletter_message'))
                            <div class="p-3 mb-3 text-xs rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-2">
                                <span>✅</span>
                                <span>{{ session('newsletter_message') }}</span>
                            </div>
                        @endif
                        <form action="{{ route('storefront.newsletter.subscribe') }}" method="POST" class="flex flex-col sm:flex-row gap-2">
                            @csrf
                            <input type="email" name="email" required placeholder="أدخل بريدك الإلكتروني"
                                class="flex-1 bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-brand-600 focus:ring-1 focus:ring-brand-600 transition-colors">
                            <button type="submit" class="px-6 py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm rounded-xl transition-all shadow hover:shadow-brand-600/30 whitespace-nowrap">
                                اشتراك مجاني
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Store Info -->
                <div class="space-y-4">
                    <h4 class="text-white text-lg font-bold">{{ $general->platform_name ?? 'متجرنا' }}</h4>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ $theme->about_us }}</p>
                    @if(!empty($general->support_phone))
                        <p class="text-sm text-gray-300">📞 خدمة العملاء: {{ $general->support_phone }}</p>
                    @endif
                </div>

                <!-- Quick Links -->
                <div>
                    <h5 class="text-white text-sm font-semibold mb-3">روابط سريعة</h5>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ url('/') }}" class="hover:text-white transition-colors">الرئيسية</a></li>
                        <li><a href="{{ url('/products') }}" class="hover:text-white transition-colors">كافة المنتجات</a></li>
                        <li><a href="{{ url('/about') }}" class="hover:text-white transition-colors">عن المتجر</a></li>
                        <li><a href="{{ url('/shipping') }}" class="hover:text-white transition-colors">الشحن والتوصيل</a></li>
                    </ul>
                </div>

                <!-- Policies -->
                <div>
                    <h5 class="text-white text-sm font-semibold mb-3">السياسات والشروط</h5>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ url('/terms') }}" class="hover:text-white transition-colors">الشروط والأحكام</a></li>
                        <li><a href="{{ url('/privacy') }}" class="hover:text-white transition-colors">سياسة الخصوصية</a></li>
                        <li><a href="{{ url('/sitemap.xml') }}" class="hover:text-white transition-colors">خريطة الموقع (Sitemap)</a></li>
                    </ul>
                </div>

                <!-- Trust and Payment -->
                <div class="space-y-3">
                    <h5 class="text-white text-sm font-semibold">تسوق آمن ومضمون</h5>
                    <p class="text-xs text-gray-400">معاينة الطلب قبل الاستلام مع ضمان حق الاسترجاع والاستبدال خلال 14 يوماً وفق قانون حماية المستهلك.</p>
                    <div class="flex items-center gap-2 pt-2 text-xs text-gray-300">
                        <span class="px-2.5 py-1 bg-gray-800 rounded border border-gray-700">الدفع عند الاستلام</span>
                        <span class="px-2.5 py-1 bg-gray-800 rounded border border-gray-700">معاينة مجانية</span>
                    </div>
                </div>
            </div>

            @php
                $hasWhiteLabelFeature = \App\Support\Billing\Features::enabled('white_label');
                $isWhiteLabel = $hasWhiteLabelFeature && ($theme->white_label_enabled ?? false);
            @endphp
            <div class="border-t border-gray-800 mt-10 pt-6 text-center text-xs text-gray-500 flex flex-col sm:flex-row justify-between items-center gap-4">
                <p>© {{ date('Y') }} {{ $general->platform_name ?? 'متجرنا' }}. جميع الحقوق محفوظة.</p>
                @if(! $isWhiteLabel)
                    <p class="text-gray-600">
                        مشغل بواسطة منصة التجارة والتسويق بالعمولة 
                        <a href="https://affiliate-os.com" target="_blank" rel="noopener" class="hover:text-gray-400 transition underline underline-offset-2">AffiliateOS</a>
                    </p>
                @endif
            </div>
        </div>
    </footer>

    <!-- Mobile Bottom Navigation Bar -->
    <div class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 px-6 py-2 flex justify-between items-center shadow-lg">
        <a href="{{ url('/') }}" class="flex flex-col items-center text-xs {{ request()->is('/') ? 'text-brand-600 font-bold' : 'text-gray-600' }}">
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>الرئيسية</span>
        </a>
        <a href="{{ url('/products') }}" class="flex flex-col items-center text-xs {{ request()->is('products*') ? 'text-brand-600 font-bold' : 'text-gray-600' }}">
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
            <span>المنتجات</span>
        </a>
        <a href="{{ url('/search') }}" class="flex flex-col items-center text-xs {{ request()->is('search*') ? 'text-brand-600 font-bold' : 'text-gray-600' }}">
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <span>البحث</span>
        </a>
        <a href="{{ url('/cart') }}" class="flex flex-col items-center text-xs text-gray-600 relative">
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            <span>السلة</span>
        </a>
    </div>

    @livewireScripts
</body>
</html>
