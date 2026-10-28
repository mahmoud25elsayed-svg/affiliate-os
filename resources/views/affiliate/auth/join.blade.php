@extends('layouts.affiliate')

@section('title', 'انضم إلى برنامج المسوقين')

@section('content')
<div class="max-w-4xl mx-auto py-8">
    <div class="text-center mb-10">
        <span class="inline-block px-3 py-1 bg-indigo-100 text-indigo-700 text-xs font-extrabold rounded-full mb-3">
            فرصة عمل حر وتحقيق أرباح
        </span>
        <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight">
            انضم لبرنامج المسوقين بالعمولة لمتجر <span class="text-indigo-600">{{ $storeName }}</span>
        </h1>
        <p class="mt-3 text-slate-600 max-w-2xl mx-auto text-base">
            سوّق منتجاتنا الأصلية واحصل على أرباح مجزية مع سحب فوري لأرباحك عبر فودافون كاش، إنستاباي، أو حسابك البنكي.
        </p>
    </div>

    <!-- Feature Highlights -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm text-center">
            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mx-auto mb-4 font-bold text-xl">
                💰
            </div>
            <h3 class="font-bold text-slate-900 mb-2">أرباح غير محدودة</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                @if($settings->isMarkupModel())
                    حدد سعر البيع وهامش ربحك بحرية لكل منتج وفق قدرتك التسويقية.
                @else
                    عمولة مجزية تبدأ من {{ $settings->default_commission_rate }}% على كل طلب مؤكد.
                @endif
            </p>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm text-center">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mx-auto mb-4 font-bold text-xl">
                ⚡
            </div>
            <h3 class="font-bold text-slate-900 mb-2">سحب أرباح مرن</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                سحب متاح بحد أدنى {{ number_format($settings->min_payout_minor / 100, 2) }} ج.م، وتحويل عبر المحافظ الإلكترونية وإنستاباي.
            </p>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm text-center">
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center mx-auto mb-4 font-bold text-xl">
                📊
            </div>
            <h3 class="font-bold text-slate-900 mb-2">لوحة متابعة فورية</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                تابع حالة طلباتك، الشحن، التسليم، ورصيد محفظتك لحظة بلحظة وبشفافية تامة.
            </p>
        </div>
    </div>

    <!-- Registration Form -->
    <div class="max-w-xl mx-auto bg-white p-8 rounded-3xl border border-slate-200 shadow-lg">
        <h2 class="text-2xl font-bold text-slate-900 mb-6 text-center">إنشاء حساب مسوق جديد</h2>

        <form method="POST" action="{{ route('affiliate.register') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="block text-sm font-semibold text-slate-700 mb-1">الاسم بالكامل *</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                       class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                       placeholder="مثال: أحمد محمود إبراهيم">
                @error('name') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-semibold text-slate-700 mb-1">رقم الهاتف المحمول (مصر) *</label>
                <input type="text" name="phone" id="phone" value="{{ old('phone') }}" required dir="ltr"
                       class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono text-right"
                       placeholder="01012345678">
                <span class="text-xs text-slate-400 mt-1 block">سنرسل رمز تحقق (OTP) في رسالة نصية قصيرة لتأكيد الرقم.</span>
                @error('phone') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">البريد الإلكتروني (اختياري)</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" dir="ltr"
                       class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-right"
                       placeholder="name@example.com">
                @error('email') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">كلمة المرور *</label>
                    <input type="password" name="password" id="password" required
                           class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                           placeholder="••••••••">
                    @error('password') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-1">تأكيد كلمة المرور *</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                           class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                           placeholder="••••••••">
                </div>
            </div>

            <div>
                <label for="referral_code" class="block text-sm font-semibold text-slate-700 mb-1">كود الإحالة (إن وجد)</label>
                <input type="text" name="referral_code" id="referral_code" value="{{ old('referral_code', $referralCode) }}" dir="ltr"
                       class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono text-right"
                       placeholder="AFF-XXXXXX">
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-md transition duration-200 mt-2">
                تسجيل الحساب وتأكيد الهاتف
            </button>
        </form>

        <div class="mt-6 text-center border-t border-slate-100 pt-6">
            <span class="text-sm text-slate-500">لديك حساب مسوق بالفعل؟</span>
            <a href="{{ route('affiliate.login') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 mr-1">
                تسجيل الدخول هنا
            </a>
        </div>
    </div>
</div>
@endsection

