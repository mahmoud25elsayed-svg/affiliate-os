<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

    <!-- Filters Sidebar -->
    <aside class="space-y-6">
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-6 sticky top-24">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                <h3 class="font-bold text-gray-900 text-base">{{ __('تصفية المنتجات') }}</h3>
                <button type="button" wire:click="resetFilters" class="text-xs text-brand-600 hover:underline font-semibold">
                    {{ __('إعادة ضبط') }}
                </button>
            </div>

            <!-- Sort By -->
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">{{ __('الترتيب حسب') }}</label>
                <select wire:model.live="sort" class="w-full rounded-xl border-gray-200 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="latest">{{ __('الأحدث وصل حديثاً') }}</option>
                    <option value="price_asc">{{ __('السعر: من الأقل للأعلى') }}</option>
                    <option value="price_desc">{{ __('السعر: من الأعلى للأقل') }}</option>
                </select>
            </div>

            <!-- Brand Filter -->
            @if($brands->isNotEmpty())
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">{{ __('العلامة التجارية') }}</label>
                <select wire:model.live="brandId" class="w-full rounded-xl border-gray-200 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">{{ __('جميع العلامات') }}</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <!-- Price Range Filter -->
            <div class="space-y-3">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">{{ __('نطاق السعر (') }}{{ \App\Support\Money::symbol() }})</label>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <input type="number" wire:model.live.debounce.400ms="minPrice" placeholder="{{ __('من') }}" class="w-full rounded-xl border-gray-200 text-xs focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    <div>
                        <input type="number" wire:model.live.debounce.400ms="maxPrice" placeholder="{{ __('إلى') }}" class="w-full rounded-xl border-gray-200 text-xs focus:border-brand-600 focus:ring-brand-600">
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Products Grid -->
    <main class="lg:col-span-3 space-y-6">
        <div class="flex items-center justify-between text-xs text-gray-500 px-1">
            <span>{{ __('عرض') }} <strong>{{ $products->total() }}</strong> {{ __('منتج') }}</span>
            <div wire:loading class="text-brand-600 font-bold">{{ __('جاري التحديث...') }}</div>
        </div>

        @if($products->isNotEmpty())
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 sm:gap-6">
                @foreach($products as $product)
                    @php
                        $firstVariant = $product->variants->first();
                        $retailPrice = $firstVariant?->prices->first();
                        $pricing = $firstVariant ? $firstVariant->getRelationValue('wholesalePrice') : null;
                        $comparePrice = $pricing && $pricing->compare_at_price_minor ? \App\Support\Money::fromMinor($pricing->compare_at_price_minor) : null;
                    @endphp
                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-lg transition-all flex flex-col group">
                        <a href="{{ url('/products/'.$product->id) }}" class="block aspect-square bg-gray-100 overflow-hidden relative">
                            @if($product->thumbnail)
                                <img src="{{ $product->thumbnail->getUrl() }}" alt="{{ $product->translateAttribute('name') }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-4xl text-gray-300">📦</div>
                            @endif

                            @if($comparePrice && $retailPrice && $comparePrice->amount > $retailPrice->price->value)
                                <span class="absolute top-2.5 right-2.5 bg-red-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow">
                                    {{ __('خصم') }}
                                </span>
                            @endif
                        </a>

                        <div class="p-4 flex-1 flex flex-col justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 block mb-1 uppercase tracking-wider">
                                    {{ $product->brand?->name ?? __('متجرنا') }}
                                </span>
                                <h3 class="font-bold text-gray-900 text-sm line-clamp-2 group-hover:text-brand-600 transition-colors">
                                    <a href="{{ url('/products/'.$product->id) }}">
                                        {{ $product->translateAttribute('name') }}
                                    </a>
                                </h3>
                            </div>

                            <div class="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between">
                                <div>
                                    <span class="text-base font-black text-brand-600">
                                        {{ \App\Support\Money::show($retailPrice?->price) }}
                                    </span>
                                    @if($comparePrice && $retailPrice && $comparePrice->amount > $retailPrice->price->value)
                                        <span class="text-xs text-gray-400 line-through mr-1 block">
                                            {{ $comparePrice->format() }}
                                        </span>
                                    @endif
                                </div>
                                <a href="{{ url('/products/'.$product->id) }}" class="p-2 rounded-lg bg-brand-50 text-brand-600 hover:bg-brand-600 hover:text-white transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-6">
                {{ $products->links() }}
            </div>
        @else
            <div class="bg-white rounded-3xl p-12 text-center border border-gray-100 shadow-sm space-y-4">
                <span class="text-6xl block">📦</span>
                <h3 class="text-xl font-bold text-gray-800">{{ __('لا توجد منتجات مطابقة') }}</h3>
                <p class="text-sm text-gray-500">{{ __('جرب تعديل خيارات التصفية أو اختيار نطاق سعر أوسع.') }}</p>
                <button type="button" wire:click="resetFilters" class="px-6 py-2.5 bg-brand-600 text-white font-bold rounded-xl text-sm hover:bg-brand-700 transition-colors">
                    {{ __('إعادة ضبط الفلاتر') }}
                </button>
            </div>
        @endif
    </main>

</div>
