<div class="py-10 bg-gray-50 min-h-[75vh]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('storefront.account.orders') }}" class="inline-flex items-center gap-1.5 text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>العودة لطلباتي</span>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Order Summary & Items -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-gray-100">
                        <div>
                            <span class="text-xs text-gray-400 block">رقم الطلب</span>
                            <h1 class="text-xl font-bold font-mono text-gray-900">{{ $order->reference }}</h1>
                        </div>
                        <div class="text-left">
                            <span class="text-xs text-gray-400 block">تاريخ الإجراء</span>
                            <span class="text-sm font-medium text-gray-700">{{ $order->created_at?->translatedFormat('d F Y - h:i A') }}</span>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="divide-y divide-gray-100 py-4">
                        @foreach ($order->lines as $line)
                            <div class="py-4 flex items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $line->description }}</h3>
                                    <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $line->identifier ?? '' }}</p>
                                    <p class="text-xs text-gray-500 mt-1">الكمية: {{ $line->quantity }}</p>
                                </div>
                                <div class="text-left font-bold text-sm text-gray-900">
                                    {{ \App\Support\Money::show(($line->sub_total->value ?? 0)) }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pricing Breakdown -->
                    <div class="pt-4 border-t border-gray-100 space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>المجموع الفرعي</span>
                            <span>{{ \App\Support\Money::show(($order->sub_total->value ?? 0)) }}</span>
                        </div>
                        @if (($order->discount_total->value ?? 0) > 0)
                            <div class="flex justify-between text-emerald-600 font-medium">
                                <span>خصم الكوبون</span>
                                <span>-{{ \App\Support\Money::show(($order->discount_total->value ?? 0)) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-gray-600">
                            <span>الشحن</span>
                            <span>{{ \App\Support\Money::show(($order->shipping_total->value ?? 0)) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold text-gray-900 pt-2 border-t border-gray-100">
                            <span>الإجمالي الكلي</span>
                            <span class="text-emerald-700">{{ \App\Support\Money::show(($order->total->value ?? 0)) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping & Details -->
            <div class="space-y-6">
                <!-- Status Badge & Simplified Timeline -->
                @php
                    $statusEnum = \App\Domain\Orders\Enums\OrderStatus::tryFrom($order->status);
                @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-gray-400">متابعة مسار الطلب</h2>

                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                        <span class="text-xs text-emerald-600 block mb-0.5">الحالة الحالية:</span>
                        <span class="text-sm font-bold text-emerald-800">
                            {{ $statusEnum?->customerLabel() ?? $order->status }}
                        </span>
                    </div>

                    <!-- Customer Timeline -->
                    <div class="pt-3 border-t border-gray-100">
                        <h3 class="text-xs font-bold text-gray-700 mb-3">سجل التحديثات:</h3>
                        @if ($events->isEmpty())
                            <p class="text-xs text-gray-400">جاري معالجة وتحديث مسار الطلب...</p>
                        @else
                            <ol class="relative border-r border-gray-200 space-y-4 mr-2">
                                @foreach ($events as $event)
                                    @php
                                        $eventStatusEnum = \App\Domain\Orders\Enums\OrderStatus::tryFrom($event->to_status);
                                    @endphp
                                    <li class="mr-3">
                                        <div class="absolute w-2.5 h-2.5 bg-emerald-500 rounded-full mt-1 -mr-1.5 border border-white"></div>
                                        <p class="text-xs font-semibold text-gray-800">
                                            {{ $eventStatusEnum?->customerLabel() ?? $event->to_status }}
                                        </p>
                                        <span class="text-[10px] text-gray-400 block mt-0.5">
                                            {{ $event->created_at->translatedFormat('d M Y - h:i A') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>

                    <p class="text-xs text-gray-500 pt-2 border-t border-gray-100">طريقة الدفع: {{ $order->payment_method === 'cod' ? 'الدفع عند الاستلام' : 'بطاقة إلكترونية' }}</p>
                </div>

                <!-- Shipping Address -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">عنوان الشحن</h2>
                    @if ($order->shippingAddress)
                        <div class="text-sm text-gray-700 space-y-1">
                            <p class="font-bold text-gray-900">{{ $order->shippingAddress->first_name }} {{ $order->shippingAddress->last_name }}</p>
                            <p dir="ltr" class="text-right font-mono text-xs text-gray-500">{{ $order->shippingAddress->contact_phone }}</p>
                            <p>{{ $order->shippingAddress->line_one }}</p>
                            <p class="text-gray-500 text-xs">{{ $order->shippingAddress->city }}</p>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">لا يوجد عنوان شحن مسجل.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
