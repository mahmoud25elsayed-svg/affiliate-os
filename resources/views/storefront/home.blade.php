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
