<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('دليل المطورين والربط البرمجي (AffiliateOS API & Webhooks Docs)') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Fira+Code:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        code, pre { font-family: 'Fira Code', monospace; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-12 space-y-12">

        <!-- Header -->
        <div class="border-b border-slate-800 pb-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <span class="inline-block px-3 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-full text-xs font-bold mb-3">
                    {{ __('🚀 إصدار v1.0 • REST API & Webhooks') }}
                </span>
                <h1 class="text-3xl sm:text-4xl font-black text-white">
                    {{ __('دليل الربط البرمجي للمطورين (Developer API)') }}
                </h1>
                <p class="text-sm text-slate-400 mt-2">
                    {{ __('واجهات برمجية آمنة للمتجر والمسوقين مع مفاتيح بنطاقات (Scopes)، ضمانات Idempotency، وWebhooks موقّعة بـ HMAC.') }}
                </p>
            </div>
            <div>
                <a href="{{ url('/api/v1/openapi.json') }}" target="_blank"
                   class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-indigo-300 font-mono text-xs font-bold rounded-xl border border-slate-700 transition flex items-center gap-2">
                    <span>{{ __('📄 تحميل OpenAPI Schema JSON') }}</span>
                    <span>↗</span>
                </a>
            </div>
        </div>

        <!-- 1. Authentication -->
        <div class="bg-slate-800/60 rounded-3xl p-6 sm:p-8 border border-slate-700/80 space-y-4">
            <h2 class="text-lg font-black text-white flex items-center gap-2">
                <span>🔑</span>
                <span>{{ __('المصادقة وتفويض الصلاحيات (Authentication & Scopes)') }}</span>
            </h2>
            <p class="text-xs text-slate-300 leading-relaxed">
                {{ __('يتم إرسال مفتاح الـ API في ترويسة الطلب عبر') }} <code class="bg-slate-900 px-2 py-0.5 rounded text-amber-300 font-mono">Authorization: Bearer aff_live_...</code> {{ __('أو') }} <code class="bg-slate-900 px-2 py-0.5 rounded text-amber-300 font-mono">X-API-Key: aff_live_...</code>.
            </p>

            <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-left font-mono text-xs text-emerald-400 overflow-x-auto" dir="ltr">
curl -X GET "https://store.affiliate-os.com/api/v1/store/products" \
  -H "Authorization: Bearer aff_live_abc123..." \
  -H "Accept: application/json"
            </div>
        </div>

        <!-- 2. Idempotency Key Guarantee -->
        <div class="bg-slate-800/60 rounded-3xl p-6 sm:p-8 border border-slate-700/80 space-y-4">
            <h2 class="text-lg font-black text-white flex items-center gap-2">
                <span>🛡️</span>
                <span>{{ __('ضمانات عدم تكرار العمليات (Idempotency-Key Header)') }}</span>
            </h2>
            <p class="text-xs text-slate-300 leading-relaxed">
                {{ __('كافة عمليات الإنشاء والتعديل (مثل إنشاء طلب أو تعديل كمية المخزون) تتطلب ترويسة') }} <code class="bg-slate-900 px-2 py-0.5 rounded text-indigo-300 font-mono">Idempotency-Key: &lt;UUID&gt;</code> {{ __('لمنع تنفيذ العملية أكثر من مرة في حال انقطاع الشبكة أو إعادة الإرسال.') }}
            </p>
        </div>

        <!-- 3. Webhooks & HMAC Verification -->
        <div class="bg-slate-800/60 rounded-3xl p-6 sm:p-8 border border-slate-700/80 space-y-4">
            <h2 class="text-lg font-black text-white flex items-center gap-2">
                <span>⚡</span>
                <span>{{ __('الـ Webhooks الصادرة والتحقق من التوقيع (HMAC Signature)') }}</span>
            </h2>
            <p class="text-xs text-slate-300 leading-relaxed">
                {{ __('ترسل المنصة أحداث فورية (مثل') }} <code class="text-indigo-300">order.created</code>, <code class="text-indigo-300">order.status_updated</code>, <code class="text-indigo-300">order.delivered</code>{{ __(') موقّعة بهيدر') }} <code class="bg-slate-900 px-2 py-0.5 rounded text-amber-300 font-mono">X-AffiliateOS-Signature</code>.
            </p>

            <!-- Verification code snippet (PHP) -->
            <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800 text-left font-mono text-xs text-slate-300 overflow-x-auto space-y-1" dir="ltr">
                <span class="text-slate-500">{{ __('// التحقق من توقيع الـ Webhook في PHP:') }}</span><br>
                <span class="text-indigo-400">$payload</span> = file_get_contents(<span class="text-emerald-400">'php://input'</span>);<br>
                <span class="text-indigo-400">$receivedSignature</span> = $_SERVER[<span class="text-emerald-400">'HTTP_X_AFFILIATEOS_SIGNATURE'</span>] ?? <span class="text-emerald-400">''</span>;<br>
                <span class="text-indigo-400">$expectedSignature</span> = <span class="text-emerald-400">'sha256='</span> . hash_hmac(<span class="text-emerald-400">'sha256'</span>, $payload, <span class="text-indigo-400">$webhookSecret</span>);<br>
                <br>
                <span class="text-amber-400">if</span> (!hash_equals($expectedSignature, $receivedSignature)) {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;http_response_code(<span class="text-emerald-400">401</span>);<br>
                &nbsp;&nbsp;&nbsp;&nbsp;exit(<span class="text-emerald-400">'Invalid signature'</span>);<br>
                }
            </div>
        </div>

        <!-- 4. Embed Form Snippet -->
        <div class="bg-slate-800/60 rounded-3xl p-6 sm:p-8 border border-slate-700/80 space-y-4">
            <h2 class="text-lg font-black text-white flex items-center gap-2">
                <span>📦</span>
                <span>{{ __('كود تضمين نموذج الطلب لصفحات هبوط المسوقين (Embeddable Iframe)') }}</span>
            </h2>
            <p class="text-xs text-slate-300 leading-relaxed">
                {{ __('يمكن للأفلييت تضمين نموذج الشراء المباشر في أي صفحة هبوط خارجية (WordPress / Webflow / صفحة HTML مخصصة):') }}
            </p>

            <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-left font-mono text-xs text-amber-300 overflow-x-auto" dir="ltr">
&lt;iframe 
  src="https://yourstore.com/embed/order/1?ref=AFF_CODE&utm_source=tiktok&utm_campaign=winter"
  width="100%" 
  height="700" 
  style="border:none; border-radius: 24px; overflow: hidden;"
&gt;&lt;/iframe&gt;
            </div>
        </div>

    </div>
</body>
</html>

