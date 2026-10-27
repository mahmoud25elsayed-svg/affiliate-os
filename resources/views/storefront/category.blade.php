@extends('layouts.storefront')

@section('title', ($category ? ($category->translateAttribute('name') ?? 'فئة') : 'كافة المنتجات') . ' - ' . ($general->platform_name ?? 'متجرنا'))
@section('meta_description', 'تصفح أفضل منتجات ' . ($category ? ($category->translateAttribute('name') ?? '') : ($general->platform_name ?? '')) . ' مع توصيل سريع لجميع المحافظات والدفع عند الاستلام')

@section('schema')
    <script type="application/ld+json">
        {!! $breadcrumbSchema !!}
    </script>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-gray-500 font-medium">
        @foreach($breadcrumbs as $index => $item)
            @if($index > 0)
                <span>/</span>
            @endif
            @if(!$loop->last)
                <a href="{{ $item['url'] }}" class="hover:text-brand-600 transition-colors">{{ $item['name'] }}</a>
            @else
                <span class="text-gray-900 font-bold">{{ $item['name'] }}</span>
            @endif
        @endforeach
    </nav>

    <!-- Category Header -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">
                {{ $category ? ($category->translateAttribute('name') ?? 'الفئة') : 'كافة المنتجات' }}
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                تصفح المنتجات المتاحة واكتشف أحدث العروض والأسعار المنافسة
            </p>
        </div>
        @if($category && $category->parent)
            <span class="px-3 py-1 bg-brand-50 text-brand-600 text-xs font-bold rounded-full border border-brand-100">
                فئة فرعية من {{ $category->parent->translateAttribute('name') }}
            </span>
        @endif
    </div>

    <!-- Livewire Product Filter & Grid -->
    <livewire:storefront.product-filter :category-id="$category?->id" />

</div>
@endsection
