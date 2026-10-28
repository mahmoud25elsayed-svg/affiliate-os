@extends('layouts.storefront')

@section('title', ($general->platform_name ?? 'متجرنا') . ' - ' . ($theme->hero_headline ?? 'تسوق الآن'))
@section('meta_description', $theme->hero_subheadline ?? 'أفضل المنتجات والعروض مع توصيل سريع لجميع المحافظات والدفع عند الاستلام')

@section('schema')
    <script type="application/ld+json">
        {!! $orgSchema !!}
    </script>
@endsection

@section('content')
<div class="space-y-12 pb-16">

    <!-- 1. Hero Banner Section -->
    @if($theme->show_hero_banner)
    <section class="relative bg-gradient-to-l from-gray-950 via-gray-900 to-gray-800 text-white overflow-hidden py-16 sm:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                <div class="space-y-6 text-center lg:text-right">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-brand-600/30 text-brand-600 border border-brand-600/40">
                        ⚡ عروض وتخفيضات حصرية لفترة محدودة
                    </span>
                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight leading-tight">
                        {{ $theme->hero_headline }}
                    </h1>
                    <p class="text-base sm:text-lg text-gray-300 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        {{ $theme->hero_subheadline }}
                    </p>
                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                        <a href="{{ url($theme->hero_button_url) }}" class="px-8 py-3.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-lg shadow-brand-600/30 transition-all hover:-translate-y-0.5">
                            {{ $theme->hero_button_text }}
                        </a>
                        <a href="{{ url('/products') }}" class="px-6 py-3.5 bg-gray-800 hover:bg-gray-700 text-gray-200 font-semibold rounded-xl border border-gray-700 transition-colors">
                            استكشف الكتالوج
                        </a>
                    </div>
                </div>

                @if(!empty($theme->hero_image_url))
                <div class="relative flex justify-center">
                    <img src="{{ $theme->hero_image_url }}" alt="{{ $theme->hero_headline }}" class="rounded-2xl shadow-2xl max-h-[420px] object-cover border border-gray-700" loading="lazy">
                </div>
                @else
                <div class="relative flex justify-center">
                    <div class="w-full max-w-md h-80 rounded-2xl bg-gradient-to-tr from-brand-600/20 to-cyan-500/20 border border-gray-700/60 backdrop-blur-md flex flex-col items-center justify-center p-8 text-center shadow-2xl">
                        <span class="text-5xl mb-4">🛍️</span>
                        <h3 class="text-xl font-bold text-white mb-2">جودة وضمان حقيقي</h3>
                        <p class="text-sm text-gray-400">معاينة الطلب عند الاستلام قبل الدفع لضمان الرضا الكامل.</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    <!-- Daily Deal Banner (P14) -->
    @if(isset($activeDailyDeal) && $activeDailyDeal && $activeDailyDeal->product)
    @php
        $dealProduct = $activeDailyDeal->product;
        $dealVariant = $dealProduct->variants->first();
        $dealStdPrice = $dealVariant?->prices->first()?->price?->value ?? 0;
        $dealDiscounted = $activeDailyDeal->calculateDiscountedPrice($dealStdPrice);
    @endphp
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-amber-500 via-rose-500 to-red-600 p-6 sm:p-10 text-white shadow-xl">
            <div class="relative z-10 grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                <div class="md:col-span-8 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/20 backdrop-blur-md rounded-full text-xs font-black tracking-wide uppercase">
                        <span>⭐ {{ $activeDailyDeal->badge_text ?? 'صفقة اليوم الحصرية' }}</span>
                    </div>
                    <h2 class="text-2xl sm:text-4xl font-black leading-tight">
                        {{ $activeDailyDeal->banner_title ?? $dealProduct->translateAttribute('name') }}
                    </h2>
                    <p class="text-sm sm:text-base text-white/90 line-clamp-2">
                        {{ $dealProduct->translateAttribute('description') ?? 'عرض خاص متاح لليوم فقط بخصم استثنائي، سارع بالحجز قبل نفاد الكمية!' }}
                    </p>
                    <div class="flex flex-wrap items-baseline gap-3 pt-2">
                        <span class="text-3xl sm:text-4xl font-black text-yellow-300">
                            {{ \App\Support\Money::fromMinor($dealDiscounted)->format('ar') }}
                        </span>
                        @if($dealStdPrice > $dealDiscounted)
                            <span class="text-lg text-white/70 line-through">
                                {{ \App\Support\Money::fromMinor($dealStdPrice)->format('ar') }}
                            </span>
                            <span class="px-2.5 py-1 bg-yellow-400 text-gray-900 rounded-lg text-xs font-black">
                                خصم {{ $activeDailyDeal->discount_type === 'percentage' ? $activeDailyDeal->discount_value.'%' : \App\Support\Money::fromMinor($activeDailyDeal->discount_value)->format('ar') }}
                            </span>
                        @endif
                    </div>
                    <div class="pt-2">
                        <a href="{{ url('/products/'.$dealProduct->id) }}" class="inline-flex items-center gap-2 px-8 py-3.5 bg-white text-rose-600 hover:bg-gray-100 font-extrabold rounded-2xl shadow-lg transition-transform hover:scale-105">
                            <span>اغتنم الفرصة واشترِ الآن</span>
                            <span>&larr;</span>
                        </a>
                    </div>
                </div>
                <div class="md:col-span-4 flex justify-center">
                    @php
                        $dealImg = $dealProduct->media->first()?->getUrl();
                    @endphp
                    @if($dealImg)
                        <img src="{{ $dealImg }}" alt="{{ $dealProduct->translateAttribute('name') }}" class="w-64 h-64 sm:w-72 sm:h-72 object-cover rounded-2xl shadow-2xl border-4 border-white/30 transform hover:rotate-1 transition-transform">
                    @else
                        <div class="w-64 h-64 rounded-2xl bg-white/10 backdrop-blur border border-white/20 flex items-center justify-center text-7xl">
                            🎁
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
    @endif

    <!-- Flash Sale Section (P14) -->
    @if(isset($activeFlashSale) && $activeFlashSale && $activeFlashSale->items->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" x-data="flashSaleCountdown('{{ $activeFlashSale->ends_at->toISOString() }}')">
        <div class="bg-gradient-to-br from-gray-900 via-slate-900 to-zinc-900 rounded-3xl p-6 sm:p-8 border border-red-500/30 shadow-2xl relative overflow-hidden">
            <!-- Header with Countdown -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-gray-800">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-red-600/20 border border-red-500/40 flex items-center justify-center text-2xl animate-pulse">
                        ⚡
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl sm:text-2xl font-black text-white">{{ $activeFlashSale->name }}</h2>
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-red-600 text-white">فلاش سيل</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">تخفيضات نارية سريعة تنتهي قريباً مع كميات محدودة جداً</p>
                    </div>
                </div>

                <!-- Digital Timer -->
                <div class="flex items-center gap-2 text-center select-none" dir="ltr">
                    <div class="bg-gray-800/90 border border-gray-700 rounded-xl px-3 py-2 min-w-[54px]">
                        <span class="block text-lg sm:text-xl font-black text-red-500 font-mono" x-text="days">00</span>
                        <span class="block text-[9px] uppercase tracking-wider text-gray-400 font-semibold">أيام</span>
                    </div>
                    <span class="text-red-500 font-bold text-lg">:</span>
                    <div class="bg-gray-800/90 border border-gray-700 rounded-xl px-3 py-2 min-w-[54px]">
                        <span class="block text-lg sm:text-xl font-black text-red-500 font-mono" x-text="hours">00</span>
                        <span class="block text-[9px] uppercase tracking-wider text-gray-400 font-semibold">ساعات</span>
                    </div>
                    <span class="text-red-500 font-bold text-lg">:</span>
                    <div class="bg-gray-800/90 border border-gray-700 rounded-xl px-3 py-2 min-w-[54px]">
                        <span class="block text-lg sm:text-xl font-black text-red-500 font-mono" x-text="minutes">00</span>
                        <span class="block text-[9px] uppercase tracking-wider text-gray-400 font-semibold">دقائق</span>
                    </div>
                    <span class="text-red-500 font-bold text-lg">:</span>
                    <div class="bg-gray-800/90 border border-gray-700 rounded-xl px-3 py-2 min-w-[54px]">
                        <span class="block text-lg sm:text-xl font-black text-red-500 font-mono" x-text="seconds">00</span>
                        <span class="block text-[9px] uppercase tracking-wider text-gray-400 font-semibold">ثواني</span>
                    </div>
                </div>
            </div>

            <!-- Items Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6 pt-6">
                @foreach($activeFlashSale->items as $item)
                    @php
                        $variant = $item->variant;
                        $prod = $variant?->product;
                        $stdPrice = $variant?->prices->first()?->price?->value ?? 0;
                        $flashPrice = $item->flash_price_minor;
                        $percentClaimed = $item->allocated_quantity > 0 ? min(100, (int) round(($item->sold_quantity / $item->allocated_quantity) * 100)) : 100;
                        $remaining = max(0, $item->allocated_quantity - $item->sold_quantity);
                        $img = $prod?->media->first()?->getUrl();
                    @endphp
                    @if($prod)
                    <div class="bg-gray-800/60 border border-gray-700/60 rounded-2xl p-4 flex flex-col justify-between hover:border-red-500/50 transition-all group">
                        <div>
                            <div class="relative aspect-square rounded-xl overflow-hidden bg-gray-900 mb-3">
                                @if($img)
                                    <img src="{{ $img }}" alt="{{ $prod->translateAttribute('name') }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-4xl text-gray-600">⚡</div>
                                @endif
                                <span class="absolute top-2 right-2 px-2 py-0.5 bg-red-600 text-white font-black text-[10px] rounded-md shadow">
                                    خصم مباشر
                                </span>
                            </div>

                            <a href="{{ url('/products/'.$prod->id) }}" class="block">
                                <h3 class="text-sm font-bold text-gray-100 group-hover:text-red-400 transition-colors line-clamp-2">
                                    {{ $prod->translateAttribute('name') }}
                                </h3>
                            </a>

                            <div class="flex items-baseline gap-2 mt-2">
                                <span class="text-base font-black text-red-400">
                                    {{ \App\Support\Money::fromMinor($flashPrice)->format('ar') }}
                                </span>
                                @if($stdPrice > $flashPrice)
                                    <span class="text-xs text-gray-500 line-through">
                                        {{ \App\Support\Money::fromMinor($stdPrice)->format('ar') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Progress Bar & Stock Notice -->
                        <div class="mt-4 pt-3 border-t border-gray-700/60">
                            <div class="flex justify-between items-center text-[11px] mb-1.5">
                                <span class="text-gray-400">تم حجز: {{ $percentClaimed }}%</span>
                                <span class="text-red-400 font-semibold">متبقي {{ $remaining }}</span>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2 overflow-hidden">
                                <div class="bg-gradient-to-r from-red-500 to-amber-500 h-2 rounded-full transition-all duration-500" style="width: {{ $percentClaimed }}%"></div>
                            </div>
                            <div class="mt-3">
                                <a href="{{ url('/products/'.$prod->id) }}" class="block w-full text-center py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-xs transition-colors shadow">
                                    اطلب الآن
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('flashSaleCountdown', (endsAtIso) => ({
                endsAt: new Date(endsAtIso).getTime(),
                days: '00',
                hours: '00',
                minutes: '00',
                seconds: '00',
                timer: null,
                init() {
                    this.update();
                    this.timer = setInterval(() => this.update(), 1000);
                },
                update() {
                    const now = new Date().getTime();
                    const diff = this.endsAt - now;
                    if (diff <= 0) {
                        this.days = '00';
                        this.hours = '00';
                        this.minutes = '00';
                        this.seconds = '00';
                        if (this.timer) clearInterval(this.timer);
                        return;
                    }
                    const d = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const s = Math.floor((diff % (1000 * 60)) / 1000);

                    this.days = String(d).padStart(2, '0');
                    this.hours = String(h).padStart(2, '0');
                    this.minutes = String(m).padStart(2, '0');
                    this.seconds = String(s).padStart(2, '0');
                }
            }));
        });
    </script>
    @endif

    <!-- Features & Trust Badges Section -->
    @if($theme->show_features_badges && !empty($theme->features_badges))
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-sm">
            @foreach($theme->features_badges as $badge)
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-brand-50 flex items-center justify-center text-brand-600 flex-shrink-0 text-xl font-bold">
                    @if($badge['icon'] === 'truck')
                        🚚
                    @elseif($badge['icon'] === 'shield')
                        🛡️
                    @elseif($badge['icon'] === 'refresh')
                        🔄
                    @else
                        🎧
                    @endif
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-900">{{ $badge['title'] }}</h4>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $badge['subtitle'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <!-- 2. Featured Categories Section -->
    @if($theme->show_featured_categories && $categories->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-black text-gray-900 tracking-tight">تصفح حسب الفئات</h2>
                <p class="text-sm text-gray-500">اختر الفئة المفضلة للوصول السريع لمنتجاتك</p>
            </div>
            <a href="{{ url('/products') }}" class="text-sm font-semibold text-brand-600 hover:underline">
                عرض الكل &larr;
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-4">
            @foreach($categories as $category)
                <a href="{{ url('/categories/'.$category->id) }}" class="group flex flex-col items-center text-center p-4 bg-white rounded-xl border border-gray-100 hover:border-brand-600/30 hover:shadow-md transition-all">
                    <div class="w-14 h-14 rounded-full bg-brand-50 flex items-center justify-center text-brand-600 text-xl font-bold mb-3 group-hover:scale-110 transition-transform">
                        📁
                    </div>
                    <span class="text-xs font-bold text-gray-800 group-hover:text-brand-600 line-clamp-1 transition-colors">
                        {{ $category->translateAttribute('name') ?? 'فئة' }}
                    </span>
                    <span class="text-[10px] text-gray-400 mt-1">
                        {{ $category->products_count }} منتج
                    </span>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    <!-- 3. Featured Products Section -->
    @if($theme->show_featured_products && $featuredProducts->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-black text-gray-900 tracking-tight">المنتجات الأكثر طلباً</h2>
                <p class="text-sm text-gray-500">تشكيلة مختارة من أفضل المنتجات المضمونة</p>
            </div>
            <a href="{{ url('/products') }}" class="text-sm font-semibold text-brand-600 hover:underline">
                مشاهدة الجميع &larr;
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
            @foreach($featuredProducts as $product)
                @php
                    $firstVariant = $product->variants->first();
                    $retailPrice = $firstVariant?->prices->first();
                    $pricing = $firstVariant ? $firstVariant->getRelationValue('wholesalePrice') : null;
                    $comparePrice = $pricing && $pricing->compare_at_price_minor ? \App\Support\Money::fromMinor($pricing->compare_at_price_minor) : null;
                @endphp
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-lg transition-all group flex flex-col">
                    <a href="{{ url('/products/'.$product->id) }}" class="relative block aspect-square bg-gray-100 overflow-hidden">
                        @if($product->thumbnail)
                            <img src="{{ $product->thumbnail->getUrl() }}" alt="{{ $product->translateAttribute('name') }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-gray-300">
                                📦
                            </div>
                        @endif

                        @if($comparePrice && $retailPrice && $comparePrice->amount > $retailPrice->price->value)
                            <span class="absolute top-2.5 right-2.5 bg-red-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow">
                                خصم مميز
                            </span>
                        @endif
                    </a>

                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider block mb-1">
                                {{ $product->brand?->name ?? 'متجرنا' }}
                            </span>
                            <h3 class="font-bold text-gray-900 text-sm group-hover:text-brand-600 line-clamp-2 transition-colors">
                                <a href="{{ url('/products/'.$product->id) }}">
                                    {{ $product->translateAttribute('name') ?? 'منتج' }}
                                </a>
                            </h3>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between">
                            <div>
                                <span class="text-base font-black text-brand-600">
                                    {{ $retailPrice ? $retailPrice->price->formatted() : '0.00 ج.م' }}
                                </span>
                                @if($comparePrice && $retailPrice && $comparePrice->amount > $retailPrice->price->value)
                                    <span class="text-xs text-gray-400 line-through mr-1 block">
                                        {{ $comparePrice->format('ar') }}
                                    </span>
                                @endif
                            </div>

                            <a href="{{ url('/products/'.$product->id) }}" class="p-2 rounded-lg bg-brand-50 text-brand-600 hover:bg-brand-600 hover:text-white transition-colors" title="عرض التفاصيل">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    <!-- 4. Promotional Banner -->
    @if($theme->show_offers_banner)
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-amber-500 to-orange-600 p-8 sm:p-12 text-white shadow-xl relative overflow-hidden flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="space-y-2 text-center sm:text-right relative z-10">
                <span class="text-xs font-black uppercase tracking-wider bg-black/20 px-3 py-1 rounded-full">عرض اليوم</span>
                <h3 class="text-2xl sm:text-4xl font-black">معاينة الطلب عند الاستلام مجاناً</h3>
                <p class="text-sm sm:text-base text-amber-100 max-w-lg">تسوق بكل ثقة مع ضمان استرجاع مجاني وسداد عند الاستلام في جميع المحافظات.</p>
            </div>
            <a href="{{ url('/products') }}" class="px-8 py-3.5 bg-white text-gray-900 font-bold rounded-xl shadow hover:bg-gray-100 transition-colors whitespace-nowrap relative z-10">
                تسوق العروض الآن
            </a>
        </div>
    </section>
    @endif

    <!-- 5. Latest Products Section -->
    @if($theme->show_latest_products && $latestProducts->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-black text-gray-900 tracking-tight">أحدث الإضافات</h2>
                <p class="text-sm text-gray-500">منتجات وصلت حديثاً إلى المتجر</p>
            </div>
            <a href="{{ url('/products') }}" class="text-sm font-semibold text-brand-600 hover:underline">
                استعراض المزيد &larr;
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
            @foreach($latestProducts as $product)
                @php
                    $firstVariant = $product->variants->first();
                    $retailPrice = $firstVariant?->prices->first();
                @endphp
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-lg transition-all group flex flex-col">
                    <a href="{{ url('/products/'.$product->id) }}" class="relative block aspect-square bg-gray-100 overflow-hidden">
                        @if($product->thumbnail)
                            <img src="{{ $product->thumbnail->getUrl() }}" alt="{{ $product->translateAttribute('name') }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-gray-300">
                                📦
                            </div>
                        @endif
                    </a>

                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider block mb-1">
                                {{ $product->brand?->name ?? 'متجرنا' }}
                            </span>
                            <h3 class="font-bold text-gray-900 text-sm group-hover:text-brand-600 line-clamp-2 transition-colors">
                                <a href="{{ url('/products/'.$product->id) }}">
                                    {{ $product->translateAttribute('name') ?? 'منتج' }}
                                </a>
                            </h3>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between">
                            <span class="text-base font-black text-brand-600">
                                {{ $retailPrice ? $retailPrice->price->formatted() : '0.00 ج.م' }}
                            </span>

                            <a href="{{ url('/products/'.$product->id) }}" class="p-2 rounded-lg bg-brand-50 text-brand-600 hover:bg-brand-600 hover:text-white transition-colors" title="عرض التفاصيل">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif

</div>
@endsection
