<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>مساعد الذكاء الاصطناعي (Claude AI) - لوحة التاجر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Fira+Code:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        code, pre { font-family: 'Fira Code', monospace; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                    <a href="{{ route('store.kpis.index') }}" class="hover:text-slate-600 transition">لوحة التحكم</a>
                    <span>/</span>
                    <span class="text-slate-700">أدوات الذكاء الاصطناعي</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>✨</span>
                    <span>مساعد الذكاء الاصطناعي للمتجر (Claude AI)</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    توليد أوصاف المنتجات الاحترافية، تحسين السيو (SEO)، اقتراح التصنيفات، وترجمة المحتوى التسويقي بدون إرسال أي بيانات شخصية للعملاء.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                    محرك Claude 3.5 نشط
                </span>
            </div>
        </div>

        <!-- Quota KPI & Usage Bar -->
        @php
            $quotaPercent = $limit > 0 ? min(round(($usage / $limit) * 100), 100) : 0;
            $isNearLimit = $quotaPercent >= 80;
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-2">
                <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
                    <span>الاستخدام الشهري للطلبات</span>
                    <span>📊</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-slate-900">{{ $usage }}</span>
                    <span class="text-sm text-slate-400 font-bold">/ {{ $limit > 0 ? $limit : 'غير محدود' }} طلب</span>
                </div>
                <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 {{ $isNearLimit ? 'bg-amber-500' : 'bg-indigo-600' }}" style="width: {{ $quotaPercent }}%"></div>
                </div>
                <div class="flex justify-between text-[11px] text-slate-400 font-medium">
                    <span>تم استهلاك {{ $quotaPercent }}%</span>
                    <span>يتجدد في أول كل شهر</span>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-2">
                <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
                    <span>إجمالي التوكنز هذا الشهر</span>
                    <span>🪙</span>
                </div>
                <div class="text-3xl font-black text-slate-900">{{ number_format($totalTokensThisMonth) }}</div>
                <p class="text-xs text-slate-400">يشمل مدخلات البرومبت والمخرجات المولدة</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-2">
                <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
                    <span>معايير الخصوصية والأمان</span>
                    <span>🔒</span>
                </div>
                <div class="text-lg font-bold text-emerald-700 flex items-center gap-2">
                    <span>✓</span>
                    <span>حماية تامة من PII</span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    يتم حجب أي أرقام هواتف أو إيميلات للعملاء تلقائياً قبل إرسال الطلب للنموذج لضمان الخصوصية التامة.
                </p>
            </div>
        </div>

        <!-- Interactive AI Tools -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50/70 p-4 flex flex-wrap gap-2">
                <button onclick="switchTab('copy')" id="tab-btn-copy" class="px-4 py-2 text-xs font-bold rounded-xl transition bg-indigo-600 text-white shadow-sm">
                    📝 صياغة وصف المنتج و SEO
                </button>
                <button onclick="switchTab('tags')" id="tab-btn-tags" class="px-4 py-2 text-xs font-bold rounded-xl transition bg-white text-slate-700 hover:bg-slate-100 border border-slate-200">
                    🏷️ اقتراح التصنيفات والوسوم
                </button>
                <button onclick="switchTab('translate')" id="tab-btn-translate" class="px-4 py-2 text-xs font-bold rounded-xl transition bg-white text-slate-700 hover:bg-slate-100 border border-slate-200">
                    🌐 ترجمة المحتوى التسويقي
                </button>
            </div>

            <!-- Tab 1: Product Copy -->
            <div id="tab-copy" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">اسم أو عنوان المنتج *</label>
                            <input type="text" id="copy-title" placeholder="مثال: سماعة رأس لاسلكية عازلة للضوضاء" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">أهم الخصائص والمواصفات (اختياري)</label>
                            <textarea id="copy-features" rows="3" placeholder="مثال: بطارية 36 ساعة، بلوتوث 5.3، ميكروفون نقي، شحن سريع Type-C" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">اللهجة واللغة</label>
                                <select id="copy-dialect" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                                    <option value="egyptian" selected>عامية مصرية (تسويقية جذابة)</option>
                                    <option value="msa">عربية فصحى راقية</option>
                                    <option value="english">إنجليزية (English)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">نبرة الخطاب</label>
                                <select id="copy-tone" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                                    <option value="persuasive" selected>مقنعة وتركز على الفوائد</option>
                                    <option value="enthusiastic">حماسية ومثيرة للاهتمام</option>
                                    <option value="professional">رسمية وموثوقة</option>
                                    <option value="luxury">فخمة وراقية (Luxury)</option>
                                </select>
                            </div>
                        </div>
                        <button onclick="generateCopy()" id="btn-generate-copy" class="w-full py-3 px-4 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center justify-center gap-2 shadow-sm">
                            <span>✨</span>
                            <span>توليد الوصف وتحسين السيو الآن</span>
                        </button>
                    </div>

                    <!-- Output Area -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700">المخرجات المولدة</span>
                            <span id="copy-loading" class="text-xs text-indigo-600 font-bold hidden animate-pulse">جاري التوليد بواسطة Claude...</span>
                        </div>
                        <div id="copy-output" class="text-xs text-slate-500 italic space-y-3">
                            املأ بيانات المنتج واضغط على زر التوليد لعرض المحتوى التسويقي وميتاداتا السيو هنا.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Category & Tags -->
            <div id="tab-tags" class="p-6 space-y-6 hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">اسم المنتج *</label>
                            <input type="text" id="tags-title" placeholder="مثال: قلاية هوائية ذكية بدون زيت 6 لتر" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">وصف مختصر (اختياري)</label>
                            <textarea id="tags-desc" rows="3" placeholder="قلاية هوائية لتحضير الأطعمة الصحية بدون زيوت مع شاشة ديجيتال وبرامج متعددة" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                        </div>
                        <button onclick="suggestTags()" id="btn-suggest-tags" class="w-full py-3 px-4 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center justify-center gap-2 shadow-sm">
                            <span>🏷️</span>
                            <span>اقتراح التصنيفات والوسوم</span>
                        </button>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700">التصنيفات والوسوم المقترحة</span>
                            <span id="tags-loading" class="text-xs text-indigo-600 font-bold hidden animate-pulse">جاري التحليل...</span>
                        </div>
                        <div id="tags-output" class="text-xs text-slate-500 italic space-y-4">
                            ستظهر هنا شجرة التصنيفات ووسوم البحث الشائعة للمنتج.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Translate -->
            <div id="tab-translate" class="p-6 space-y-6 hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">النص التسويقي المراد ترجمته *</label>
                            <textarea id="trans-text" rows="5" placeholder="أدخل النص التسويقي هنا..." class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">اللغة المستهدفة</label>
                            <select id="trans-lang" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                                <option value="en" selected>الإنجليزية (English)</option>
                                <option value="ar">العربية (Arabic)</option>
                            </select>
                        </div>
                        <button onclick="translateText()" id="btn-translate" class="w-full py-3 px-4 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center justify-center gap-2 shadow-sm">
                            <span>🌐</span>
                            <span>ترجمة النص باحترافية</span>
                        </button>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700">النص المترجم</span>
                            <span id="trans-loading" class="text-xs text-indigo-600 font-bold hidden animate-pulse">جاري الترجمة...</span>
                        </div>
                        <div id="trans-output" class="text-xs text-slate-500 italic leading-relaxed">
                            سيظهر النص المترجم بأسلوب تجاري جذاب هنا.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Logs Table -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">سجل استهلاك الذكاء الاصطناعي الأخير</h3>
                    <p class="text-xs text-slate-400 mt-0.5">تفاصيل التوكنز والعمليات المنفذة في هذا المتجر</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200/60 font-bold">
                        <tr>
                            <th class="py-3 px-4">التاريخ والوقت</th>
                            <th class="py-3 px-4">نوع العملية</th>
                            <th class="py-3 px-4">المحرك / النموذج</th>
                            <th class="py-3 px-4">توكنز الإدخال</th>
                            <th class="py-3 px-4">توكنز الإخراج</th>
                            <th class="py-3 px-4">الإجمالي</th>
                            <th class="py-3 px-4">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($recentLogs as $log)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-3 px-4 text-slate-400 font-mono text-[11px]">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                <td class="py-3 px-4 font-bold text-slate-800">
                                    @switch($log->action)
                                        @case('product_copy') وصف المنتج وسيو @break
                                        @case('category_tags') تصنيفات ووسوم @break
                                        @case('translate') ترجمة محتوى @break
                                        @case('ad_copy') نصوص إعلانات @break
                                        @case('marketing_angles') زوايا تسويقية @break
                                        @case('policy_check') فحص سياسات @break
                                        @default {{ $log->action }}
                                    @endswitch
                                </td>
                                <td class="py-3 px-4 font-mono text-[11px] text-slate-500">{{ $log->model }}</td>
                                <td class="py-3 px-4 font-mono text-[11px]">{{ number_format($log->prompt_tokens) }}</td>
                                <td class="py-3 px-4 font-mono text-[11px]">{{ number_format($log->completion_tokens) }}</td>
                                <td class="py-3 px-4 font-mono text-[11px] font-bold text-indigo-600">{{ number_format($log->total_tokens) }}</td>
                                <td class="py-3 px-4">
                                    @if($log->status === 'success')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">ناجح</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200" title="{{ $log->error_message }}">فشل</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">لا توجد عمليات ذكاء اصطناعي مسجلة بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function switchTab(tabId) {
            ['copy', 'tags', 'translate'].forEach(id => {
                document.getElementById('tab-' + id).classList.add('hidden');
                const btn = document.getElementById('tab-btn-' + id);
                btn.className = 'px-4 py-2 text-xs font-bold rounded-xl transition bg-white text-slate-700 hover:bg-slate-100 border border-slate-200';
            });

            document.getElementById('tab-' + tabId).classList.remove('hidden');
            const activeBtn = document.getElementById('tab-btn-' + tabId);
            activeBtn.className = 'px-4 py-2 text-xs font-bold rounded-xl transition bg-indigo-600 text-white shadow-sm';
        }

        async function generateCopy() {
            const title = document.getElementById('copy-title').value.trim();
            const features = document.getElementById('copy-features').value.trim();
            const dialect = document.getElementById('copy-dialect').value;
            const tone = document.getElementById('copy-tone').value;

            if (!title) {
                alert('يرجى إدخال اسم المنتج');
                return;
            }

            const loading = document.getElementById('copy-loading');
            const output = document.getElementById('copy-output');
            const btn = document.getElementById('btn-generate-copy');

            loading.classList.remove('hidden');
            btn.disabled = true;

            try {
                const res = await fetch("{{ route('store.ai.product-copy') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ title, features, dialect, tone })
                });

                const data = await res.json();
                if (data.success) {
                    const d = data.data;
                    let bulletsHtml = (d.bullet_points || []).map(b => `<li class="flex items-center gap-1.5"><span class="text-indigo-600">✓</span><span>${b}</span></li>`).join('');
                    output.innerHTML = `
                        <div class="space-y-4 not-italic text-slate-800">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 block mb-1">العنوان التسويقي</span>
                                <h4 class="text-sm font-black text-slate-900">${d.headline}</h4>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">الوصف التسويقي</span>
                                <p class="text-xs leading-relaxed text-slate-600">${d.description}</p>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">أبرز المميزات</span>
                                <ul class="text-xs space-y-1 text-slate-600">${bulletsHtml}</ul>
                            </div>
                            <div class="pt-3 border-t border-slate-200/80 space-y-2 bg-indigo-50/50 p-3 rounded-xl border">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-800 block">بيانات تحسين السيو (SEO Meta)</span>
                                <div class="text-xs">
                                    <span class="font-bold text-slate-700">العنوان: </span>
                                    <span class="text-indigo-900">${d.seo_title}</span>
                                </div>
                                <div class="text-xs">
                                    <span class="font-bold text-slate-700">الوصف: </span>
                                    <span class="text-slate-600">${d.seo_description}</span>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    output.innerHTML = `<span class="text-rose-600 font-bold">خطأ: ${data.error}</span>`;
                }
            } catch (err) {
                output.innerHTML = `<span class="text-rose-600 font-bold">فشل الاتصال بالخادم: ${err.message}</span>`;
            } finally {
                loading.classList.add('hidden');
                btn.disabled = false;
            }
        }

        async function suggestTags() {
            const title = document.getElementById('tags-title').value.trim();
            const description = document.getElementById('tags-desc').value.trim();

            if (!title) {
                alert('يرجى إدخال اسم المنتج');
                return;
            }

            const loading = document.getElementById('tags-loading');
            const output = document.getElementById('tags-output');
            const btn = document.getElementById('btn-suggest-tags');

            loading.classList.remove('hidden');
            btn.disabled = true;

            try {
                const res = await fetch("{{ route('store.ai.suggest-tags') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ title, description })
                });

                const data = await res.json();
                if (data.success) {
                    const categoriesHtml = (data.data.categories || []).map(c => `<span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold">${c}</span>`).join(' ');
                    const tagsHtml = (data.data.tags || []).map(t => `<span class="px-2 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 rounded-md text-[11px] font-medium">#${t}</span>`).join(' ');

                    output.innerHTML = `
                        <div class="space-y-4 not-italic">
                            <div>
                                <span class="text-xs font-bold text-slate-700 block mb-2">التصنيفات المقترحة:</span>
                                <div class="flex flex-wrap gap-1.5">${categoriesHtml}</div>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-slate-700 block mb-2">وسوم البحث الشائعة (Tags):</span>
                                <div class="flex flex-wrap gap-1.5">${tagsHtml}</div>
                            </div>
                        </div>
                    `;
                } else {
                    output.innerHTML = `<span class="text-rose-600 font-bold">خطأ: ${data.error}</span>`;
                }
            } catch (err) {
                output.innerHTML = `<span class="text-rose-600 font-bold">فشل الاتصال: ${err.message}</span>`;
            } finally {
                loading.classList.add('hidden');
                btn.disabled = false;
            }
        }

        async function translateText() {
            const text = document.getElementById('trans-text').value.trim();
            const target_lang = document.getElementById('trans-lang').value;

            if (!text) {
                alert('يرجى إدخال النص المطلوب ترجمته');
                return;
            }

            const loading = document.getElementById('trans-loading');
            const output = document.getElementById('trans-output');
            const btn = document.getElementById('btn-translate');

            loading.classList.remove('hidden');
            btn.disabled = true;

            try {
                const res = await fetch("{{ route('store.ai.translate') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ text, target_lang })
                });

                const data = await res.json();
                if (data.success) {
                    output.innerHTML = `
                        <div class="not-italic space-y-2">
                            <p class="text-xs leading-relaxed text-slate-800 bg-white p-3 rounded-xl border border-slate-200">${data.data.translation}</p>
                            <button onclick="navigator.clipboard.writeText(this.previousElementSibling.innerText); alert('تم النسخ إلى الحافظة');" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition">📋 نسخ النص المترجم</button>
                        </div>
                    `;
                } else {
                    output.innerHTML = `<span class="text-rose-600 font-bold">خطأ: ${data.error}</span>`;
                }
            } catch (err) {
                output.innerHTML = `<span class="text-rose-600 font-bold">فشل الاتصال: ${err.message}</span>`;
            } finally {
                loading.classList.add('hidden');
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>

