<div>
    <!-- Cart trigger button in header -->
    <button wire:click="toggle" type="button" class="relative p-2.5 rounded-full text-slate-700 hover:text-emerald-600 hover:bg-slate-100 transition focus:outline-none" aria-label="سلة المشتريات">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
        </svg>
        @if($count > 0)
            <span class="absolute -top-1 -right-1 bg-emerald-600 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center animate-pulse">
                {{ $count }}
            </span>
        @endif
    </button>

    <!-- Drawer Overlay & Panel -->
    @if($isOpen)
        <div class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
            <!-- Background backdrop -->
            <div wire:click="close" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>

            <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col justify-between">
                    <!-- Drawer Header -->
                    <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center space-x-3 space-x-reverse">
                            <h2 class="text-lg font-bold text-slate-900" id="slide-over-title">سلة المشتريات</h2>
                            <span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-0.5 rounded-full font-semibold">
                                {{ $count }} منتج
                            </span>
                        </div>
                        <button wire:click="close" type="button" class="text-slate-400 hover:text-slate-600 transition p-1 rounded-lg">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Items List -->
                    <div class="flex-1 overflow-y-auto px-6 py-4 divide-y divide-slate-100">
                        @forelse($items as $item)
                            <div class="py-4 flex gap-4 items-center">
                                <div class="w-16 h-16 rounded-xl bg-slate-50 border border-slate-100 flex-shrink-0 overflow-hidden flex items-center justify-center">
                                    @if($item['image_url'])
                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-semibold text-slate-900 truncate">{{ $item['name'] }}</h4>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $item['sku'] }}</p>
                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-sm font-bold text-emerald-600">
                                            {{ \App\Support\Money::show($item['subtotal_minor']) }}
                                        </span>
                                        <!-- Quantity controls -->
                                        <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden">
                                            <button wire:click="decrement({{ $item['variant_id'] }})" type="button" class="px-2 py-1 bg-slate-50 text-slate-600 hover:bg-slate-100 transition text-xs font-bold">-</button>
                                            <span class="px-3 text-xs font-bold text-slate-800">{{ $item['quantity'] }}</span>
                                            <button wire:click="increment({{ $item['variant_id'] }})" type="button" class="px-2 py-1 bg-slate-50 text-slate-600 hover:bg-slate-100 transition text-xs font-bold">+</button>
                                        </div>
                                    </div>
                                </div>
                                <button wire:click="removeItem({{ $item['variant_id'] }})" type="button" class="text-slate-300 hover:text-red-500 transition p-1" title="حذف">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        @empty
                            <div class="py-16 text-center">
                                <div class="w-16 h-16 mx-auto mb-4 bg-slate-100 rounded-full flex items-center justify-center text-slate-400">
                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-slate-800">سلتك فارغة حالياً</h3>
                                <p class="text-sm text-slate-500 mt-1">تصفح منتجاتنا وأضف ما يعجبك إلى سلتك!</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Footer / Checkout button -->
                    @if($count > 0)
                        <div class="p-6 border-t border-slate-100 bg-slate-50 space-y-4">
                            <div class="flex items-center justify-between text-base font-bold text-slate-900">
                                <span>المجموع الفرعي:</span>
                                <span class="text-lg text-emerald-600">{{ $subtotalDecimal }} {{ \App\Support\Money::symbol() }}</span>
                            </div>
                            <p class="text-xs text-slate-500">الشحن والخصومات يتم حسابها بدقة عند إتمام الطلب.</p>
                            <div class="grid grid-cols-2 gap-3">
                                <a href="{{ route('storefront.cart') }}" class="w-full text-center py-3 px-4 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 bg-white hover:bg-slate-100 transition shadow-sm">
                                    عرض السلة
                                </a>
                                <a href="{{ route('storefront.checkout') }}" class="w-full text-center py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold transition shadow-lg shadow-emerald-600/20">
                                    إتمام الطلب
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
