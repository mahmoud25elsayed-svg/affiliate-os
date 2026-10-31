@extends('layouts.affiliate')

@section('title', __('تأكيد رقم الهاتف - OTP'))

@section('content')
<div class="max-w-md mx-auto py-12">
    <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-xl text-center">
        <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 text-2xl font-bold">
            📱
        </div>

        <h1 class="text-2xl font-extrabold text-slate-900 mb-2">{{ __('تأكيد رقم الهاتف') }}</h1>
        <p class="text-xs text-slate-500 mb-6">
            {{ __('تم إرسال رمز تحقق مكون من 6 أرقام في رسالة نصية قصيرة (SMS) إلى هاتفك:') }}
            <span class="font-bold text-slate-800 font-mono block mt-1" dir="ltr">{{ $affiliate?->phone ?? __('هاتفك المسجل') }}</span>
        </p>

        <form method="POST" action="{{ route('affiliate.verify-otp.submit') }}" class="space-y-6">
            @csrf

            <div>
                <label for="code" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('أدخل رمز التحقق (OTP)') }}</label>
                <input type="text" name="code" id="code" maxlength="6" autofocus required dir="ltr"
                       class="w-48 mx-auto text-center tracking-widest text-2xl font-mono px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="123456">
                @error('code') <span class="text-xs text-rose-600 mt-2 block">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-md transition duration-200">
                {{ __('تأكيد وتفعيل الحساب') }}
            </button>
        </form>
    </div>
</div>
@endsection

