<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التتبع الإعلاني و Conversions API - لوحة التاجر</title>
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
    @include('store.partials.panel-bar')
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                    <a href="{{ route('store.kpis.index') }}" class="hover:text-slate-600 transition">لوحة التحكم</a>
                    <span>/</span>
                    <span class="text-slate-700">التتبع الإعلاني والبكسلات</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>🎯</span>
                    <span>التتبع الإعلاني (Meta & TikTok Pixel + Conversions API)</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    ربط بكسلات التتبع بالمتصفح وإرسال الأحداث مباشرة من السيرفر مع كود منع التكرار (event_id) والحدث المخصص عند التسليم الفعلي.
                </p>
            </div>
        </div>

        <!-- Alert messages -->
        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold flex items-center gap-2">
                <span>⚠</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- KPI Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
                <span class="text-xs font-bold text-slate-400">إجمالي الأحداث المرسلة بنجاح</span>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($totalSent) }}</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
                <span class="text-xs font-bold text-slate-400">الأحداث المتعثرة / الفاشلة</span>
                <p class="text-2xl font-black text-rose-600">{{ number_format($totalFailed) }}</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
                <span class="text-xs font-bold text-slate-400">أحداث التسليم الفعلي (Delivered)</span>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($deliveredEventsCount) }}</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
                <span class="text-xs font-bold text-slate-400">حالة التتبع السيرفري (CAPI)</span>
                <div class="flex items-center gap-2 pt-1">
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $settings->isMetaCapiActive() ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-400' }}">
                        Meta {{ $settings->isMetaCapiActive() ? 'مفعل' : 'معطل' }}
                    </span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $settings->isTikTokApiActive() ? 'bg-neutral-900 text-white' : 'bg-slate-100 text-slate-400' }}">
                        TikTok {{ $settings->isTikTokApiActive() ? 'مفعل' : 'معطل' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Configuration Settings Form -->
        <form action="{{ route('store.pixels.update') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- 1. Meta (Facebook & Instagram) Settings -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-sm space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">📘</span>
                            <div>
                                <h2 class="text-base font-black text-slate-900">Meta Pixel & Conversions API</h2>
                                <p class="text-xs text-slate-500">التتبع عبر فيسبوك وانستجرام</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="meta_pixel_enabled" value="1" class="sr-only peer" {{ $settings->meta_pixel_enabled ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Meta Pixel ID (معرف البكسل)</label>
                            <input type="text" name="meta_pixel_id" value="{{ old('meta_pixel_id', $settings->meta_pixel_id) }}"
                                   placeholder="e.g. 123456789012345"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-mono text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Conversions API Access Token (رمز الوصول للواجهة البرمجية)</label>
                            <textarea name="meta_conversions_api_token" rows="3"
                                      placeholder="EAA..."
                                      class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-mono text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">{{ old('meta_conversions_api_token', $settings->meta_conversions_api_token) }}</textarea>
                            <span class="text-[11px] text-slate-400">احصل عليه من Meta Events Manager > Settings > Conversions API > Generate Access Token</span>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Test Event Code (كود الاختبار - اختياري)</label>
                            <input type="text" name="meta_test_event_code" value="{{ old('meta_test_event_code', $settings->meta_test_event_code) }}"
                                   placeholder="e.g. TEST12345"
                                   class="w-full px-4 py-2 rounded-xl border border-slate-200 font-mono text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- 2. TikTok Settings -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-sm space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🎵</span>
                            <div>
                                <h2 class="text-base font-black text-slate-900">TikTok Pixel & Events API</h2>
                                <p class="text-xs text-slate-500">التتبع عبر منصة تيك توك</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="tiktok_pixel_enabled" value="1" class="sr-only peer" {{ $settings->tiktok_pixel_enabled ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-neutral-900"></div>
                        </label>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">TikTok Pixel ID (معرف بكسل تيك توك)</label>
                            <input type="text" name="tiktok_pixel_id" value="{{ old('tiktok_pixel_id', $settings->tiktok_pixel_id) }}"
                                   placeholder="e.g. C123456789ABCDEF"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-mono text-xs focus:ring-2 focus:ring-neutral-900 focus:outline-none">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">TikTok Events API Access Token</label>
                            <textarea name="tiktok_events_api_token" rows="3"
                                      placeholder="act_..."
                                      class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-mono text-xs focus:ring-2 focus:ring-neutral-900 focus:outline-none">{{ old('tiktok_events_api_token', $settings->tiktok_events_api_token) }}</textarea>
                            <span class="text-[11px] text-slate-400">احصل عليه من TikTok Ads Manager > Assets > Events > Settings</span>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Test Event Code (كود الاختبار - اختياري)</label>
                            <input type="text" name="tiktok_test_event_code" value="{{ old('tiktok_test_event_code', $settings->tiktok_test_event_code) }}"
                                   placeholder="e.g. TEST12345"
                                   class="w-full px-4 py-2 rounded-xl border border-slate-200 font-mono text-xs focus:ring-2 focus:ring-neutral-900 focus:outline-none">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Delivery Optimization Feature Box -->
            <div class="bg-indigo-50/70 rounded-3xl p-6 sm:p-8 border border-indigo-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <h3 class="text-sm font-black text-indigo-950 flex items-center gap-2">
                        <span>📦</span>
                        <span>إرسال حدث مخصص عند التسليم الفعلي فقط (Actual Delivery Optimization)</span>
                    </h3>
                    <p class="text-xs text-indigo-800 leading-relaxed max-w-3xl">
                        ميزة حصرية للتجارة الإلكترونية بنظام الدفع عند الاستلام (COD): يتم إرسال حدث <code class="font-mono font-bold bg-indigo-100 px-1.5 py-0.5 rounded text-indigo-900">Delivered</code> لخوارزميات فيسبوك وتيك توك فقط عند استلام العميل للطلب ودفع ثمنه بالفعل، مما يمنع خوارزميات الإعلانات من التعلّم على الطلبات الوهمية أو الملغاة.
                    </p>
                </div>
                <div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="track_delivered_event" value="1" class="sr-only peer" {{ $settings->track_delivered_event ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                    حفظ التغييرات
                </button>
            </div>
        </form>

        <!-- Test Event Buttons -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-black text-slate-900">فحص الاتصال بالأحداث الاختبارية</h3>
                <p class="text-xs text-slate-500">أرسل حدث تجريبي فوري للتحقق من وصول الأحداث إلى لوحة تحكم فيسبوك أو تيك توك.</p>
            </div>
            <div class="flex gap-2">
                <form action="{{ route('store.pixels.test') }}" method="POST">
                    @csrf
                    <input type="hidden" name="platform" value="meta">
                    <button type="submit" class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-xl text-xs font-bold transition">
                        فحص Meta CAPI ↗
                    </button>
                </form>

                <form action="{{ route('store.pixels.test') }}" method="POST">
                    @csrf
                    <input type="hidden" name="platform" value="tiktok">
                    <button type="submit" class="px-4 py-2 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 border border-neutral-300 rounded-xl text-xs font-bold transition">
                        فحص TikTok Events API ↗
                    </button>
                </form>
            </div>
        </div>

        <!-- Diagnostic Panel: Event Logs Table -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden space-y-4 p-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <span>📊</span>
                        <span>لوحة تشخيص ومراقبة الأحداث المرسلة (Live Event Diagnostics)</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">سجل فوري لكافة الأحداث الصادرة إلى Meta CAPI و TikTok API وحالة استجابتها.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 border-b border-slate-100">
                            <th class="py-3 px-4 font-bold">الوقت والتاريخ</th>
                            <th class="py-3 px-4 font-bold">المنصة</th>
                            <th class="py-3 px-4 font-bold">نوع الحدث</th>
                            <th class="py-3 px-4 font-bold">معرف منع التكرار (event_id)</th>
                            <th class="py-3 px-4 font-bold">رقم الطلب</th>
                            <th class="py-3 px-4 font-bold">الحالة</th>
                            <th class="py-3 px-4 font-bold">كود الاستجابة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono">
                        @forelse($events as $event)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 text-slate-500 text-[11px] font-sans">
                                    {{ $event->created_at?->format('Y-m-d H:i:s') ?? '-' }}
                                </td>
                                <td class="py-3 px-4">
                                    @if($event->platform === 'meta')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700">Meta CAPI</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-neutral-900 text-white">TikTok API</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-bold font-sans">
                                    @if($event->event_name === 'Delivered')
                                        <span class="text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded font-black">📦 {{ $event->event_name }}</span>
                                    @elseif($event->event_name === 'Purchase' || $event->event_name === 'CompletePayment')
                                        <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-black">💰 {{ $event->event_name }}</span>
                                    @else
                                        <span class="text-slate-700">{{ $event->event_name }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-600 text-[11px]">
                                    {{ $event->event_id }}
                                </td>
                                <td class="py-3 px-4 font-sans text-slate-700">
                                    {{ $event->order?->reference ?? ($event->order_id ? '#' . $event->order_id : '-') }}
                                </td>
                                <td class="py-3 px-4 font-sans">
                                    @if($event->status === 'sent')
                                        <span class="inline-flex items-center gap-1 text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded text-[11px]">
                                            <span>✓</span> <span>ناجح</span>
                                        </span>
                                    @elseif($event->status === 'failed')
                                        <span class="inline-flex items-center gap-1 text-rose-700 font-bold bg-rose-50 px-2 py-0.5 rounded text-[11px]">
                                            <span>✗</span> <span>فشل</span>
                                        </span>
                                    @else
                                        <span class="text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded text-[11px]">قيد الإرسال</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-500">
                                    {{ $event->response_code ?? ($event->error_message ? 'ERR' : '-') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400 font-sans">
                                    لا توجد أحداث مرسلة حتى الآن.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-4 border-t border-slate-100">
                {{ $events->links() }}
            </div>
        </div>

    </div>
</body>
</html>

