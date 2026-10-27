<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل المصادقة الثنائية (2FA) | AffiliateOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.8);
            --border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', 'Inter', sans-serif; }
        body {
            background-color: var(--bg);
            background-image: radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.2) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(14, 165, 233, 0.2) 0px, transparent 50%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            max-width: 500px;
            width: 100%;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .header { text-align: center; margin-bottom: 2rem; }
        .title { font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; }
        .subtitle { font-size: 0.95rem; color: var(--text-muted); line-height: 1.6; }
        .secret-box {
            background: rgba(15, 23, 42, 0.8);
            border: 1px dashed var(--border);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            margin: 1.5rem 0;
        }
        .secret-label { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .secret-key { font-family: monospace; font-size: 1.2rem; font-weight: 700; color: #38bdf8; letter-spacing: 2px; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; }
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            color: #fff;
            font-size: 1.25rem;
            text-align: center;
            letter-spacing: 6px;
            outline: none;
            transition: all 0.2s;
        }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3); }
        .error-message { color: var(--danger); font-size: 0.85rem; margin-top: 0.5rem; }
        .btn-submit {
            width: 100%;
            padding: 0.875rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover { background: var(--primary-hover); }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 class="title">تفعيل المصادقة الثنائية (2FA)</h1>
            <p class="subtitle">المصادقة الثنائية إلزامية لمديري المنصة لضمان أمان النظام وحماية الحسابات.</p>
        </div>

        <div class="secret-box">
            <div class="secret-label">أدخل المفتاح السري يدويًا في تطبيق المصادقة (Google Authenticator / Authy):</div>
            <div class="secret-key">{{ $secret }}</div>
        </div>

        <form action="{{ route('platform.2fa.confirm') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="code" class="form-label">رمز التحقق المكون من 6 أرقام</label>
                <input type="text" id="code" name="code" class="form-input" placeholder="000000" maxlength="6" autofocus required>
                @error('code')
                    <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn-submit">تأكيد التفعيل والدخول</button>
        </form>
    </div>
</body>
</html>
