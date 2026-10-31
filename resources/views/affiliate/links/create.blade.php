@extends('layouts.affiliate')

@section('title', __('إنشاء رابط تتبع جديد'))

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('إنشاء رابط تتبع جديد') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('خصص الرابط لمنتج محدد أو للمتجر، وحدد سعر بيع مخصص للحملة.') }}</p>
        </div>
        <a href="{{ route('affiliate.links.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            {{ __('العودة للروابط') }}
        </a>
    </div>

    @if($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl text-sm">
            <ul class="list-disc pr-4 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('affiliate.links.store') }}" method="POST" class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm space-y-5">
        @csrf

        <!-- Title -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('اسم الحملة / وصف الرابط (اختياري)') }}</label>
            <input type="text" name="title" value="{{ old('title') }}" placeholder="{{ __('مثال: إعلان تيك توك - خصم نهاية الشهر') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
            <p class="text-xs text-gray-400 mt-1">{{ __('يساعدك في التمييز بين حملاتك الإعلانية المختلفة.') }}</p>
        </div>

        <!-- Target Product -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('المنتج المستهدف') }}</label>
            <select name="product_id" id="product_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                <option value="">{{ __('-- المتجر بالكامل (الصفحة الرئيسية) --') }}</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" {{ (string) old('product_id', $selectedProductId) === (string) $product->id ? 'selected' : '' }}>
                        {{ $product->translateAttribute('name') ?? __('منتج #:id', ['id' => $product->id]) }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">{{ __('عند اختيار منتج، سيتم توجيه الزائر مباشرة إلى صفحة هذا المنتج.') }}</p>
        </div>

        <!-- Custom Selling Price -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('سعر البيع المخصص (') }}{{ \App\Support\Money::symbol() }}{{ __(') (اختياري)') }}</label>
            <input type="number" step="0.5" name="custom_selling_price" value="{{ old('custom_selling_price') }}" placeholder="{{ __('اتركه فارغاً لاستخدام السعر الافتراضي') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
            <p class="text-xs text-gray-500 mt-1">{{ __('إذا حددت سعراً، سيحسب ربحك كالفارق بين هذا السعر وسعر الجملة. يجب أن يكون السعر ضمن الحدود المسموح بها.') }}</p>
        </div>

        <!-- Custom Code / Slug -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('كود الرابط المخصص (Slug) (اختياري)') }}</label>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 font-mono" dir="ltr">/ref/</span>
                <input type="text" name="custom_code" value="{{ old('custom_code') }}" placeholder="TIKTOK2026" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm uppercase font-mono">
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ __('اتركه فارغاً ليتم توليد كود عشوائي فريد تلقائياً.') }}</p>
        </div>

        <!-- Submit -->
        <div class="pt-4 border-t flex justify-end gap-3">
            <a href="{{ route('affiliate.links.index') }}" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition">{{ __('إلغاء') }}</a>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition shadow-sm">
                {{ __('تأكيد وإنشاء الرابط') }}
            </button>
        </div>
    </form>
</div>
@endsection

