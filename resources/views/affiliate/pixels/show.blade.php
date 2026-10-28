@extends('layouts.affiliate')

@section('title', 'بكسلات التتبع الإعلاني')

@section('content')
<div class="max-w-2xl mx-auto py-6">
    <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-md space-y-6">
        <div class="border-b border-slate-100 pb-5">
            <h1 class="text-xl font-extrabold text-slate-900 flex items-center gap-2">
                <span>🎯</span>
                <span>بكسلات التتبع الإعلاني الخاصة بك (Meta & TikTok Pixel)</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                أدخل معرف البكسل الخاص بك ليتم إطلاقه تلقائياً عند زيارة العملاء لروابطك الترويجية أو صفحات الهبوط المضمنة لتتبع مبيعاتك وحملاتك الممولة.
            </p>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <form action="{{ route('affiliate.pixels.update') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Meta Pixel -->
            <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100 space-y-3">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📘</span>
                    <h3 class="text-sm font-bold text-slate-900">Meta Pixel ID (Facebook & Instagram)</h3>
                </div>
                <div>
                    <input type="text" name="meta_pixel_id" value="{{ old('meta_pixel_id', $affiliate->meta_pixel_id) }}"
                           placeholder="مثال: 123456789012345"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-mono text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <span class="text-[11px] text-slate-400 mt-1 block">سيتم إطلاق أحداث PageView و Purchase تلقائياً على بكسلك عند شراء العملاء القادمين عبر رابطك.</span>
                </div>
            </div>

            <!-- TikTok Pixel -->
            <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100 space-y-3">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🎵</span>
                    <h3 class="text-sm font-bold text-slate-900">TikTok Pixel ID</h3>
                </div>
                <div>
                    <input type="text" name="tiktok_pixel_id" value="{{ old('tiktok_pixel_id', $affiliate->tiktok_pixel_id) }}"
                           placeholder="مثال: C123456789ABCDEF"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 font-mono text-xs focus:ring-2 focus:ring-neutral-800 focus:outline-none">
                    <span class="text-[11px] text-slate-400 mt-1 block">معرف بكسل تيك توك المربوط بحسابك الإعلاني في TikTok Ads Manager.</span>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                    حفظ البكسلات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

