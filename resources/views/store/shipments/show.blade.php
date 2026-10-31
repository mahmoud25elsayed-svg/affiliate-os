<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تتبع الشحنة {{ $shipment->tracking_number }} - لوحة المتجر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    @include('store.partials.panel-bar')
    <div class="max-w-5xl mx-auto px-4 py-8 space-y-6">

        <!-- Top Header & Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('store.shipments.index') }}" class="p-2 bg-white border border-slate-200 rounded-xl text-slate-500 hover:text-slate-900 transition">
                        ➜
                    </a>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 font-mono">{{ $shipment->tracking_number }}</h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            شحنة الطلب #{{ $shipment->order_id }} — شركة الشحن: {{ $shipment->courierAccount?->name ?? 'غير محدد' }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2 space-x-reverse">
                <a href="{{ route('store.shipments.waybill', $shipment) }}" target="_blank" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black transition shadow-md flex items-center gap-2">
                    <span>📄</span>
                    <span>طباعة بوليصة الشحن (AWB)</span>
                </a>
            </div>
        </div>

        <!-- Current Status Banner -->
        @php
            $badgeBg = match($shipment->status->color()) {
                'emerald' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                'blue' => 'bg-blue-50 border-blue-200 text-blue-800',
                'amber' => 'bg-amber-50 border-amber-200 text-amber-800',
                'indigo' => 'bg-indigo-50 border-indigo-200 text-indigo-800',
                default => 'bg-rose-50 border-rose-200 text-rose-800',
            };
        @endphp
        <div class="p-6 rounded-3xl border {{ $badgeBg }} flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
            <div>
                <span class="text-[11px] font-bold block opacity-75">الحالة الحالية للشحنة</span>
                <span class="text-xl font-black">{{ $shipment->status->label() }}</span>
            </div>
            <div class="text-xs font-medium">
                @if($shipment->delivered_at)
                    <span>تم التسليم بتاريخ: {{ $shipment->delivered_at->format('Y-m-d H:i') }}</span>
                @elseif($shipment->dispatched_at)
                    <span>تاريخ الخروج للشحن: {{ $shipment->dispatched_at->format('Y-m-d H:i') }}</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Left: Recipient & Financial Summary (1 Col) -->
            <div class="space-y-6">
                <!-- Recipient Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 space-y-4 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400">بيانات المستلم والتوصيل</h3>
                    <div class="space-y-2 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[11px]">اسم العميل</span>
                            <span class="font-black text-slate-900 text-sm">{{ $shipment->recipient_name }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">رقم الهاتف</span>
                            <a href="tel:{{ $shipment->recipient_phone }}" class="font-bold text-blue-600 hover:underline">
                                {{ $shipment->recipient_phone }}
                            </a>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">عنوان التسليم</span>
                            <span class="font-medium text-slate-700">
                                📍 {{ $shipment->delivery_city }}، {{ $shipment->delivery_address }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Financials Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 space-y-4 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400">البيانات المالية للتحصيل</h3>
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                            <span class="text-slate-500">مبلغ التحصيل (COD):</span>
                            <span class="font-black text-emerald-700 text-sm">
                                {{ number_format($shipment->cod_amount_minor / 100, 2) }} ج.م
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">رسوم التوصيل لشركة الشحن:</span>
                            <span class="font-bold text-slate-800">
                                {{ number_format($shipment->shipping_fees_minor / 100, 2) }} ج.م
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Tracking Timeline (2 Cols) -->
            <div class="md:col-span-2 space-y-6">
                <div class="bg-white rounded-3xl border border-slate-200 p-6 space-y-6 shadow-sm">
                    <h3 class="text-sm font-black text-slate-900">سجل تتبع الشحنة والمسار الزمني</h3>

                    <div class="relative border-r-2 border-slate-100 pr-6 space-y-6 mr-2">
                        @php
                            $history = array_reverse($shipment->status_history ?? []);
                        @endphp

                        @forelse($history as $item)
                            <div class="relative">
                                <!-- Dot -->
                                <div class="absolute -right-[31px] top-1 w-3 h-3 bg-blue-600 rounded-full border-2 border-white shadow-sm"></div>

                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-black text-slate-900">
                                            {{ \App\Domain\Fulfillment\Enums\ShipmentStatus::tryFrom($item['status'] ?? '')?->label() ?? $item['status'] }}
                                        </span>
                                        <span class="text-[11px] text-slate-400 font-mono">
                                            {{ isset($item['timestamp']) ? \Illuminate\Support\Carbon::parse($item['timestamp'])->format('Y-m-d H:i') : '' }}
                                        </span>
                                    </div>
                                    @if(!empty($item['note']))
                                        <p class="text-xs text-slate-500">{{ $item['note'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400">لا توجد سجلات تتبع إضافية.</div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

    </div>
</body>
</html>

