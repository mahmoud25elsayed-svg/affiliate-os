<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('المتجر مغلق مؤقتًا') }} - {{ $tenantName ?? 'المتجر' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a, #030712);
            color: #f8fafc;
            padding: 24px;
        }
        .card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 48px 36px;
            max-width: 540px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(249, 115, 22, 0.2));
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            color: #f87171;
        }
        h1 {
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
        }
        .store-badge {
            display: inline-block;
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            padding: 4px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        p {
            color: #94a3b8;
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 28px;
        }
        .footer-note {
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-circle">
            <svg style="width: 40px; height: 40px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>

        <div class="store-badge">{{ $tenantName ?? 'المتجر الإلكتروني' }}</div>

        <h1>{{ __('المتجر مغلق مؤقتًا') }}</h1>

        <p>
            {{ __('هذا المتجر غير متاح لاستقبال الطلبات في الوقت الحالي نظرًا لأعمال الصيانة والتحديث. يرجى معاودة الزيارة لاحقًا.') }}
        </p>

        <div class="footer-note">
            {{ __('هل أنت صاحب المتجر؟ يرجى تسجيل الدخول إلى لوحة التحكم لإدارة خطة الاشتراك.') }}
        </div>
    </div>
</body>
</html>
