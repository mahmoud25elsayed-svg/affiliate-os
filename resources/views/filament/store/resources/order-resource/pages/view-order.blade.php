<x-filament-panels::page>
    @php
        $statusEnum = \App\Domain\Orders\Enums\OrderStatus::tryFrom($order->status);
    @endphp

    <div class="space-y-6">
        <!-- Top Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('حالة الطلب الحالية') }}</span>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-{{ $statusEnum?->color() ?? 'gray' }}-50 text-{{ $statusEnum?->color() ?? 'gray' }}-700 border border-{{ $statusEnum?->color() ?? 'gray' }}-200 dark:bg-gray-800 dark:text-gray-200">
                        {{ $statusEnum?->label() ?? $order->status }}
                    </span>
                </div>
                <span class="text-xs text-gray-400 mt-2 block">{{ __('رقم الطلب:') }} <strong class="font-mono text-gray-800 dark:text-gray-200">{{ $order->reference }}</strong></span>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('العميل والمحافظة') }}</span>
                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $order->shippingAddress?->first_name ?? __('عميل زائر') }} {{ $order->shippingAddress?->last_name ?? '' }}</p>
                <p dir="ltr" class="text-xs font-mono text-gray-500 dark:text-gray-400 text-right mt-0.5">{{ $order->shippingAddress?->contact_phone ?? $order->customer_phone_e164 ?? '-' }}</p>
                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">{{ $order->shippingAddress?->city ?? __('جمهورية مصر العربية') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('طريقة الدفع والتاريخ') }}</span>
                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                    {{ $order->payment_method === 'cod' ? __('الدفع عند الاستلام (COD)') : __('بطاقة إلكترونية') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('تاريخ الطلب:') }} {{ $order->created_at?->format('Y-m-d h:i A') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <span class="text-xs text-gray-500 dark:text-gray-400 block mb-1">{{ __('إجمالي الفاتورة') }}</span>
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                    {{ \App\Support\Money::show(($order->total->value ?? 0)) }}
                </p>
                <span class="text-xs text-gray-400 mt-1 block">{{ __('الشحن:') }} {{ \App\Support\Money::show(($order->shipping_total->value ?? 0)) }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Items & Financial Breakdown -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Products Table -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ __('محتويات الطلب (') }}{{ $order->lines->count() }} {{ __('أصناف)') }}</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($order->lines as $line)
                            <div class="p-4 flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $line->description }}</h4>
                                    <p class="text-xs text-gray-400 font-mono mt-0.5">SKU: {{ $line->identifier ?? '-' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('الكمية:') }} <strong>{{ $line->quantity }}</strong> × {{ \App\Support\Money::show(($line->unit_price->value ?? 0)) }}</p>
                                </div>
                                <div class="text-left font-bold text-sm text-gray-900 dark:text-gray-100">
                                    {{ \App\Support\Money::show(($line->sub_total->value ?? 0)) }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pricing Summary -->
                    <div class="p-5 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800 space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>{{ __('المجموع الفرعي:') }}</span>
                            <span>{{ \App\Support\Money::show(($order->sub_total->value ?? 0)) }}</span>
                        </div>
                        @if (($order->discount_total->value ?? 0) > 0)
                            <div class="flex justify-between text-emerald-600 dark:text-emerald-400 font-medium">
                                <span>{{ __('خصم الكوبون:') }}</span>
                                <span>-{{ \App\Support\Money::show(($order->discount_total->value ?? 0)) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-gray-600 dark:text-gray-300">
                            <span>{{ __('تكلفة الشحن:') }}</span>
                            <span>{{ \App\Support\Money::show(($order->shipping_total->value ?? 0)) }}</span>
                        </div>
                        @if ($order->cod_fee_minor > 0)
                            <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                <span>{{ __('رسوم الدفع عند الاستلام:') }}</span>
                                <span>{{ \App\Support\Money::show($order->cod_fee_minor) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-base font-bold text-gray-900 dark:text-gray-100 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <span>{{ __('صافي الإجمالي:') }}</span>
                            <span class="text-emerald-600 dark:text-emerald-400">{{ \App\Support\Money::show(($order->total->value ?? 0)) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Detailed Shipping Address & Notes -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm space-y-3">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 border-b border-gray-100 dark:border-gray-800 pb-2">{{ __('عنوان التوصيل وملاحظات العميل') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-300"><strong>{{ __('العنوان:') }}</strong> {{ $order->shippingAddress?->line_one ?? '-' }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-300"><strong>{{ __('المدينة / المحافظة:') }}</strong> {{ $order->shippingAddress?->city ?? '-' }}</p>
                    @if ($order->notes)
                        <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 rounded-lg text-xs text-amber-900 dark:text-amber-200">
                            <strong>{{ __('ملاحظات العميل المسجلة:') }}</strong> {{ $order->notes }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right: State Machine Timeline (order_events) -->
            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <span>{{ __('سجل حالات الطلب (Timeline)') }}</span>
                        <span class="text-xs font-normal text-gray-400">{{ $events->count() }} {{ __('حدث') }}</span>
                    </h3>

                    @if ($events->isEmpty())
                        <p class="text-xs text-gray-400 text-center py-6">{{ __('لا توجد أحداث مسجلة لهذا الطلب حتى الآن.') }}</p>
                    @else
                        <ol class="relative border-r border-gray-200 dark:border-gray-700 space-y-6 mr-3">
                            @foreach ($events as $event)
                                @php
                                    $toStatusEnum = \App\Domain\Orders\Enums\OrderStatus::tryFrom($event->to_status);
                                    $fromStatusEnum = $event->from_status ? \App\Domain\Orders\Enums\OrderStatus::tryFrom($event->from_status) : null;
                                @endphp
                                <li class="mr-4">
                                    <div class="absolute w-3 h-3 bg-emerald-500 rounded-full mt-1.5 -mr-1.5 border border-white dark:border-gray-900"></div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-bold text-gray-900 dark:text-gray-100">
                                            {{ $toStatusEnum?->label() ?? $event->to_status }}
                                        </span>
                                        <time class="text-[10px] text-gray-400">{{ $event->created_at->format('m/d H:i') }}</time>
                                    </div>

                                    @if ($fromStatusEnum)
                                        <p class="text-[11px] text-gray-400 mt-0.5">{{ __('انتقال من:') }} {{ $fromStatusEnum->label() }}</p>
                                    @endif

                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                        {{ __('بواسطة:') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ $event->actorLabel() }}</span>
                                    </p>

                                    @if ($event->reason_code)
                                        <div class="mt-1.5 inline-block px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-900">
                                            {{ __('السبب:') }} {{ $event->reason_code->label() }}
                                        </div>
                                    @endif

                                    @if ($event->note)
                                        <p class="mt-1.5 text-xs text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800/60 p-2 rounded border border-gray-100 dark:border-gray-800">
                                            {{ $event->note }}
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
