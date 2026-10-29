@extends('platform.marketing.layout')

@section('content')
<section style="padding: 5rem 0 3rem; text-align: center;">
    <div class="container" style="max-width: 850px;">
        <span class="badge">أفكار واستراتيجيات</span>
        <h1 style="font-size: 2.75rem; font-weight: 900; margin-bottom: 1.25rem;">مدونة التجارة والتسويق بالعمولة</h1>
        <p style="font-size: 1.15rem; color: var(--text-muted); line-height: 1.7;">
            مقالات عملية، دراسات حالة، وأفضل الممارسات لتنمية مبيعاتك وإدارة شبكات المسوقين في مصر والشرق الأوسط.
        </p>
    </div>
</section>

<section style="padding-top: 1rem; padding-bottom: 5rem;">
    <div class="container">
        <div class="grid-3">
            @foreach($articles as $article)
                <article class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; font-size: 0.85rem; color: #818cf8;">
                            <span>{{ $article['date'] }}</span>
                            <span>⏱️ {{ $article['reading_time'] }}</span>
                        </div>
                        <h2 style="font-size: 1.35rem; font-weight: 800; margin-bottom: 0.75rem; line-height: 1.4;">
                            <a href="{{ route('platform.marketing.blog.post', $article['slug']) }}" style="color: #fff; text-decoration: none;">
                                {{ $article['title'] }}
                            </a>
                        </h2>
                        <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.7; margin-bottom: 1.5rem;">
                            {{ $article['excerpt'] }}
                        </p>
                    </div>

                    <div style="border-top: 1px solid var(--border); padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">بواسطة: {{ $article['author'] }}</span>
                        <a href="{{ route('platform.marketing.blog.post', $article['slug']) }}" style="color: #818cf8; text-decoration: none; font-weight: 700; font-size: 0.9rem;">
                            اقرأ المزيد ←
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endsection
