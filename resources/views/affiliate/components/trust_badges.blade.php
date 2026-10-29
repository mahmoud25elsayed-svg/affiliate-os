@inject('trustService', 'App\Domain\Reports\Services\StorePublicTrustMetricsService')
@php
    $trustMetrics = $trustService->getMetrics();
@endphp

@if($trustMetrics['enabled'])
    <div class="bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-indigo-500/10 border border-emerald-200/60 rounded-2xl p-4 my-4 shadow-sm" dir="rtl">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-emerald-600 text-lg">🛡️</span>
            <h3 class="text-xs font-black text-slate-800">مؤشرات الشفافية والأداء للمسوقين (Trust Badges)</h3>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">موثوق</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
            <!-- Payout Speed -->
            <div class="bg-white/80 backdrop-blur-sm p-3 rounded-xl border border-emerald-100 shadow-xs">
                <span class="text-[10px] font-bold text-slate-400 block mb-1">⚡ سرعة سحب الأرباح</span>
                <span class="text-xs font-black text-emerald-700 block">{{ $trustMetrics['average_payout_time'] }}</span>
            </div>

            <!-- Delivery Success Rate -->
            <div class="bg-white/80 backdrop-blur-sm p-3 rounded-xl border border-emerald-100 shadow-xs">
                <span class="text-[10px] font-bold text-slate-400 block mb-1">📦 نسبة نجاح التوصيل</span>
                <span class="text-xs font-black text-emerald-700 block">{{ $trustMetrics['delivery_success_rate'] }}%</span>
            </div>

            <!-- Active Affiliates -->
            <div class="bg-white/80 backdrop-blur-sm p-3 rounded-xl border border-emerald-100 shadow-xs">
                <span class="text-[10px] font-bold text-slate-400 block mb-1">👥 مسوق نشط بالمنصة</span>
                <span class="text-xs font-black text-indigo-700 block">+{{ max(1, $trustMetrics['active_affiliates_count']) }}</span>
            </div>

            <!-- Delivered Orders -->
            <div class="bg-white/80 backdrop-blur-sm p-3 rounded-xl border border-emerald-100 shadow-xs">
                <span class="text-[10px] font-bold text-slate-400 block mb-1">✅ طلبات مسلّمة بنجاح</span>
                <span class="text-xs font-black text-slate-900 block">+{{ max(10, $trustMetrics['delivered_orders_count']) }}</span>
            </div>
        </div>
    </div>
@endif
