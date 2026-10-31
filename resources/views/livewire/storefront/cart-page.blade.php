<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex items-center text-sm text-slate-500 mb-8" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}" class="hover:text-emerald-600 transition">الرئيسية</a>
        <span class="mx-2 text-slate-300">/</span>
        <span class="text-slate-900 font-semibold">سلة المشتريات</span>
    </nav>

    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">سلة المشتريات</h1>
        @if($count > 0)
            <button wire:click="clearCart" type="button" class="text-sm font-semibold text-red-500 hover:text-red-700 transition">
                إفراغ السلة
            </button>
        @endif
    </div>

    @if($count > 0)
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Items Table/List -->
            <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden divide-y divide-slate-100">
                @foreach($items as $item)
                    <div class="p-6 flex flex-col sm:flex-row items-center justify-between gap-6 hover:bg-slate-50/50 transition">
                        <div class="flex items-center gap-4 w-full sm:w-auto">
                            <div class="w-20 h-20 rounded-xl bg-slate-50 border border-slate-100 flex-shrink-0 overflow-hidden flex items-center justify-center">
                                @if($item['image_url'])
                                    <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="w-full h-full object-cover">
                                @else
                                    <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">{{ $item['name'] }}</h3>
                                <p class="text-xs text-slate-400 mt-1">كود المنتج: {{ $item['sku'] }}</p>
                                <span class="text-sm font-semibold text-slate-600 block mt-1">
                                    {{ \App\Support\Money::show($item['unit_price_minor']) }} للقطعة
                                </span>
                            </div>
                        </div>

                        <!-- Quantity and Subtotal -->
                        <div class="flex items-center justify-between w-full sm:w-auto sm:gap-8">
                            <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
                                <button wire:click="decrement({{ $item['variant_id'] }})" type="button" class="px-3 py-1.5 bg-slate-50 text-slate-600 hover:bg-slate-100 transition font-bold">-</button>
                                <span class="px-4 py-1 text-sm font-bold text-slate-800">{{ $item['quantity'] }}</span>
                                <button wire:click="increment({{ $item['variant_id'] }})" type="button" class="px-3 py-1.5 bg-slate-50 text-slate-600 hover:bg-slate-100 transition font-bold">+</button>
                            </div>

                            <div class="text-left">
                                <span class="text-base font-extrabold text-emerald-600 block">
                                    {{ \App\Support\Money::show($item['subtotal_minor']) }}
                                </span>
                            </div>

                            <button wire:click="removeItem({{ $item['variant_id'] }})" type="button" class="text-slate-300 hover:text-red-500 transition p-2 rounded-lg" title="حذف">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Summary Card -->
            <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-6">
                <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4">ملخص الطلب</h2>

                <div class="space-y-3">
                    <div class="flex justify-between text-sm text-slate-600">
                        <span>إجمالي المنتجات:</span>
                        <span class="font-bold text-slate-900">{{ $subtotalDecimal }} {{ \App\Support\Money::symbol() }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-slate-600">
                        <span>تكلفة الشحن:</span>
                        <span class="text-emerald-600 font-semibold">تُحسب في الخطوة التالية</span>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4 flex justify-between items-center text-lg font-black text-slate-900">
                    <span>المجموع المقدر:</span>
                    <span class="text-emerald-600">{{ $subtotalDecimal }} {{ \App\Support\Money::symbol() }}</span>
                </div>

                <div class="space-y-3 pt-2">
                    <a href="{{ route('storefront.checkout') }}" class="w-full flex items-center justify-center py-4 px-6 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-base font-bold transition shadow-lg shadow-emerald-600/25">
                        المتابعة إلى الدفع
                    </a>
                    <a href="{{ route('storefront.products.index') }}" class="w-full flex items-center justify-center py-3 px-6 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold transition">
                        متابعة التسوق
                    </a>
                </div>
            </div>
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white rounded-3xl border border-slate-100 p-12 text-center max-w-lg mx-auto shadow-sm">
            <div class="w-20 h-20 mx-auto mb-6 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-slate-900 mb-2">سلة المشتريات فارغة</h2>
            <p class="text-slate-500 text-sm mb-8 leading-relaxed">
                لم تقم بإضافة أي منتجات إلى سلتك بعد. استكشف أفضل العروض والمنتجات المميزة في متجرنا الآن!
            </p>
            <a href="{{ route('storefront.products.index') }}" class="inline-flex items-center justify-center px-8 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-sm transition shadow-lg shadow-emerald-600/20">
                تصفح المنتجات
            </a>
        </div>
    @endif
</div>
