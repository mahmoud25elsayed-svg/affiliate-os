<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex items-center text-sm text-slate-500 mb-8" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}" class="hover:text-emerald-600 transition">{{ __('الرئيسية') }}</a>
        <span class="mx-2 text-slate-300">/</span>
        <a href="{{ route('storefront.cart') }}" class="hover:text-emerald-600 transition">{{ __('السلة') }}</a>
        <span class="mx-2 text-slate-300">/</span>
        <span class="text-slate-900 font-semibold">{{ __('إتمام الطلب') }}</span>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">{{ __('إتمام الشراء والدفع') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('أدخل بيانات التوصيل وأكد طلبك في خطوة واحدة سريعة.') }}</p>
    </div>

    @if($count === 0)
        <div class="bg-white rounded-3xl border border-slate-100 p-12 text-center max-w-lg mx-auto shadow-sm">
            <div class="w-16 h-16 mx-auto mb-4 bg-slate-100 rounded-full flex items-center justify-center text-slate-400">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-slate-900 mb-2">{{ __('سلتك فارغة') }}</h2>
            <p class="text-slate-500 text-sm mb-6">{{ __('لا يمكنك إتمام الطلب لأن سلة التسوق فارغة حالياً.') }}</p>
            <a href="{{ route('storefront.products.index') }}" class="inline-flex px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold text-sm hover:bg-emerald-700 transition">
                {{ __('تصفح المنتجات') }}
            </a>
        </div>
    @else
        <form wire:submit.prevent="submitOrder" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Left 7 columns: Delivery & Payment Details -->
            <div class="lg:col-span-7 space-y-6">
                <!-- Delivery Info Card -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 sm:p-8 space-y-6">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                            1
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ __('بيانات التوصيل') }}</h2>
                            <p class="text-xs text-slate-400">{{ __('أدخل اسمك ورقم هاتفك لتأكيد الطلب') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Customer Name -->
                        <div class="sm:col-span-2">
                            <label for="customer_name" class="block text-xs font-bold text-slate-700 mb-1.5">{{ __('الاسم بالكامل *') }}</label>
                            <input wire:model="customer_name" type="text" id="customer_name" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition text-sm text-slate-900 @error('customer_name') border-red-500 @enderror" placeholder="{{ __('مثال: أحمد محمد علي') }}">
                            @error('customer_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Customer Phone -->
                        <div class="sm:col-span-2">
                            <label for="customer_phone" class="block text-xs font-bold text-slate-700 mb-1.5">{{ __('رقم الموبايل *') }}</label>
                            <input wire:model="customer_phone" type="tel" id="customer_phone" dir="ltr" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition text-sm text-slate-900 text-right @error('customer_phone') border-red-500 @enderror" placeholder="{{ \App\Support\Location\Market::country()->example }}">
                            <p class="text-xs text-slate-400 mt-1">{{ __('سيتم التواصل معك عبر الهاتف أو الواتساب لتأكيد موعد التسليم.') }}</p>
                            @error('customer_phone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Governorate -->
                        <div>
                            <label for="governorate_id" class="block text-xs font-bold text-slate-700 mb-1.5">{{ __('المحافظة *') }}</label>
                            <select wire:model.live="governorate_id" id="governorate_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition text-sm text-slate-900 bg-white">
                                @foreach($governorates as $gov)
                                    <option value="{{ $gov->id }}">{{ $gov->label() }}</option>
                                @endforeach
                            </select>
                            @error('governorate_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- City -->
                        <div>
                            <label for="city" class="block text-xs font-bold text-slate-700 mb-1.5">{{ __('المدينة / المنطقة *') }}</label>
                            <input wire:model="city" type="text" id="city" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition text-sm text-slate-900 @error('city') border-red-500 @enderror" placeholder="{{ __('مثال: مدينة نصر / سموحة') }}">
                            @error('city') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Full Address -->
                        <div class="sm:col-span-2">
                            <label for="address" class="block text-xs font-bold text-slate-700 mb-1.5">{{ __('العنوان بالتفصيل *') }}</label>
                            <input wire:model="address" type="text" id="address" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition text-sm text-slate-900 @error('address') border-red-500 @enderror" placeholder="{{ __('اسم الشارع، رقم العقار، رقم الشقة أو علامة مميزة') }}">
                            @error('address') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Delivery Notes -->
                        <div class="sm:col-span-2">
                            <label for="notes" class="block text-xs font-bold text-slate-700 mb-1.5">{{ __('ملاحظات إضافية للتوصيل (اختياري)') }}</label>
                            <textarea wire:model="notes" id="notes" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition text-sm text-slate-900" placeholder="{{ __('أي تعليمات خاصة للمندوب...') }}"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Card -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 sm:p-8 space-y-4">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                            2
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ __('طريقة الدفع') }}</h2>
                            <p class="text-xs text-slate-400">{{ __('اختر طريقة الدفع المناسبة لك') }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($activeGateways as $gatewayKey => $gatewayName)
                            <label class="relative flex items-center gap-4 p-4 rounded-xl border-2 transition cursor-pointer {{ $payment_method === $gatewayKey ? 'border-emerald-500 bg-emerald-50/20 shadow-sm' : 'border-slate-100 hover:border-slate-200' }}">
                                <input type="radio" wire:model.live="payment_method" value="{{ $gatewayKey }}" class="w-5 h-5 text-emerald-600 focus:ring-emerald-500">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-bold text-slate-900">{{ $gatewayName }}</span>
                                        @if($gatewayKey === 'cod' && $settings->allow_cod && $settings->cod_fee_minor > 0)
                                            <span class="text-xs font-semibold text-slate-500">+ {{ $codFeeDecimal }} {{ \App\Support\Money::symbol() }} {{ __('رسم دفع') }}</span>
                                        @elseif($gatewayKey === 'paymob')
                                            <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">{{ __('كروت ومحافظ وإنستاباي') }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        @if($gatewayKey === 'cod')
                                            {{ __('ادفع نقدًا لمندوب التوصيل بعد استلام ومعاينة طلبك بأمان.') }}
                                        @elseif($gatewayKey === 'paymob')
                                            {{ __('دفع فوري آمن ومباشر عبر الفيزا والماستركارد أو المحافظ الإلكترونية.') }}
                                        @else
                                            {{ __('دفع إلكتروني فوري ومباشر.') }}
                                        @endif
                                    </p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right 5 columns: Order Summary, Coupon & Confirm -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 sm:p-8 space-y-6">
                    <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4">{{ __('ملخص الطلب (') }}{{ $count }} {{ __('منتجات)') }}</h2>

                    <!-- Items list snapshot -->
                    <div class="divide-y divide-slate-100 max-h-60 overflow-y-auto pl-1">
                        @foreach($items as $item)
                            <div class="py-3 flex items-center justify-between gap-3 text-sm">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-7 h-7 bg-slate-100 rounded-lg flex items-center justify-center text-xs font-bold text-slate-600 flex-shrink-0">
                                        {{ $item['quantity'] }}x
                                    </span>
                                    <span class="text-slate-800 font-medium truncate">{{ $item['name'] }}</span>
                                </div>
                                <span class="text-slate-900 font-bold whitespace-nowrap">
                                    {{ \App\Support\Money::show($item['subtotal_minor']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <!-- Coupon Code Input -->
                    <div class="border-t border-slate-100 pt-4 space-y-2">
                        <label for="coupon_code" class="block text-xs font-bold text-slate-700">{{ __('كوبون الخصم') }}</label>
                        @if($applied_coupon_code)
                            <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-xl border border-emerald-200">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-sm font-bold text-emerald-800">{{ $applied_coupon_code }}</span>
                                </div>
                                <button wire:click="removeCoupon" type="button" class="text-xs font-bold text-red-500 hover:text-red-700 transition">
                                    {{ __('إلغاء') }}
                                </button>
                            </div>
                        @else
                            <div class="flex gap-2">
                                <input wire:model="coupon_code" type="text" id="coupon_code" placeholder="{{ __('أدخل كود الكوبون') }}" class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-emerald-600 uppercase">
                                <button wire:click="applyCoupon" type="button" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition">
                                    {{ __('تطبيق') }}
                                </button>
                            </div>
                        @endif

                        @if($coupon_error)
                            <p class="text-xs text-red-500">{{ $coupon_error }}</p>
                        @endif
                        @if($coupon_success)
                            <p class="text-xs text-emerald-600 font-semibold">{{ $coupon_success }}</p>
                        @endif
                    </div>

                    <!-- Financial Breakdown (Server Calculated) -->
                    <div class="border-t border-slate-100 pt-4 space-y-3 text-sm">
                        <div class="flex justify-between text-slate-600">
                            <span>{{ __('المجموع الفرعي:') }}</span>
                            <span class="font-bold text-slate-900">{{ $subtotalDecimal }} {{ \App\Support\Money::symbol() }}</span>
                        </div>

                        @if($discount_minor > 0)
                            <div class="flex justify-between text-emerald-600 font-semibold">
                                <span>{{ __('خصم الكوبون:') }}</span>
                                <span>- {{ $discountDecimal }} {{ \App\Support\Money::symbol() }}</span>
                            </div>
                        @endif

                        <div class="flex justify-between text-slate-600">
                            <div>
                                <span>{{ __('تكلفة الشحن:') }}</span>
                                <span class="text-xs text-slate-400 block">{{ $shippingQuote->delivery_time_ar }}</span>
                            </div>
                            <div class="text-left">
                                @if($shippingQuote->is_free)
                                    <span class="bg-emerald-100 text-emerald-800 text-xs px-2 py-0.5 rounded-full font-bold">{{ __('مجاني') }}</span>
                                @else
                                    <span class="font-bold text-slate-900">{{ $shippingDecimal }} {{ \App\Support\Money::symbol() }}</span>
                                @endif
                            </div>
                        </div>

                        @if($payment_method === 'cod' && $codFeeDecimal > 0)
                            <div class="flex justify-between text-slate-600">
                                <span>{{ __('رسم الدفع عند الاستلام:') }}</span>
                                <span class="font-bold text-slate-900">{{ $codFeeDecimal }} {{ \App\Support\Money::symbol() }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Final Total -->
                    <div class="border-t-2 border-slate-900/10 pt-4 flex justify-between items-center text-xl font-black text-slate-900">
                        <span>{{ __('الإجمالي الكلي:') }}</span>
                        <span class="text-emerald-600 text-2xl font-black">{{ $totalDecimal }} {{ \App\Support\Money::symbol() }}</span>
                    </div>

                    <!-- Error Alert -->
                    @if($errors->has('cart'))
                        <div class="p-3 bg-red-50 text-red-700 text-xs rounded-xl border border-red-200">
                            {{ $errors->first('cart') }}
                        </div>
                    @endif

                    <!-- Place Order Button -->
                    <button type="submit" wire:loading.attr="disabled" class="w-full flex items-center justify-center py-4 px-6 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-base font-extrabold transition shadow-lg shadow-emerald-600/30">
                        <span wire:loading.remove>{{ __('تأكيد الطلب الآن') }}</span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            {{ __('جاري تأكيد الطلب...') }}
                        </span>
                    </button>

                    <div class="flex items-center justify-center gap-2 text-xs text-slate-400 text-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span>{{ __('تسوق آمن ومحمي 100% — الدفع عند الاستلام بعد المعاينة') }}</span>
                    </div>
                </div>
            </div>
        </form>
    @endif
</div>
