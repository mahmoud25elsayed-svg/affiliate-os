<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير والتحليلات المتقدمة - لوحة التاجر</title>
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

        <!-- Top Header & Actions -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>📈</span>
                    <span>التقارير والتحليلات المالية والتشغيلية (P33)</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    تحليلات دقيقة مبنية على التجميعات اليومية وسجلات الأداء دون إجهاد قاعدة البيانات
                </p>
            </div>

            <!-- Export Buttons Dropdown / Row -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('store.reports.export', ['type' => 'sales', 'preset' => $preset, 'from' => $from, 'to' => $to]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    <span>📥</span>
                    <span>تصدير المبيعات CSV</span>
                </a>
                <a href="{{ route('store.reports.export', ['type' => 'products', 'preset' => $preset, 'from' => $from, 'to' => $to]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    <span>📦</span>
                    <span>تصدير المنتجات CSV</span>
                </a>
                <a href="{{ route('store.reports.export', ['type' => 'affiliates', 'preset' => $preset, 'from' => $from, 'to' => $to]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    <span>👥</span>
                    <span>تصدير المسوقين CSV</span>
                </a>
                <a href="{{ route('store.reports.export', ['type' => 'returns', 'preset' => $preset, 'from' => $from, 'to' => $to]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    <span>🔄</span>
                    <span>أسباب المرتجعات CSV</span>
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <!-- Preset Buttons -->
            <div class="flex flex-wrap gap-1 text-xs font-bold">
                @php
                    $presets = [
                        'today' => 'اليوم',
                        'yesterday' => 'أمس',
                        'last_7_days' => 'آخر 7 أيام',
                        'last_30_days' => 'آخر 30 يوم',
                        'this_month' => 'هذا الشهر',
                        'last_month' => 'الشهر الماضي',
                    ];
                @endphp
                @foreach ($presets as $pKey => $pLabel)
                    <a href="{{ route('store.reports.index', ['preset' => $pKey]) }}"
                       class="px-3 py-1.5 rounded-xl transition {{ $preset === $pKey ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                        {{ $pLabel }}
                    </a>
                @endforeach
            </div>

            <!-- Custom Date Range Form -->
            <form method="GET" action="{{ route('store.reports.index') }}" class="flex items-center gap-2 text-xs">
                <input type="hidden" name="preset" value="custom">
                <span class="text-slate-500 font-semibold">من:</span>
                <input type="date" name="from" value="{{ $from }}" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-slate-800 text-xs">
                <span class="text-slate-500 font-semibold">إلى:</span>
                <input type="date" name="to" value="{{ $to }}" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-slate-800 text-xs">
                <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg transition">تطبيق</button>
            </form>
        </div>

        <!-- Metric KPI Cards Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <!-- Gross Revenue -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-500">إجمالي المبيعات (المسلّمة)</p>
                <p class="text-xl font-black text-emerald-600 mt-2">
                    {{ number_format($overview['gross_revenue_minor'] / 100, 2) }} <span class="text-xs font-bold">ج.م</span>
                </p>
                <p class="text-[10px] text-slate-400 mt-1">{{ $overview['delivered_orders_count'] }} طلب مسلّم</p>
            </div>

            <!-- Net Profit -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-500">صافي أرباح التاجر</p>
                <p class="text-xl font-black {{ $overview['net_profit_minor'] >= 0 ? 'text-indigo-600' : 'text-rose-600' }} mt-2">
                    {{ number_format($overview['net_profit_minor'] / 100, 2) }} <span class="text-xs font-bold">ج.م</span>
                </p>
                <p class="text-[10px] text-slate-400 mt-1">بعد استقطاع العمولات والشحن والضرائب</p>
            </div>

            <!-- Orders Count & Rates -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-500">إجمالي الطلبات</p>
                <p class="text-xl font-black text-slate-900 mt-2">{{ $overview['orders_count'] }}</p>
                <p class="text-[10px] text-slate-500 mt-1">المؤكدة: {{ $overview['confirmed_orders_count'] }}</p>
            </div>

            <!-- Delivery Rate -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-500">نسبة التسليم</p>
                <p class="text-xl font-black text-emerald-600 mt-2">{{ $overview['delivery_rate'] }}%</p>
                <p class="text-[10px] text-slate-400 mt-1">{{ $overview['delivered_orders_count'] }} طلب مسلّم</p>
            </div>

            <!-- Return Rate -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-500">نسبة المرتجعات</p>
                <p class="text-xl font-black text-rose-600 mt-2">{{ $overview['return_rate'] }}%</p>
                <p class="text-[10px] text-slate-400 mt-1">{{ $overview['returned_orders_count'] }} طلب مرتجع/فاشل</p>
            </div>

            <!-- AOV -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <p class="text-xs font-bold text-slate-500">متوسط قيمة الطلب (AOV)</p>
                <p class="text-xl font-black text-slate-800 mt-2">
                    {{ number_format($overview['aov_minor'] / 100, 2) }} <span class="text-xs font-bold">ج.م</span>
                </p>
                <p class="text-[10px] text-slate-400 mt-1">لكل طلب مكتمل</p>
            </div>
        </div>

        <!-- Cost & Expense Breakdown Row -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-slate-100 p-3.5 rounded-xl border border-slate-200 flex justify-between items-center">
                <span class="text-xs font-bold text-slate-600">عمولات المسوقين المستحقة:</span>
                <span class="text-sm font-black text-purple-700">{{ number_format($overview['affiliate_commission_minor'] / 100, 2) }} ج.م</span>
            </div>
            <div class="bg-slate-100 p-3.5 rounded-xl border border-slate-200 flex justify-between items-center">
                <span class="text-xs font-bold text-slate-600">مصاريف الشحن والتوصيل:</span>
                <span class="text-sm font-black text-amber-700">{{ number_format($overview['shipping_expense_minor'] / 100, 2) }} ج.م</span>
            </div>
            <div class="bg-slate-100 p-3.5 rounded-xl border border-slate-200 flex justify-between items-center">
                <span class="text-xs font-bold text-slate-600">تكلفة البضاعة المباعة (COGS):</span>
                <span class="text-sm font-black text-slate-700">{{ number_format($overview['cogs_minor'] / 100, 2) }} ج.م</span>
            </div>
            <div class="bg-slate-100 p-3.5 rounded-xl border border-slate-200 flex justify-between items-center">
                <span class="text-xs font-bold text-slate-600">الضرائب المحصلة (VAT):</span>
                <span class="text-sm font-black text-blue-700">{{ number_format($overview['tax_minor'] / 100, 2) }} ج.م</span>
            </div>
        </div>

        <!-- Main Analysis Grids (Two Columns) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Top Products -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-sm font-black text-slate-900 flex items-center gap-2">
                        <span>📦</span>
                        <span>أفضل المنتجات مبيعاً وأعلاها عائداً</span>
                    </h2>
                    <span class="text-[11px] font-bold text-slate-400">{{ count($topProducts) }} منتج</span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="text-slate-400 font-bold border-b border-slate-100">
                                <th class="pb-2">المنتج</th>
                                <th class="pb-2 text-center">المطلوب</th>
                                <th class="pb-2 text-center">المسلّم</th>
                                <th class="pb-2 text-left">المبيعات</th>
                                <th class="pb-2 text-left">المرتجع</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($topProducts as $prod)
                                <tr>
                                    <td class="py-2.5 font-bold text-slate-800">
                                        {{ $prod['name'] }}
                                        @if($prod['identifier'])
                                            <span class="block text-[10px] text-slate-400 font-normal">{{ $prod['identifier'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-center text-slate-600">{{ $prod['total_units_ordered'] }}</td>
                                    <td class="py-2.5 text-center font-bold text-emerald-600">{{ $prod['delivered_units'] }}</td>
                                    <td class="py-2.5 text-left font-black text-slate-900">{{ number_format($prod['total_revenue_minor'] / 100, 2) }} ج.م</td>
                                    <td class="py-2.5 text-left font-bold {{ $prod['return_rate'] > 20 ? 'text-rose-600' : 'text-slate-500' }}">
                                        {{ $prod['return_rate'] }}%
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400">لا توجد مبيعات منتجات في هذه الفترة</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Affiliates -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-sm font-black text-slate-900 flex items-center gap-2">
                        <span>👥</span>
                        <span>أفضل المسوقين (Affiliates) إنجازاً</span>
                    </h2>
                    <span class="text-[11px] font-bold text-slate-400">{{ count($topAffiliates) }} مسوق</span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="text-slate-400 font-bold border-b border-slate-100">
                                <th class="pb-2">المسوق</th>
                                <th class="pb-2 text-center">الطلبات</th>
                                <th class="pb-2 text-center">نسبة التسليم</th>
                                <th class="pb-2 text-left">المبيعات</th>
                                <th class="pb-2 text-left">العمولة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($topAffiliates as $aff)
                                <tr>
                                    <td class="py-2.5 font-bold text-slate-800">
                                        {{ $aff['name'] }}
                                        <span class="block text-[10px] text-slate-400 font-normal">{{ $aff['phone'] }} ({{ $aff['code'] }})</span>
                                    </td>
                                    <td class="py-2.5 text-center text-slate-600">{{ $aff['orders_count'] }} ({{ $aff['delivered_count'] }} مسلّم)</td>
                                    <td class="py-2.5 text-center font-bold {{ $aff['delivery_rate'] >= 70 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $aff['delivery_rate'] }}%</td>
                                    <td class="py-2.5 text-left font-black text-slate-900">{{ number_format($aff['revenue_minor'] / 100, 2) }} ج.م</td>
                                    <td class="py-2.5 text-left font-bold text-purple-700">{{ number_format($aff['commission_minor'] / 100, 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400">لا توجد طلبات مسوقين في هذه الفترة</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Governorates Breakdown -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-sm font-black text-slate-900 flex items-center gap-2">
                        <span>🗺️</span>
                        <span>توزيع الطلبات والتسليم حسب المحافظات المصرية</span>
                    </h2>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="text-slate-400 font-bold border-b border-slate-100">
                                <th class="pb-2">المحافظة</th>
                                <th class="pb-2 text-center">الطلبات</th>
                                <th class="pb-2 text-center">نسبة التسليم</th>
                                <th class="pb-2 text-center">نسبة المرتجع</th>
                                <th class="pb-2 text-left">المبيعات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($governorates as $gov)
                                <tr>
                                    <td class="py-2.5 font-bold text-slate-800">{{ $gov['governorate'] }}</td>
                                    <td class="py-2.5 text-center text-slate-600">{{ $gov['orders_count'] }}</td>
                                    <td class="py-2.5 text-center font-bold text-emerald-600">{{ $gov['delivery_rate'] }}%</td>
                                    <td class="py-2.5 text-center font-bold {{ $gov['return_rate'] > 20 ? 'text-rose-600' : 'text-slate-500' }}">{{ $gov['return_rate'] }}%</td>
                                    <td class="py-2.5 text-left font-black text-slate-900">{{ number_format($gov['revenue_minor'] / 100, 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400">لا توجد طلبات بمواقع محددة</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Returns and Return Reasons -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-sm font-black text-slate-900 flex items-center gap-2">
                        <span>🔄</span>
                        <span>تحليل أسباب المرتجعات وفشل التوصيل</span>
                    </h2>
                    <span class="text-xs font-black text-rose-600">{{ $returnsBreakdown['returned_count'] }} حالة مرتجعة ({{ $returnsBreakdown['return_rate'] }}%)</span>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach($returnsBreakdown['reasons'] as $rItem)
                        @if($rItem['count'] > 0 || $loop->first)
                            <div>
                                <div class="flex justify-between text-xs font-bold text-slate-700 mb-1">
                                    <span>{{ $rItem['label'] }}</span>
                                    <span>{{ $rItem['count'] }} حالات ({{ $rItem['percentage'] }}%)</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2">
                                    <div class="bg-rose-500 h-2 rounded-full" style="width: {{ min(100, $rItem['percentage']) }}%"></div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

        </div>

        <!-- Inventory and Tax Summary Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Inventory Health -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <h2 class="text-sm font-black text-slate-900 flex items-center gap-2 pb-3 border-b border-slate-100">
                    <span>📊</span>
                    <span>صحة المخزون ودوران البضاعة</span>
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 text-center">
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <span class="text-xs font-bold text-slate-400 block">إجمالي الـ SKUs</span>
                        <span class="text-lg font-black text-slate-900">{{ $inventoryReport['total_skus'] }}</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <span class="text-xs font-bold text-slate-400 block">القطع بالمستودع</span>
                        <span class="text-lg font-black text-slate-900">{{ $inventoryReport['total_units_on_hand'] }}</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <span class="text-xs font-bold text-slate-400 block">المنتجات المنتهية</span>
                        <span class="text-lg font-black text-rose-600">{{ $inventoryReport['stockout_skus'] }}</span>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <span class="text-xs font-bold text-slate-400 block">معدل الدوران (30 يوم)</span>
                        <span class="text-lg font-black text-indigo-600">{{ $inventoryReport['turnover_rate'] }}x</span>
                    </div>
                </div>
            </div>

            <!-- Tax Report -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <h2 class="text-sm font-black text-slate-900 flex items-center gap-2 pb-3 border-b border-slate-100">
                    <span>🧾</span>
                    <span>تقرير الضرائب والقيمة المضافة (VAT)</span>
                </h2>
                <div class="grid grid-cols-2 gap-3 mt-4 text-center">
                    <div class="p-4 bg-slate-50 rounded-xl">
                        <span class="text-xs font-bold text-slate-400 block">إجمالي الضرائب المحصلة</span>
                        <span class="text-xl font-black text-blue-600">{{ number_format($taxReport['tax_collected_minor'] / 100, 2) }} ج.م</span>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-xl">
                        <span class="text-xs font-bold text-slate-400 block">متوسط النسبة الضريبية الفعلية</span>
                        <span class="text-xl font-black text-slate-800">{{ $taxReport['effective_tax_rate'] }}%</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
