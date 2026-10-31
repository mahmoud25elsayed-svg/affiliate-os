<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('حدث خطأ غير متوقع - 500') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full text-center space-y-6 bg-gray-800/80 backdrop-blur p-8 rounded-3xl border border-gray-700 shadow-2xl">
        <span class="text-7xl block">⚠️</span>
        <div class="space-y-2">
            <h1 class="text-6xl font-black text-rose-500">500</h1>
            <h2 class="text-2xl font-bold text-white">{{ __('حدث خطأ في النظام') }}</h2>
            <p class="text-sm text-gray-400">{{ __('نعتذر عن هذا الخطأ الفني، فريقنا يعمل على معالجته فوراً. يرجى المحاولة بعد قليل.') }}</p>
        </div>
        <a href="/" class="inline-block px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-indigo-600/30 text-sm">
            {{ __('العودة للرئيسية') }}
        </a>
    </div>
</body>
</html>
