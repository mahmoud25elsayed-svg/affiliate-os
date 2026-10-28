@extends('layouts.affiliate')

@section('title', 'كتالوج منتجات المتجر')

@section('content')
<div class="space-y-6">
    {{-- Header & Search --}}
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">كتالوج المنتجات وأسعار الجملة</h1>
            <p class="text-sm text-slate-500 mt-1">اختر المنتجات وابدأ في تسويقها أو اطلبها مباشرة لعملائك بالهامش الربحي الذي تحدده</p>
        </div>

        <form method="GET" action="{{ route('affiliate.catalog.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث بالاسم أو الكود (SKU)..."
                       class="text-sm rounded-xl border-slate-200 pr-10 pl-4 py-2 w-64 focus:ring-indigo-500 focus:border-indigo-500">
                <svg class="w-5 h-5 text-slate-400 absolute right-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <select name="collection_id" onchange="this.form.submit()" class="text-sm rounded-xl border-slate-200 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">جميع التصنيفات</option>
                @foreach($collections as $col)
                    <option value="{{ $col->id }}" {{ request('collection_id') == $col->id ? 'selected' : '' }}>
                        {{ $col->translateAttribute('name') ?? 'تصنيف' }}
                    </option>
                @endforeach
            </select>

            @if(request('search') || request('collection_id'))
                <a href="{{ route('affiliate.catalog.index') }}" class="text-xs text-rose-600 hover:underline">إلغاء الفلترة</a>
            @endif
        </form>
    </div>

    {{-- Products Grid --}}
    @if($products->isEmpty())
        <div class="bg-white rounded-2xl p-12 text-center border border-slate-200">
            <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800">لا توجد منتجات مطابقة</h3>
            <p class="text-sm text-slate-500 mt-1">جرّب البحث بكلمة أخرى أو تغيير التصنيف.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($products as $product)
                @php
                    $firstVariant = $product->variants->first();
                    $wholesale = $firstVariant?->wholesalePrice;
                    $wholesalePriceMinor = $wholesale?->wholesale_price_minor ?? 0;
                    $minPriceMinor = max($wholesale?->min_price_minor ?? $wholesalePriceMinor, $wholesalePriceMinor + $settings->min_selling_markup_minor);
                    $maxPriceMinor = min($wholesale?->max_price_minor ?? $wholesalePriceMinor, $wholesalePriceMinor + $settings->max_selling_markup_minor);
                    if ($maxPriceMinor < $minPriceMinor) $maxPriceMinor = $minPriceMinor;
                    $img = $product->thumbnail?->getUrl();
                @endphp
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between">
                    <div>
                        <div class="h-48 bg-slate-100 relative overflow-hidden flex items-center justify-center">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $product->translateAttribute('name') }}" class="w-full h-full object-cover">
                            @else
                                <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            @endif
                            <div class="absolute top-3 right-3 bg-white/90 backdrop-blur-sm px-2.5 py-1 rounded-lg text-xs font-bold text-slate-700 shadow-sm">
                                SKU: {{ $firstVariant?->sku ?? 'N/A' }}
                            </div>
                        </div>

                        <div class="p-5 space-y-3">
                            <h3 class="font-bold text-slate-900 line-clamp-2 hover:text-indigo-600 transition-colors">
                                {{ $product->translateAttribute('name') ?? 'منتج' }}
                            </h3>

                            <div class="bg-slate-50 p-3 rounded-xl space-y-1.5 text-xs">
                                <div class="flex justify-between items-center text-slate-600">
                                    <span>سعر الجملة (تكلفة عليك):</span>
                                    <span class="font-bold font-mono text-slate-900 text-sm">{{ number_format($wholesalePriceMinor / 100, 2) }} ج.م</span>
                                </div>
                                <div class="flex justify-between items-center text-slate-500">
                                    <span>النطاق المسموح للبيع:</span>
                                    <span class="font-semibold text-emerald-600 font-mono">{{ number_format($minPriceMinor / 100) }} - {{ number_format($maxPriceMinor / 100) }} ج.م</span>
                                </div>
                                <div class="flex justify-between items-center text-indigo-600 pt-1 border-t border-slate-200">
                                    <span>ربحك المتوقع للقطعة:</span>
                                    <span class="font-black font-mono">+{{ number_format(($minPriceMinor - $wholesalePriceMinor) / 100) }} إلى {{ number_format(($maxPriceMinor - $wholesalePriceMinor) / 100) }} ج.م</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 pt-0">
                        @if($firstVariant)
                            <a href="{{ route('affiliate.orders.create', ['variant_id' => $firstVariant->id]) }}"
                               class="w-full inline-flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm py-2.5 px-4 rounded-xl shadow-sm hover:shadow transition-all">
                                <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                عمل طلب مباشر للعميل
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $products->links() }}
        </div>
    @endif
</div>
@endsection
