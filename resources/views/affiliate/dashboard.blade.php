@extends('layouts.affiliate')

@section('title', 'لوحة تحكم المسوق')

@section('content')
<div class="space-y-6">

    <!-- Status Alerts -->
    @if($affiliate->isPending())
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-start space-x-3 space-x-reverse">
            <span class="text-2xl ml-3">⏳</span>
            <div>
                <h3 class="font-bold text-amber-900 text-sm">حسابك في انتظار مراجعة إدارة المتجر</h3>
                <p class="text-xs text-amber-700 mt-1">
                    تم تأكيد هاتفك بنجاح، وتجري مراجعة طلب انضمامك للبرنامج. ستتمكن من تسجيل الطلبات وسحب الأرباح فور اعتماد الحساب.
                </p>
            </div>
        </div>
    @endif

    @if(!$affiliate->hasApprovedKyc())
        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center space-x-3 space-x-reverse">
                <span class="text-2xl ml-3">🪪</span>
                <div>
                    <h3 class="font-bold text-indigo-900 text-sm">توثيق الهوية الشخصية (KYC) مطلوب</h3>
                    <p class="text-xs text-indigo-700 mt-0.5">
                        @if($affiliate->kyc_status === 'pending')
                            تم رفع بطاقتك بنجاح وهي قيد المراجعة حالياً من قِبل التاجر.
                        @elseif($affiliate->kyc_status === 'rejected')
                            تم رفض وثائق الهوية: <span class="font-bold">{{ $affiliate->kyc_rejected_reason }}</span>. يرجى إعادة رفع صور واضحة.
                        @else
                            يرجى رفع صورة بطاقة الرقم القومي (وجه وظهر) لتتمكن من سحب أرباحك لاحقاً.
                        @endif
                    </p>
                </div>
            </div>
            @if($affiliate->kyc_status !== 'pending')
                <a href="{{ route('affiliate.kyc.show') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm text-center">
                    توثيق الهوية الآن
                </a>
            @endif
        </div>
    @endif

    @if($affiliate->isPayoutFrozen())
        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 flex items-center space-x-3 space-x-reverse">
            <span class="text-2xl ml-3">🔒</span>
            <div>
                <h3 class="font-bold text-rose-900 text-sm">عمليات السحب مجمدة مؤقتاً لأسباب أمنية</h3>
                <p class="text-xs text-rose-700 mt-0.5">
                    تم تجميد السحب حتى <span class="font-bold font-mono">{{ $affiliate->payouts_frozen_until?->format('d/m/Y H:i') }}</span> بسبب تعديل بيانات استلام الأرباح.
                </p>
            </div>
        </div>
    @endif

    @if($hasDebt)
        <div class="bg-rose-50 border border-rose-300 rounded-2xl p-5 flex items-start space-x-3 space-x-reverse shadow-sm">
            <span class="text-2xl ml-3">⚠️</span>
            <div>
                <h3 class="font-bold text-rose-900 text-sm">تنبيه مالي: يوجد رصيد مدين مستحق عليك</h3>
                <p class="text-xs text-rose-700 mt-1">
                    المبلغ المستحق عليك: <strong class="font-mono text-sm">{{ number_format($debtMinor / 100, 2) }} ج.م</strong> بسبب مرتجعات متأخرة بعد تسوية الأرباح وسحبها.
                    عمليات السحب محجوبة تلقائياً حتى يتم تسوية هذا الدين من أرباح طلباتك القادمة.
                </p>
            </div>
        </div>
    @endif

    <!-- Profile & Referral Overview Banner -->
    <div class="bg-gradient-to-l from-indigo-700 to-indigo-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center space-x-3 space-x-reverse mb-2">
                <h1 class="text-2xl sm:text-3xl font-extrabold">{{ $affiliate->name }}</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $affiliate->isActive() ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-slate-950' }}">
                    {{ $affiliate->isActive() ? 'حساب نشط' : ($affiliate->isPending() ? 'قيد المراجعة' : 'معلق') }}
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-indigo-200 text-xs sm:text-sm">
                <span>كود المسوق: <strong class="font-mono bg-indigo-800/80 px-2 py-0.5 rounded text-amber-300">{{ $affiliate->code }}</strong></span>
                <span>•</span>
                <span>متوسط زمن الدفع: <strong class="text-white">{{ $averageTurnaround }}</strong></span>
                <span>•</span>
                <span>أيام التحويل: <strong class="text-white">{{ implode('، ', array_map(fn($d) => match(strtolower($d)) { 'sunday' => 'الأحد', 'monday' => 'الاثنين', 'tuesday' => 'الثلاثاء', 'wednesday' => 'الأربعاء', 'thursday' => 'الخميس', 'friday' => 'الجمعة', 'saturday' => 'السبت', default => $d }, $settings->payout_days)) }}</strong></span>
            </div>
        </div>

        <!-- Referral Link Box -->
        <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/20 w-full md:w-auto text-left" x-data="{ copied: false }">
            <label class="block text-xs text-indigo-200 mb-1.5 font-semibold text-right">رابط الإحالة لبرنامج المسوقين:</label>
            <div class="flex items-center space-x-2 space-x-reverse">
                <input type="text" readonly value="{{ $referralLink }}" id="refLink" dir="ltr"
                       class="bg-indigo-950/60 text-white text-xs font-mono px-3 py-2 rounded-lg border border-indigo-400/30 focus:outline-none w-full md:w-64">
                <button @click="navigator.clipboard.writeText('{{ $referralLink }}'); copied = true; setTimeout(() => copied = false, 2500)"
                        class="bg-amber-400 hover:bg-amber-500 text-slate-950 font-bold px-3 py-2 rounded-lg text-xs whitespace-nowrap transition">
                    <span x-show="!copied">نسخ الرابط</span>
                    <span x-show="copied" class="text-emerald-900 font-bold">تم النسخ! ✓</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 1. Wallet Balances Cards -->
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-700 flex items-center space-x-2 space-x-reverse">
                <span>💰</span>
                <span>محفظة الأرباح والأرصدة</span>
            </h2>
            <a href="{{ route('affiliate.wallet.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                عرض تفاصيل المحفظة ←
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Available -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-slate-500">الرصيد المتاح للسحب</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-extrabold {{ $availableBalance < 0 ? 'text-rose-600' : 'text-emerald-600' }} font-mono">
                        {{ number_format($availableBalance / 100, 2) }}
                    </span>
                    <span class="text-xs font-bold text-slate-400">ج.م</span>
                </div>
                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400">حد السحب: {{ number_format($settings->min_payout_minor / 100, 2) }} ج.م</span>
                    @if($affiliate->canWithdraw() && $availableBalance >= $settings->min_payout_minor)
                        <a href="{{ route('affiliate.payouts.index') }}" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-900 bg-emerald-50 px-2 py-0.5 rounded-lg">
                            سحب أرباح ←
                        </a>
                    @endif
                </div>
            </div>

            <!-- Pending -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-slate-500">الأرباح المعلقة (تحت التسليم)</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-extrabold text-amber-600 font-mono">
                        {{ number_format($pendingBalance / 100, 2) }}
                    </span>
                    <span class="text-xs font-bold text-slate-400">ج.م</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-3 block pt-3 border-t border-slate-100">
                    تتحول للمتاح بعد {{ $settings->return_window_days }} يوماً من تسليم الطلب
                </span>
            </div>

            <!-- Locked -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-slate-500">أرباح محجوزة قيد التحويل</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-extrabold text-indigo-600 font-mono">
                        {{ number_format($lockedBalance / 100, 2) }}
                    </span>
                    <span class="text-xs font-bold text-slate-400">ج.م</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-3 block pt-3 border-t border-slate-100">
                    طلبات سحب قيد المراجعة أو التنفيذ
                </span>
            </div>

            <!-- Withdrawn -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-slate-500">إجمالي المسحوبات المستلمة</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-extrabold text-slate-800 font-mono">
                        {{ number_format($withdrawnBalance / 100, 2) }}
                    </span>
                    <span class="text-xs font-bold text-slate-400">ج.م</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-3 block pt-3 border-t border-slate-100">
                    أرباح تم تحويلها لحسابك بنجاح
                </span>
            </div>
        </div>
    </div>

    <!-- 2. Order Performance Statistics Cards -->
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-700 flex items-center space-x-2 space-x-reverse">
                <span>📦</span>
                <span>إحصائيات وحركات الطلبات</span>
            </h2>
            <a href="{{ route('affiliate.orders.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                كل طلباتي ←
            </a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
            <!-- Total Orders -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold text-slate-500 block mb-1">إجمالي الطلبات</span>
                <span class="text-xl sm:text-2xl font-extrabold text-slate-900 font-mono">{{ $totalOrders }}</span>
                <span class="text-[11px] text-slate-400 block mt-1">الحد: {{ $settings->default_daily_order_limit }}/يوم</span>
            </div>

            <!-- Delivered Orders -->
            <div class="bg-white p-4 rounded-2xl border border-emerald-200 bg-emerald-50/20 shadow-sm">
                <span class="text-xs font-semibold text-emerald-800 block mb-1">الطلبات المسلّمة ✓</span>
                <span class="text-xl sm:text-2xl font-extrabold text-emerald-700 font-mono">{{ $deliveredOrders }}</span>
                <span class="text-[11px] text-emerald-600 block mt-1">نسبة التسليم: {{ $deliveryRate }}%</span>
            </div>

            <!-- In Progress Orders -->
            <div class="bg-white p-4 rounded-2xl border border-indigo-200 bg-indigo-50/20 shadow-sm">
                <span class="text-xs font-semibold text-indigo-800 block mb-1">جاري الشحن/المعالجة</span>
                <span class="text-xl sm:text-2xl font-extrabold text-indigo-700 font-mono">{{ $inProgressOrders }}</span>
                <span class="text-[11px] text-indigo-600 block mt-1">قيد التوصيل والمتابعة</span>
            </div>

            <!-- Cancelled Orders -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold text-slate-500 block mb-1">الطلبات الملغاة</span>
                <span class="text-xl sm:text-2xl font-extrabold text-slate-600 font-mono">{{ $cancelledOrders }}</span>
                <span class="text-[11px] text-slate-400 block mt-1">ملغي قبل الشحن</span>
            </div>

            <!-- Returned Orders -->
            <div class="bg-white p-4 rounded-2xl border border-rose-200 bg-rose-50/20 shadow-sm col-span-2 sm:col-span-1">
                <span class="text-xs font-semibold text-rose-800 block mb-1">المرتجعات</span>
                <span class="text-xl sm:text-2xl font-extrabold text-rose-700 font-mono">{{ $returnedOrders }}</span>
                <span class="text-[11px] text-rose-600 block mt-1">فشل تسليم / مرتجع</span>
            </div>
        </div>
    </div>

    <!-- 3. Tables Grid: Recent Orders & Recent Ledger Movements -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Recent Orders -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-800 text-sm flex items-center space-x-2 space-x-reverse">
                    <span>🛍️</span>
                    <span>أحدث الطلبات</span>
                </h3>
                <a href="{{ route('affiliate.orders.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold">
                    عرض الكل ←
                </a>
            </div>

            @if($recentOrders->isEmpty())
                <div class="py-10 text-center text-slate-400 text-xs">
                    لم تقم بتسجيل أي طلبات حتى الآن.
                    <div class="mt-3">
                        <a href="{{ route('affiliate.orders.create') }}" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg font-bold text-xs hover:bg-indigo-700">
                            + تسجيل أول طلب الآن
                        </a>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-right">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100">
                                <th class="pb-2 font-semibold">رقم الطلب</th>
                                <th class="pb-2 font-semibold">الحالة</th>
                                <th class="pb-2 font-semibold">ربحك</th>
                                <th class="pb-2 font-semibold">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentOrders as $order)
                                @php
                                    $snapshot = is_array($order->price_snapshot) ? $order->price_snapshot : json_decode($order->price_snapshot ?? '[]', true);
                                    $commissionMinor = (int) ($snapshot['affiliate_profit_minor'] ?? ($order->meta?->affiliate_commission_minor ?? 0));
                                @endphp
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2.5 font-mono font-bold text-indigo-600">
                                        <a href="{{ route('affiliate.orders.show', $order->id) }}">
                                            #{{ $order->reference ?? $order->id }}
                                        </a>
                                    </td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold
                                            @if($order->status === 'delivered') bg-emerald-100 text-emerald-800
                                            @elseif($order->status === 'cancelled') bg-slate-100 text-slate-600
                                            @elseif(str_contains($order->status, 'return')) bg-rose-100 text-rose-800
                                            @else bg-amber-100 text-amber-800 @endif">
                                            {{ $order->status }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 font-mono font-bold text-emerald-600">
                                        {{ number_format($commissionMinor / 100, 2) }} ج.م
                                    </td>
                                    <td class="py-2.5 text-slate-400 font-mono text-[11px]">
                                        {{ $order->created_at?->format('m/d H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Recent Ledger Movements -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-bold text-slate-800 text-sm flex items-center space-x-2 space-x-reverse">
                    <span>📑</span>
                    <span>أحدث حركات المحفظة والدفتر المالي</span>
                </h3>
                <a href="{{ route('affiliate.wallet.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold">
                    كشف الحساب ←
                </a>
            </div>

            @if($recentMovements->isEmpty())
                <div class="py-10 text-center text-slate-400 text-xs">
                    لا توجد حركات مالية مسجلة بعد.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-right">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100">
                                <th class="pb-2 font-semibold">البيان</th>
                                <th class="pb-2 font-semibold">الحساب</th>
                                <th class="pb-2 font-semibold">المبلغ</th>
                                <th class="pb-2 font-semibold">الوقت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentMovements as $line)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2.5 text-slate-700 font-medium max-w-[160px] truncate" title="{{ $line->journalEntry?->description }}">
                                        {{ $line->journalEntry?->description ?: $line->journalEntry?->type }}
                                    </td>
                                    <td class="py-2.5 text-[11px] text-slate-500">
                                        {{ $line->account?->name }}
                                    </td>
                                    <td class="py-2.5 font-mono font-bold {{ $line->amount_minor >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $line->amount_minor >= 0 ? '+' : '' }}{{ number_format($line->amount_minor / 100, 2) }} ج.م
                                    </td>
                                    <td class="py-2.5 text-slate-400 font-mono text-[11px]">
                                        {{ $line->created_at->format('m/d H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

    <!-- 4. Quick Action Hub -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
        <a href="{{ route('affiliate.orders.create') }}" class="p-4 bg-white rounded-2xl border border-slate-200 hover:border-indigo-400 hover:shadow-md transition text-center group">
            <span class="text-2xl block mb-1">🛒</span>
            <span class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 block">تسجيل طلب جديد</span>
            <span class="text-[10px] text-slate-400">سعر حر وحساب فوري للأرباح</span>
        </a>

        <a href="{{ route('affiliate.catalog.index') }}" class="p-4 bg-white rounded-2xl border border-slate-200 hover:border-indigo-400 hover:shadow-md transition text-center group">
            <span class="text-2xl block mb-1">📦</span>
            <span class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 block">كتالوج المنتجات</span>
            <span class="text-[10px] text-slate-400">أسعار الجملة وهوامش الربح</span>
        </a>

        <a href="{{ route('affiliate.links.index') }}" class="p-4 bg-white rounded-2xl border border-slate-200 hover:border-indigo-400 hover:shadow-md transition text-center group">
            <span class="text-2xl block mb-1">🔗</span>
            <span class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 block">روابط التتبع و QR</span>
            <span class="text-[10px] text-slate-400">تسويق وإسناد مباشر</span>
        </a>

        <a href="{{ route('affiliate.payouts.index') }}" class="p-4 bg-white rounded-2xl border border-slate-200 hover:border-indigo-400 hover:shadow-md transition text-center group">
            <span class="text-2xl block mb-1">💳</span>
            <span class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 block">طلبات السحب والدفع</span>
            <span class="text-[10px] text-slate-400">سحب فودافون كاش وإنستاباي</span>
        </a>
    </div>

</div>
@endsection
