<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('فاتورة رقم') }} {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', 'Amiri', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            direction: rtl;
            text-align: right;
            color: #1e293b;
            margin: 0;
            padding: 30px;
            font-size: 13px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .brand-title {
            font-size: 24px;
            font-weight: bold;
            color: #4f46e5;
            margin: 0 0 5px 0;
        }
        .brand-sub {
            color: #64748b;
            font-size: 12px;
            margin: 0;
        }
        .invoice-title {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 5px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-paid {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-pending {
            background-color: #fef9c3;
            color: #854d0e;
        }
        .badge-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 25px;
        }
        .info-col {
            vertical-align: top;
            width: 50%;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .info-label {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .info-value {
            font-size: 13px;
            color: #0f172a;
            font-weight: 600;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            text-align: right;
            padding: 10px 12px;
            border-bottom: 2px solid #cbd5e1;
            font-size: 12px;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .total-box {
            width: 40%;
            margin-right: auto;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #cbd5e1;
        }
        .total-row.grand-total {
            border-bottom: none;
            border-top: 2px solid #4f46e5;
            margin-top: 6px;
            padding-top: 10px;
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #94a3b8;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: middle;">
                    <h1 class="brand-title">AffiliateOS</h1>
                    <p class="brand-sub">{{ __('منصة التجارة الإلكترونية والتسويق بالعمولة') }}</p>
                </td>
                <td style="vertical-align: middle; text-align: left;">
                    <div class="invoice-title">{{ __('فاتورة اشتراك') }}</div>
                    <div style="font-size: 13px; color: #64748b; margin-bottom: 6px;">{{ $invoice->invoice_number }}</div>
                    @if($invoice->status === 'paid')
                        <span class="badge badge-paid">{{ __('مدفوعة') }}</span>
                    @elseif($invoice->status === 'pending')
                        <span class="badge badge-pending">{{ __('قيد المراجعة') }}</span>
                    @else
                        <span class="badge badge-rejected">{{ __('مرفوضة') }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="info-grid">
        <tr>
            <td class="info-col" style="padding-left: 10px;">
                <div class="info-card">
                    <div class="info-label">{{ __('بيانات المتجر والتاجر') }}</div>
                    <div class="info-value">{{ $invoice->tenant->name }}</div>
                    <div style="color: #64748b; font-size: 12px; margin-top: 2px;">
                        {{ __('معرّف المتجر:') }} {{ $invoice->tenant->id }}<br>
                        {{ __('البريد الإلكتروني:') }} {{ $invoice->tenant->owner?->email ?? '-' }}
                    </div>
                </div>
            </td>
            <td class="info-col" style="padding-right: 10px;">
                <div class="info-card">
                    <div class="info-label">{{ __('تواريخ الفاتورة') }}</div>
                    <table style="width: 100%; font-size: 12px;">
                        <tr>
                            <td style="color: #64748b;">{{ __('تاريخ الإصدار:') }}</td>
                            <td style="font-weight: 600; text-align: left;">{{ $invoice->created_at?->format('Y-m-d') }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">{{ __('فترة الاشتراك:') }}</td>
                            <td style="font-weight: 600; text-align: left;">{{ $invoice->billing_period_starts_at->format('Y-m-d') }} {{ __('إلى') }} {{ $invoice->billing_period_ends_at->format('Y-m-d') }}</td>
                        </tr>
                        @if($invoice->paid_at)
                        <tr>
                            <td style="color: #166534; font-weight: bold;">{{ __('تاريخ السداد:') }}</td>
                            <td style="font-weight: bold; color: #166534; text-align: left;">{{ $invoice->paid_at->format('Y-m-d H:i') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>{{ __('البند / الوصف') }}</th>
                <th style="width: 15%; text-align: center;">{{ __('الدورة') }}</th>
                <th style="width: 15%; text-align: center;">{{ __('الكمية') }}</th>
                <th style="width: 25%; text-align: left;">{{ __('المبلغ') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div style="font-weight: bold; color: #0f172a;">
                        {{ __('اشتراك') }} {{ $invoice->subscription->plan->name }}
                    </div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                        {{ $invoice->subscription->plan->description }}
                    </div>
                </td>
                <td style="text-align: center;">
                    {{ $invoice->subscription->billing_cycle === 'yearly' ? __('سنوي') : __('شهري') }}
                </td>
                <td style="text-align: center;">1</td>
                <td style="text-align: left; font-weight: bold;">
                    {{ $invoice->amountMoney()->format() }}
                </td>
            </tr>
        </tbody>
    </table>

    <table style="width: 100%;">
        <tr>
            <td style="vertical-align: top; width: 55%; color: #64748b; font-size: 11px;">
                @if($invoice->payment_reference)
                    <strong>{{ __('الرقم المرجعي للعملية:') }}</strong> {{ $invoice->payment_reference }}<br>
                @endif
                @if($invoice->payment_method)
                    <strong>{{ __('طريقة السداد:') }}</strong> {{ $invoice->payment_method }}<br>
                @endif
                @if($invoice->notes)
                    <strong>{{ __('ملاحظات:') }}</strong> {{ $invoice->notes }}<br>
                @endif
            </td>
            <td style="vertical-align: top; width: 45%;">
                <table style="width: 100%; border-collapse: collapse; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <tr>
                        <td style="padding: 8px 12px; color: #64748b;">{{ __('المجموع الفرعي:') }}</td>
                        <td style="padding: 8px 12px; text-align: left; font-weight: 600;">{{ $invoice->amountMoney()->format() }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 12px; color: #64748b;">{{ __('ضريبة القيمة المضافة:') }}</td>
                        <td style="padding: 8px 12px; text-align: left; font-weight: 600;">0.00 {{ \App\Support\Money::symbol() }}</td>
                    </tr>
                    <tr style="border-top: 2px solid #4f46e5;">
                        <td style="padding: 10px 12px; font-weight: bold; color: #4f46e5; font-size: 14px;">{{ __('الإجمالي الكلي:') }}</td>
                        <td style="padding: 10px 12px; text-align: left; font-weight: bold; color: #4f46e5; font-size: 14px;">{{ $invoice->amountMoney()->format() }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        <p>{{ __('شكرًا لاشتراككم في منصة AffiliateOS. هذه الفاتورة صادرة إلكترونيًا ومسجلة على النظام.') }}</p>
    </div>
</body>
</html>
