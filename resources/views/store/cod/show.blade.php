<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('تفاصيل كشف التسوية #') }}{{ $remittance->reference }} {{ __('- لوحة المتجر') }}</title>
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

        <!-- Navigation & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('store.cod.index') }}" class="p-2.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 rounded-xl transition shadow-sm text-sm">
                    {{ __('← العودة للكشوفات') }}
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-black text-slate-900 font-mono">{{ $remittance->reference }}</h1>
                        @php
                            $color = $remittance->status->badgeColor();
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black bg-{{ $color }}-100 text-{{ $color }}-800 border border-{{ $color }}-200">
                            {{ $remittance->status->label() }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ __('شركة الشحن:') }} <span class="font-bold text-slate-700">{{ $remittance->courierAccount?->name }}</span>
                        {{ __('• تم الإنشاء:') }} {{ $remittance->created_at->format('Y-m-d H:i') }}
                    </p>
                </div>
            </div>
            @if($remittance->status->isSettled())
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs font-bold text-emerald-800 flex items-center gap-2">
                    <span>✓</span>
                    <span>{{ __('تمت التسوية البنكية والترحيل المحاسبي بتاريخ') }} {{ $remittance->settled_at?->format('Y-m-d H:i') }}</span>
                </div>
            @endif
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

        <!-- Financial Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-slate-500 block mb-1">{{ __('المحصل الفعلي (COD)') }}</span>
                <span class="text-2xl font-black text-slate-900">{{ number_format($remittance->total_collected_minor / 100, 2) }} <span class="text-xs font-normal">{{ \App\Support\Money::symbol() }}</span></span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-rose-600 block mb-1">{{ __('خصومات مصاريف الشحن') }}</span>
                <span class="text-2xl font-black text-rose-600">-{{ number_format($remittance->total_shipping_fees_minor / 100, 2) }} <span class="text-xs font-normal">{{ \App\Support\Money::symbol() }}</span></span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-emerald-600 block mb-1">{{ __('صافي المبلغ المورد') }}</span>
                <span class="text-2xl font-black text-emerald-600">{{ number_format($remittance->net_payout_minor / 100, 2) }} <span class="text-xs font-normal">{{ \App\Support\Money::symbol() }}</span></span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="text-xs font-bold text-slate-500 block mb-1">{{ __('حالة المطابقة') }}</span>
                <div class="text-sm font-bold mt-1">
                    <span class="text-emerald-700 font-black text-lg">{{ $remittance->matched_orders_count }}</span> {{ __('مطابق') }}
                    @if($remittance->discrepancy_orders_count > 0)
                        <span class="text-amber-700 mr-2"><span class="font-black text-lg">{{ $remittance->discrepancy_orders_count }}</span> {{ __('فروقات') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Settlement Action Card (If not yet settled) -->
        @if(! $remittance->status->isSettled() && $remittance->status !== \App\Domain\Finance\Enums\CodRemittanceStatus::CANCELLED)
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 p-6 rounded-3xl shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-black text-emerald-950 flex items-center gap-2">
                            <span>🏦</span>
                            <span>{{ __('تأكيد استلام التحويل البنكي وتوريد الكشف') }}</span>
                        </h3>
                        <p class="text-xs text-emerald-800 mt-1 max-w-2xl leading-relaxed">
                            {{ __('عند تأكيد التسوية: سيتم نقل الطلبات المطابقة إلى حالة') }} <strong>{{ __('تمت التسوية (Settled)') }}</strong>{{ __('، فك حجز عمولات المسوقين ونقلها للرصيد المتاح، وترحيل القيد المحاسبي المزدوج (خصم حساب وسيط التحصيل COD وإيداع الصافي بحساب البنك مع تسجيل مصروف الشحن).') }}
                        </p>
                    </div>

                    <form action="{{ route('store.cod.settle', $remittance) }}" method="POST" class="flex flex-col sm:flex-row items-center gap-2">
                        @csrf
                        <input type="text" name="bank_reference" placeholder="{{ __('رقم الحوالة البنكية / التحويل') }}" required class="text-xs font-mono px-3.5 py-2.5 bg-white border border-emerald-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none w-full sm:w-56">
                        <button type="submit" onclick="return confirm(@js(__('هل أنت متأكد من تأكيد تسوية هذا الكشف وترحيل القيود المالية؟')))" class="w-full sm:w-auto px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm whitespace-nowrap">
                            {{ __('تأكيد التوريد والتسوية') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <!-- Remittance Items List -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-black text-slate-900 text-sm">{{ __('بيانات الشحنات المضمنة بالكشف (') }}{{ $remittance->items->count() }})</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ __('تفاصيل مطابقة المبالغ المحصلة ومصاريف الشحن لكل بوليصة') }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50/75 border-b border-slate-200 text-slate-500 font-bold">
                        <tr>
                            <th class="px-4 py-3.5">{{ __('رقم البوليصة / التتبع') }}</th>
                            <th class="px-4 py-3.5">{{ __('الطلب') }}</th>
                            <th class="px-4 py-3.5">{{ __('المحصل المبلغ (الوارد)') }}</th>
                            <th class="px-4 py-3.5">{{ __('المتوقع بالنظام') }}</th>
                            <th class="px-4 py-3.5">{{ __('فارق التحصيل') }}</th>
                            <th class="px-4 py-3.5">{{ __('مصاريف الشحن') }}</th>
                            <th class="px-4 py-3.5">{{ __('حالة المطابقة') }}</th>
                            <th class="px-4 py-3.5">{{ __('ملاحظات') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($remittance->items as $item)
                            <tr class="hover:bg-slate-50/60 transition {{ $item->match_status->isDiscrepancy() ? 'bg-amber-50/30' : '' }}">
                                <td class="px-4 py-4 font-mono font-bold text-slate-900">
                                    {{ $item->tracking_number }}
                                </td>
                                <td class="px-4 py-4 font-bold">
                                    @if($item->order)
                                        <span class="text-blue-600 font-mono">#{{ $item->order->reference ?? $item->order->id }}</span>
                                    @else
                                        <span class="text-slate-400">{{ __('غير مرتبط') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-black text-slate-900">
                                    {{ \App\Support\Money::show($item->reported_cod_minor) }}
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    {{ \App\Support\Money::show($item->expected_cod_minor) }}
                                </td>
                                <td class="px-4 py-4 font-bold {{ $item->cod_discrepancy_minor < 0 ? 'text-rose-600' : ($item->cod_discrepancy_minor > 0 ? 'text-blue-600' : 'text-slate-400') }}">
                                    @if($item->cod_discrepancy_minor !== 0)
                                        {{ ($item->cod_discrepancy_minor > 0 ? '+' : '') . number_format($item->cod_discrepancy_minor / 100, 2) }} {{ \App\Support\Money::symbol() }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-semibold text-slate-700">
                                    {{ \App\Support\Money::show($item->shipping_fee_minor) }}
                                </td>
                                <td class="px-4 py-4">
                                    @if($item->match_status === \App\Domain\Finance\Enums\RemittanceItemMatchStatus::MATCHED)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            {{ __('✓ مطابق') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-200">
                                            ⚠️ {{ $item->match_status->label() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-slate-500 max-w-xs truncate" title="{{ $item->notes }}">
                                    {{ $item->notes ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">
                                    {{ __('لا توجد شحنات مسجلة بهذا الكشف.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>

