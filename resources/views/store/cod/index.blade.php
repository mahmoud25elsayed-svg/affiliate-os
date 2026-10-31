<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسوية التحصيلات والتوريد (COD) - لوحة المتجر</title>
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

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 bg-emerald-600 text-white rounded-2xl shadow-sm text-lg font-black">💰</span>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">تسوية التحصيلات والتوريد (COD)</h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            مطابقة كشوفات شركات الشحن، رصد الفروقات، توريد المبالغ للبنك، وترحيل القيود المحاسبية تلقائياً
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2 space-x-reverse">
                <a href="{{ route('store.shipments.index') }}" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-sm">
                    🚚 إدارة الشحنات
                </a>
                <a href="{{ route('store.cod.create') }}" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <span>+</span>
                    <span>استيراد كشف تسوية جديد</span>
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

        <!-- Stats Overview -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-slate-500 block mb-1">إجمالي الكشوفات</span>
                <span class="text-2xl font-black text-slate-900">{{ number_format($stats['total_count']) }}</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-amber-600 block mb-1">بانتظار التأكيد والتوريد</span>
                <span class="text-2xl font-black text-amber-600">{{ number_format($stats['pending_settlement']) }}</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-emerald-600 block mb-1">كشوفات مسواة وموردة</span>
                <span class="text-2xl font-black text-emerald-600">{{ number_format($stats['settled_count']) }}</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-blue-600 block mb-1">صافي المبالغ الموردة</span>
                <span class="text-2xl font-black text-blue-600">{{ number_format($stats['total_settled_minor'] / 100, 2) }} <span class="text-xs font-normal">{{ \App\Support\Money::symbol() }}</span></span>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('store.cod.index') }}" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition {{ empty($currentStatus) ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    الكل
                </a>
                @foreach($statuses as $status)
                    <a href="{{ route('store.cod.index', ['status' => $status->value]) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition {{ $currentStatus === $status->value ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $status->label() }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Remittances Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50/75 border-b border-slate-200 text-slate-500 font-bold">
                        <tr>
                            <th class="px-4 py-3.5">رقم الكشف / المرجع</th>
                            <th class="px-4 py-3.5">شركة الشحن</th>
                            <th class="px-4 py-3.5">الحالة</th>
                            <th class="px-4 py-3.5">المحصل المبلغ (COD)</th>
                            <th class="px-4 py-3.5">مصاريف الشحن</th>
                            <th class="px-4 py-3.5">صافي التوريد</th>
                            <th class="px-4 py-3.5">الطلبات (مطابق / فروقات)</th>
                            <th class="px-4 py-3.5">تاريخ التوريد</th>
                            <th class="px-4 py-3.5 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($remittances as $remittance)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-4 py-4 font-mono font-bold text-slate-900">
                                    <a href="{{ route('store.cod.show', $remittance) }}" class="text-blue-600 hover:underline">
                                        {{ $remittance->reference }}
                                    </a>
                                </td>
                                <td class="px-4 py-4 text-slate-700 font-semibold">
                                    {{ $remittance->courierAccount?->name ?? 'غير محدد' }}
                                </td>
                                <td class="px-4 py-4">
                                    @php
                                        $color = $remittance->status->badgeColor();
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black bg-{{ $color }}-100 text-{{ $color }}-800 border border-{{ $color }}-200">
                                        {{ $remittance->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-black text-slate-900">
                                    {{ \App\Support\Money::show($remittance->total_collected_minor) }}
                                </td>
                                <td class="px-4 py-4 font-bold text-rose-600">
                                    -{{ \App\Support\Money::show($remittance->total_shipping_fees_minor) }}
                                </td>
                                <td class="px-4 py-4 font-black text-emerald-700">
                                    {{ \App\Support\Money::show($remittance->net_payout_minor) }}
                                </td>
                                <td class="px-4 py-4">
                                    <span class="text-emerald-700 font-bold">{{ $remittance->matched_orders_count }} مطابق</span>
                                    @if($remittance->discrepancy_orders_count > 0)
                                        <span class="text-amber-700 font-bold mr-1">/ {{ $remittance->discrepancy_orders_count }} فروقات</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    {{ $remittance->settled_at ? $remittance->settled_at->format('Y-m-d H:i') : 'لم يتم بعد' }}
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <a href="{{ route('store.cod.show', $remittance) }}" class="inline-block px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition text-[11px]">
                                        عرض التفاصيل
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                                    <p class="font-bold">لا توجد كشوفات تسوية مسجلة حتى الآن.</p>
                                    <a href="{{ route('store.cod.create') }}" class="mt-2 inline-block text-blue-600 hover:underline">
                                        + اضغط هنا لاستيراد أول كشف تسوية
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($remittances->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $remittances->links() }}
                </div>
            @endif
        </div>

    </div>
</body>
</html>

