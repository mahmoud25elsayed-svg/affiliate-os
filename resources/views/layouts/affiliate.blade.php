<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('بوابة المسوقين')) - {{ $storeName ?? 'AffiliateOS' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Top Navigation -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3 space-x-reverse">
                <a href="{{ route('affiliate.dashboard') }}" class="flex items-center space-x-2 space-x-reverse font-extrabold text-xl text-indigo-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>{{ __('برنامج المسوقين |') }} {{ $storeName ?? __('المتجر') }}</span>
                </a>
            </div>

            <div class="flex items-center space-x-4 space-x-reverse">
                @if(Auth::guard('affiliate')->check())
                    @php $aff = Auth::guard('affiliate')->user(); @endphp
                    <nav class="hidden md:flex items-center space-x-2 space-x-reverse text-sm font-semibold">
                        <a href="{{ route('affiliate.dashboard') }}" class="text-slate-700 hover:text-indigo-600 px-2 py-2 rounded-lg">{{ __('الرئيسية') }}</a>
                        <a href="{{ route('affiliate.catalog.index') }}" class="text-slate-700 hover:text-indigo-600 px-2 py-2 rounded-lg">{{ __('الكتالوج') }}</a>
                        <a href="{{ route('affiliate.links.index') }}" class="text-slate-700 hover:text-indigo-600 px-2 py-2 rounded-lg">{{ __('روابط التتبع') }}</a>
                        <a href="{{ route('affiliate.orders.index') }}" class="text-slate-700 hover:text-indigo-600 px-2 py-2 rounded-lg">{{ __('طلباتي') }}</a>
                        <a href="{{ route('affiliate.orders.create') }}" class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-3 py-1.5 rounded-lg font-bold">{{ __('طلب جديد +') }}</a>
                        <a href="{{ route('affiliate.wallet.index') }}" class="text-slate-700 hover:text-indigo-600 px-2 py-2 rounded-lg">{{ __('المحفظة') }}</a>
                        <a href="{{ route('affiliate.payouts.index') }}" class="text-slate-700 hover:text-indigo-600 px-2 py-2 rounded-lg">{{ __('سحب الأرباح') }}</a>
                        <a href="{{ route('affiliate.payout-settings.show') }}" class="text-slate-700 hover:text-indigo-600 px-2 py-2 rounded-lg">{{ __('بيانات الدفع') }}</a>
                        <a href="{{ route('affiliate.kyc.show') }}" class="text-slate-700 hover:text-indigo-600 px-2 py-2 rounded-lg">KYC</a>
                    </nav>

                    <div class="flex items-center space-x-3 space-x-reverse border-r border-slate-200 pr-4">
                        <div class="text-left">
                            <span class="block text-xs font-bold text-slate-900">{{ $aff->name }}</span>
                            <span class="inline-block px-2 py-0.5 text-xs rounded bg-indigo-100 text-indigo-700 font-mono">{{ $aff->code }}</span>
                        </div>
                        <a href="?lang={{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}" class="text-xs text-slate-600 hover:text-slate-900 font-semibold px-2 py-1 rounded border border-slate-200">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</a>
                        <form method="POST" action="{{ route('affiliate.logout') }}">
                            @csrf
                            <button type="submit" class="text-xs text-rose-600 hover:text-rose-800 font-semibold px-2 py-1 rounded border border-rose-200 hover:bg-rose-50">
                                {{ __('خروج') }}
                            </button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('affiliate.login') }}" class="text-sm font-semibold text-slate-700 hover:text-indigo-600">{{ __('تسجيل الدخول') }}</a>
                    <a href="{{ route('affiliate.join') }}" class="text-sm font-bold bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 shadow-sm">{{ __('انضم إلينا كمسوق') }}</a>
                @endif
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
        @if(session('success'))
            <div class="p-4 mb-4 text-sm text-emerald-800 bg-emerald-50 rounded-xl border border-emerald-200 flex items-center">
                <svg class="w-5 h-5 ml-2 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 mb-4 text-sm text-rose-800 bg-rose-50 rounded-xl border border-rose-200 flex items-center">
                <svg class="w-5 h-5 ml-2 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('info'))
            <div class="p-4 mb-4 text-sm text-blue-800 bg-blue-50 rounded-xl border border-blue-200 flex items-center">
                <svg class="w-5 h-5 ml-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <span>{{ session('info') }}</span>
            </div>
        @endif
    </div>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center text-xs text-slate-500">
        <p>© {{ date('Y') }} {{ $storeName ?? 'AffiliateOS' }} {{ __('- جميع الحقوق محفوظة لبرنامج المسوقين بالعمولة.') }}</p>
    </footer>

</body>
</html>

