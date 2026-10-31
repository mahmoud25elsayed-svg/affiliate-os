<div class="space-y-6 pt-4 border-t border-gray-100">

    <!-- Promotional Notice (P14) -->
    @if(isset($promotional) && $promotional && $promotional['is_promotional'])
        @if($promotional['promotion_type'] === 'flash_sale' && $promotional['flash_sale_item'])
            @php
                $fItem = $promotional['flash_sale_item'];
                $rem = max(0, $fItem->allocated_quantity - $fItem->sold_quantity);
            @endphp
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-2xl animate-pulse">⚡</span>
                    <div>
                        <h4 class="text-xs font-black text-red-600 uppercase">عرض الفلاش سيل مفعّل!</h4>
                        <p class="text-[11px] text-red-700">سعر خاص ومحدود، متبقي {{ $rem }} قطعة فقط بالسعر المخفض.</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 bg-red-600 text-white rounded-lg text-xs font-black">
                    وفّر {{ \App\Support\Money::fromMinor($promotional['original_price_minor'] - $promotional['price_minor'])->format('ar') }}
                </span>
            </div>
        @elseif($promotional['promotion_type'] === 'daily_deal')
            <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 flex items-center gap-3">
                <span class="text-2xl">⭐</span>
                <div>
                    <h4 class="text-xs font-black text-amber-700 uppercase">صفقة اليوم الخاصة!</h4>
                    <p class="text-[11px] text-amber-800">ينتهي العرض بنهاية اليوم أو حتى نفاد الكمية.</p>
                </div>
            </div>
        @endif
    @endif

    <!-- Price Section -->
    <div class="flex items-baseline gap-3">
        @if(! empty($linkedPriceMinor))
            {{-- Price from the affiliate link the visitor came through; this is what checkout charges. --}}
            <span class="text-3xl font-black text-brand-600">
                {{ \App\Support\Money::fromMinor($linkedPriceMinor)->format('ar') }}
            </span>
        @elseif(isset($promotional) && $promotional && $promotional['is_promotional'])
            <span class="text-3xl font-black text-red-600">
                {{ \App\Support\Money::fromMinor($promotional['price_minor'])->format('ar') }}
            </span>
            <span class="text-sm text-gray-400 line-through">
                {{ \App\Support\Money::fromMinor($promotional['original_price_minor'])->format('ar') }}
            </span>
            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-bold">
                تخفيض حصري
            </span>
        @else
            <span class="text-3xl font-black text-brand-600">
                {{ $price ? $price->price->formatted() : '0.00 ج.م' }}
            </span>
            @if($pricing && $pricing->compare_at_price_minor && $price && $pricing->compare_at_price_minor > $price->price->value)
                @php
                    $compareMoney = \App\Support\Money::fromMinor($pricing->compare_at_price_minor);
                @endphp
                <span class="text-sm text-gray-400 line-through">
                    {{ $compareMoney->format('ar') }}
                </span>
                <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-bold">
                    وفر {{ \App\Support\Money::fromMinor($pricing->compare_at_price_minor - $price->price->value)->format('ar') }}
                </span>
            @endif
        @endif
    </div>

    <!-- SKU & Digital Notice -->
    <div class="flex items-center gap-4 text-xs text-gray-500 font-mono">
        <span>كود المنتج (SKU): <strong>{{ $selectedVariant?->sku ?? '-' }}</strong></span>
        @if($pricing && $pricing->is_digital)
            <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-sans font-bold">📥 منتج رقمي للتحميل المباشر</span>
        @endif
    </div>

    <!-- Quantity & Action Buttons -->
    <div class="space-y-4 pt-2">
        <div class="flex items-center gap-4">
            <span class="text-sm font-bold text-gray-700">الكمية:</span>
            <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden bg-gray-50">
                <button type="button" wire:click="decrement" class="px-3 py-2 text-gray-600 hover:bg-gray-200 transition-colors font-bold text-base select-none">
                    -
                </button>
                <span class="px-4 py-2 font-bold text-gray-900 text-sm bg-white min-w-[40px] text-center">
                    {{ $quantity }}
                </span>
                <button type="button" wire:click="increment" class="px-3 py-2 text-gray-600 hover:bg-gray-200 transition-colors font-bold text-base select-none">
                    +
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
            <button type="button" wire:click="addToCart" class="w-full py-4 px-6 bg-brand-50 text-brand-600 hover:bg-brand-600 hover:text-white font-bold rounded-2xl transition-all shadow-sm flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span>أضف إلى السلة</span>
            </button>

            <button type="button" wire:click="buyNow" class="w-full py-4 px-6 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-2xl transition-all shadow-lg shadow-brand-600/30 flex items-center justify-center gap-2">
                <span>اشتري الآن (شراء فوري)</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </div>

</div>
