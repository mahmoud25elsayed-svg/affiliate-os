<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوليصة شحن طلب {{ $order->reference }}</title>
    <style>
        @page {
            size: A5 landscape;
            margin: 10mm;
        }
        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #111827;
            background: #fff;
            margin: 0;
            padding: 15px;
            font-size: 12px;
            line-height: 1.4;
        }
        .container {
            border: 2px solid #000;
            padding: 12px;
            border-radius: 4px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .title h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 900;
        }
        .title p {
            margin: 2px 0 0 0;
            font-size: 11px;
            color: #4b5563;
        }
        .barcode-box {
            text-align: center;
            border: 1px solid #000;
            padding: 6px 14px;
            background: #f9fafb;
        }
        .barcode-font {
            font-family: 'Courier New', Courier, monospace;
            font-size: 20px;
            letter-spacing: 5px;
            font-weight: bold;
            display: block;
        }
        .parties-grid {
            display: flex;
            gap: 15px;
            margin-bottom: 12px;
        }
        .party-card {
            flex: 1;
            border: 1px solid #374151;
            border-radius: 4px;
            padding: 8px 10px;
        }
        .party-card h3 {
            margin: 0 0 6px 0;
            font-size: 12px;
            background: #f3f4f6;
            padding: 3px 6px;
            border-bottom: 1px solid #d1d5db;
        }
        .party-card p {
            margin: 3px 0;
            font-size: 11px;
        }
        .cod-banner {
            border: 3px solid #dc2626;
            background: #fef2f2;
            padding: 10px;
            text-align: center;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .cod-banner h2 {
            margin: 0;
            font-size: 14px;
            color: #991b1b;
        }
        .cod-banner .amount {
            font-size: 24px;
            font-weight: 900;
            color: #b91c1c;
            margin: 4px 0 0 0;
        }
        .items-summary {
            border: 1px solid #9ca3af;
            padding: 8px;
            margin-bottom: 12px;
            background: #fafafa;
        }
        .items-summary h4 {
            margin: 0 0 4px 0;
            font-size: 11px;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            border-top: 1px dashed #000;
            padding-top: 8px;
            font-size: 11px;
        }
        .sig-box {
            width: 45%;
            height: 45px;
            border-bottom: 1px solid #9ca3af;
        }
        .action-bar {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        .btn {
            background: #111827;
            color: #fff;
            padding: 6px 14px;
            border: none;
            border-radius: 4px;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
            .container {
                border-width: 2px;
            }
        }
    </style>
</head>
<body>

    @if (! ($isPdf ?? false))
        <div class="action-bar no-print">
            <button onclick="window.print()" class="btn">طباعة البوليصة 🖨️</button>
            <a href="?pdf=1" class="btn" style="background: #4b5563;">تحميل كـ PDF 📄</a>
        </div>
    @endif

    <div class="container">
        <div class="header">
            <div class="title">
                <h1>بوليصة شحن وتوصيل داخلية</h1>
                <p>متجر: <strong>{{ $settings->platform_name ?? 'AffiliateOS Store' }}</strong> | التاريخ: {{ $order->created_at?->format('Y-m-d') }}</p>
            </div>
            <div class="barcode-box">
                <span class="barcode-font">*{{ $order->reference }}*</span>
                <span style="font-size: 10px; color: #4b5563;">رقم الشحنة المرجعي</span>
            </div>
        </div>

        <div class="parties-grid">
            <div class="party-card">
                <h3>بيانات الراسل (المتجر)</h3>
                <p><strong>الاسم:</strong> {{ $settings->platform_name ?? 'المتجر الإلكتروني' }}</p>
                <p><strong>هاتف التواصل:</strong> {{ $settings->support_phone ?? '' }}</p>
                <p><strong>المحافظة:</strong> القاهرة</p>
                <p><strong>نوع الشحنة:</strong> طرد تجاري مغلف</p>
            </div>
            <div class="party-card">
                <h3>بيانات المستلم (العميل)</h3>
                <p><strong>الاسم:</strong> {{ $order->shippingAddress?->first_name ?? 'العميل' }} {{ $order->shippingAddress?->last_name ?? '' }}</p>
                <p><strong>رقم الهاتف الأساسي:</strong> <span dir="ltr">{{ $order->shippingAddress?->contact_phone ?? $order->customer_phone_e164 }}</span></p>
                <p><strong>المحافظة والمدينة:</strong> {{ $order->shippingAddress?->city ?? '' }}</p>
                <p><strong>العنوان بالتفصيل:</strong> {{ $order->shippingAddress?->line_one ?? '' }}</p>
                @if ($order->notes)
                    <p><strong>ملاحظات التسليم:</strong> {{ $order->notes }}</p>
                @endif
            </div>
        </div>

        <div class="cod-banner">
            @if ($order->payment_method === 'cod')
                <h2>المبلغ المطلوب تحصيله نقداً عند الاستلام (COD)</h2>
                <div class="amount">{{ number_format(($order->total->value ?? 0) / 100, 2) }} جنيه مصري</div>
                <div style="font-size: 11px; color: #7f1d1d; margin-top: 3px;">* برجاء عدم تسليم الشحنة للعميل إلا بعد تحصيل كامل المبلغ أعلاه</div>
            @else
                <h2 style="color: #065f46;">شحنة مدفوعة مسبقاً (دفع إلكتروني)</h2>
                <div class="amount" style="color: #047857;">0.00 {{ \App\Support\Money::symbol() }}</div>
                <div style="font-size: 11px; color: #065f46; margin-top: 3px;">* تم سداد القيمة إلكترونياً بالكامل - لا يُحصّل أي مبلغ من العميل</div>
            @endif
        </div>

        <div class="items-summary">
            <h4>محتويات الطرد ({{ $order->lines->count() }} صنف):</h4>
            <div style="font-size: 11px; color: #374151;">
                @foreach ($order->lines as $line)
                    <span>• {{ $line->description }} (كمية: {{ $line->quantity }})</span>{{ ! $loop->last ? ' | ' : '' }}
                @endforeach
            </div>
        </div>

        <div class="signatures">
            <div>
                <span>توقيع واستلام مندوب الشحن:</span>
                <div class="sig-box"></div>
            </div>
            <div>
                <span>توقيع واستلام العميل المستلم:</span>
                <div class="sig-box"></div>
            </div>
        </div>
    </div>

</body>
</html>
