@extends('platform.marketing.layout')

@section('content')
<section style="padding: 4rem 0 2rem;">
    <div class="container" style="max-width: 800px;">
        <a href="{{ route('platform.marketing.blog') }}" style="color: #818cf8; text-decoration: none; font-weight: 700; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem;">
            → العودة إلى مقالات المدونة
        </a>

        <div style="display: flex; gap: 1rem; align-items: center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
            <span>📅 {{ $article['date'] }}</span>
            <span>•</span>
            <span>⏱️ وقت القراءة: {{ $article['reading_time'] }}</span>
            <span>•</span>
            <span>✍️ {{ $article['author'] }}</span>
        </div>

        <h1 style="font-size: 2.75rem; font-weight: 900; line-height: 1.3; margin-bottom: 1.5rem; color: #fff;">
            {{ $article['title'] }}
        </h1>

        <div class="card" style="padding: 2.5rem; margin-bottom: 3rem; background: rgba(255, 255, 255, 0.03);">
            <p style="font-size: 1.2rem; color: #cbd5e1; font-weight: 600; line-height: 1.8;">
                {{ $article['excerpt'] }}
            </p>
        </div>

        <div style="color: #e2e8f0; font-size: 1.15rem; line-height: 1.9; display: flex; flex-direction: column; gap: 1.5rem;">
            <p>{{ $article['content'] }}</p>
            <p>
                تُعد إدارة عمليات التجارة والتسويق بالعمولة بمثابة جسر الثقة الذي يربط بين جهد المسوق التسويقي وعمليات التاجر التنفيذية. عندما تتوفر الشفافية عبر لوحات تحكم واضحة وتقارير مالية لحظية، يتضاعف حماس المسوقين وتتضاعف معه مبيعات متجرك دون الحاجة لزيادة ميزانيات الإعلانات المباشرة.
            </p>
            <p>
                توفر منصة AffiliateOS هذا التناغم عبر تقنيات ربط بوالص الشحن، وإسناد النقرة الأخيرة، وجدولة تسويات COD بدقة رياضية متناهية.
            </p>
        </div>

        <!-- Author / CTA Box -->
        <div class="card" style="margin-top: 4rem; padding: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; border-color: rgba(99, 102, 241, 0.4);">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.35rem;">جاهز لتطبيق هذه الحلول في تجارتك؟</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">ابدأ تجربتك المجانية واكتشف كيف يغير AffiliateOS طريقة إدارتك للمبيعات.</p>
            </div>
            <a href="{{ route('onboarding.register') }}" class="btn btn-primary">
                ابدأ تجربتك المجانية
            </a>
        </div>
    </div>
</section>

<!-- JSON-LD BlogPosting Schema -->
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "BlogPosting",
  "headline": "{{ $article['title'] }}",
  "description": "{{ $article['excerpt'] }}",
  "author": {
    "@@type": "Organization",
    "name": "{{ $article['author'] }}"
  },
  "datePublished": "{{ $article['date'] }}",
  "inLanguage": "ar"
}
</script>
@endsection
