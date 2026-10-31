<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات برنامج المسوقين والعمولات - لوحة الإدارة</title>
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
    <div class="max-w-5xl mx-auto px-4 py-8 space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-900">إعدادات برنامج المسوقين والعمولات</h1>
                <p class="text-xs text-slate-500 mt-1">
                    التحكم في حدود العمولات الدنيا والقصوى، هوامش الربح، قواعد السحب، وسياسات Maker-Checker
                </p>
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

        <form action="{{ route('store.affiliates.settings.update') }}" method="POST" class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-8">
            @csrf

            <!-- Section 1: Commission Bounds & Margins -->
            <div class="space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center space-x-2 space-x-reverse">
                    <span>💎</span>
                    <span>حدود العمولات وهوامش الربح (Admin Bounds)</span>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">نموذج العمولة</label>
                        <select name="commission_model" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50">
                            <option value="markup" {{ $settings->commission_model === 'markup' ? 'selected' : '' }}>سعر حر فوق الجملة (Markup)</option>
                            <option value="percentage" {{ $settings->commission_model === 'percentage' ? 'selected' : '' }}>نسبة مئوية من قيمة الطلب</option>
                            <option value="both" {{ $settings->commission_model === 'both' ? 'selected' : '' }}>كلاهما (سعر حر ونسبة)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">النسبة المئوية الافتراضية (%)</label>
                        <input type="number" name="default_commission_rate" value="{{ old('default_commission_rate', $settings->default_commission_rate) }}" min="1" max="100" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">أقل عمولة مسموح بها للمسوق (بالقروش - 1000 = 10 {{ \App\Support\Money::symbol() }})</label>
                        <input type="number" name="min_commission_minor" value="{{ old('min_commission_minor', $settings->min_commission_minor) }}" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono" required>
                        <span class="text-[11px] text-slate-400">أي ما يعادل: {{ \App\Support\Money::show($settings->min_commission_minor) }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">أقصى عمولة مسموح بها للمسوق (بالقروش - 100000 = 1000 {{ \App\Support\Money::symbol() }})</label>
                        <input type="number" name="max_commission_minor" value="{{ old('max_commission_minor', $settings->max_commission_minor) }}" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono" required>
                        <span class="text-[11px] text-slate-400">أي ما يعادل: {{ \App\Support\Money::show($settings->max_commission_minor) }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">أقل زيادة بيع فوق الجملة (Markup Minor)</label>
                        <input type="number" name="min_selling_markup_minor" value="{{ old('min_selling_markup_minor', $settings->min_selling_markup_minor) }}" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono" required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">أقصى زيادة بيع فوق الجملة (Markup Minor)</label>
                        <input type="number" name="max_selling_markup_minor" value="{{ old('max_selling_markup_minor', $settings->max_selling_markup_minor) }}" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono" required>
                    </div>
                </div>
            </div>

            <!-- Section 2: Payout Rules & Maker-Checker -->
            <div class="space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center space-x-2 space-x-reverse">
                    <span>💳</span>
                    <span>قواعد السحب وسياسة Maker-Checker المزدوجة</span>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الحد الأدنى لطلب السحب (Minor Units)</label>
                        <input type="number" name="min_payout_minor" value="{{ old('min_payout_minor', $settings->min_payout_minor) }}" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono" required>
                        <span class="text-[11px] text-slate-400">الحالي: {{ \App\Support\Money::show($settings->min_payout_minor) }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">سقف Maker-Checker الإلزامي (Minor Units)</label>
                        <input type="number" name="maker_checker_threshold_minor" value="{{ old('maker_checker_threshold_minor', $settings->maker_checker_threshold_minor) }}" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono" required>
                        <span class="text-[11px] text-slate-400">فوق هذا المبلغ ({{ \App\Support\Money::show($settings->maker_checker_threshold_minor) }}) لا يجوز لنفس الموظف الاعتماد والتنفيذ</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">نافذة المرتجع بالأيام (Return Window Days)</label>
                        <input type="number" name="return_window_days" value="{{ old('return_window_days', $settings->return_window_days) }}" min="1" max="90" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono" required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">نمط تحرير الأرباح (Release Mode)</label>
                        <select name="release_mode" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50">
                            <option value="later_of_both" {{ $settings->release_mode === 'later_of_both' ? 'selected' : '' }}>الأبعد بين نافذة المرتجع وتسوية الشحن (موصى به)</option>
                            <option value="return_window" {{ $settings->release_mode === 'return_window' ? 'selected' : '' }}>بانتهاء نافذة المرتجع فقط</option>
                            <option value="courier_remittance" {{ $settings->release_mode === 'courier_remittance' ? 'selected' : '' }}>بتسوية تحصيل شركة الشحن فقط</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">أيام تحويل وسحب الأرباح المعتمدة أسبوعياً</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach([
                            'sunday' => 'الأحد',
                            'monday' => 'الاثنين',
                            'tuesday' => 'الثلاثاء',
                            'wednesday' => 'الأربعاء',
                            'thursday' => 'الخميس',
                            'friday' => 'الجمعة',
                            'saturday' => 'السبت'
                        ] as $dayKey => $dayLabel)
                            <label class="flex items-center space-x-2 space-x-reverse p-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs">
                                <input type="checkbox" name="payout_days[]" value="{{ $dayKey }}" {{ in_array($dayKey, $settings->payout_days, true) ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>{{ $dayLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Section 3: Quotas & Security -->
            <div class="space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center space-x-2 space-x-reverse">
                    <span>🛡️</span>
                    <span>الضوابط اليومية والأمان</span>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الحد اليومي لعدد الطلبات لكل مسوق</label>
                        <input type="number" name="default_daily_order_limit" value="{{ old('default_daily_order_limit', $settings->default_daily_order_limit) }}" min="1" max="500" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono" required>
                    </div>

                    <div class="space-y-2 pt-2">
                        <label class="flex items-center space-x-2 space-x-reverse text-xs">
                            <input type="hidden" name="auto_approve_registrations" value="0">
                            <input type="checkbox" name="auto_approve_registrations" value="1" {{ $settings->auto_approve_registrations ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="font-bold text-slate-700">الموافقة التلقائية على تسجيل المسوقين الجدد دون مراجعة</span>
                        </label>
                        <label class="flex items-center space-x-2 space-x-reverse text-xs">
                            <input type="hidden" name="show_customer_phone_to_affiliate" value="0">
                            <input type="checkbox" name="show_customer_phone_to_affiliate" value="1" {{ $settings->show_customer_phone_to_affiliate ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="font-bold text-slate-700">إظهار رقم هاتف العميل للمسوق في تفاصيل الطلب</span>
                        </label>
                        <label class="flex items-center space-x-2 space-x-reverse text-xs">
                            <input type="hidden" name="public_trust_metrics_enabled" value="0">
                            <input type="checkbox" name="public_trust_metrics_enabled" value="1" {{ ($settings->public_trust_metrics_enabled ?? true) ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="font-bold text-slate-700">عرض مؤشرات الشفافية والأداء العلنية (زمن الدفع، نسبة التسليم) في صفحات المسوقين</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end">
                <button type="submit" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition">
                    حفظ وتحديث الإعدادات
                </button>
            </div>
        </form>

    </div>
</body>
</html>
