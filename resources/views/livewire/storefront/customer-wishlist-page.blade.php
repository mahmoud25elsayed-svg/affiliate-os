<div class="py-10 bg-gray-50 min-h-[75vh]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">حسابي</h1>
            <p class="text-sm text-gray-500 mt-1">قائمة المنتجات المحفوظة في المفضلة</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Sidebar Navigation -->
            <div class="md:col-span-1">
                <nav class="bg-white rounded-2xl shadow-sm border border-gray-100 p-2 space-y-1">
                    <a href="{{ route('storefront.account.orders') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>طلباتي</span>
                    </a>
                    <a href="{{ route('storefront.account.addresses') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>عناويني</span>
                    </a>
                    <a href="{{ route('storefront.account.wishlist') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-emerald-50 text-emerald-800">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span>المفضلة</span>
                    </a>
                    <a href="{{ route('storefront.account.profile') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>الملف الشخصي</span>
                    </a>
                </nav>
            </div>

            <!-- Wishlist Content -->
            <div class="md:col-span-3 space-y-4">
                @if ($successMessage)
                    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl">
                        {{ $successMessage }}
                    </div>
                @endif

                @if ($items->isEmpty())
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 mb-1">قائمة المفضلة فارغة</h3>
                        <p class="text-sm text-gray-500 mb-6">احفظ منتجاتك المفضلة هنا لشرائها في أي وقت لاحقاً.</p>
                        <a href="{{ route('storefront.products.index') }}"
                            class="inline-flex items-center px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm transition-colors">
                            استكشف المنتجات
                        </a>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($items as $item)
                            @php
                                $product = $item->product;
                                $name = $product instanceof \Lunar\Models\Product ? ($product->translateAttribute('name') ?? 'منتج') : 'منتج';
                                $thumbnail = $product instanceof \Lunar\Models\Product ? $product->media->first() : null;
                                $imageUrl = $thumbnail instanceof \Spatie\MediaLibrary\MediaCollections\Models\Media ? $thumbnail->getUrl() : null;
                                $firstVariant = $product instanceof \Lunar\Models\Product ? $product->variants->first() : null;
                                $price = $firstVariant instanceof \Lunar\Models\ProductVariant ? $firstVariant->prices->first() : null;
                                $priceMinor = $price instanceof \Lunar\Models\Price ? $price->price->value : 0;
                            @endphp
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col justify-between">
                                <div>
                                    <div class="aspect-square bg-gray-50 relative overflow-hidden">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="{{ $name }}" class="w-full h-full object-cover" />
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        @endif
                                        <button type="button" wire:click="removeFromWishlist({{ $item->id }})"
                                            class="absolute top-2 left-2 p-1.5 rounded-full bg-white/90 hover:bg-white text-red-500 shadow-sm transition-colors">
                                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="p-4">
                                        <a href="{{ route('storefront.products.show', $product->id) }}" class="font-semibold text-sm text-gray-900 hover:text-emerald-600 line-clamp-1">
                                            {{ $name }}
                                        </a>
                                        <p class="font-bold text-emerald-700 text-sm mt-1">
                                            {{ number_format($priceMinor / 100, 2) }} ج.م
                                        </p>
                                    </div>
                                </div>
                                <div class="p-4 pt-0">
                                    <button type="button" wire:click="addToCart({{ $product->id }})"
                                        class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-colors flex items-center justify-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                        <span>أضف للسلة</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
