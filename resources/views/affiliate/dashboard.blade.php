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
        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5 flex items-center justify-between">
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
                <a href="{{ route('affiliate.kyc.show') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm">
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

    <!-- Profile & Referral Overview -->
    <div class="bg-gradient-to-l from-indigo-700 to-indigo-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center space-x-3 space-x-reverse mb-2">
                <h1 class="text-2xl sm:text-3xl font-extrabold">{{ $affiliate->name }}</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $affiliate->isActive() ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-slate-950' }}">
                    {{ $affiliate->isActive() ? 'حساب نشط' : ($affiliate->isPending() ? 'قيد المراجعة' : 'معلق') }}
                </span>
            </div>
            <p class="text-indigo-200 text-xs sm:text-sm">
                كود المسوق الخاص بك: <span class="font-mono font-bold bg-indigo-800/80 px-2.5 py-1 rounded-lg text-amber-300 mr-1 text-sm">{{ $affiliate->code }}</span>
            </p>
        </div>

        <!-- Referral Link Copy Box -->
        <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/20 w-full md:w-auto text-left" x-data="{ copied: false }">
            <label class="block text-xs text-indigo-200 mb-1.5 font-semibold text-right">رابط الإحالة لبرنامج المسوقين:</label>
            <div class="flex items-center space-x-2 space-x-reverse">
                <input type="text" readonly value="{{ $referralLink }}" id="refLink" dir="ltr"
                       class="bg-indigo-950/60 text-white text-xs font-mono px-3 py-2 rounded-lg border border-indigo-400/30 focus:outline-none w-full md:w-64">
                <button @click="navigator.clipboard.writeText('{{ $referralLink }}'); copied = true; setTimeout(() => copied = false, 2500)"
                        class="bg-amber-400 hover:bg-amber-500 text-slate-950 font-bold px-3 py-2 rounded-lg text-xs whitespace-nowrap transition">
                    <span x-show="!copied">نسخ الرابط</span>
                    <span x-show="copied" class="text-emerald-900">تم النسخ! ✓</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Model & Rate -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 block mb-1">نموذج التسعير المعتمد</span>
            <div class="flex items-baseline justify-between">
                <span class="text-lg font-extrabold text-slate-900">
                    {{ $settings->isMarkupModel() ? 'سعر حر (Markup)' : 'نسبة مئوية' }}
                </span>
                <span class="text-xs text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded">
                    {{ $settings->isMarkupModel() ? 'أرباح مفتوحة' : $affiliate->commission_rate . '%' }}
                </span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2 block">
                @if($settings->isMarkupModel())
                    الحد الأدنى لربحك: {{ number_format($settings->min_selling_markup_minor / 100, 2) }} ج.م
                @else
                    عمولة ثابتة على قيمة الطلب
                @endif
            </span>
        </div>

        <!-- Available for Payout -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 block mb-1">الرصيد المتاح للسحب</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-emerald-600 font-mono">0.00</span>
                <span class="text-xs text-slate-500">ج.م</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2 block">
                الحد الأدنى للطلب: {{ number_format($settings->min_payout_minor / 100, 2) }} ج.م
            </span>
        </div>

        <!-- Pending Balance -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 block mb-1">الأرباح المعلقة (قيد التوصيل)</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-amber-600 font-mono">0.00</span>
                <span class="text-xs text-slate-500">ج.م</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2 block">
                نافذة المرتجع: {{ $settings->return_window_days }} يوماً بعد التسليم
            </span>
        </div>

        <!-- Orders Count -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-400 block mb-1">إجمالي طلباتك</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-indigo-600 font-mono">0</span>
                <span class="text-xs text-slate-500">طلب</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2 block">
                الحد اليومي المتاح: {{ $settings->default_daily_order_limit }} طلب/يوم
            </span>
        </div>
    </div>

    <!-- Navigation Hub Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
        <a href="{{ route('affiliate.kyc.show') }}" class="bg-white p-6 rounded-2xl border border-slate-200 hover:border-indigo-400 transition shadow-sm flex items-center justify-between group">
            <div class="flex items-center space-x-4 space-x-reverse">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl font-bold">
                    🪪
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 group-hover:text-indigo-600 text-sm">بيانات توثيق الهوية (KYC)</h4>
                    <p class="text-xs text-slate-500 mt-0.5">رفع ومراجعة صورة بطاقة الرقم القومي لتفعيل السحب</p>
                </div>
            </div>
            <span class="text-xs font-bold px-3 py-1 rounded-full {{ $affiliate->hasApprovedKyc() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                {{ $affiliate->hasApprovedKyc() ? 'موثق ✓' : 'غير مكتمل' }}
            </span>
        </a>

        <a href="{{ route('affiliate.payout-settings.show') }}" class="bg-white p-6 rounded-2xl border border-slate-200 hover:border-indigo-400 transition shadow-sm flex items-center justify-between group">
            <div class="flex items-center space-x-4 space-x-reverse">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl font-bold">
                    💳
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 group-hover:text-indigo-600 text-sm">بيانات استلام الأرباح</h4>
                    <p class="text-xs text-slate-500 mt-0.5">محفظة فودافون كاش، إنستاباي، أو الحساب البنكي</p>
                </div>
            </div>
            <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-100 text-slate-700">
                {{ $affiliate->payout_method ? 'مضبوطة' : 'لم تُحدد' }}
            </span>
        </a>
    </div>

</div>
@endsection

