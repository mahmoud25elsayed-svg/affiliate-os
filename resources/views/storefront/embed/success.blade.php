<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم استلام طلبك بنجاح</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-transparent text-slate-800 p-4">
    <div class="max-w-md mx-auto bg-white rounded-3xl border border-slate-200/90 shadow-xl p-8 text-center space-y-6">

        <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto shadow-inner">
            ✓
        </div>

        <div>
            <span class="inline-block px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-bold mb-2">
                طلبك مؤكد ومسجل بنجاح
            </span>
            <h1 class="text-xl font-black text-slate-900">شكراً لك، تم استلام طلبك!</h1>
            <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                رقم الطلب الخاص بك هو: <span class="font-mono font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $order->reference }}</span>
            </p>
        </div>

        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-xs text-slate-600 space-y-2 text-right">
            <div class="flex justify-between">
                <span>اسم العميل:</span>
                <span class="font-bold text-slate-900">{{ $order->shippingAddress?->first_name ?? $order->customer_name }}</span>
            </div>
            <div class="flex justify-between">
                <span>المحافظة:</span>
                <span class="font-bold text-slate-900">{{ $order->shippingAddress?->state ?? 'مصر' }}</span>
            </div>
            <div class="flex justify-between border-t border-slate-200 pt-2 font-black text-slate-900">
                <span>الإجمالي عند الاستلام:</span>
                <span class="text-emerald-700 font-black text-sm">{{ number_format(((int) ($order->total->value ?? $order->total)) / 100, 2) }} ج.م</span>
            </div>
        </div>

        <p class="text-[11px] text-slate-400">
            سيقوم فريق خدمة العملاء بالتواصل معك هاتفياً لتأكيد موعد التسليم.
        </p>

    </div>

    <!-- Notify Parent Iframe / Landing Page via postMessage for Pixel conversions -->
    @include('storefront.partials.pixels')

    @if(!empty($eventId))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.trackAdEvent === 'function') {
                window.trackAdEvent('Purchase', {
                    content_type: 'product',
                    currency: '{{ $order->currency_code ?: "EGP" }}',
                    value: {{ round(((int) ($order->getRawOriginal('total') ?? 0)) / 100, 2) }},
                    order_id: '{{ $order->reference }}'
                }, '{{ $eventId }}');
            }
        });
    </script>
    @endif

    <script>
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                event: 'affiliate_os_order_created',
                order_reference: '{{ $order->reference }}',
                event_id: '{{ $eventId ?? "" }}',
                total_minor: {{ (int) ($order->getRawOriginal('total') ?? 0) }}
            }, '*');
        }
    </script>
</body>
</html>

