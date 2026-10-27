<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جاري تجهيز متجرك... | AffiliateOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --bg: #090d16;
            --card-bg: rgba(18, 24, 38, 0.85);
            --border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', sans-serif; }
        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(circle at 50% 30%, rgba(99, 102, 241, 0.25) 0%, transparent 60%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
        }
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            max-width: 520px;
            width: 100%;
            padding: 3rem 2.5rem;
            text-align: center;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7);
        }
        .spinner {
            width: 72px;
            height: 72px;
            border: 4px solid rgba(99, 102, 241, 0.2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 2rem;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .title { font-size: 1.75rem; font-weight: 800; margin-bottom: 0.5rem; }
        .subtitle { font-size: 1rem; color: var(--text-muted); margin-bottom: 2rem; }
        .steps {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            text-align: right;
            margin-bottom: 2rem;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0;
            font-size: 0.95rem;
            color: var(--text-muted);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .step:last-child { border-bottom: none; }
        .step.done { color: var(--text-main); }
        .step-icon {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
        }
        .step.done .step-icon {
            background: var(--success);
            color: #fff;
        }
        .progress-bar-wrap {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .progress-bar {
            height: 100%;
            width: 65%;
            background: var(--primary-gradient);
            border-radius: 9999px;
            animation: progress-indet 2s infinite ease-in-out;
        }
        @keyframes progress-indet {
            0% { width: 15%; margin-right: 0; }
            50% { width: 75%; margin-right: 25%; }
            100% { width: 15%; margin-right: 85%; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner" id="mainSpinner"></div>
        <h1 class="title">جاري تجهيز متجرك...</h1>
        <p class="subtitle">نحن نقوم الآن بإنشاء المتجر وتعيين كافة الإعدادات الافتراضية لمتجر <strong style="color: #fff;">{{ $tenant->name }}</strong>.</p>

        <div class="progress-bar-wrap">
            <div class="progress-bar"></div>
        </div>

        <div class="steps">
            <div class="step done">
                <span class="step-icon">✓</span>
                <span>تسجيل بيانات التاجر وحجز النطاق الفرعي</span>
            </div>
            <div class="step done">
                <span class="step-icon">✓</span>
                <span>تهيئة قاعدة البيانات والعزل الرقمي التام</span>
            </div>
            <div class="step" id="stepLunar">
                <span class="step-icon" id="iconLunar">⚡</span>
                <span>تجهيز العملة المصرية EGP وقنوات البيع والكتالوج</span>
            </div>
            <div class="step" id="stepRoles">
                <span class="step-icon" id="iconRoles">🔒</span>
                <span>تفعيل صلاحيات المالك ونظام الأمان المتقدم</span>
            </div>
        </div>

        <p style="font-size: 0.85rem; color: var(--text-muted);">سيتم توجيهك تلقائياً إلى لوحة التحكم فور الانتهاء...</p>
    </div>

    <script>
        const tenantId = "{{ $tenant->id }}";
        const pollUrl = "{{ route('onboarding.provisioning', ['tenant' => $tenant->id]) }}";

        const interval = setInterval(() => {
            fetch(pollUrl, {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.ready) {
                    clearInterval(interval);
                    document.getElementById('stepLunar').className = 'step done';
                    document.getElementById('iconLunar').textContent = '✓';
                    document.getElementById('stepRoles').className = 'step done';
                    document.getElementById('iconRoles').textContent = '✓';
                    
                    setTimeout(() => {
                        window.location.href = data.store_url;
                    }, 500);
                }
            })
            .catch(() => {});
        }, 1500);
    </script>
</body>
</html>
