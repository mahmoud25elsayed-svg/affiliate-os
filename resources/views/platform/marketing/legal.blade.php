@extends('platform.marketing.layout')

@section('content')
<section style="padding: 4rem 0;">
    <div class="container" style="max-width: 900px;">
        <div style="background: var(--surface-card); border: 1px solid var(--border); border-radius: 16px; padding: 3rem 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="legal-content" style="color: var(--text-main); font-size: 1.05rem; line-height: 1.8;">
                {!! $content !!}
            </div>
            
            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <a href="{{ route('platform.marketing.home') }}" class="btn btn-secondary">
                    ← العودة للصفحة الرئيسية
                </a>
                <a href="{{ route('onboarding.register') }}" class="btn btn-primary">
                    ابدأ تجربتك المجانية الآن
                </a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('styles')
<style>
    .legal-content h1 { font-size: 2rem; font-weight: 800; color: #fff; margin-bottom: 1.5rem; line-height: 1.3; border-bottom: 2px solid var(--primary); padding-bottom: 0.75rem; }
    .legal-content h2 { font-size: 1.4rem; font-weight: 700; color: #818cf8; margin-top: 2rem; margin-bottom: 1rem; }
    .legal-content h3 { font-size: 1.15rem; font-weight: 600; color: #fff; margin-top: 1.5rem; margin-bottom: 0.75rem; }
    .legal-content p { margin-bottom: 1.25rem; color: #d1d5db; }
    .legal-content ul, .legal-content ol { margin-bottom: 1.25rem; padding-right: 1.5rem; color: #d1d5db; }
    .legal-content li { margin-bottom: 0.5rem; }
    .legal-content hr { border: none; border-top: 1px solid var(--border); margin: 2rem 0; }
    .legal-content blockquote { background: rgba(79, 70, 229, 0.1); border-right: 4px solid var(--primary); padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; color: #e0e7ff; }
</style>
@endpush

