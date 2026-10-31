@extends('layouts.storefront')

@section('title', ($product->translateAttribute('name') ?? 'منتج') . ' - ' . ($general->platform_name ?? 'متجرنا'))
@section('meta_description', strip_tags((string) ($product->translateAttribute('description') ?? $product->translateAttribute('name'))))
@section('og_type', 'product')
@section('og_image', $product->thumbnail ? $product->thumbnail->getUrl() : '')

@section('schema')
    <script type="application/ld+json">
        {!! $productSchema !!}
    </script>
    <script type="application/ld+json">
        {!! $breadcrumbSchema !!}
    </script>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-12">

    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-gray-500 font-medium overflow-x-auto whitespace-nowrap">
        @foreach($breadcrumbs as $index => $item)
            @if($index > 0)
                <span>/</span>
            @endif
            @if(!$loop->last)
                <a href="{{ $item['url'] }}" class="hover:text-brand-600 transition-colors">{{ $item['name'] }}</a>
            @else
                <span class="text-gray-900 font-bold truncate">{{ $item['name'] }}</span>
            @endif
        @endforeach
    </nav>

    <!-- Product Showcase -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm">

        <!-- Product Image Gallery -->
        <div class="space-y-4">
            <div class="aspect-square bg-gray-50 rounded-2xl overflow-hidden border border-gray-100 flex items-center justify-center">
                @if($product->thumbnail)
                    <img src="{{ $product->thumbnail->getUrl() }}" alt="{{ $product->translateAttribute('name') }}" class="w-full h-full object-cover" loading="lazy">
                @else
                    <span class="text-7xl text-gray-300">📦</span>
                @endif
            </div>

            <!-- Thumbnails if multiple media exist -->
            @if($product->media->count() > 1)
                <div class="flex items-center gap-3 overflow-x-auto pb-2">
                    @foreach($product->media as $media)
                        <button type="button" class="w-16 h-16 rounded-xl border-2 border-transparent hover:border-brand-600 overflow-hidden flex-shrink-0 bg-gray-50">
                            <img src="{{ $media->getUrl() }}" class="w-full h-full object-cover" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Product Info & Purchase Actions -->
        <div class="space-y-6 flex flex-col justify-between">
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                        {{ $product->brand?->name ?? 'متجرنا' }}
                    </span>
                    <span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full border border-emerald-100">
                        متوفر في المخزون
                    </span>
                </div>

                <h1 class="text-2xl sm:text-4xl font-black text-gray-900 leading-tight">
                    {{ $product->translateAttribute('name') ?? 'منتج' }}
                </h1>

                <!-- Livewire Variant Picker and Add to Cart -->
                <livewire:storefront.product-variant-picker :product="$product" />

                <div class="pt-2">
                    <livewire:storefront.wishlist-button :product-id="$product->id" />
                </div>
            </div>

            <!-- Trust Badges -->
            <div class="grid grid-cols-3 gap-3 pt-6 border-t border-gray-100 text-center">
                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 space-y-1">
                    <span class="text-xl">🚚</span>
                    <h5 class="text-xs font-bold text-gray-800">شحن سريع</h5>
                    <p class="text-[10px] text-gray-500">خلال 2 - 4 أيام</p>
                </div>
                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 space-y-1">
                    <span class="text-xl">🔍</span>
                    <h5 class="text-xs font-bold text-gray-800">معاينة عند الاستلام</h5>
                    <p class="text-[10px] text-gray-500">افتح الطلب وافحصه</p>
                </div>
                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 space-y-1">
                    <span class="text-xl">🔄</span>
                    <h5 class="text-xs font-bold text-gray-800">استبدال واسترجاع</h5>
                    <p class="text-[10px] text-gray-500">خلال 14 يوماً</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details & Specifications Tabs -->
    <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
        <h2 class="text-xl font-bold text-gray-900 border-b border-gray-100 pb-4">وصف المنتج ومواصفاته</h2>
        <div class="prose prose-sm max-w-none text-gray-600 leading-relaxed">
            @if($product->translateAttribute('description'))
                {!! nl2br(e($product->translateAttribute('description'))) !!}
            @else
                <p>منتج عالي الجودة تم اختياره بعناية ليلبي كافة تطلعاتك بأفضل سعر وتجربة استخدام مميزة.</p>
            @endif
        </div>
    </div>

    <!-- Product Reviews & Ratings (P10) -->
    <livewire:storefront.product-reviews :product-id="$product->id" />

    <!-- Related Products -->
    @if($relatedProducts->isNotEmpty())
    <div class="space-y-6">
        <h2 class="text-2xl font-black text-gray-900">منتجات مشابهة قد تهمك</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">
            @foreach($relatedProducts as $related)
                @php
                    $relVariant = $related->variants->first();
                    $relPrice = $relVariant?->prices->first();
                @endphp
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col">
                    <a href="{{ url('/products/'.$related->id) }}" class="block aspect-square bg-gray-100 overflow-hidden">
                        @if($related->thumbnail)
                            <img src="{{ $related->thumbnail->getUrl() }}" alt="{{ $related->translateAttribute('name') }}" class="w-full h-full object-cover" loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-gray-300">📦</div>
                        @endif
                    </a>
                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <h4 class="font-bold text-gray-900 text-xs sm:text-sm line-clamp-2">
                            <a href="{{ url('/products/'.$related->id) }}">{{ $related->translateAttribute('name') }}</a>
                        </h4>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-sm font-black text-brand-600">{{ \App\Support\Money::show($relPrice?->price) }}</span>
                            <a href="{{ url('/products/'.$related->id) }}" class="text-xs text-brand-600 font-bold hover:underline">عرض</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
