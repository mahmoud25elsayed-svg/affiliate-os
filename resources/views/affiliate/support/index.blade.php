<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('تذاكر الدعم الفني - بوابة المسوقين') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Fira+Code:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
        code, pre { font-family: 'Fira Code', monospace; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                    <a href="{{ route('affiliate.dashboard') }}" class="hover:text-slate-600 transition">{{ __('بوابة المسوقين') }}</a>
                    <span>/</span>
                    <span class="text-slate-700">{{ __('تذاكر الدعم') }}</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>💬</span>
                    <span>{{ __('تذاكر الدعم والاستفسارات') }}</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    {{ __('تواصل مباشرة مع إدارة المتجر بخصوص العمولات، الطلبات، أو طلبات السحب.') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('affiliate.help.index') }}" class="px-4 py-2.5 rounded-xl text-xs font-bold border border-slate-200 bg-white hover:bg-slate-100 transition text-slate-700">
                    {{ __('📚 مركز المساعدة والأسئلة الشائعة') }}
                </a>
                <a href="{{ route('affiliate.support.create') }}" class="px-4 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-2 shadow-sm">
                    <span>+</span>
                    <span>{{ __('فتح تذكرة جديدة') }}</span>
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200/60 font-bold">
                        <tr>
                            <th class="py-3 px-4">{{ __('رقم التذكرة') }}</th>
                            <th class="py-3 px-4">{{ __('الموضوع') }}</th>
                            <th class="py-3 px-4">{{ __('القسم') }}</th>
                            <th class="py-3 px-4">{{ __('الأولوية') }}</th>
                            <th class="py-3 px-4">{{ __('الحالة') }}</th>
                            <th class="py-3 px-4">{{ __('آخر تحديث') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('إجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($tickets as $ticket)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900">{{ $ticket->ticket_number }}</td>
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <a href="{{ route('affiliate.support.show', $ticket->id) }}" class="hover:text-indigo-600 transition">
                                        {{ $ticket->subject }}
                                    </a>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-md text-[11px] bg-slate-100 text-slate-700 font-semibold">
                                        {{ $ticket->department }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 uppercase">
                                        {{ $ticket->priority }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    @switch($ticket->status)
                                        @case('resolved')
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">{{ __('تم الحل') }}</span>
                                            @break
                                        @case('closed')
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">{{ __('مغلقة') }}</span>
                                            @break
                                        @case('pending_customer')
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 animate-pulse">{{ __('رد من الدعم') }}</span>
                                            @break
                                        @default
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-200">{{ __('بانتظار الرد') }}</span>
                                    @endswitch
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-500">
                                    {{ $ticket->updated_at->diffForHumans() }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <a href="{{ route('affiliate.support.show', $ticket->id) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition">
                                        {{ __('عرض التذكرة') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">{{ __('لا توجد لديك تذاكر دعم حالية.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($tickets->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>

    </div>
</body>
</html>

