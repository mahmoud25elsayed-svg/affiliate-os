@extends('layouts.affiliate')

@section('title', __('تسجيل دخول المسوقين'))

@section('content')
<div class="max-w-md mx-auto py-12">
    <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-xl">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-extrabold text-slate-900">{{ __('تسجيل دخول المسوق') }}</h1>
            <p class="text-xs text-slate-500 mt-1">{{ __('أهلاً بك مجدداً في برنامج مسوقي') }} {{ $storeName }}</p>
        </div>

        <form method="POST" action="{{ route('affiliate.login.submit') }}" class="space-y-5">
            @csrf

            <div>
                <label for="login" class="block text-sm font-semibold text-slate-700 mb-1">{{ __('رقم الهاتف أو البريد الإلكتروني') }}</label>
                <input type="text" name="login" id="login" value="{{ old('login') }}" required dir="ltr"
                       class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-right"
                       placeholder="{{ \App\Support\Location\Market::country()->example }} {{ __('أو') }} name@example.com">
                @error('login') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">{{ __('كلمة المرور') }}</label>
                <input type="password" name="password" id="password" required
                       class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                       placeholder="••••••••">
                @error('password') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center space-x-2 space-x-reverse cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                    <span class="text-slate-600 text-xs">{{ __('تذكرني على هذا الجهاز') }}</span>
                </label>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-md transition duration-200">
                {{ __('تسجيل الدخول') }}
            </button>
        </form>

        <div class="mt-6 text-center border-t border-slate-100 pt-6">
            <span class="text-sm text-slate-500">{{ __('لست مسوقاً لدينا بعد؟') }}</span>
            <a href="{{ route('affiliate.join') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 mr-1">
                {{ __('انضم الآن مجاناً') }}
            </a>
        </div>
    </div>
</div>
@endsection

