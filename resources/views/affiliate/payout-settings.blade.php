@extends('layouts.affiliate')

@section('title', __('بيانات استلام الأرباح'))

@section('content')
<div class="max-w-2xl mx-auto py-6">
    <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-md">
        <div class="border-b border-slate-100 pb-5 mb-6">
            <h1 class="text-xl font-extrabold text-slate-900">{{ __('بيانات استلام الأرباح والسحب') }}</h1>
            <p class="text-xs text-slate-500 mt-1">{{ __('اختر الطريقة المفضلة لتحويل عمولاتك عند طلب السحب.') }}</p>
        </div>

        @if($affiliate->isPayoutFrozen())
            <div class="p-4 mb-6 text-sm text-rose-800 bg-rose-50 rounded-2xl border border-rose-200 flex items-start space-x-3 space-x-reverse">
                <span class="text-xl ml-2">🔒</span>
                <div>
                    <span class="font-bold block">{{ __('عمليات السحب مجمدة مؤقتاً لحماية حسابك:') }}</span>
                    <span class="text-xs mt-1 block">
                        {{ __('ينتهي التجميد الأمني في') }} <span class="font-bold font-mono">{{ $affiliate->payouts_frozen_until?->format('d/m/Y H:i') }}</span> {{ __('(بعد 24 ساعة من آخر تعديل).') }}
                    </span>
                </div>
            </div>
        @endif

        <div class="p-4 mb-6 bg-amber-50 rounded-2xl border border-amber-200 text-xs text-amber-900 space-y-1">
            <span class="font-bold block text-amber-950">{{ __('تنبيه أمان مهم:') }}</span>
            <p>{{ __('• تعديل بيانات الدفع يتطلب رمز تحقق (OTP) يُرسل إلى هاتفك المسجل.') }}</p>
            <p>{{ __('• بعد حفظ البيانات الجديدة، يتم تلقائياً تجميد السحب لمدة 24 ساعة لمنع أي محاولة سحب غير مصرح بها في حال اختراق الحساب.') }}</p>
        </div>

        <!-- Request OTP button -->
        <div class="mb-6 p-4 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-800 block">{{ __('الخطوة 1: طلب رمز التحقق') }}</span>
                <span class="text-[11px] text-slate-500 block">{{ __('أرسل رمز OTP إلى هاتفك (') }}{{ substr($affiliate->phone ?? '', 0, 4) . '****' . substr($affiliate->phone ?? '', -3) }})</span>
            </div>
            <form method="POST" action="{{ route('affiliate.payout-settings.request-otp') }}">
                @csrf
                <button type="submit" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl shadow-sm">
                    {{ __('إرسال رمز OTP') }}
                </button>
            </form>
        </div>

        <!-- Update Form -->
        <form method="POST" action="{{ route('affiliate.payout-settings.update') }}" class="space-y-6" x-data="{ method: '{{ old('payout_method', $affiliate->payout_method ?? 'wallet_phone') }}' }">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">{{ __('اختر طريقة الدفع *') }}</label>
                <div class="grid grid-cols-3 gap-3">
                    <label class="border p-3 rounded-2xl cursor-pointer text-center transition flex flex-col items-center justify-center"
                           :class="method === 'wallet_phone' ? 'border-indigo-600 bg-indigo-50/50 text-indigo-900 font-bold ring-2 ring-indigo-500' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                        <input type="radio" name="payout_method" value="wallet_phone" class="hidden" x-model="method">
                        <span class="text-xl mb-1">📱</span>
                        <span class="text-xs">{{ __('محفظة إلكترونية') }}</span>
                    </label>

                    <label class="border p-3 rounded-2xl cursor-pointer text-center transition flex flex-col items-center justify-center"
                           :class="method === 'instapay' ? 'border-indigo-600 bg-indigo-50/50 text-indigo-900 font-bold ring-2 ring-indigo-500' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                        <input type="radio" name="payout_method" value="instapay" class="hidden" x-model="method">
                        <span class="text-xl mb-1">⚡</span>
                        <span class="text-xs">{{ __('إنستاباي (InstaPay)') }}</span>
                    </label>

                    <label class="border p-3 rounded-2xl cursor-pointer text-center transition flex flex-col items-center justify-center"
                           :class="method === 'bank_account' ? 'border-indigo-600 bg-indigo-50/50 text-indigo-900 font-bold ring-2 ring-indigo-500' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                        <input type="radio" name="payout_method" value="bank_account" class="hidden" x-model="method">
                        <span class="text-xl mb-1">🏦</span>
                        <span class="text-xs">{{ __('حساب بنكي') }}</span>
                    </label>
                </div>
            </div>

            <!-- Fields for Wallet -->
            <div x-show="method === 'wallet_phone'" class="space-y-4 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('مزود المحفظة') }}</label>
                    <select name="wallet_provider" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs">
                        <option value="vodafone_cash">{{ __('فودافون كاش (Vodafone Cash)') }}</option>
                        <option value="orange_cash">{{ __('أورنج كاش (Orange Cash)') }}</option>
                        <option value="etisalat_cash">{{ __('إي آند كاش (e& Cash)') }}</option>
                        <option value="we_pay">{{ __('وي باي (WE Pay)') }}</option>
                        <option value="smart_wallet">{{ __('محفظة بنكية ذكية') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('رقم هاتف المحفظة') }}</label>
                    <input type="text" name="wallet_phone" dir="ltr"
                           value="{{ old('wallet_phone', $affiliate->payout_details['phone'] ?? '') }}"
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs font-mono text-right"
                           placeholder="{{ \App\Support\Location\Market::country()->example }}">
                </div>
            </div>

            <!-- Fields for InstaPay -->
            <div x-show="method === 'instapay'" class="space-y-4 p-4 bg-slate-50 rounded-2xl border border-slate-200" style="display: none;">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('عنوان الدفع اللحظي (IPA / Handle)') }}</label>
                    <input type="text" name="instapay_handle" dir="ltr"
                           value="{{ old('instapay_handle', $affiliate->payout_details['instapay_handle'] ?? '') }}"
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs font-mono text-right"
                           placeholder="username@instapay">
                </div>
            </div>

            <!-- Fields for Bank -->
            <div x-show="method === 'bank_account'" class="space-y-4 p-4 bg-slate-50 rounded-2xl border border-slate-200" style="display: none;">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('اسم البنك') }}</label>
                        <input type="text" name="bank_name"
                               value="{{ old('bank_name', $affiliate->payout_details['bank_name'] ?? '') }}"
                               class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs"
                               placeholder="{{ __('البنك الأهلي المصري') }}">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('اسم صاحب الحساب') }}</label>
                        <input type="text" name="account_holder_name"
                               value="{{ old('account_holder_name', $affiliate->payout_details['account_holder_name'] ?? '') }}"
                               class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs"
                               placeholder="{{ __('مطابق للبطاقة') }}">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">{{ __('رقم الحساب الدولي (IBAN)') }}</label>
                    <input type="text" name="iban" dir="ltr"
                           value="{{ old('iban', $affiliate->payout_details['iban'] ?? '') }}"
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-xs font-mono text-right"
                           placeholder="EG000000000000000000000000000">
                </div>
            </div>

            <!-- Step 2: Confirm with OTP -->
            <div class="border-t border-slate-200 pt-4">
                <label for="otp_code" class="block text-sm font-bold text-slate-800 mb-1">{{ __('الخطوة 2: أدخل رمز التحقق (OTP) لحفظ التعديلات *') }}</label>
                <input type="text" name="otp_code" id="otp_code" maxlength="6" required dir="ltr"
                       class="w-48 text-center tracking-widest text-xl font-mono px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="123456">
                @error('otp_code') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-md transition duration-200">
                {{ __('حفظ بيانات الدفع وتفعيل التجميد الأمني (24 ساعة)') }}
            </button>
        </form>
    </div>
</div>
@endsection

