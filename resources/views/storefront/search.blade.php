@extends('layouts.storefront')

@section('title', 'نتائج البحث عن "' . $query . '" - ' . ($general->platform_name ?? 'متجرنا'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Search Header -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm">
        <h1 class="text-2xl font-black text-gray-900">
            نتائج البحث عن: <span class="text-brand-600">"{{ $query }}"</span>
        </h1>
        <p class="text-sm text-gray-500 mt-1">
            تم العثور على {{ $products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $products->total() : $products->count() }} منتج
        </p>
    </div>

    <!-- Results Grid -->
    @if($products->isNotEmpty())
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
            @foreach($products as $product)
                @php
                    $firstVariant = $product->variants->first();
                    $retailPrice = $firstVariant?->prices->first();
                @endphp
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-lg transition-all flex flex-col group">
                    <a href="{{ url('/products/'.$product->id) }}" class="block aspect-square bg-gray-100 overflow-hidden relative">
                        @if($product->thumbnail)
                            <img src="{{ $product->thumbnail->getUrl() }}" alt="{{ $product->translateAttribute('name') }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-gray-300">📦</div>
                        @endif
                    </a>

                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 block mb-1 uppercase tracking-wider">
                                {{ $product->brand?->name ?? 'متجرنا' }}
                            </span>
                            <h3 class="font-bold text-gray-900 text-sm line-clamp-2 group-hover:text-brand-600 transition-colors">
                                <a href="{{ url('/products/'.$product->id) }}">
                                    {{ $product->translateAttribute('name') }}
                                </a>
                            </h3>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between">
                            <span class="text-base font-black text-brand-600">
                                {{ \App\Support\Money::show($retailPrice?->price) }}
                            </span>
                            <a href="{{ url('/products/'.$product->id) }}" class="p-2 rounded-lg bg-brand-50 text-brand-600 hover:bg-brand-600 hover:text-white transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if(method_exists($products, 'links'))
            <div class="pt-6">
                {{ $products->links() }}
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="bg-white rounded-3xl p-12 text-center border border-gray-100 shadow-sm max-w-lg mx-auto space-y-4">
            <span class="text-6xl block">🔎</span>
            <h3 class="text-xl font-bold text-gray-800">لم يتم العثور على نتائج</h3>
            <p class="text-sm text-gray-500">جرّب البحث بكلمات أخرى أو تصفح كافة منتجات الكتالوج.</p>
            <a href="{{ url('/products') }}" class="inline-block px-6 py-2.5 bg-brand-600 text-white font-bold rounded-xl text-sm hover:bg-brand-700 transition-colors">
                تصفح جميع المنتجات
            </a>
        </div>
    @endif

</div>
@endsection
