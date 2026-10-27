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
                    <a href="{{ url('/cart') }}" class="relative p-2.5 text-gray-700 hover:text-brand-600 rounded-full hover:bg-gray-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span class="absolute top-1 right-1 bg-brand-600 text-white text-[10px] font-bold rounded-full h-4 w-4 flex items-center justify-center">0</span>
                    </a>
                </div>
            </div>

            <!-- Categories Links Bar -->
            <div class="hidden sm:flex items-center gap-6 py-2.5 text-sm font-medium border-t border-gray-100 overflow-x-auto scrollbar-none">
                <a href="{{ url('/') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap {{ request()->is('/') ? 'text-brand-600 font-bold' : 'text-gray-700' }}">الرئيسية</a>
                <a href="{{ url('/products') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap text-gray-700">كافة المنتجات</a>
                <a href="{{ url('/about') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap text-gray-600">عن المتجر</a>
                <a href="{{ url('/shipping') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap text-gray-600">الشحن والتوصيل</a>
                <a href="{{ url('/terms') }}" class="hover:text-brand-600 transition-colors whitespace-nowrap text-gray-600">الشروط والأحكام</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 mt-16 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
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

            <div class="border-t border-gray-800 mt-10 pt-6 text-center text-xs text-gray-500 flex flex-col sm:flex-row justify-between items-center gap-4">
                <p>© {{ date('Y') }} {{ $general->platform_name ?? 'متجرنا' }}. جميع الحقوق محفوظة.</p>
                <p class="text-gray-600">مشغل بواسطة منصة التجارة والتسويق بالعمولة AffiliateOS</p>
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
