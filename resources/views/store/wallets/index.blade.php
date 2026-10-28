<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محافظ العملاء وبرنامج الولاء - لوحة المتجر</title>
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
                    <span class="p-2.5 bg-violet-600 text-white rounded-2xl shadow-sm text-lg font-black">💳</span>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">محافظ العملاء ونقاط الولاء</h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            متابعة أرصدة رصيد المتجر للعملاء، نقاط المكافآت المكتسبة، وسجل العمليات المالية والخصومات
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2 space-x-reverse">
                <a href="{{ route('store.cod.index') }}" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-sm">
                    💰 تسوية COD
                </a>
                <a href="{{ route('store.shipments.index') }}" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-sm">
                    🚚 إدارة الشحنات
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
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-slate-500 block mb-1">إجمالي محافظ العملاء</span>
                <span class="text-2xl font-black text-slate-900">{{ number_format($stats['total_wallets']) }}</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-emerald-600 block mb-1">إجمالي الأرصدة المتاحة للعملاء</span>
                <span class="text-2xl font-black text-emerald-600">{{ number_format($stats['total_balance_minor'] / 100, 2) }} <span class="text-xs font-normal">ج.م</span></span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-violet-600 block mb-1">إجمالي نقاط الولاء المعلقة</span>
                <span class="text-2xl font-black text-violet-600">{{ number_format($stats['total_loyalty_points']) }} <span class="text-xs font-normal">نقطة</span></span>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between gap-4">
            <form action="{{ route('store.wallets.index') }}" method="GET" class="flex-1 max-w-md flex items-center gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="ابحث باسم العميل، الهاتف، أو البريد..." class="w-full text-xs px-3.5 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:outline-none">
                <button type="submit" class="px-4 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition">
                    بحث
                </button>
            </form>
        </div>

        <!-- Wallets Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50/75 border-b border-slate-200 text-slate-500 font-bold">
                        <tr>
                            <th class="px-4 py-3.5">العميل</th>
                            <th class="px-4 py-3.5">رقم الهاتف</th>
                            <th class="px-4 py-3.5">البريد الإلكتروني</th>
                            <th class="px-4 py-3.5">رصيد المحفظة (Store Credit)</th>
                            <th class="px-4 py-3.5">الحالة</th>
                            <th class="px-4 py-3.5 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($wallets as $wallet)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-4 py-4 font-bold text-slate-900">
                                    <a href="{{ route('store.wallets.show', $wallet) }}" class="text-violet-600 hover:underline">
                                        {{ $wallet->customer ? trim($wallet->customer->first_name . ' ' . $wallet->customer->last_name) : "عميل #{$wallet->customer_id}" }}
                                    </a>
                                </td>
                                <td class="px-4 py-4 font-mono text-slate-600">
                                    {{ $wallet->customer?->phone ?? '-' }}
                                </td>
                                <td class="px-4 py-4 text-slate-600 font-mono">
                                    {{ $wallet->customer?->email ?? '-' }}
                                </td>
                                <td class="px-4 py-4 font-black text-emerald-700 text-sm">
                                    {{ number_format($wallet->balance_minor / 100, 2) }} ج.م
                                </td>
                                <td class="px-4 py-4">
                                    @if($wallet->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            نشط
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-600 border border-slate-200">
                                            معطل
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <a href="{{ route('store.wallets.show', $wallet) }}" class="inline-block px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition text-[11px]">
                                        عرض السجل والتعديل
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-slate-400 font-bold">
                                    لا توجد محافظ عملاء مسجلة حالياً.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($wallets->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $wallets->links() }}
                </div>
            @endif
        </div>

    </div>
</body>
</html>

