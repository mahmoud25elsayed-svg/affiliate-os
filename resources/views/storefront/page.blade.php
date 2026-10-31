@extends('layouts.storefront')

@section('title', $title . ' - ' . ($general->platform_name ?? __('متجرنا')))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs text-gray-500 font-medium">
        <a href="{{ url('/') }}" class="hover:text-brand-600 transition-colors">{{ __('الرئيسية') }}</a>
        <span>/</span>
        <span class="text-gray-900 font-bold">{{ $title }}</span>
    </nav>

    <!-- Page Content Card -->
    <article class="bg-white rounded-3xl p-8 sm:p-12 border border-gray-100 shadow-sm space-y-6">
        <h1 class="text-3xl sm:text-4xl font-black text-gray-900 border-b border-gray-100 pb-6">
            {{ $title }}
        </h1>

        <div class="prose prose-base text-gray-700 leading-relaxed max-w-none">
            {!! nl2br(e($content)) !!}
        </div>
    </article>

</div>
@endsection
