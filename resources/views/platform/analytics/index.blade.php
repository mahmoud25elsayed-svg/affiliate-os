<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحليلات المنصة المركزية (Platform Metrics & MRR)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Header -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div>
                <h1 class="text-2xl font-black text-white flex items-center gap-2">
                    <span>⚡</span>
                    <span>تحليلات المنصة المركزية ومؤشرات الـ SaaS</span>
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    متابعة الإيراد الشهري المتكرر (MRR)، التجار النشطين، معدل الانقطاع، وتوزيع الاشتراكات
                </p>
            </div>
            <div>
                <a href="/platform" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-bold rounded-xl border border-slate-700 transition">
                    العودة إلى لوحة الإدارة
                </a>
            </div>
        </div>

        <!-- 4 Top KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- MRR -->
            <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700/60 shadow-lg">
                <span class="text-xs font-bold text-slate-400 block">الإيراد الشهري المتكرر (MRR)</span>
                <span class="text-2xl font-black text-emerald-400 mt-2 block">
                    {{ number_format($mrrMinor / 100, 2) }} <span class="text-xs text-slate-300">ج.م</span>
                </span>
                <span class="text-[11px] text-slate-400 mt-1 block">محسوب لجميع الاشتراكات النشطة والتجريبية</span>
            </div>

            <!-- Active Merchants -->
            <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700/60 shadow-lg">
                <span class="text-xs font-bold text-slate-400 block">التجار النشطون (Active Merchants)</span>
                <span class="text-2xl font-black text-indigo-400 mt-2 block">{{ $activeMerchants }}</span>
                <span class="text-[11px] text-slate-400 mt-1 block">متاجر مفعلة أو باشتراكات سارية</span>
            </div>

            <!-- Churn Rate -->
            <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700/60 shadow-lg">
                <span class="text-xs font-bold text-slate-400 block">معدل الانقطاع (Churn Rate)</span>
                <span class="text-2xl font-black {{ $churnRate > 5 ? 'text-amber-400' : 'text-emerald-400' }} mt-2 block">
                    {{ $churnRate }}%
                </span>
                <span class="text-[11px] text-slate-400 mt-1 block">خلال الـ 30 يوماً الماضية</span>
            </div>

            <!-- Tenant Errors -->
            <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700/60 shadow-lg">
                <span class="text-xs font-bold text-slate-400 block">سجلات الأخطاء المسجلة</span>
                <span class="text-2xl font-black text-rose-400 mt-2 block">{{ count($recentLogs) }}</span>
                <span class="text-[11px] text-slate-400 mt-1 block">أحدث أخطاء تم رصدها بالمستأجرين</span>
            </div>
        </div>

        <!-- Plan Usage Breakdown & Errors Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Plan Usage -->
            <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700/60 shadow-lg">
                <h2 class="text-sm font-black text-white flex items-center gap-2 pb-3 border-b border-slate-700">
                    <span>💎</span>
                    <span>توزيع الاشتراكات وحصة الخطط (Plan Usage)</span>
                </h2>
                <div class="mt-4 space-y-4">
                    @forelse($planBreakdown as $planSlug => $pData)
                        <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-800">
                            <div class="flex justify-between items-center text-xs font-bold mb-1.5">
                                <span class="text-white">{{ $pData['name'] }}</span>
                                <span class="text-slate-400">{{ $pData['count'] }} متجر ({{ $pData['percentage'] }}%)</span>
                            </div>
                            <div class="w-full bg-slate-800 rounded-full h-2 mb-2">
                                <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ min(100, $pData['percentage']) }}%"></div>
                            </div>
                            <div class="flex justify-between text-[11px] text-slate-400 font-semibold">
                                <span>عائد الخطة الشهري:</span>
                                <span class="text-emerald-400 font-bold">{{ number_format($pData['mrr_minor'] / 100, 2) }} ج.م</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500 py-4 text-center">لا توجد اشتراكات مسجلة حالياً</p>
                    @endforelse
                </div>
            </div>

            <!-- Errors per Tenant Summary -->
            <div class="bg-slate-800/80 p-5 rounded-2xl border border-slate-700/60 shadow-lg">
                <h2 class="text-sm font-black text-white flex items-center gap-2 pb-3 border-b border-slate-700">
                    <span>⚠️</span>
                    <span>الأخطاء المرصودة لكل مستأجر (Errors Per Tenant)</span>
                </h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-700 pb-2">
                                <th class="pb-2">المتجر / المستأجر</th>
                                <th class="pb-2 text-center">عدد الأخطاء</th>
                                <th class="pb-2 text-left">آخر خطأ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse($tenantErrors as $err)
                                <tr>
                                    <td class="py-2.5 font-bold text-slate-200">
                                        {{ $err['tenant_name'] }}
                                        @if($err['tenant_id'])
                                            <span class="block text-[10px] text-slate-500 font-normal">#{{ $err['tenant_id'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-center font-bold text-rose-400">{{ $err['error_count'] }}</td>
                                    <td class="py-2.5 text-left text-slate-400 text-[11px]">{{ $err['last_error_at']->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-slate-500">لا توجد أخطاء مسجلة بالمستأجرين. جميع الأنظمة مستقرة تماماً!</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</body>
</html>
