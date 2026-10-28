@extends('layouts.storefront')

@section('title', ($general->platform_name ?? 'بوتيك') . ' - ' . ($theme->hero_headline ?? 'مجموعات فاخرة وتصاميم مميزة'))
@section('meta_description', $theme->hero_subheadline ?? 'اكتشف أرقى التشكيلات والمنتجات العصرية مع تجربة تسوق فريدة وتوصيل حتى باب منزلك')

@section('schema')
    <script type="application/ld+json">
        {!! $orgSchema !!}
    </script>
@endsection

@section('content')
<div class="space-y-16 pb-20 bg-stone-50/50">

    <!-- 1. Boutique Hero Banner -->
    @if($theme->show_hero_banner)
    <section class="relative bg-gradient-to-b from-stone-900 via-stone-850 to-stone-900 text-stone-150 py-20 sm:py-28 overflow-hidden border-b border-stone-800">
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#d4af37_1px,transparent_1px)] [background-size:16px_16px]"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7 space-y-6 text-center lg:text-right">
                    <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide bg-amber-500/10 text-amber-300 border border-amber-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        تشكيلة الموسم الحصرية • بوتيك فاخر
                    </span>
                    <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight leading-[1.2]">
                        {{ $theme->hero_headline }}
                    </h1>
                    <p class="text-base sm:text-lg text-stone-300 max-w-xl mx-auto lg:mx-0 leading-relaxed font-light">
                        {{ $theme->hero_subheadline }}
                    </p>
                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-4">
                        <a href="{{ url($theme->hero_button_url) }}"
                           class="px-8 py-4 bg-amber-600 hover:bg-amber-500 text-stone-950 font-black rounded-full shadow-lg shadow-amber-600/20 transition-all hover:-translate-y-0.5">
                            {{ $theme->hero_button_text }}
                        </a>
                        <a href="{{ url('/products') }}"
                           class="px-8 py-4 bg-stone-800/80 hover:bg-stone-700 text-white font-medium rounded-full border border-stone-700 transition-all">
                            استكشف المجموعة الكاملة
                        </a>
                    </div>
                </div>

                @if(!empty($theme->hero_image_url))
                <div class="lg:col-span-5 flex justify-center">
                    <div class="relative group">
                        <div class="absolute -inset-2 bg-gradient-to-r from-amber-500/20 to-stone-500/20 rounded-3xl blur-xl opacity-70 group-hover:opacity-100 transition duration-700"></div>
                        <img src="{{ $theme->hero_image_url }}" alt="{{ $theme->hero_headline }}" class="relative rounded-3xl object-cover shadow-2xl max-h-[440px] w-auto border border-stone-700/50">
                    </div>
                </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    <!-- 2. Boutique Trust Badges -->
    @if($theme->show_features_badges && !empty($theme->features_badges))
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 bg-white p-8 rounded-3xl border border-stone-200/80 shadow-sm">
            @foreach($theme->features_badges as $badge)
            <div class="flex items-start gap-4 p-2">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 border border-amber-200/60 flex items-center justify-center text-amber-700 flex-shrink-0 text-xl font-bold">
                    @if($badge['icon'] === 'truck')
                        🚚
                    @elseif($badge['icon'] === 'shield')
                        🛡️
                    @elseif($badge['icon'] === 'refresh')
                        🔄
                    @else
                        💎
                    @endif
                </div>
                <div>
                    <h4 class="text-sm font-bold text-stone-900">{{ $badge['title'] }}</h4>
                    <p class="text-xs text-stone-500 mt-1 leading-relaxed">{{ $badge['subtitle'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <!-- 3. Featured Categories -->
    @if($theme->show_featured_categories && $categories->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-end justify-between border-b border-stone-200 pb-4">
            <div>
                <span class="text-xs font-bold text-amber-700 uppercase tracking-widest">تصفح حسب الذوق</span>
                <h3 class="text-2xl font-black text-stone-900 mt-1">المجموعات المختارة</h3>
            </div>
            <a href="{{ url('/products') }}" class="text-xs font-bold text-stone-600 hover:text-stone-900 transition flex items-center gap-1">
                <span>عرض الكل</span>
                <span>←</span>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach($categories as $category)
            <a href="{{ route('storefront.categories.show', $category->id) }}"
               class="group p-5 bg-white hover:bg-amber-50/50 rounded-2xl border border-stone-200 hover:border-amber-300 transition-all text-center flex flex-col items-center">
                <div class="w-16 h-16 rounded-full bg-stone-100 group-hover:bg-amber-100/60 flex items-center justify-center text-2xl transition mb-3">
                    ✨
                </div>
                <h4 class="text-xs font-bold text-stone-800 group-hover:text-amber-800 transition">
                    {{ $category->translateAttribute('name') }}
                </h4>
                <span class="text-[10px] text-stone-400 mt-1">{{ $category->products_count }} قطعة</span>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    <!-- 4. Featured Boutique Products -->
    @if($theme->show_featured_products && $featuredProducts->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-end justify-between border-b border-stone-200 pb-4">
            <div>
                <span class="text-xs font-bold text-amber-700 uppercase tracking-widest">المختارات الفاخرة</span>
                <h3 class="text-2xl font-black text-stone-900 mt-1">القطع الأكثر تميزًا</h3>
            </div>
            <a href="{{ url('/products') }}" class="text-xs font-bold text-stone-600 hover:text-stone-900 transition flex items-center gap-1">
                <span>تصفح الكتالوج</span>
                <span>←</span>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach($featuredProducts as $product)
                @php
                    $firstVariant = $product->variants->first();
                    $priceMinor = $firstVariant?->prices->first()?->price->value ?? 0;
                    $priceFormatted = number_format($priceMinor / 100, 2);
                    $imageUrl = $product->thumbnail?->getUrl() ?? ($product->media->first()?->getUrl() ?? null);
                @endphp
                <div class="group bg-white rounded-3xl border border-stone-200/80 hover:border-amber-400/60 p-4 transition-all hover:shadow-lg hover:-translate-y-1 flex flex-col justify-between">
                    <div>
                        <div class="relative aspect-square w-full rounded-2xl bg-stone-100 overflow-hidden mb-4">
                            @if($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $product->translateAttribute('name') }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" loading="lazy">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-stone-300 text-3xl">👗</div>
                            @endif
                            <span class="absolute top-2 right-2 px-2.5 py-1 rounded-full text-[10px] font-bold bg-white/90 backdrop-blur text-stone-800 shadow-sm">
                                أصلي 100%
                            </span>
                        </div>

                        <a href="{{ route('storefront.products.show', $product->id) }}" class="block">
                            <h4 class="text-sm font-bold text-stone-900 group-hover:text-amber-800 transition line-clamp-2 leading-snug">
                                {{ $product->translateAttribute('name') }}
                            </h4>
                        </a>
                    </div>

                    <div class="mt-4 pt-3 border-t border-stone-100 flex items-center justify-between">
                        <div>
                            <span class="text-base font-black text-stone-900">{{ $priceFormatted }}</span>
                            <span class="text-xs font-semibold text-stone-500">ج.م</span>
                        </div>
                        <a href="{{ route('storefront.products.show', $product->id) }}"
                           class="p-2.5 rounded-xl bg-stone-900 hover:bg-amber-600 text-white transition text-xs font-bold">
                            طلب الآن
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif

</div>
@endsection
