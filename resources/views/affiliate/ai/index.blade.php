<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('استوديو الذكاء الاصطناعي للمسوقين - بوابة الأفلييت') }}</title>
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
                    <a href="{{ route('affiliate.dashboard') }}" class="hover:text-slate-600 transition">{{ __('بوابة المسوقين') }}</a>
                    <span>/</span>
                    <span class="text-slate-700">{{ __('الذكاء الاصطناعي') }}</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>⚡</span>
                    <span>{{ __('استوديو الذكاء الاصطناعي للمسوقين (AI Copywriter)') }}</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    {{ __('توليد نصوص إعلانات احترافية لفيسبوك وتيك توك، استخراج زوايا تسويقية إبداعية، وفحص الإعلانات لضمان عدم حظر الحساب الإعلاني.') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('affiliate.dashboard') }}" class="px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 bg-white hover:bg-slate-100 transition text-slate-700">
                    {{ __('← العودة للرئيسية') }}
                </a>
            </div>
        </div>

        <!-- Studio Navigation Tabs -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50/70 p-4 flex flex-wrap gap-2">
                <button onclick="switchTab('adcopy')" id="tab-btn-adcopy" class="px-4 py-2 text-xs font-bold rounded-xl transition bg-indigo-600 text-white shadow-sm">
                    {{ __('📢 كاتب الإعلانات (فيسبوك وتيك توك)') }}
                </button>
                <button onclick="switchTab('angles')" id="tab-btn-angles" class="px-4 py-2 text-xs font-bold rounded-xl transition bg-white text-slate-700 hover:bg-slate-100 border border-slate-200">
                    {{ __('🎯 أفكار وزوايا تسويقية') }}
                </button>
                <button onclick="switchTab('policy')" id="tab-btn-policy" class="px-4 py-2 text-xs font-bold rounded-xl transition bg-white text-slate-700 hover:bg-slate-100 border border-slate-200">
                    {{ __('🛡️ فحص السياسات وتجنب الحظر') }}
                </button>
            </div>

            <!-- Tab 1: Ad Copy Generator -->
            <div id="tab-adcopy" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('اسم المنتج أو العرض *') }}</label>
                            <input type="text" id="ad-product-name" placeholder="{{ __('مثال: مضخة مياه أوتوماتيكية ذكية للشحن') }}" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('السعر أو العرض الترويجي *') }}</label>
                            <input type="text" id="ad-price" placeholder="مثال: 399 {{ \App\Support\Money::symbol() }} + شحن مجاني عند طلب قطعتين" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('المنصة الإعلانية') }}</label>
                                <select id="ad-platform" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                                    <option value="facebook" selected>{{ __('فيسبوك وإنستجرام (Meta)') }}</option>
                                    <option value="tiktok">{{ __('تيك توك (TikTok)') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('نبرة الخطاب') }}</label>
                                <select id="ad-tone" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                                    <option value="enthusiastic" selected>{{ __('حماسية وجذابة') }}</option>
                                    <option value="problem_solving">{{ __('حل مشكلة حقيقية (Pain Point)') }}</option>
                                    <option value="urgency">{{ __('استعجال وعرض محدود (FOMO)') }}</option>
                                    <option value="storytelling">{{ __('قصة وتجربة واقعية') }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('الجمهور المستهدف (اختياري)') }}</label>
                            <input type="text" id="ad-audience" placeholder="{{ __('مثال: الأمهات وربات البيوت، أصحاب المكاتب والشركات') }}" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <button onclick="generateAdCopy()" id="btn-generate-ad" class="w-full py-3 px-4 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center justify-center gap-2 shadow-sm">
                            <span>⚡</span>
                            <span>{{ __('توليد نماذج الإعلانات الآن') }}</span>
                        </button>
                    </div>

                    <!-- Output Area -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700">{{ __('نماذج الإعلانات المولدة') }}</span>
                            <span id="ad-loading" class="text-xs text-indigo-600 font-bold hidden animate-pulse">{{ __('جاري التحضير...') }}</span>
                        </div>
                        <div id="ad-output" class="text-xs text-slate-500 italic space-y-4">
                            {{ __('اختر تفاصيل المنتج واضغط توليد للحصول على نماذج إعلانات جاهزة للنسخ والحملات الإعلانية.') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Marketing Angles -->
            <div id="tab-angles" class="p-6 space-y-6 hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('اسم المنتج *') }}</label>
                            <input type="text" id="angles-product" placeholder="{{ __('مثال: مرتبة مساج وتسخين للجسم كامل') }}" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('الجمهور المستهدف (اختياري)') }}</label>
                            <input type="text" id="angles-audience" placeholder="{{ __('مثال: كبار السن، الموظفين الجالسين فترات طويلة، الرياضيين') }}" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <button onclick="suggestAngles()" id="btn-suggest-angles" class="w-full py-3 px-4 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center justify-center gap-2 shadow-sm">
                            <span>🎯</span>
                            <span>{{ __('استخراج الزوايا التسويقية') }}</span>
                        </button>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700">{{ __('الزوايا والهوكات المقترحة') }}</span>
                            <span id="angles-loading" class="text-xs text-indigo-600 font-bold hidden animate-pulse">{{ __('جاري التحليل...') }}</span>
                        </div>
                        <div id="angles-output" class="text-xs text-slate-500 italic space-y-3">
                            {{ __('ستظهر هنا زوايا إعلانية غير تقليدية تزيد نسبة النقر (CTR).') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Policy Checker -->
            <div id="tab-policy" class="p-6 space-y-6 hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('نص الإعلان المراد فحصه *') }}</label>
                            <textarea id="policy-text" rows="6" placeholder="{{ __('الصق نص الإعلان هنا قبل إطلاقه في الحملة لفحصه ضد سياسات ميتا وتيك توك...') }}" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                        </div>
                        <button onclick="checkPolicy()" id="btn-check-policy" class="w-full py-3 px-4 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center justify-center gap-2 shadow-sm">
                            <span>🛡️</span>
                            <span>{{ __('فحص التوافق وتفادي حظر الحساب') }}</span>
                        </button>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700">{{ __('تقرير الفحص الأمني والإعلاني') }}</span>
                            <span id="policy-loading" class="text-xs text-indigo-600 font-bold hidden animate-pulse">{{ __('جاري فحص السياسات...') }}</span>
                        </div>
                        <div id="policy-output" class="text-xs text-slate-500 italic space-y-3">
                            {{ __('سيظهر هنا تقييم الأمان، الكلمات المشبوهة، والبدائل المقترحة.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        function switchTab(tabId) {
            ['adcopy', 'angles', 'policy'].forEach(id => {
                document.getElementById('tab-' + id).classList.add('hidden');
                const btn = document.getElementById('tab-btn-' + id);
                btn.className = 'px-4 py-2 text-xs font-bold rounded-xl transition bg-white text-slate-700 hover:bg-slate-100 border border-slate-200';
            });

            document.getElementById('tab-' + tabId).classList.remove('hidden');
            const activeBtn = document.getElementById('tab-btn-' + tabId);
            activeBtn.className = 'px-4 py-2 text-xs font-bold rounded-xl transition bg-indigo-600 text-white shadow-sm';
        }

        async function generateAdCopy() {
            const product_name = document.getElementById('ad-product-name').value.trim();
            const price = document.getElementById('ad-price').value.trim();
            const platform = document.getElementById('ad-platform').value;
            const tone = document.getElementById('ad-tone').value;
            const target_audience = document.getElementById('ad-audience').value.trim();

            if (!product_name || !price) {
                alert(@js(__('يرجى إدخال اسم المنتج والسعر')));
                return;
            }

            const loading = document.getElementById('ad-loading');
            const output = document.getElementById('ad-output');
            const btn = document.getElementById('btn-generate-ad');

            loading.classList.remove('hidden');
            btn.disabled = true;

            try {
                const res = await fetch("{{ route('affiliate.ai.ad-copy') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ product_name, price, platform, tone, target_audience })
                });

                const data = await res.json();
                if (data.success) {
                    const variants = data.data.variants || [];
                    let html = '';
                    variants.forEach((v, idx) => {
                        html += `
                            <div class="bg-white p-4 rounded-xl border border-slate-200 space-y-2.5 not-italic">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full">{{ __('نموذج') }} ${idx + 1}</span>
                                    <button onclick="navigator.clipboard.writeText(this.closest('.space-y-2.5').querySelector('.primary-text').innerText); alert('{{ __('تم نسخ النص') }}');" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition">📋 {{ __('نسخ الإعلان') }}</button>
                                </div>
                                <p class="primary-text text-xs leading-relaxed text-slate-800">${v.primary_text}</p>
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-slate-700">{{ __('العنوان') }}: ${v.headline}</span>
                                    <span class="text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md">${v.cta}</span>
                                </div>
                            </div>
                        `;
                    });
                    output.innerHTML = html || @js(__('لم يتم توليد نماذج'));
                } else {
                    output.innerHTML = `<span class="text-rose-600 font-bold">{{ __('خطأ') }}: ${data.error}</span>`;
                }
            } catch (err) {
                output.innerHTML = `<span class="text-rose-600 font-bold">{{ __('فشل الاتصال') }}: ${err.message}</span>`;
            } finally {
                loading.classList.add('hidden');
                btn.disabled = false;
            }
        }

        async function suggestAngles() {
            const product_name = document.getElementById('angles-product').value.trim();
            const target_audience = document.getElementById('angles-audience').value.trim();

            if (!product_name) {
                alert(@js(__('يرجى إدخال اسم المنتج')));
                return;
            }

            const loading = document.getElementById('angles-loading');
            const output = document.getElementById('angles-output');
            const btn = document.getElementById('btn-suggest-angles');

            loading.classList.remove('hidden');
            btn.disabled = true;

            try {
                const res = await fetch("{{ route('affiliate.ai.suggest-angles') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ product_name, target_audience })
                });

                const data = await res.json();
                if (data.success) {
                    const angles = data.data.angles || [];
                    let html = '<ul class="space-y-2 not-italic">';
                    angles.forEach((a, i) => {
                        html += `
                            <li class="p-3 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 flex items-start gap-2">
                                <span class="text-indigo-600 font-bold">#${i + 1}</span>
                                <span>${a}</span>
                            </li>
                        `;
                    });
                    html += '</ul>';
                    output.innerHTML = html;
                } else {
                    output.innerHTML = `<span class="text-rose-600 font-bold">{{ __('خطأ') }}: ${data.error}</span>`;
                }
            } catch (err) {
                output.innerHTML = `<span class="text-rose-600 font-bold">{{ __('فشل الاتصال') }}: ${err.message}</span>`;
            } finally {
                loading.classList.add('hidden');
                btn.disabled = false;
            }
        }

        async function checkPolicy() {
            const ad_text = document.getElementById('policy-text').value.trim();

            if (!ad_text) {
                alert(@js(__('يرجى إدخال نص الإعلان المطلوب تدقيقه')));
                return;
            }

            const loading = document.getElementById('policy-loading');
            const output = document.getElementById('policy-output');
            const btn = document.getElementById('btn-check-policy');

            loading.classList.remove('hidden');
            btn.disabled = true;

            try {
                const res = await fetch("{{ route('affiliate.ai.check-policy') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ ad_text })
                });

                const data = await res.json();
                if (data.success) {
                    const d = data.data;
                    const isSafe = d.is_compliant && d.risk_score < 30;
                    const badgeClass = isSafe ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200';
                    const scoreText = isSafe ? @js(__('آمن للنشر')) : @js(__('مخاطرة حظر مرتفعة'));

                    let flagsHtml = (d.flagged_phrases || []).map(f => `<span class="px-2 py-0.5 bg-rose-100 text-rose-800 rounded text-[11px] font-bold">${f}</span>`).join(' ');
                    let suggestionsHtml = (d.suggestions || []).map(s => `<li>${s}</li>`).join('');

                    output.innerHTML = `
                        <div class="space-y-4 not-italic">
                            <div class="p-3 border rounded-xl flex items-center justify-between ${badgeClass}">
                                <span class="font-bold text-xs">${scoreText}</span>
                                <span class="font-mono font-bold text-xs">{{ __('نسبة الخطر') }}: ${d.risk_score}%</span>
                            </div>
                            ${flagsHtml ? `<div><span class="text-[11px] font-bold text-slate-500 block mb-1">العبارات المشبوهة:</span><div class="flex flex-wrap gap-1">${flagsHtml}</div></div>` : ''}
                            <div>
                                <span class="text-[11px] font-bold text-slate-500 block mb-1">{{ __('نصائح التحسين') }}:</span>
                                <ul class="list-disc list-inside text-xs text-slate-700 space-y-1">${suggestionsHtml || @js(__('لا توجد ملاحظات.'))}</ul>
                            </div>
                        </div>
                    `;
                } else {
                    output.innerHTML = `<span class="text-rose-600 font-bold">{{ __('خطأ') }}: ${data.error}</span>`;
                }
            } catch (err) {
                output.innerHTML = `<span class="text-rose-600 font-bold">{{ __('فشل الاتصال') }}: ${err.message}</span>`;
            } finally {
                loading.classList.add('hidden');
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>

