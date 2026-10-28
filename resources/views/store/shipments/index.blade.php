<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الشحنات وبوالص الشحن - لوحة المتجر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 bg-blue-600 text-white rounded-2xl shadow-sm text-lg font-black">🚚</span>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">إدارة الشحنات وبوالص التوصيل</h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            متابعة تسليم الطلبات لشركات الشحن، إصدار البوالص AWB، والتحديث اللحظي لخط السير
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2 space-x-reverse">
                <a href="{{ route('store.call-center.index') }}" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-sm">
                    📞 مكتب الكول سنتر
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center shadow-sm">
                <span class="ml-2 text-base">✓</span>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold flex items-center shadow-sm">
                <span class="ml-2 text-base">⚠️</span>
                {{ session('error') }}
            </div>
        @endif

        <!-- KPI Metrics Bar -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">إجمالي الشحنات</div>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ number_format($stats['total']) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">في الطريق / مركز التوزيع</div>
                <div class="text-2xl font-black text-blue-600 mt-1">{{ number_format($stats['in_transit']) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">خرج للتوصيل اليوم</div>
                <div class="text-2xl font-black text-amber-600 mt-1">{{ number_format($stats['out_for_delivery']) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">تم التسليم والتحصيل</div>
                <div class="text-2xl font-black text-emerald-600 mt-1">{{ number_format($stats['delivered']) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">مرتجعات / فشل تسليم</div>
                <div class="text-2xl font-black text-rose-600 mt-1">{{ number_format($stats['returning_or_returned']) }}</div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
            <form method="GET" action="{{ route('store.shipments.index') }}" class="flex flex-wrap items-center gap-2 w-full">
                <!-- Search Input -->
                <input type="text" name="search" value="{{ $search }}" placeholder="ابحث برقم التتبع، رقم الطلب، هاتف العميل..." class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500 flex-1 min-w-[240px]">

                <!-- Status Filter -->
                <select name="status" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                    <option value="">جميع الحالات</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}" {{ $currentStatus === $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition">
                    تصفية
                </button>
                @if($search || $currentStatus)
                    <a href="{{ route('store.shipments.index') }}" class="px-3 py-2 text-slate-500 hover:text-slate-800 text-xs font-bold transition">
                        إلغاء التصفية
                    </a>
                @endif
            </form>
        </div>

        <!-- Shipments Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200/80">
                        <tr>
                            <th class="py-3 px-4">رقم التتبع (Tracking #)</th>
                            <th class="py-3 px-4">رقم الطلب</th>
                            <th class="py-3 px-4">شركة الشحن</th>
                            <th class="py-3 px-4">العميل والوجهة</th>
                            <th class="py-3 px-4">المبلغ المطلوب (COD)</th>
                            <th class="py-3 px-4">الحالة</th>
                            <th class="py-3 px-4">تاريخ الإرسال</th>
                            <th class="py-3 px-4 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($shipments as $sh)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-black text-blue-600 font-mono text-sm">
                                        {{ $sh->tracking_number }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    #{{ $sh->order_id }}
                                    <span class="text-[10px] text-slate-400 block font-normal">{{ $sh->order?->reference }}</span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 bg-slate-100 rounded-lg text-slate-800 font-bold text-[11px]">
                                        {{ $sh->courierAccount?->name ?? 'افتراضي' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900">{{ $sh->recipient_name }}</div>
                                    <div class="text-[11px] text-slate-500">📍 {{ $sh->delivery_city }} — 📞 {{ $sh->recipient_phone }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-black text-emerald-700">
                                    {{ number_format($sh->cod_amount_minor / 100, 2) }} ج.م
                                </td>
                                <td class="py-3.5 px-4">
                                    @php
                                        $badgeBg = match($sh->status->color()) {
                                            'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'blue' => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                            default => 'bg-rose-50 text-rose-700 border-rose-200',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-black border {{ $badgeBg }}">
                                        {{ $sh->status->label() }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                    {{ $sh->dispatched_at?->format('Y-m-d H:i') ?? $sh->created_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="{{ route('store.shipments.show', $sh) }}" class="px-2.5 py-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg text-[11px] font-bold transition shadow-sm" title="تفاصيل التتبع">
                                            🔍 تتبع
                                        </a>
                                        <a href="{{ route('store.shipments.waybill', $sh) }}" target="_blank" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg text-[11px] font-bold transition shadow-sm" title="طباعة البوليصة">
                                            📄 البوليصة
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400 font-bold">
                                    لا توجد شحنات مسجلة مطابقة للبحث.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($shipments->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $shipments->links() }}
                </div>
            @endif
        </div>

    </div>
</body>
</html>

