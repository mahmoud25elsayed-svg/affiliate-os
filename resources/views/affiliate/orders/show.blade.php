@extends('layouts.affiliate')

@section('title', 'تفاصيل الطلب: ' . $order->reference)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-slate-900 font-mono">{{ $order->reference }}</h1>
                @php $statusEnum = \App\Domain\Orders\Enums\OrderStatus::tryFrom($order->status); @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                    @if($order->status === 'delivered') bg-emerald-100 text-emerald-800
                    @elseif(str_contains($order->status, 'cancelled') || str_contains($order->status, 'failed')) bg-rose-100 text-rose-800
                    @elseif($order->status === 'shipped' || $order->status === 'out_for_delivery') bg-blue-100 text-blue-800
                    @else bg-amber-100 text-amber-800 @endif">
                    {{ $statusEnum?->label() ?? $order->status }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">تاريخ الإنشاء: {{ $order->created_at?->format('Y-m-d H:i') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('affiliate.orders.index') }}" class="text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-xl">
                &rarr; العودة للطلبات
            </a>
            @if($canCancel)
                <button type="button" onclick="document.getElementById('cancel-modal').classList.remove('hidden')"
                        class="text-sm font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 px-4 py-2 rounded-xl">
                    إلغاء الطلب
                </button>
            @endif
        </div>
    </div>

    {{-- Quality Gate Status Alert --}}
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-2">
        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            نتيجة فحص جودة الطلب (Quality Gate v1)
        </h3>

        <div class="flex items-center gap-3">
            <span class="inline-block px-3 py-1 rounded-lg text-xs font-bold
                @if($order->quality_result === 'pass') bg-emerald-100 text-emerald-800
                @elseif($order->quality_result === 'review') bg-amber-100 text-amber-800
                @else bg-rose-100 text-rose-800 @endif">
                {{ match($order->quality_result) {
                    'pass' => 'تم الاجتياز بنجاح (Passed)',
                    'review' => 'يتطلب مراجعة من التاجر (Pending Review)',
                    'reject' => 'مرفوض لعدم استيفاء الجودة (Rejected)',
                    default => $order->quality_result
                } }}
            </span>
        </div>

        @php
            $rawMeta = $order->meta;
            $reasons = is_array($rawMeta) && isset($rawMeta['quality_reasons']) ? (array) $rawMeta['quality_reasons'] : [];
        @endphp
        @if(!empty($reasons))
            <ul class="list-disc pr-5 mt-2 space-y-1 text-xs text-slate-600">
                @foreach($reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Details Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Customer Info --}}
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-3">
            <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-2">بيانات العميل والتوصيل</h3>
            <div class="text-xs space-y-2 text-slate-600">
                <div class="flex justify-between">
                    <span class="text-slate-400">اسم العميل:</span>
                    <span class="font-bold text-slate-800">{{ $order->shippingAddress?->first_name ?? 'العميل' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">رقم الهاتف:</span>
                    <span class="font-bold font-mono text-slate-800">{{ $displayedPhone }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">المحافظة والمدينة:</span>
                    <span class="font-semibold text-slate-800">{{ $order->shippingAddress?->city ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">العنوان:</span>
                    <span class="font-semibold text-slate-800">{{ $order->shippingAddress?->line_one ?? '-' }}</span>
                </div>
                @if($order->shippingAddress?->line_two)
                    <div class="flex justify-between">
                        <span class="text-slate-400">العقار / الشقة:</span>
                        <span class="text-slate-800">{{ $order->shippingAddress->line_two }}</span>
                    </div>
                @endif
                @if($order->notes)
                    <div class="pt-2 border-t border-slate-100">
                        <span class="text-slate-400 block mb-1">ملاحظات التوصيل:</span>
                        <p class="text-slate-700 bg-slate-50 p-2 rounded-lg">{{ $order->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Financial Summary --}}
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-3">
            <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-2">ملخص الحساب المالي للطلب</h3>
            <div class="text-xs space-y-2 text-slate-600">
                @php
                    $subTotal = is_object($order->sub_total) && isset($order->sub_total->value) ? (int) $order->sub_total->value : (int) $order->sub_total;
                    $shippingTotal = is_object($order->shipping_total) && isset($order->shipping_total->value) ? (int) $order->shipping_total->value : (int) $order->shipping_total;
                    $totalVal = is_object($order->total) && isset($order->total->value) ? (int) $order->total->value : (int) $order->total;
                @endphp
                <div class="flex justify-between">
                    <span class="text-slate-400">سعر المنتجات (المحدد للعميل):</span>
                    <span class="font-bold font-mono text-slate-800">{{ number_format($subTotal / 100, 2) }} ج.م</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">تكلفة الشحن للمحافظة:</span>
                    <span class="font-bold font-mono text-slate-800">{{ number_format($shippingTotal / 100, 2) }} ج.م</span>
                </div>
                @if($order->cod_fee_minor > 0)
                    <div class="flex justify-between">
                        <span class="text-slate-400">رسوم الدفع عند الاستلام (COD):</span>
                        <span class="font-bold font-mono text-slate-800">{{ number_format($order->cod_fee_minor / 100, 2) }} ج.م</span>
                    </div>
                @endif
                <div class="flex justify-between pt-2 border-t border-slate-100 font-bold text-sm">
                    <span class="text-slate-900">إجمالي التحصيل من العميل:</span>
                    <span class="font-mono text-indigo-700">{{ number_format($totalVal / 100, 2) }} ج.م</span>
                </div>
                <div class="flex justify-between p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 font-bold text-sm mt-3">
                    <span>صافي عمولتك وربحك:</span>
                    <span class="font-mono text-emerald-700 text-base">+{{ number_format($order->affiliate_profit_minor / 100, 2) }} ج.م</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Order Lines --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="p-4 bg-slate-50 border-b border-slate-200">
            <h3 class="text-sm font-black text-slate-900">عناصر ومنتجات الطلب</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50/50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4">المنتج</th>
                        <th class="py-3 px-4">كود SKU</th>
                        <th class="py-3 px-4">الكمية</th>
                        <th class="py-3 px-4">سعر البيع للقطعة</th>
                        <th class="py-3 px-4">إجمالي السطر</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($order->lines as $line)
                        @php
                            $unitPrice = is_object($line->unit_price) && isset($line->unit_price->value) ? (int) $line->unit_price->value : (int) $line->unit_price;
                            $sub = is_object($line->sub_total) && isset($line->sub_total->value) ? (int) $line->sub_total->value : (int) $line->sub_total;
                        @endphp
                        <tr>
                            <td class="py-3 px-4 font-semibold text-slate-900">{{ $line->description }}</td>
                            <td class="py-3 px-4 font-mono text-slate-500">{{ $line->identifier }}</td>
                            <td class="py-3 px-4 font-bold font-mono">{{ $line->quantity }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-slate-800">{{ number_format($unitPrice / 100, 2) }} ج.م</td>
                            <td class="py-3 px-4 font-mono font-bold text-indigo-700">{{ number_format($sub / 100, 2) }} ج.م</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Cancel Modal --}}
@if($canCancel)
<div id="cancel-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-slate-900">هل أنت متأكد من إلغاء هذا الطلب؟</h3>
        <p class="text-xs text-slate-500">مسموح بإلغاء الطلب قبل البدء في تجهيزه وتعبئته بالمخزن. لا يمكن التراجع عن الإلغاء.</p>

        <form method="POST" action="{{ route('affiliate.orders.cancel', $order->reference) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">سبب الإلغاء (اختياري)</label>
                <textarea name="reason" rows="2" placeholder="العميل غيّر رأيه / خطأ في العنوان..."
                          class="w-full text-xs rounded-xl border-slate-200 py-2 focus:ring-rose-500 focus:border-rose-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('cancel-modal').classList.add('hidden')"
                        class="text-xs font-semibold px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl">
                    تراجع
                </button>
                <button type="submit" class="text-xs font-bold px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl shadow-sm">
                    تأكيد إلغاء الطلب
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

