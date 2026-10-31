{{-- Way back into the merchant panel from the standalone merchant tools (they are not Filament pages). --}}
@php
    $panelTenant = tenant('id');
    $panelHome = $panelTenant ? route('filament.store.pages.dashboard', ['tenant' => $panelTenant]) : url('/store');
    $panelOrders = $panelTenant ? route('filament.store.resources.orders.index', ['tenant' => $panelTenant]) : url('/store');
@endphp
<nav dir="rtl" style="position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;padding:.6rem 1.25rem;background:#0f172a;color:#e2e8f0;font-family:Cairo,Inter,system-ui,sans-serif;font-size:.9rem;box-shadow:0 1px 3px rgba(0,0,0,.25)">
    <a href="{{ $panelHome }}" style="color:#fff;font-weight:700;text-decoration:none">&rarr; لوحة تحكم المتجر</a>
    <a href="{{ $panelOrders }}" style="color:#cbd5e1;text-decoration:none">الطلبات</a>
    @if (Route::has('store.call-center.index'))<a href="{{ route('store.call-center.index') }}" style="color:#cbd5e1;text-decoration:none">الكول سنتر</a>@endif
    @if (Route::has('store.shipments.index'))<a href="{{ route('store.shipments.index') }}" style="color:#cbd5e1;text-decoration:none">الشحنات</a>@endif
    @if (Route::has('store.reports.index'))<a href="{{ route('store.reports.index') }}" style="color:#cbd5e1;text-decoration:none">التقارير</a>@endif
    <span style="margin-inline-start:auto;color:#94a3b8">{{ auth()->user()?->name }}</span>
</nav>
