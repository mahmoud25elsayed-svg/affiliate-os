<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقارير أداء الكول سنتر وعينات الجودة (QA) - لوحة المتجر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Top Header & Navigation -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 bg-indigo-600 text-white rounded-2xl shadow-sm text-lg font-black">📊</span>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">تقارير أداء الكول سنتر وعينات الجودة (QA)</h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            متابعة معدلات التأكيد اللحظية، متوسط زمن المكالمة، وسجل التدقيق لعينات المكالمات المسجلة
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2 space-x-reverse">
                <a href="{{ route('store.call-center.index') }}" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                    🎧 العودة لمكتب الاتصال
                </a>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">إجمالي المكالمات</div>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ number_format($totalAttempts) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">طلبات مؤكدة</div>
                <div class="text-2xl font-black text-emerald-600 mt-1">{{ number_format($confirmedCount) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">طلبات ملغاة</div>
                <div class="text-2xl font-black text-rose-600 mt-1">{{ number_format($cancelledCount) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">تعذر الوصول (استنفاد)</div>
                <div class="text-2xl font-black text-amber-600 mt-1">{{ number_format($unreachableCount) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">معدل التأكيد (Confirmation %)</div>
                <div class="text-2xl font-black text-indigo-600 mt-1">{{ $confirmationRate }}%</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">متوسط زمن المكالمة</div>
                <div class="text-2xl font-black text-slate-800 mt-1">
                    {{ floor($avgDuration / 60) }}د {{ $avgDuration % 60 }}ث
                </div>
            </div>
        </div>

        <!-- QA Sampling & Recent Calls Log -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-black text-slate-900">سجل عينات تدقيق الجودة (QA Sampling)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">آخر 20 مكالمة مسجلة لمراجعة الجودة وملاحظات العملاء</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200/80">
                        <tr>
                            <th class="py-3 px-4">رقم الطلب</th>
                            <th class="py-3 px-4">الموظف (Agent)</th>
                            <th class="py-3 px-4">المحاولة</th>
                            <th class="py-3 px-4">النتيجة</th>
                            <th class="py-3 px-4">المدة</th>
                            <th class="py-3 px-4">التاريخ والوقت</th>
                            <th class="py-3 px-4">الملاحظات</th>
                            <th class="py-3 px-4">التسجيل الصوتي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($qaCalls as $call)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-3.5 px-4 font-black text-indigo-600">
                                    #{{ $call->order_id }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    {{ $call->agent?->name ?? 'غير محدد' }}
                                </td>
                                <td class="py-3.5 px-4">
                                    محاولة {{ $call->attempt_number }}
                                </td>
                                <td class="py-3.5 px-4">
                                    @php
                                        $badgeColor = match($call->outcome) {
                                            'confirmed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'customer_cancelled', 'wrong_number' => 'bg-rose-50 text-rose-700 border-rose-200',
                                            'postponed' => 'bg-blue-50 text-blue-700 border-blue-200',
                                            default => 'bg-amber-50 text-amber-700 border-amber-200',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-black border {{ $badgeColor }}">
                                        {{ $call->getOutcomeEnum()->label() }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    {{ $call->duration_seconds ? "{$call->duration_seconds} ثانية" : '-' }}
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                    {{ $call->created_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="py-3.5 px-4 max-w-xs truncate text-slate-600">
                                    {{ $call->notes ?: '-' }}
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($call->recording_url)
                                        <a href="{{ $call->recording_url }}" target="_blank" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-[11px] font-bold border border-indigo-200 transition inline-flex items-center gap-1">
                                            <span>▶️</span>
                                            <span>استماع</span>
                                        </a>
                                    @else
                                        <span class="text-slate-400 text-[11px]">غير متوفر</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-400 font-bold">
                                    لا توجد مكالمات مسجلة حتى الآن.
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

