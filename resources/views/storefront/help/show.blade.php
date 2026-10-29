<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $article->question }} - {{ $tenant->name ?? 'المتجر' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 py-12 space-y-6">

        <div class="flex items-center justify-between">
            <a href="{{ route('storefront.help.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition">
                ← العودة لمركز المساعدة
            </a>
            <span class="text-xs font-bold px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700">{{ $article->category }}</span>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-8 space-y-6">
            <h1 class="text-2xl font-black text-slate-900 leading-snug">{{ $article->question }}</h1>
            <div class="text-xs leading-relaxed text-slate-600 whitespace-pre-wrap pt-4 border-t border-slate-100">
                {{ $article->answer }}
            </div>
        </div>

    </div>
</body>
</html>

