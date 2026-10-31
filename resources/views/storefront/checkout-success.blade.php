@extends('layouts.storefront')

@section('title', __('تم تأكيد طلبك بنجاح | ') . $order->reference)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
    <!-- Success Banner Card -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-8 sm:p-12 text-center space-y-6">
        <div class="w-20 h-20 mx-auto bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center animate-bounce">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <div>
            <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-extrabold rounded-full mb-3">
                {{ __('تم استلام طلبك بنجاح') }}
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">{{ __('شكراً لتسوقك معنا!') }}</h1>
            <p class="text-slate-500 text-sm mt-2">
                {{ __('تم تسجيل طلبك برقم مرجعي:') }} <strong class="text-emerald-700 font-bold font-mono tracking-wider">{{ $order->reference }}</strong>
            </p>
        </div>

        <!-- Order Summary & Delivery Box -->
        <div class="bg-slate-50 rounded-2xl p-6 text-right space-y-4 border border-slate-100">
            <h3 class="text-sm font-bold text-slate-900 border-b border-slate-200 pb-3 flex items-center justify-between">
                <span>{{ __('تفاصيل الشحن والتوصيل') }}</span>
                <span class="text-xs font-normal text-slate-500">{{ __('الدفع عند الاستلام') }}</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-slate-600">
                <div>
                    <span class="text-slate-400 block mb-0.5">{{ __('الاسم:') }}</span>
                    <span class="font-bold text-slate-800 text-sm">{{ $shippingAddress?->first_name }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block mb-0.5">{{ __('رقم الهاتف:') }}</span>
                    <span class="font-bold text-slate-800 text-sm font-mono" dir="ltr">{{ $order->customer_phone_e164 }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-slate-400 block mb-0.5">{{ __('عنوان التوصيل:') }}</span>
                    <span class="font-medium text-slate-800">{{ $shippingAddress?->city }} - {{ $shippingAddress?->line_one }}</span>
                </div>
            </div>
        </div>

        <!-- Items Summary List -->
        <div class="border border-slate-100 rounded-2xl overflow-hidden divide-y divide-slate-100 text-right">
            @foreach($order->lines as $line)
                <div class="p-4 flex items-center justify-between text-sm">
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-md bg-slate-100 text-xs font-bold text-slate-700 flex items-center justify-center">
                            {{ $line->quantity }}x
                        </span>
                        <span class="font-semibold text-slate-900">{{ $line->description }}</span>
                    </div>
                    <span class="font-bold text-slate-800">
                        {{ \App\Support\Money::show($line->total->value) }}
                    </span>
                </div>
            @endforeach

            <!-- Total Amount -->
            <div class="p-4 bg-slate-50/80 flex items-center justify-between text-base font-extrabold text-slate-900">
                <span>{{ __('المبلغ المطلوب عند الاستلام:') }}</span>
                <span class="text-emerald-600 text-xl font-black">
                    {{ \App\Support\Money::show($order->total->value) }}
                </span>
            </div>
        </div>

        <!-- Next Steps Note -->
        <div class="p-4 bg-blue-50 border border-blue-100 rounded-2xl flex items-center gap-3 text-right">
            <svg class="w-6 h-6 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-xs text-blue-800 leading-relaxed">
                {{ __('سيتواصل معك فريق خدمة العملاء خلال ساعات لتأكيد العنوان وموعد التسليم قبل خروج الشحنة مع المندوب.') }}
            </p>
        </div>

        <!-- Action Button -->
        <div class="pt-2">
            <a href="{{ route('storefront.home') }}" class="inline-flex items-center justify-center px-8 py-3.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-sm transition shadow-sm">
                {{ __('العودة إلى المتجر') }}
            </a>
        </div>
    </div>
</div>

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
@endsection
