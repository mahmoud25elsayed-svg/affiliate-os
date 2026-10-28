@extends('layouts.affiliate')

@section('title', 'روابط التتبع والإحالة')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">روابط التتبع والإحالة (Tracking Links)</h1>
            <p class="text-sm text-gray-500 mt-1">أنشئ روابط مخصصة لحملاتك على فيسبوك وتيك توك وتتبع الزيارات والطلبات ومعدل التحويل بدقة.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('affiliate.links.create') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                إنشاء رابط جديد
            </a>
        </div>
    </div>

    <!-- Stats Overview -->
    @php
        $totalClicks = $links->sum('clicks_count');
        $totalOrders = $links->sum('orders_count');
        $overallConversion = $totalClicks > 0 ? round(($totalOrders / $totalClicks) * 100, 1) : 0;
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
            <p class="text-xs font-medium text-gray-500">إجمالي الروابط</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $links->total() }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
            <p class="text-xs font-medium text-gray-500">إجمالي النقرات</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($totalClicks) }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
            <p class="text-xs font-medium text-gray-500">الطلبات المسندة</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($totalOrders) }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
            <p class="text-xs font-medium text-gray-500">معدل التحويل (CR)</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $overallConversion }}%</p>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm text-gray-600">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4">اسم الحملة / الكود</th>
                        <th class="px-6 py-4">الهدف</th>
                        <th class="px-6 py-4">سعر البيع المخصص</th>
                        <th class="px-6 py-4">الرابط المباشر</th>
                        <th class="px-6 py-4 text-center">النقرات</th>
                        <th class="px-6 py-4 text-center">الطلبات</th>
                        <th class="px-6 py-4 text-center">التحويل</th>
                        <th class="px-6 py-4 text-left">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($links as $link)
                        @php
                            $cr = $link->clicks_count > 0 ? round(($link->orders_count / $link->clicks_count) * 100, 1) : 0;
                            $linkUrl = $link->getUrl();
                        @endphp
                        <tr class="hover:bg-gray-50/70 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900">{{ $link->title ?? 'رابط تتبع' }}</div>
                                <div class="text-xs text-blue-600 font-mono mt-0.5">{{ $link->code }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($link->product)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        {{ $link->product->translateAttribute('name') ?? "منتج #{$link->product_id}" }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                        المتجر بالكامل
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($link->custom_selling_price_minor)
                                    <span class="font-bold text-emerald-700">{{ number_format($link->custom_selling_price_minor / 100, 2) }} ج.م</span>
                                @else
                                    <span class="text-xs text-gray-400">سعر المتجر الافتراضي</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <input type="text" readonly value="{{ $linkUrl }}" class="text-xs bg-gray-50 border border-gray-200 rounded px-2.5 py-1 font-mono text-gray-600 w-48 truncate" id="url-{{ $link->id }}">
                                    <button onclick="navigator.clipboard.writeText('{{ $linkUrl }}'); alert('تم نسخ الرابط بنجاح!');" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition" title="نسخ الرابط">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-gray-800">{{ number_format($link->clicks_count) }}</td>
                            <td class="px-6 py-4 text-center font-bold text-emerald-700">{{ number_format($link->orders_count) }}</td>
                            <td class="px-6 py-4 text-center font-bold text-amber-700">{{ $cr }}%</td>
                            <td class="px-6 py-4 text-left">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="document.getElementById('qr-modal-{{ $link->id }}').classList.remove('hidden')" class="p-1.5 text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded transition" title="عرض QR Code">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                    </button>
                                    <form action="{{ route('affiliate.links.destroy', $link->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الرابط؟');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition" title="حذف الرابط">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>

                                <!-- QR Modal -->
                                <div id="qr-modal-{{ $link->id }}" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                                    <div class="bg-white rounded-2xl max-w-sm w-full p-6 text-center space-y-4 shadow-xl">
                                        <div class="flex justify-between items-center pb-2 border-b">
                                            <h3 class="font-bold text-gray-900">{{ $link->title ?? $link->code }}</h3>
                                            <button onclick="document.getElementById('qr-modal-{{ $link->id }}').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
                                        </div>
                                        <div class="flex justify-center p-3">
                                            {!! $link->getQrCodeSvg() !!}
                                        </div>
                                        <p class="text-xs text-gray-500 font-mono">{{ $linkUrl }}</p>
                                        <button onclick="document.getElementById('qr-modal-{{ $link->id }}').classList.add('hidden')" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-semibold py-2 rounded-lg">إغلاق</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-12 text-gray-400">
                                لا توجد روابط تتبع حتى الآن. انقر على "إنشاء رابط جديد" لبدء ترويج المنتجات وحملاتك!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($links->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $links->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

