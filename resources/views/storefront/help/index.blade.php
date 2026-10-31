<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('مركز المساعدة والأسئلة الشائعة -') }} {{ $tenant->name ?? __('المتجر') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 py-12 space-y-8">

        <!-- Header -->
        <div class="text-center space-y-3">
            <h1 class="text-3xl font-black text-slate-900">{{ __('مركز المساعدة والأسئلة الشائعة') }}</h1>
            <p class="text-sm text-slate-500 max-w-lg mx-auto">
                {{ __('إجابات شاملة لجميع استفساراتك حول الشحن، التوصيل، طرق الدفع عند الاستلام، وسياسة الاستبدال والاسترجاع.') }}
            </p>
        </div>

        <!-- Search Bar -->
        <form action="{{ route('storefront.help.index') }}" method="GET" class="relative max-w-xl mx-auto">
            <input type="text" name="q" value="{{ $searchQuery }}" placeholder="{{ __('ابحث عن سؤال أو استفسار (مثال: موعد التوصيل، الاسترجاع)...') }}" class="w-full py-3.5 pr-12 pl-4 text-xs font-semibold rounded-2xl border border-slate-200 bg-white shadow-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            <span class="absolute right-4 top-3.5 text-slate-400">🔍</span>
        </form>

        <!-- Category Pills -->
        @if(! empty($categories))
            <div class="flex flex-wrap items-center justify-center gap-2">
                <a href="{{ route('storefront.help.index') }}" class="px-4 py-2 rounded-xl text-xs font-bold transition {{ empty($currentCategory) && empty($searchQuery) ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                    {{ __('جميع الأسئلة') }}
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('storefront.help.index', ['category' => $cat]) }}" class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $currentCategory === $cat ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
        @endif

        <!-- Q&A Accordion -->
        <div class="space-y-4">
            @forelse($articles as $art)
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-2">
                    <div class="flex items-start justify-between gap-4">
                        <h3 class="text-sm font-bold text-slate-900">{{ $art->question }}</h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 shrink-0">{{ $art->category }}</span>
                    </div>
                    <p class="text-xs leading-relaxed text-slate-600 whitespace-pre-wrap pt-2 border-t border-slate-100">
                        {{ $art->answer }}
                    </p>
                </div>
            @empty
                <div class="bg-white rounded-3xl border border-slate-200/80 p-12 text-center text-slate-400 text-xs">
                    {{ __('لم يتم العثور على إجابات تطابق استفسارك. يرجى مراجعة فريق الدعم الفني.') }}
                </div>
            @endforelse
        </div>

    </div>
</body>
</html>

