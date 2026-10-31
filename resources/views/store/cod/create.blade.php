<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('استيراد كشف تسوية وتوريد تحصيلات - لوحة المتجر') }}</title>
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
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

        <!-- Navigation & Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('store.cod.index') }}" class="p-2.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-xl transition shadow-sm text-sm">
                    {{ __('← العودة للكشوفات') }}
                </a>
                <div>
                    <h1 class="text-xl font-black text-slate-900">{{ __('استيراد كشف تسوية جديد (COD Remittance)') }}</h1>
                    <p class="text-xs text-slate-500">{{ __('إدخال بيانات التحصيل الواردة من شركة الشحن لمطابقتها مع الطلبات') }}</p>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold space-y-1">
                @foreach($errors->all() as $error)
                    <p>⚠️ {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('store.cod.store') }}" method="POST" class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Courier Account -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">{{ __('شركة الشحن *') }}</label>
                    <select name="courier_account_id" required class="w-full text-xs font-semibold px-3.5 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none bg-white">
                        <option value="">{{ __('-- اختر شركة الشحن --') }}</option>
                        @foreach($courierAccounts as $account)
                            <option value="{{ $account->id }}" {{ old('courier_account_id') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }} ({{ strtoupper($account->driver) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Reference -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">{{ __('رقم مرجع الكشف / الفاتورة لدى شركة الشحن') }}</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" placeholder="{{ __('مثال: INV-2026-10-884') }}" class="w-full text-xs font-mono px-3.5 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">{{ __('ملاحظات داخلية') }}</label>
                <input type="text" name="notes" value="{{ old('notes') }}" placeholder="{{ __('مثال: كشف تحصيلات الأسبوع الرابع لشهر أكتوبر') }}" class="w-full text-xs px-3.5 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <!-- CSV / Rows Input -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700">{{ __('بيانات الشحنات (CSV / أسطر نصية) *') }}</label>
                    <span class="text-[11px] text-slate-400 font-mono">tracking_number,cod_amount,shipping_fee,status</span>
                </div>
                <textarea name="raw_csv" rows="10" required placeholder="MCK-12345678,350.00,45.00,delivered
MCK-87654321,290.00,45.00,delivered
BST-99887766,500.00,50.00,delivered" class="w-full text-xs font-mono px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:outline-none bg-slate-50/50 leading-relaxed"></textarea>
                <div class="mt-2 text-[11px] text-slate-500 bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-1">
                    <p class="font-bold text-slate-700">{{ __('📌 إرشادات التنسيق:') }}</p>
                    <p>{{ __('• كل سطر يحتوي على:') }} <code>{{ __('رقم_التتبع,المبلغ_المحصل,مصاريف_الشحن,الحالة') }}</code></p>
                    <p>{{ __('• المبالغ بعملة المتجر (مثال:') }} <code>350.00</code> {{ __('أو') }} <code>350</code>).</p>
                    <p>{{ __('• سيقوم النظام تلقائياً بربط أرقام التتبع بالشحنات المسجلة وحساب أي فروقات نقدية فور الحفظ.') }}</p>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('store.cod.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">
                    {{ __('إلغاء') }}
                </a>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                    {{ __('حفظ ومطابقة الكشف') }}
                </button>
            </div>
        </form>

    </div>
</body>
</html>

