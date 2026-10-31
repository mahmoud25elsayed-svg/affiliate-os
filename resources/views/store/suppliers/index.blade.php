<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('إدارة الموردين وتوريدات البضاعة - لوحة الإدارة') }}</title>
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

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>🏭</span>
                    <span>{{ __('إدارة الموردين وبوابة التوريدات (Suppliers Gateway)') }}</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    {{ __('تسجيل الموردين، متابعة التوريدات المخزنية، وربط أسعار الجملة ومستحقات الموردين بالدفتر المالي') }}
                </p>
            </div>
            <div>
                <a href="{{ route('store.kpis.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition inline-flex items-center space-x-2 space-x-reverse">
                    <span>📊</span>
                    <span>{{ __('لوحة مؤشرات الأداء (KPIs)') }}</span>
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center">
                <span class="ml-2 text-base">✓</span>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Supplier Registration Form -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center space-x-2 space-x-reverse">
                    <span>➕</span>
                    <span>{{ __('تسجيل مورد جديد') }}</span>
                </h2>

                <form action="{{ route('store.suppliers.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">{{ __('اسم المسؤول / المورد *') }}</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50" placeholder="{{ __('مثال: الحاج أحمد عبد الله') }}">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">{{ __('اسم الشركة / المعرض') }}</label>
                        <input type="text" name="company_name" value="{{ old('company_name') }}" class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50" placeholder="{{ __('مثال: مؤسسة النور للتجارة') }}">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">{{ __('رقم الهاتف *') }}</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" required class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50 font-mono" placeholder="{{ \App\Support\Location\Market::country()->example }}">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">{{ __('البريد الإلكتروني') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50 font-mono" placeholder="supplier@example.com">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">{{ __('متوسط زمن التوريد (أيام) *') }}</label>
                        <input type="number" name="default_lead_time_days" value="{{ old('default_lead_time_days', 2) }}" min="1" max="30" required class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50 font-mono">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">{{ __('العنوان / المخزن') }}</label>
                        <input type="text" name="address" value="{{ old('address') }}" class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50" placeholder="{{ __('العتبة - القاهرة') }}">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">{{ __('ملاحظات إضافية') }}</label>
                        <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow-sm">
                        {{ __('حفظ وتسجيل المورد') }}
                    </button>
                </form>
            </div>

            <!-- Suppliers List -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-800 flex items-center space-x-2 space-x-reverse">
                            <span>📋</span>
                            <span>{{ __('قائمة الموردين المسجلين (') }}{{ $suppliers->total() }})</span>
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                                <tr>
                                    <th class="p-4">{{ __('المورد / الشركة') }}</th>
                                    <th class="p-4">{{ __('الهاتف') }}</th>
                                    <th class="p-4 text-center">{{ __('أذون الوارد') }}</th>
                                    <th class="p-4 text-center">{{ __('المنتجات المرتبطة') }}</th>
                                    <th class="p-4 text-center">{{ __('الحالة') }}</th>
                                    <th class="p-4 text-center">{{ __('إجراءات') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @forelse($suppliers as $supplier)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="p-4">
                                            <span class="font-bold text-slate-900 block">{{ $supplier->name }}</span>
                                            @if($supplier->company_name)
                                                <span class="text-[11px] text-slate-400 block">{{ $supplier->company_name }}</span>
                                            @endif
                                        </td>
                                        <td class="p-4 font-mono text-slate-600">{{ $supplier->phone }}</td>
                                        <td class="p-4 text-center font-bold font-mono">{{ $supplier->batches_count }}</td>
                                        <td class="p-4 text-center font-bold font-mono">{{ $supplier->wholesale_prices_count }}</td>
                                        <td class="p-4 text-center">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $supplier->isActive() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $supplier->isActive() ? __('نشط') : __('معلق') }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <a href="{{ route('store.suppliers.show', $supplier->id) }}" class="px-3 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg font-bold text-xs">
                                                {{ __('التفاصيل والوارد ←') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-400">
                                            {{ __('لا يوجد موردون مسجلون حتى الآن. استخدم النموذج لإضافة موردك الأول.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($suppliers->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $suppliers->links() }}
                    </div>
                @endif
            </div>

        </div>

    </div>
</body>
</html>
