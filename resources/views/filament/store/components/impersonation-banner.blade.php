@if (session()->has('impersonator_id'))
    <div style="background: #f59e0b; color: #111827; padding: 0.65rem 1.25rem; font-weight: 700; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; z-index: 99999; position: relative; border-bottom: 2px solid #d97706; font-family: 'Cairo', sans-serif;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>⚠️</span>
            <span>أنت تتصفح هذا المتجر الآن كمدير للمنصة بصلاحيات مؤقتة (الدخول كتاجر مسجل في سجل التدقيق).</span>
        </div>
        <form action="{{ route('impersonation.stop') }}" method="POST" style="margin: 0;">
            @csrf
            <button type="submit" style="background: #111827; color: #ffffff; border: none; padding: 0.35rem 0.85rem; border-radius: 0.375rem; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: background 0.2s;">
                إنهاء الجلسة والعودة للوحة المنصة ←
            </button>
        </form>
    </div>
@endif
