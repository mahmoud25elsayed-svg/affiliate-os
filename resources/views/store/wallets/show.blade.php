<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محفظة العميل #{{ $wallet->customer_id }} - لوحة المتجر</title>
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

        <!-- Navigation & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('store.wallets.index') }}" class="p-2.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-xl transition shadow-sm text-sm">
                    ← العودة لقائمة المحافظ
                </a>
                <div>
                    <h1 class="text-xl font-black text-slate-900">
                        {{ $wallet->customer ? trim($wallet->customer->first_name . ' ' . $wallet->customer->last_name) : "العميل #{$wallet->customer_id}" }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        الهاتف: <span class="font-mono text-slate-700 font-bold">{{ $wallet->customer?->phone ?? '-' }}</span>
                        • البريد: <span class="font-mono text-slate-700 font-bold">{{ $wallet->customer?->email ?? '-' }}</span>
                    </p>
                </div>
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
        @if($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold space-y-1">
                @foreach($errors->all() as $error)
                    <p>⚠️ {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Balances Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Wallet Balance -->
            <div class="bg-gradient-to-br from-emerald-500 to-teal-700 text-white p-6 rounded-3xl shadow-sm relative overflow-hidden">
                <div class="relative z-10">
                    <span class="text-xs font-bold text-emerald-100 uppercase tracking-wider block mb-1">رصيد محفظة العميل (Store Credit)</span>
                    <div class="text-3xl font-black mb-1">
                        {{ number_format($wallet->balance_minor / 100, 2) }} <span class="text-base font-normal">ج.م</span>
                    </div>
                    <p class="text-xs text-emerald-100">رصيد متاح للاستخدام في عمليات الشراء المباشرة أو الدفع الجزئي</p>
                </div>
            </div>

            <!-- Loyalty Points -->
            <div class="bg-gradient-to-br from-violet-600 to-indigo-800 text-white p-6 rounded-3xl shadow-sm relative overflow-hidden">
                <div class="relative z-10">
                    <span class="text-xs font-bold text-violet-200 uppercase tracking-wider block mb-1">نقاط الولاء والمكافآت (Loyalty Points)</span>
                    <div class="text-3xl font-black mb-1">
                        {{ number_format($loyaltyAccount?->points_balance ?? 0) }} <span class="text-base font-normal">نقطة</span>
                    </div>
                    <p class="text-xs text-violet-200">
                        تعادل قيمة خصم نقدي: <span class="font-bold underline">{{ number_format((($loyaltyAccount?->points_balance ?? 0) * 10) / 100, 2) }} ج.م</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Management Forms -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Wallet Adjustment Form -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <h3 class="font-black text-slate-900 text-sm flex items-center gap-2">
                    <span>💵</span>
                    <span>تعديل يدوي لرصيد المحفظة</span>
                </h3>
                <form action="{{ route('store.wallets.adjust-wallet', $wallet) }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">نوع الحركة</label>
                            <select name="action" required class="w-full text-xs font-bold px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none bg-white">
                                <option value="credit">إضافة رصيد (+)</option>
                                <option value="debit">خصم رصيد (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">المبلغ (ج.م)</label>
                            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="50.00" class="w-full text-xs font-bold px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">سبب التعديل / ملاحظات</label>
                        <input type="text" name="notes" required placeholder="مثال: رصيد تعويضي عن تأخير شحنة" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                        تنفيذ تعديل المحفظة
                    </button>
                </form>
            </div>

            <!-- Loyalty Adjustment Form -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <h3 class="font-black text-slate-900 text-sm flex items-center gap-2">
                    <span>⭐</span>
                    <span>تعديل يدوي لنقاط الولاء</span>
                </h3>
                <form action="{{ route('store.wallets.adjust-loyalty', $wallet) }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">نوع الحركة</label>
                            <select name="action" required class="w-full text-xs font-bold px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:outline-none bg-white">
                                <option value="award">منح نقاط (+)</option>
                                <option value="deduct">خصم نقاط (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">عدد النقاط</label>
                            <input type="number" min="1" name="points" required placeholder="100" class="w-full text-xs font-bold px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">سبب التعديل / ملاحظات</label>
                        <input type="text" name="notes" required placeholder="مثال: بونص ترحيبي أو تصحيح نقاط" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:outline-none">
                    </div>
                    <button type="submit" class="w-full py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                        تنفيذ تعديل النقاط
                    </button>
                </form>
            </div>
        </div>

        <!-- Wallet Transactions History -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="font-black text-slate-900 text-xs">سجل حركات محفظة الرصيد ({{ $wallet->transactions->count() }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold">
                        <tr>
                            <th class="px-4 py-3">المبلغ</th>
                            <th class="px-4 py-3">النوع</th>
                            <th class="px-4 py-3">المرجع</th>
                            <th class="px-4 py-3">الملاحظات</th>
                            <th class="px-4 py-3">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($wallet->transactions as $tx)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-4 py-3 font-black {{ $tx->amount_minor > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ ($tx->amount_minor > 0 ? '+' : '') . number_format($tx->amount_minor / 100, 2) }} ج.م
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                                        {{ $tx->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-500">
                                    {{ $tx->reference_id ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $tx->notes ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-slate-400 font-mono">
                                    {{ $tx->created_at->format('Y-m-d H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-slate-400 font-semibold">
                                    لا توجد حركات مسجلة بالمحفظة بعد.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Loyalty Transactions History -->
        @if($loyaltyAccount)
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-black text-slate-900 text-xs">سجل حركات نقاط الولاء ({{ $loyaltyAccount->transactions->count() }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-bold">
                            <tr>
                                <th class="px-4 py-3">النقاط</th>
                                <th class="px-4 py-3">الرصيد بعد الحركة</th>
                                <th class="px-4 py-3">النوع</th>
                                <th class="px-4 py-3">الطلب</th>
                                <th class="px-4 py-3">الملاحظات</th>
                                <th class="px-4 py-3">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($loyaltyAccount->transactions as $ltx)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-4 py-3 font-black {{ $ltx->points > 0 ? 'text-violet-600' : 'text-rose-600' }}">
                                        {{ ($ltx->points > 0 ? '+' : '') . number_format($ltx->points) }} نقطة
                                    </td>
                                    <td class="px-4 py-3 font-bold text-slate-800">
                                        {{ number_format($ltx->balance_after) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-800">
                                            {{ $ltx->type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-slate-500">
                                        {{ $ltx->order_id ? "#{$ltx->order_id}" : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ $ltx->notes ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-400 font-mono">
                                        {{ $ltx->created_at->format('Y-m-d H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-slate-400 font-semibold">
                                        لا توجد حركات نقاط ولاء بعد.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</body>
</html>

