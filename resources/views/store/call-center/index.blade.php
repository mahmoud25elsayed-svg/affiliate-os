<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة الكول سنتر وتأكيد الطلبات - لوحة المتجر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6" x-data="{ showModifyModal: false }">

        <!-- Top Header & Navigation -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 bg-indigo-600 text-white rounded-2xl shadow-sm text-lg font-black">📞</span>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">مكتب تأكيد الطلبات والكول سنتر</h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            طابور ذكي مرتّب حسب الأولوية وتوقيت المعاودة مع قفل لمنع التزامن المزدوج
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2 space-x-reverse">
                <a href="{{ route('store.call-center.performance') }}" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                    📊 تقارير الأداء وعينات الجودة (QA)
                </a>
            </div>
        </div>

        <!-- Alerts -->
        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center shadow-sm">
                <span class="ml-2 text-base">✓</span>
                {{ session('success') }}
            </div>
        @endif
        @if(session('info'))
            <div class="p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-2xl text-xs font-bold flex items-center shadow-sm">
                <span class="ml-2 text-base">ℹ</span>
                {{ session('info') }}
            </div>
        @endif
        @if($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold shadow-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Real-Time Queue Metrics Bar -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">في انتظار التأكيد</div>
                <div class="text-2xl font-black text-amber-600 mt-1">{{ number_format($stats['pending_count'] ?? 0) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">مكالمات مستحقة الآن</div>
                <div class="text-2xl font-black text-rose-600 mt-1">{{ number_format($stats['due_count'] ?? 0) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">قيد الاتصال حالياً</div>
                <div class="text-2xl font-black text-indigo-600 mt-1">{{ number_format($stats['locked_count'] ?? 0) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">مؤكد اليوم</div>
                <div class="text-2xl font-black text-emerald-600 mt-1">{{ number_format($stats['today_confirmed'] ?? 0) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">ملغي اليوم</div>
                <div class="text-2xl font-black text-slate-600 mt-1">{{ number_format($stats['today_cancelled'] ?? 0) }}</div>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="text-[11px] font-bold text-slate-500">إجمالي مكالمات اليوم</div>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ number_format($stats['today_attempts'] ?? 0) }}</div>
            </div>
        </div>

        @if($activeOrder)
            <!-- ACTIVE CALL WORKSPACE -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

                <!-- Left Column: Customer & Order Details (2 cols) -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Customer Profile & Direct Contact Card -->
                    @php
                        $address = $activeOrder->shippingAddress()->first();
                        $phone = $address?->contact_phone ?? $activeOrder->customer?->phone ?? '';
                        $cleanPhone = preg_replace('/[^0-9]/', '', (string)$phone);
                        $snapshot = is_array($activeOrder->price_snapshot) 
                            ? $activeOrder->price_snapshot 
                            : json_decode((string)$activeOrder->price_snapshot, true) ?? [];
                    @endphp
                    <div class="bg-white rounded-3xl border-2 border-indigo-200 shadow-md p-6 space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 gap-3">
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-black rounded-lg border border-indigo-100">
                                    طلب #{{ $activeOrder->id }}
                                </span>
                                <span class="text-xs text-slate-400">مرجع: {{ $activeOrder->reference }}</span>
                                <span class="px-2 py-0.5 bg-amber-50 text-amber-700 text-[11px] font-bold rounded-md">
                                    محاولة رقم {{ ((int)$activeOrder->call_attempts_count) + 1 }}
                                </span>
                            </div>

                            <!-- Release Lock Button -->
                            <form method="POST" action="{{ route('store.call-center.release', $activeOrder) }}" onsubmit="return confirm('هل تريد إرجاع الطلب إلى الطابور وتحرير القفل؟');">
                                @csrf
                                <button type="submit" class="text-xs font-bold text-slate-500 hover:text-rose-600 transition flex items-center gap-1">
                                    <span>🔓 تحرير الحجز والعودة</span>
                                </button>
                            </form>
                        </div>

                        <!-- Customer Info & Calling CTA -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <div class="text-[11px] font-bold text-slate-400">اسم العميل</div>
                                <div class="text-lg font-black text-slate-900">
                                    {{ $address?->first_name ?? 'عميل غير مسجل' }} {{ $address?->last_name ?? '' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    📍 {{ $address?->city ?? 'المدينة' }}، {{ $address?->line_one ?? 'العنوان' }}
                                </div>
                            </div>

                            <!-- Click to Call & WhatsApp -->
                            <div class="flex flex-col justify-center space-y-2">
                                <div class="text-[11px] font-bold text-slate-400">رقم الهاتف والتواصل المباشر</div>
                                <div class="flex items-center gap-2">
                                    <a href="tel:{{ $phone }}" class="flex-1 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black text-center transition shadow-sm flex items-center justify-center gap-2">
                                        <span>📞</span>
                                        <span>اتصال: {{ $phone }}</span>
                                    </a>
                                    @if($cleanPhone)
                                        <a href="https://wa.me/2{{ $cleanPhone }}" target="_blank" class="px-3 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-black transition flex items-center gap-1" title="محادثة واتساب">
                                            <span>💬 واتساب</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Line Items & Total Price Summary -->
                        <div class="pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-bold text-slate-700">محتويات الشحنة والطلب</h3>
                                <button type="button" @click="showModifyModal = true" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition">
                                    ✏️ تعديل تفاصيل الطلب أو العنوان
                                </button>
                            </div>

                            <div class="space-y-2">
                                @foreach($activeOrder->lines as $line)
                                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-900">{{ $line->description }}</span>
                                            <span class="px-2 py-0.5 bg-white border border-slate-200 rounded text-[11px] text-slate-600 font-bold">
                                                الكمية: {{ $line->quantity }}
                                            </span>
                                        </div>
                                        <div class="font-black text-slate-900">
                                            {{ number_format(($line->sub_total?->value ?? (int)$line->sub_total) / 100, 2) }} ج.م
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Financial Breakdown -->
                            <div class="mt-4 p-4 bg-slate-50/60 rounded-2xl border border-slate-100 flex flex-wrap items-center justify-between text-xs gap-3">
                                <div>
                                    <span class="text-slate-500">إجمالي المطلوب تحصيله (COD):</span>
                                    <span class="text-base font-black text-emerald-700 mr-1">
                                        {{ number_format(($activeOrder->total?->value ?? (int)$activeOrder->total) / 100, 2) }} ج.م
                                    </span>
                                </div>
                                @if(isset($snapshot['affiliate_profit_minor']))
                                    <div class="text-indigo-900 font-bold bg-indigo-50 px-3 py-1 rounded-lg border border-indigo-100">
                                        عمولة المسوق: {{ number_format(((int)$snapshot['affiliate_profit_minor']) / 100, 2) }} ج.م
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Previous Call Attempts on this order -->
                        @if(count($recentAttempts) > 0)
                            <div class="pt-4 border-t border-slate-100">
                                <h4 class="text-xs font-bold text-slate-700 mb-2">سجل المحاولات السابقة على هذا الطلب</h4>
                                <div class="space-y-2">
                                    @foreach($recentAttempts as $att)
                                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200 text-xs flex items-center justify-between">
                                            <div>
                                                <span class="font-bold text-slate-800">محاولة #{{ $att->attempt_number }}:</span>
                                                <span class="mr-1 text-slate-600">{{ $att->getOutcomeEnum()->label() }}</span>
                                                @if($att->notes)
                                                    <span class="text-slate-400 mr-2">— {{ $att->notes }}</span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-slate-400">
                                                {{ $att->created_at?->diffForHumans() }} (بواسطة: {{ $att->agent?->name ?? 'موظف' }})
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    </div>

                    <!-- Call Scripts (Accordion / Helper) -->
                    <div class="bg-white rounded-3xl border border-slate-200 p-6 space-y-4">
                        <h3 class="text-sm font-black text-slate-800 flex items-center gap-2">
                            <span>📜</span>
                            <span>السيناريو المقترح للمكالمة (Script)</span>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 space-y-1">
                                <div class="font-bold text-indigo-700">1. التحية والتأكيد:</div>
                                <div class="text-slate-600 leading-relaxed">{{ $settings->scripts['greeting'] ?? 'مرحباً بحضرتك أ/ ... مع حضرتك خدمة العملاء بخصوص تأكيد طلبك...' }}</div>
                            </div>
                            <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 space-y-1">
                                <div class="font-bold text-indigo-700">2. مراجعة العنوان والمنتج:</div>
                                <div class="text-slate-600 leading-relaxed">{{ $settings->scripts['order_review'] ?? 'بنأكد مع حضرتك تفاصيل الطلب: ... والعنوان: ... هل مناسب لحضرتك الاستلام خلال 48 ساعة؟' }}</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Record Call Outcome Form -->
                <div class="space-y-6">
                    <div class="bg-white rounded-3xl border-2 border-slate-200 p-6 space-y-6 shadow-sm sticky top-6" x-data="{ outcome: 'confirmed' }">
                        <div>
                            <h3 class="text-base font-black text-slate-900">تسجيل نتيجة المكالمة</h3>
                            <p class="text-xs text-slate-500 mt-1">اختر النتيجة المناسبة لتحديث حالة الطلب وتطبيق القواعد تلقائياً</p>
                        </div>

                        <form method="POST" action="{{ route('store.call-center.attempt', $activeOrder) }}" class="space-y-4">
                            @csrf

                            <!-- Outcome Radio Grid -->
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700">نتيجة الاتصال</label>
                                <div class="grid grid-cols-1 gap-2">
                                    @foreach($outcomes as $out)
                                        <label class="flex items-center p-3 rounded-xl border cursor-pointer transition text-xs font-bold"
                                            :class="outcome === '{{ $out->value }}' ? 'bg-indigo-50 border-indigo-400 text-indigo-900 shadow-sm' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'">
                                            <input type="radio" name="outcome" value="{{ $out->value }}" x-model="outcome" class="text-indigo-600 ml-2">
                                            <span>{{ $out->label() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Callback Date (Conditional for postponed) -->
                            <div class="space-y-1" x-show="outcome === 'postponed'" x-cloak>
                                <label class="text-xs font-bold text-slate-700">توقيت المعاودة المطلوب</label>
                                <input type="datetime-local" name="scheduled_callback_at" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500">
                                <p class="text-[11px] text-slate-400">سيظهر الطلب في صدارة الطابور عند حلول هذا الموعد</p>
                            </div>

                            <!-- Duration (Seconds) -->
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-slate-700">مدة المكالمة (بالثواني)</label>
                                <input type="number" name="duration_seconds" placeholder="مثال: 45" min="0" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <!-- Recording URL (QA) -->
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-slate-700">رابط تسجيل المكالمة (اختياري)</label>
                                <input type="url" name="recording_url" placeholder="https://..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <!-- Notes -->
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-slate-700">ملاحظات المكالمة</label>
                                <textarea name="notes" rows="3" placeholder="ملاحظات الموظف، تعليمات التسليم، إلخ..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500"></textarea>
                            </div>

                            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-xl shadow-md transition">
                                حفظ نتيجة المكالمة وإنهاء الطلب
                            </button>
                        </form>
                    </div>
                </div>

            </div>

            <!-- ORDER MODIFICATION MODAL -->
            <div x-show="showModifyModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
                <div class="bg-white rounded-3xl border border-slate-200 max-w-lg w-full p-6 space-y-6 shadow-2xl text-slate-800" @click.away="showModifyModal = false">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="text-base font-black text-slate-900">تعديل الطلب أثناء المكالمة</h3>
                        <button type="button" @click="showModifyModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
                    </div>

                    <form method="POST" action="{{ route('store.call-center.modify', $activeOrder) }}" class="space-y-4">
                        @csrf

                        <!-- Quantity -->
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">الكمية المطلوبة</label>
                            <input type="number" name="quantity" min="1" max="50" value="{{ $activeOrder->lines->first()?->quantity ?? 1 }}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500" required>
                        </div>

                        <!-- Customer Name -->
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">اسم العميل</label>
                            <input type="text" name="customer_name" value="{{ $address?->first_name ?? '' }}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <!-- Contact Phone -->
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">رقم الهاتف البديل / المحدث</label>
                            <input type="text" name="contact_phone" value="{{ $address?->contact_phone ?? '' }}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <!-- Governorate -->
                        <div class="space-y-1">
                            <label class="text-xs font-bold text-slate-700">المحافظة</label>
                            <select name="governorate_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                @foreach($governorates as $gov)
                                    <option value="{{ $gov->id }}" {{ ($snapshot['governorate_id'] ?? null) == $gov->id ? 'selected' : '' }}>
                                        {{ $gov->name_ar }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- City & Address -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-slate-700">المدينة / المنطقة</label>
                                <input type="text" name="city" value="{{ $address?->city ?? '' }}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-slate-700">العنوان التفصيلي</label>
                                <input type="text" name="line_one" value="{{ $address?->line_one ?? '' }}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-[11px] text-amber-800 font-bold">
                            ⚠️ سيتم إعادة احتساب تكلفة الشحن وحصص أرباح المسوق تلقائياً وتسجيل الفرق في سجل أحداث الطلب.
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="showModifyModal = false" class="px-4 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50">
                                إلغاء
                            </button>
                            <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-black hover:bg-indigo-700 transition">
                                تأكيد التعديل وإعادة التسعير
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        @else
            <!-- EMPTY DESK (NO ACTIVE CALL CLAIMED) -->
            <div class="bg-white rounded-3xl border-2 border-dashed border-slate-200 p-12 text-center space-y-6 shadow-sm">
                <div class="w-16 h-16 mx-auto bg-indigo-50 text-indigo-600 rounded-3xl flex items-center justify-center text-3xl font-black">
                    🎧
                </div>
                <div class="max-w-md mx-auto space-y-2">
                    <h2 class="text-xl font-black text-slate-900">لا يوجد طلب قيد الاتصال حالياً</h2>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        طابور الانتظار يحتوي على 
                        <span class="font-black text-indigo-600">{{ number_format($stats['pending_count'] ?? 0) }}</span> 
                        طلباً بانتظار التأكيد. انقر على الزر أدناه لحجز الطلب التالي وبدء المكالمة.
                    </p>
                </div>

                <form method="POST" action="{{ route('store.call-center.claim') }}">
                    @csrf
                    <button type="submit" class="px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-sm rounded-2xl shadow-lg shadow-indigo-600/20 transition transform active:scale-95 inline-flex items-center gap-2">
                        <span>⚡</span>
                        <span>سحب الطلب التالي للاتصال (Claim Next Order)</span>
                    </button>
                </form>
            </div>
        @endif

    </div>
</body>
</html>

