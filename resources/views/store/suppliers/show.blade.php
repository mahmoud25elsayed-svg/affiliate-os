<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل المورد: {{ $supplier->name }} - لوحة الإدارة</title>
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

        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3 space-x-reverse">
                <a href="{{ route('store.suppliers.index') }}" class="p-2 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 text-slate-600 transition">
                    ←
                </a>
                <div>
                    <h1 class="text-2xl font-black text-slate-900">{{ $supplier->name }}</h1>
                    <span class="text-xs text-slate-500 font-mono">{{ $supplier->company_name ?? 'مورد مستقل' }} • هاتف: {{ $supplier->phone }}</span>
                </div>
            </div>
            <div>
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $supplier->isActive() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $supplier->isActive() ? 'حساب نشط' : 'معلق' }}
                </span>
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

        <!-- Top Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Payable Balance -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold text-slate-400 block mb-1">رصيد مستحقات المورد في الدفتر</span>
                <div class="flex items-baseline space-x-2 space-x-reverse">
                    <span class="text-2xl font-black font-mono {{ $payableBalanceMinor < 0 ? 'text-rose-600' : 'text-slate-900' }}">
                        {{ number_format(abs($payableBalanceMinor) / 100, 2) }}
                    </span>
                    <span class="text-xs font-bold text-slate-400">{{ \App\Support\Money::symbol() }} (دائن)</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-2 block pt-2 border-t border-slate-100">
                    الحساب المالي: <code class="font-mono text-indigo-600">supplier:{{ $supplier->id }}:payable</code>
                </span>
            </div>

            <!-- Batches Count -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold text-slate-400 block mb-1">إجمالي أذون التوريد</span>
                <span class="text-2xl font-black text-slate-900 font-mono">{{ $supplier->batches->count() }}</span>
                <span class="text-[11px] text-slate-400 mt-2 block pt-2 border-t border-slate-100">
                    متوسط زمن التسليم: {{ $supplier->default_lead_time_days }} أيام
                </span>
            </div>

            <!-- Products Supplied -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold text-slate-400 block mb-1">المنتجات المرتبطة</span>
                <span class="text-2xl font-black text-slate-900 font-mono">{{ $supplier->wholesalePrices->count() }}</span>
                <span class="text-[11px] text-slate-400 mt-2 block pt-2 border-t border-slate-100">
                    منتجات بسعر الجملة
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Record New Supply Batch Form -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center space-x-2 space-x-reverse">
                    <span>📥</span>
                    <span>تسجيل إذن وارد توريدات جديد</span>
                </h2>

                <form action="{{ route('store.suppliers.batches.store', $supplier->id) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">المنتج والمتغير (Variant) *</label>
                        <select name="product_variant_id" required class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50 font-sans">
                            <option value="">-- اختر المتغير المطلوب توريده --</option>
                            @foreach($variants as $variant)
                                <option value="{{ $variant->id }}">
                                    {{ $variant->product?->translateAttribute('name') ?? 'منتج' }} (SKU: {{ $variant->sku }}) - مخزون حالي: {{ $variant->stock ?? 0 }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">الكمية الموردة (قطع) *</label>
                        <input type="number" name="quantity_supplied" min="1" required class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50 font-mono" placeholder="50">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">تكلفة الوحدة من المورد ({{ \App\Support\Money::symbol() }}) *</label>
                        <input type="number" step="0.01" name="unit_cost" min="0.01" required class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50 font-mono" placeholder="120.00">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">ملاحظات إذن الاستلام</label>
                        <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 p-2.5 bg-slate-50" placeholder="رقم فاتورة المورد، حالة الكراتين..."></textarea>
                    </div>

                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-800 space-y-1">
                        <span class="font-bold block">ملاحظة القيد المالي:</span>
                        <span>سيتم زيادة مخزون المنتج آلياً، وترحيل قيد مزدوج متوازن: مدين مخزون البضاعة ودائن مستحقات المورد.</span>
                    </div>

                    <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow-sm">
                        تأكيد استلام الوارد وقيد المخزون
                    </button>
                </form>
            </div>

            <!-- Batches History -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-800 flex items-center space-x-2 space-x-reverse">
                        <span>📜</span>
                        <span>سجل أذون الوارد والتوريدات المخزنية</span>
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                            <tr>
                                <th class="p-4">رقم الإذن</th>
                                <th class="p-4">المنتج / المتغير</th>
                                <th class="p-4 text-center">الكمية</th>
                                <th class="p-4 text-center">سعر الوحدة</th>
                                <th class="p-4 text-center">الإجمالي</th>
                                <th class="p-4 text-center">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($supplier->batches as $batch)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="p-4 font-mono font-bold text-slate-900">{{ $batch->batch_number }}</td>
                                    <td class="p-4">
                                        <span class="font-bold block">{{ $batch->variant?->product?->translateAttribute('name') ?? 'منتج' }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ $batch->variant?->sku }}</span>
                                    </td>
                                    <td class="p-4 text-center font-bold font-mono text-indigo-600">+{{ $batch->quantity_supplied }}</td>
                                    <td class="p-4 text-center font-mono">{{ \App\Support\Money::show($batch->unit_cost_minor) }}</td>
                                    <td class="p-4 text-center font-mono font-bold text-slate-900">{{ \App\Support\Money::show($batch->total_cost_minor) }}</td>
                                    <td class="p-4 text-center font-mono text-slate-500">{{ $batch->received_at?->format('Y-m-d H:i') ?? $batch->created_at?->format('Y-m-d') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400">
                                        لا توجد أذون توريد مسجلة لهذا المورد بعد.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</body>
</html>
