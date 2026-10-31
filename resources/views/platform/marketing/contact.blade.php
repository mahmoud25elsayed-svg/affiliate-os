@extends('platform.marketing.layout')

@section('content')
<section style="padding: 5rem 0 3rem; text-align: center;">
    <div class="container" style="max-width: 850px;">
        <span class="badge">{{ __('نحن هنا لمساعدتك') }}</span>
        <h1 style="font-size: 2.75rem; font-weight: 900; margin-bottom: 1.25rem;">{{ __('تواصل مع فريق مستشاري المنصة') }}</h1>
        <p style="font-size: 1.15rem; color: var(--text-muted); line-height: 1.7;">
            {{ __('سواء كنت تاجراً ترغب في بدء تجارتك، أو لديك استفسار عن الخطط والربط اللوجستي، نحن سعداء بالتواصل معك.') }}
        </p>
    </div>
</section>

<section style="padding-top: 1rem; padding-bottom: 5rem;">
    <div class="container" style="max-width: 680px;">
        <div class="card" style="padding: 2.5rem;">
            @if ($errors->any())
                <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <ul style="list-style: none;">
                        @foreach ($errors->all() as $error)
                            <li>⚠️ {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('platform.marketing.contact.submit') }}" method="POST" style="display: flex; flex-direction: column; gap: 1.5rem;">
                @csrf
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 0.5rem; color: #fff;">{{ __('الاسم الكامل *') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" required style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); color: #fff; font-size: 1rem;" placeholder="{{ __('مثال: أحمد محمد') }}">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 700; margin-bottom: 0.5rem; color: #fff;">{{ __('البريد الإلكتروني *') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" required style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); color: #fff; font-size: 1rem;" placeholder="name@company.com">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 700; margin-bottom: 0.5rem; color: #fff;">{{ __('رقم الهاتف المحمول *') }}</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" required style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); color: #fff; font-size: 1rem;" placeholder="010XXXXXXXX">
                    </div>
                </div>

                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 0.5rem; color: #fff;">{{ __('نوع النشاط أو المتجر') }}</label>
                    <select name="business_type" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; background: #1f2937; border: 1px solid var(--border); color: #fff; font-size: 1rem;">
                        <option value="fashion">{{ __('ملابس وأزياء') }}</option>
                        <option value="electronics">{{ __('إلكترونيات وإكسسوارات') }}</option>
                        <option value="cosmetics">{{ __('مستحضرات تجميل وعناية') }}</option>
                        <option value="home">{{ __('مستلزمات منزل وديكور') }}</option>
                        <option value="other">{{ __('نشاط تجاري آخر') }}</option>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 0.5rem; color: #fff;">{{ __('تفاصيل الاستفسار أو الرسالة *') }}</label>
                    <textarea name="message" rows="5" required style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); color: #fff; font-size: 1rem; line-height: 1.6;" placeholder="{{ __('اكتب استفسارك هنا...') }}">{{ old('message') }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.9rem; font-size: 1.1rem; width: 100%;">
                    {{ __('إرسال الاستفسار') }}
                </button>
            </form>
        </div>
    </div>
</section>
@endsection
