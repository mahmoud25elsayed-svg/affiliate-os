<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل تاجر جديد | AffiliateOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --bg: #090d16;
            --card-bg: rgba(18, 24, 38, 0.85);
            --border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --success: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', sans-serif; }
        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(99, 102, 241, 0.2) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(168, 85, 247, 0.2) 0%, transparent 40%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
        }
        .container {
            width: 100%;
            max-width: 620px;
        }
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            padding: 2.75rem 2.25rem;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7);
        }
        .header { text-align: center; margin-bottom: 2.25rem; }
        .logo {
            font-size: 1.4rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.75rem;
            display: inline-block;
        }
        .title { font-size: 1.75rem; font-weight: 800; margin-bottom: 0.5rem; }
        .subtitle { font-size: 0.95rem; color: var(--text-muted); }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        .full-width { grid-column: span 2; }
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: #e2e8f0;
        }
        .form-input {
            width: 100%;
            padding: 0.85rem 1rem;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s ease;
        }
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }
        .input-group {
            display: flex;
            gap: 0.5rem;
        }
        .subdomain-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
            direction: ltr;
        }
        .subdomain-suffix {
            position: absolute;
            right: 1rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            pointer-events: none;
            direction: ltr;
        }
        .subdomain-input {
            padding-right: 130px;
            direction: ltr;
            text-align: left;
        }
        .status-badge {
            font-size: 0.8rem;
            margin-top: 0.35rem;
            font-weight: 600;
            display: block;
        }
        .status-badge.available { color: var(--success); }
        .status-badge.unavailable { color: var(--danger); }
        .btn-otp {
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 0.75rem;
            padding: 0 1.25rem;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-otp:hover:not(:disabled) {
            background: rgba(99, 102, 241, 0.3);
            color: #fff;
        }
        .btn-otp:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: var(--primary-gradient);
            color: #fff;
            border: none;
            border-radius: 0.85rem;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
            margin-top: 1rem;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(99, 102, 241, 0.6);
        }
        .error-text {
            color: var(--danger);
            font-size: 0.8rem;
            margin-top: 0.35rem;
            display: block;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #fca5a5;
            font-size: 0.9rem;
        }
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <a href="{{ url('/') }}" class="logo">⚡ AffiliateOS</a>
                <h1 class="title">سجل متجرك الآن مجاناً</h1>
                <p class="subtitle">أنشئ متجرك الإلكتروني وبرنامج الأفلييت وابدأ البيع خلال دقيقة واحدة</p>
            </div>

            @if ($errors->any())
                <div class="alert-error">
                    <ul style="padding-right: 1.25rem;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('onboarding.register.submit') }}" method="POST" id="regForm">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name" class="form-label">الاسم الكامل</label>
                        <input type="text" id="name" name="name" class="form-input" value="{{ old('name') }}" placeholder="أحمد محمد" required>
                    </div>

                    <div class="form-group">
                        <label for="store_name" class="form-label">اسم المتجر</label>
                        <input type="text" id="store_name" name="store_name" class="form-input" value="{{ old('store_name') }}" placeholder="متجر النخبة" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="subdomain" class="form-label">رابط المتجر (النطاق الفرعي بالإنجليزية)</label>
                        <div class="subdomain-input-wrap">
                            <input type="text" id="subdomain" name="subdomain" class="form-input subdomain-input" value="{{ old('subdomain') }}" placeholder="el-nokhba" required>
                            <span class="subdomain-suffix">.{{ config('tenancy.root_domain', 'localhost') }}</span>
                        </div>
                        <span id="subdomainStatus" class="status-badge"></span>
                    </div>

                    <div class="form-group full-width">
                        <label for="phone" class="form-label">رقم الهاتف المصري (لإرسال رمز التحقق OTP)</label>
                        <div class="input-group">
                            <input type="tel" id="phone" name="phone" class="form-input" value="{{ old('phone') }}" placeholder="01012345678" required>
                            <button type="button" id="btnSendOtp" class="btn-otp">إرسال الرمز</button>
                        </div>
                        <span id="otpStatus" class="status-badge"></span>
                    </div>

                    <div class="form-group full-width">
                        <label for="otp" class="form-label">رمز التحقق (OTP مكون من 6 أرقام)</label>
                        <input type="text" id="otp" name="otp" class="form-input" placeholder="123456" maxlength="6" required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" id="email" name="email" class="form-input" value="{{ old('email') }}" placeholder="ahmed@example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">كلمة المرور (8 خانات على الأقل)</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">إنشاء المتجر وتجهيز الحساب ←</button>
            </form>
        </div>
    </div>

    <script>
        const subdomainInput = document.getElementById('subdomain');
        const subdomainStatus = document.getElementById('subdomainStatus');
        let checkTimeout = null;

        subdomainInput.addEventListener('input', function() {
            clearTimeout(checkTimeout);
            const val = this.value.trim().toLowerCase();
            if (val.length < 3) {
                subdomainStatus.textContent = '';
                return;
            }

            checkTimeout = setTimeout(() => {
                fetch(`{{ route('onboarding.check-subdomain') }}?subdomain=${encodeURIComponent(val)}`)
                    .then(r => r.json())
                    .then(data => {
                        subdomainStatus.textContent = data.message;
                        subdomainStatus.className = 'status-badge ' + (data.available ? 'available' : 'unavailable');
                    })
                    .catch(() => {});
            }, 300);
        });

        const btnSendOtp = document.getElementById('btnSendOtp');
        const phoneInput = document.getElementById('phone');
        const otpStatus = document.getElementById('otpStatus');

        btnSendOtp.addEventListener('click', function() {
            const phone = phoneInput.value.trim();
            if (!phone) {
                otpStatus.textContent = 'يرجى إدخال رقم الهاتف أولاً';
                otpStatus.className = 'status-badge unavailable';
                return;
            }

            btnSendOtp.disabled = true;
            btnSendOtp.textContent = 'جاري الإرسال...';

            fetch(`{{ route('onboarding.send-otp') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ phone: phone })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    otpStatus.textContent = 'تم إرسال رمز التحقق بنجاح.';
                    otpStatus.className = 'status-badge available';
                    if (data.code) {
                        document.getElementById('otp').value = data.code;
                    }
                    startCooldown(60);
                } else {
                    otpStatus.textContent = data.message || 'فشل إرسال الرمز';
                    otpStatus.className = 'status-badge unavailable';
                    btnSendOtp.disabled = false;
                    btnSendOtp.textContent = 'إرسال الرمز';
                }
            })
            .catch(() => {
                otpStatus.textContent = 'حدث خطأ في الاتصال';
                otpStatus.className = 'status-badge unavailable';
                btnSendOtp.disabled = false;
                btnSendOtp.textContent = 'إرسال الرمز';
            });
        });

        function startCooldown(sec) {
            let left = sec;
            const timer = setInterval(() => {
                left--;
                if (left <= 0) {
                    clearInterval(timer);
                    btnSendOtp.disabled = false;
                    btnSendOtp.textContent = 'إعادة إرسال الرمز';
                } else {
                    btnSendOtp.textContent = `انتظر (${left}) ثانية`;
                }
            }, 1000);
        }
    </script>
</body>
</html>
