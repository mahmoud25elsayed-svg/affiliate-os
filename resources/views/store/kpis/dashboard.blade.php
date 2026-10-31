<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('لوحة مؤشرات الأداء التشغيلية (KPIs) - لوحة الإدارة') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    @include('store.partials.panel-bar')
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Top Header & Period Filter -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>📊</span>
                    <span>{{ __('لوحة مؤشرات الأداء التشغيلية (Pilot KPIs)') }}</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    {{ __('متابعة كفاءة الكول سنتر، نسب التسليم والارتجاع، زمن التحصيل ودوران الأرباح، وصحة المخزون') }}
                </p>
            </div>

            <!-- Period Switcher -->
            <div class="inline-flex p-1 bg-white border border-slate-200 rounded-2xl shadow-sm text-xs font-bold">
                <a href="{{ route('store.kpis.index', ['period' => 'today']) }}"
                   class="px-3 py-1.5 rounded-xl transition {{ $currentPeriod === 'today' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    {{ __('اليوم') }}
                </a>
                <a href="{{ route('store.kpis.index', ['period' => 'week']) }}"
                   class="px-3 py-1.5 rounded-xl transition {{ $currentPeriod === 'week' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    {{ __('آخر 7 أيام') }}
                </a>
                <a href="{{ route('store.kpis.index', ['period' => 'month']) }}"
                   class="px-3 py-1.5 rounded-xl transition {{ $currentPeriod === 'month' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    {{ __('آخر 30 يوم') }}
                </a>
                <a href="{{ route('store.kpis.index', ['period' => 'all']) }}"
                   class="px-3 py-1.5 rounded-xl transition {{ $currentPeriod === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    {{ __('كل الفترات') }}
                </a>
            </div>
        </div>

        <!-- 1. Executive Operations KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Confirmation Rate -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500">{{ __('نسبة تأكيد الكول سنتر') }}</span>
                    <span class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold">📞</span>
                </div>
                <div class="flex items-baseline space-x-2 space-x-reverse">
                    <span class="text-3xl font-black text-indigo-700 font-mono">{{ $metrics['confirmation_rate'] }}%</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-2 block pt-2 border-t border-slate-100">
                    {{ $metrics['confirmed_orders'] }} {{ __('طلب مؤكد من أصل') }} {{ $metrics['total_orders'] }} {{ __('وارد') }}
                </span>
            </div>

            <!-- Delivery Success Rate -->
            <div class="bg-white p-5 rounded-3xl border border-emerald-200 bg-emerald-50/10 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-emerald-800">{{ __('نسبة التسليم الفعلي') }}</span>
                    <span class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">🚚</span>
                </div>
                <div class="flex items-baseline space-x-2 space-x-reverse">
                    <span class="text-3xl font-black text-emerald-600 font-mono">{{ $metrics['delivery_rate'] }}%</span>
                </div>
                <span class="text-[11px] text-emerald-700 mt-2 block pt-2 border-t border-emerald-100/60">
                    {{ $metrics['delivered_orders'] }} {{ __('طلب تم تسليمه وتحصيله بنجاح') }}
                </span>
            </div>

            <!-- Return Rate -->
            <div class="bg-white p-5 rounded-3xl border border-rose-200 bg-rose-50/10 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-rose-800">{{ __('نسبة الارتجاع والتلف') }}</span>
                    <span class="w-8 h-8 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-sm font-bold">↩️</span>
                </div>
                <div class="flex items-baseline space-x-2 space-x-reverse">
                    <span class="text-3xl font-black text-rose-600 font-mono">{{ $metrics['return_rate'] }}%</span>
                </div>
                <span class="text-[11px] text-rose-700 mt-2 block pt-2 border-t border-rose-100/60">
                    {{ $metrics['returned_orders'] }} {{ __('طلب مرتجع / فشل تسليم') }}
                </span>
            </div>

            <!-- Payout Turnaround -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500">{{ __('متوسط زمن دورة الدفع') }}</span>
                    <span class="w-8 h-8 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center text-sm font-bold">⚡</span>
                </div>
                <div class="flex items-baseline space-x-2 space-x-reverse">
                    <span class="text-xl font-black text-slate-800">{{ $averageTurnaround }}</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-2 block pt-2 border-t border-slate-100">
                    {{ __('من استلام الطلب حتى تحويل الأرباح للمسوق') }}
                </span>
            </div>
        </div>

        <!-- 2. Financial Summary & Margins Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold text-slate-400 block mb-1">{{ __('إجمالي المبيعات المحصلة') }}</span>
                <span class="text-xl font-extrabold text-slate-900 font-mono">{{ number_format($metrics['gross_revenue_minor'] / 100, 2) }}</span>
                <span class="text-xs text-slate-400 mr-1">{{ \App\Support\Money::symbol() }}</span>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold text-indigo-400 block mb-1">{{ __('عمولات المسوقين المستحقة') }}</span>
                <span class="text-xl font-extrabold text-indigo-600 font-mono">{{ number_format($metrics['affiliate_commission_minor'] / 100, 2) }}</span>
                <span class="text-xs text-slate-400 mr-1">{{ \App\Support\Money::symbol() }}</span>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold text-amber-500 block mb-1">{{ __('مصروفات الشحن والتوصيل') }}</span>
                <span class="text-xl font-extrabold text-amber-600 font-mono">{{ number_format($metrics['shipping_expense_minor'] / 100, 2) }}</span>
                <span class="text-xs text-slate-400 mr-1">{{ \App\Support\Money::symbol() }}</span>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-emerald-300 bg-emerald-500 text-white shadow-md">
                <span class="text-xs font-bold text-emerald-100 block mb-1">{{ __('صافي هامش المتجر (Net Margin)') }}</span>
                <span class="text-xl font-black font-mono">{{ number_format($metrics['net_merchant_margin_minor'] / 100, 2) }}</span>
                <span class="text-xs text-emerald-100 mr-1">{{ \App\Support\Money::symbol() }}</span>
            </div>
        </div>

        <!-- 3. Pipeline Breakdown & Stock Health Alerts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Order Pipeline Flow -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-800 flex items-center space-x-2 space-x-reverse border-b border-slate-100 pb-3">
                    <span>📦</span>
                    <span>{{ __('توزيع دورة حياة الطلبات (Order Pipeline)') }}</span>
                </h3>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200">
                        <span class="text-xs font-bold text-amber-800 block">{{ __('في انتظار التأكيد') }}</span>
                        <span class="text-2xl font-black text-amber-700 font-mono mt-1 block">{{ $metrics['pending_confirmation_orders'] }}</span>
                        <span class="text-[10px] text-amber-600">{{ __('طابور الكول سنتر') }}</span>
                    </div>

                    <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200">
                        <span class="text-xs font-bold text-blue-800 block">{{ __('مؤكد ومجهز') }}</span>
                        <span class="text-2xl font-black text-blue-700 font-mono mt-1 block">{{ $metrics['confirmed_orders'] - $metrics['shipped_orders'] }}</span>
                        <span class="text-[10px] text-blue-600">{{ __('حجز المخزون والتغليف') }}</span>
                    </div>

                    <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-200">
                        <span class="text-xs font-bold text-indigo-800 block">{{ __('جاري التوصيل') }}</span>
                        <span class="text-2xl font-black text-indigo-700 font-mono mt-1 block">{{ $metrics['shipped_orders'] - $metrics['delivered_orders'] - $metrics['returned_orders'] }}</span>
                        <span class="text-[10px] text-indigo-600">{{ __('مع شركات الشحن') }}</span>
                    </div>

                    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200">
                        <span class="text-xs font-bold text-emerald-800 block">{{ __('تم التسليم والتحصيل') }}</span>
                        <span class="text-2xl font-black text-emerald-700 font-mono mt-1 block">{{ $metrics['delivered_orders'] }}</span>
                        <span class="text-[10px] text-emerald-600">{{ __('جاهز للتسوية والإفراج') }}</span>
                    </div>

                    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200">
                        <span class="text-xs font-bold text-rose-800 block">{{ __('مرتجع / فشل توصيل') }}</span>
                        <span class="text-2xl font-black text-rose-700 font-mono mt-1 block">{{ $metrics['returned_orders'] }}</span>
                        <span class="text-[10px] text-rose-600">{{ __('راجع للمخزن للفحص') }}</span>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-100 border border-slate-200">
                        <span class="text-xs font-bold text-slate-700 block">{{ __('ملغي') }}</span>
                        <span class="text-2xl font-black text-slate-700 font-mono mt-1 block">{{ $metrics['cancelled_orders'] }}</span>
                        <span class="text-[10px] text-slate-500">{{ __('قبل التجهيز والشحن') }}</span>
                    </div>
                </div>
            </div>

            <!-- Stock Alerts & Quick Shortcuts -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 flex items-center space-x-2 space-x-reverse border-b border-slate-100 pb-3">
                        <span>⚠️</span>
                        <span>{{ __('صحة المخزون والتوريدات') }}</span>
                    </h3>

                    <div class="space-y-3 mt-4">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-xs font-bold text-slate-600">{{ __('منتجات نفدت تماماً:') }}</span>
                            <span class="font-mono font-bold text-sm {{ $metrics['stockout_count'] > 0 ? 'text-rose-600' : 'text-slate-700' }}">
                                {{ $metrics['stockout_count'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-xs font-bold text-slate-600">{{ __('منتجات مخزونها منخفض (≤ 5):') }}</span>
                            <span class="font-mono font-bold text-sm {{ $metrics['low_stock_count'] > 0 ? 'text-amber-600' : 'text-slate-700' }}">
                                {{ $metrics['low_stock_count'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-xs font-bold text-slate-600">{{ __('الموردون المسجلون:') }}</span>
                            <span class="font-mono font-bold text-sm text-indigo-600">
                                {{ $metrics['suppliers_count'] }} {{ __('مورد') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Shortcuts -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <span class="text-xs font-bold text-slate-400 block mb-2">{{ __('إجراءات سريعة:') }}</span>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('store.suppliers.index') }}" class="p-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl text-center">
                            {{ __('إدارة الموردين ←') }}
                        </a>
                        <a href="{{ route('store.cod.index') }}" class="p-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl text-center">
                            {{ __('تسويات COD ←') }}
                        </a>
                        <a href="{{ route('store.affiliates.settings.show') }}" class="p-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl text-center">
                            {{ __('حدود العمولات ←') }}
                        </a>
                        <a href="{{ route('store.call-center.index') }}" class="p-2.5 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold rounded-xl text-center">
                            {{ __('مكتب الكول سنتر ←') }}
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </div>
</body>
</html>
