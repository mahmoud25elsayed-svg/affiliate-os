@extends('layouts.affiliate')

@section('title', 'إدارة ومتابعة طلباتي')

@section('content')
<div class="space-y-6">
    {{-- Header & Quick Actions --}}
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">سجل الطلبات والعمولات</h1>
            <p class="text-sm text-slate-500 mt-1">تتبع حالة كل طلب، أرباحك، ونتائج فحص الجودة والتوصيل</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('affiliate.orders.import-csv.show') }}" class="text-sm font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 px-4 py-2.5 rounded-xl transition-colors">
                استيراد ملف CSV
            </a>
            <a href="{{ route('affiliate.orders.create') }}" class="text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                طلب جديد للعميل
            </a>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex flex-wrap gap-1.5 text-xs font-semibold">
            <a href="{{ route('affiliate.orders.index') }}" class="px-3 py-1.5 rounded-lg {{ empty($currentStatus) ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                الكل
            </a>
            <a href="{{ route('affiliate.orders.index', ['status' => 'submitted']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'submitted' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                تم الإرسال
            </a>
            <a href="{{ route('affiliate.orders.index', ['status' => 'pending_confirmation']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'pending_confirmation' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                بانتظار التأكيد
            </a>
            <a href="{{ route('affiliate.orders.index', ['status' => 'confirmed']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'confirmed' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                تم التأكيد
            </a>
            <a href="{{ route('affiliate.orders.index', ['status' => 'shipped']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'shipped' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                مع شركة الشحن
            </a>
            <a href="{{ route('affiliate.orders.index', ['status' => 'delivered']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'delivered' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                تم التسليم
            </a>
            <a href="{{ route('affiliate.orders.index', ['status' => 'cancelled_by_customer']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'cancelled_by_customer' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                ملغي
            </a>
        </div>

        <form method="GET" action="{{ route('affiliate.orders.index') }}" class="relative">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث برقم الطلب أو الهاتف..."
                   class="text-xs rounded-xl border-slate-200 pr-9 pl-3 py-2 w-60 focus:ring-indigo-500 focus:border-indigo-500">
            <svg class="w-4 h-4 text-slate-400 absolute right-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </form>
    </div>

    {{-- Orders Table --}}
    @if($orders->isEmpty())
        <div class="bg-white rounded-2xl p-12 text-center border border-slate-200">
            <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800">لا توجد طلبات مسجلة</h3>
            <p class="text-sm text-slate-500 mt-1">ابدأ بإنشاء أول طلب مباشر لعملائك أو تصفح الكتالوج لاختيار المنتجات.</p>
            <a href="{{ route('affiliate.orders.create') }}" class="mt-4 inline-block bg-indigo-600 text-white text-xs font-bold px-4 py-2 rounded-xl">
                إنشاء أول طلب +
            </a>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500">
                        <tr>
                            <th class="py-3.5 px-4">رقم الطلب</th>
                            <th class="py-3.5 px-4">العميل والمحافظة</th>
                            <th class="py-3.5 px-4">إجمالي التحصيل</th>
                            <th class="py-3.5 px-4">ربحك في الطلب</th>
                            <th class="py-3.5 px-4">حالة الطلب</th>
                            <th class="py-3.5 px-4">فحص الجودة</th>
                            <th class="py-3.5 px-4">تاريخ الطلب</th>
                            <th class="py-3.5 px-4 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($orders as $order)
                            @php
                                $totalVal = is_object($order->total) && isset($order->total->value) ? (int) $order->total->value : (int) $order->total;
                                $statusEnum = \App\Domain\Orders\Enums\OrderStatus::tryFrom($order->status);
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-4 font-mono font-bold text-indigo-700">
                                    <a href="{{ route('affiliate.orders.show', $order->reference) }}" class="hover:underline">
                                        {{ $order->reference }}
                                    </a>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="block font-semibold text-slate-900">{{ $order->shippingAddress?->first_name ?? 'عميل' }}</span>
                                    <span class="text-xs text-slate-400">{{ $order->shippingAddress?->city ?? '-' }}</span>
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                    {{ \App\Support\Money::show($totalVal) }}
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-emerald-600">
                                    +{{ \App\Support\Money::show($order->affiliate_profit_minor) }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                                        @if($order->status === 'delivered') bg-emerald-100 text-emerald-800
                                        @elseif(str_contains($order->status, 'cancelled') || str_contains($order->status, 'failed')) bg-rose-100 text-rose-800
                                        @elseif($order->status === 'shipped' || $order->status === 'out_for_delivery') bg-blue-100 text-blue-800
                                        @else bg-amber-100 text-amber-800 @endif">
                                        {{ $statusEnum?->label() ?? $order->status }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold
                                        @if($order->quality_result === 'pass') bg-emerald-50 text-emerald-700 border border-emerald-200
                                        @elseif($order->quality_result === 'review') bg-amber-50 text-amber-700 border border-amber-200
                                        @else bg-rose-50 text-rose-700 border border-rose-200 @endif">
                                        {{ match($order->quality_result) {
                                            'pass' => 'مقبول (Pass)',
                                            'review' => 'مراجعة (Review)',
                                            'reject' => 'مرفوض (Reject)',
                                            default => $order->quality_result ?? 'معلق'
                                        } }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-500 font-mono">
                                    {{ $order->created_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="{{ route('affiliate.orders.show', $order->reference) }}"
                                       class="text-xs font-bold text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200">
                                        التفاصيل &larr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $orders->links() }}
            </div>
        </div>
    @endif
</div>
@endsection

