<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('فاتورة طلب') }} {{ $order->reference }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
            background: #fff;
            margin: 0;
            padding: 20px;
            font-size: 13px;
            line-height: 1.5;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .brand h1 {
            margin: 0;
            font-size: 22px;
            color: #111827;
        }
        .brand p {
            margin: 4px 0 0 0;
            color: #6b7280;
            font-size: 12px;
        }
        .invoice-title {
            text-align: left;
        }
        .invoice-title h2 {
            margin: 0;
            font-size: 18px;
            color: #059669;
        }
        .invoice-title p {
            margin: 3px 0 0 0;
            font-size: 12px;
            color: #4b5563;
        }
        .meta-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }
        .meta-box {
            flex: 1;
            background: #f9fafb;
            border: 1px solid #f3f4f6;
            border-radius: 8px;
            padding: 14px;
        }
        .meta-box h3 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }
        .meta-box p {
            margin: 4px 0;
            font-size: 12px;
        }
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        table.items-table th, table.items-table td {
            border: 1px solid #e5e7eb;
            padding: 10px;
            text-align: right;
        }
        table.items-table th {
            background: #f9fafb;
            font-weight: bold;
            color: #374151;
        }
        .totals-table {
            width: 320px;
            margin-right: auto;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .totals-table td {
            padding: 6px 12px;
        }
        .totals-table tr.grand-total {
            background: #ecfdf5;
            font-size: 15px;
            font-weight: bold;
            color: #065f46;
            border-top: 2px solid #059669;
        }
        .footer-note {
            border-top: 1px dashed #d1d5db;
            padding-top: 15px;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }
        .action-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            background: #059669;
            color: #fff;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary {
            background: #4b5563;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>

    @if (! ($isPdf ?? false))
        <div class="action-bar no-print">
            <button onclick="window.print()" class="btn">{{ __('طباعة الفاتورة 🖨️') }}</button>
            <a href="?pdf=1" class="btn btn-secondary">{{ __('تحميل كملف PDF 📄') }}</a>
        </div>
    @endif

    <div class="header">
        <div class="brand">
            <h1>{{ $settings->platform_name ?? __('متجر إلكتروني') }}</h1>
            <p>{{ __('خدمة العملاء:') }} {{ $settings->support_phone ?? '' }} | {{ $settings->support_email ?? '' }}</p>
        </div>
        <div class="invoice-title">
            <h2>{{ __('فاتورة مبيعات') }}</h2>
            <p>{{ __('رقم الفاتورة:') }} <strong>{{ $order->reference }}</strong></p>
            <p>{{ __('تاريخ الطلب:') }} {{ $order->created_at?->format('Y-m-d H:i') }}</p>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-box">
            <h3>{{ __('بيانات العميل والتوصيل') }}</h3>
            <p><strong>{{ __('الاسم:') }}</strong> {{ $order->shippingAddress?->first_name ?? __('عميل') }} {{ $order->shippingAddress?->last_name ?? '' }}</p>
            <p><strong>{{ __('الهاتف:') }}</strong> {{ $order->shippingAddress?->contact_phone ?? $order->customer_phone_e164 }}</p>
            <p><strong>{{ __('المحافظة:') }}</strong> {{ $order->shippingAddress?->city ?? __('جمهورية مصر العربية') }}</p>
            <p><strong>{{ __('العنوان:') }}</strong> {{ $order->shippingAddress?->line_one ?? '' }}</p>
        </div>
        <div class="meta-box">
            <h3>{{ __('تفاصيل الطلب والدفع') }}</h3>
            <p><strong>{{ __('طريقة الدفع:') }}</strong> {{ $order->payment_method === 'cod' ? __('الدفع عند الاستلام (COD)') : __('دفع إلكتروني') }}</p>
            <p><strong>{{ __('حالة الطلب:') }}</strong> {{ \App\Domain\Orders\Enums\OrderStatus::tryFrom($order->status)?->label() ?? $order->status }}</p>
            @if ($order->notes)
                <p><strong>{{ __('ملاحظات العميل:') }}</strong> {{ $order->notes }}</p>
            @endif
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">#</th>
                <th>{{ __('اسم المنتج / الصنف') }}</th>
                <th style="width: 120px;">{{ __('كود الصنف (SKU)') }}</th>
                <th style="width: 60px; text-align: center;">{{ __('الكمية') }}</th>
                <th style="width: 100px; text-align: left;">{{ __('سعر الوحدة') }}</th>
                <th style="width: 110px; text-align: left;">{{ __('المجموع') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->lines as $index => $line)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $line->description }}</strong>
                    </td>
                    <td>{{ $line->identifier ?? '-' }}</td>
                    <td style="text-align: center;">{{ $line->quantity }}</td>
                    <td style="text-align: left;">{{ \App\Support\Money::show(($line->unit_price->value ?? 0)) }}</td>
                    <td style="text-align: left;">{{ \App\Support\Money::show(($line->sub_total->value ?? 0)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>{{ __('المجموع الفرعي:') }}</td>
            <td style="text-align: left; font-weight: bold;">{{ \App\Support\Money::show(($order->sub_total->value ?? 0)) }}</td>
        </tr>
        @if (($order->discount_total->value ?? 0) > 0)
            <tr style="color: #059669;">
                <td>{{ __('خصم الكوبون:') }}</td>
                <td style="text-align: left; font-weight: bold;">-{{ \App\Support\Money::show(($order->discount_total->value ?? 0)) }}</td>
            </tr>
        @endif
        <tr>
            <td>{{ __('تكلفة الشحن:') }}</td>
            <td style="text-align: left;">{{ \App\Support\Money::show(($order->shipping_total->value ?? 0)) }}</td>
        </tr>
        @if ($order->cod_fee_minor > 0)
            <tr>
                <td>{{ __('رسوم الدفع عند الاستلام:') }}</td>
                <td style="text-align: left;">{{ \App\Support\Money::show($order->cod_fee_minor) }}</td>
            </tr>
        @endif
        <tr class="grand-total">
            <td>{{ __('الإجمالي المطلوب سداده:') }}</td>
            <td style="text-align: left;">{{ \App\Support\Money::show(($order->total->value ?? 0)) }}</td>
        </tr>
    </table>

    <div class="footer-note">
        <p>{{ __('شكراً لطلبكم من متجرنا. يحق للعميل استبدال أو استرجاع المنتج خلال 14 يوماً وفق قوانين حماية المستهلك المعمول بها.') }}</p>
        <p>{{ __('تم استخراج هذه الفاتورة آلياً بواسطة المنصة.') }}</p>
    </div>

</body>
</html>
