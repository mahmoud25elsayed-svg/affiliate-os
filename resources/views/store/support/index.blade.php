<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('مركز الدعم الفني والتذاكر - لوحة التاجر') }}</title>
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
    @include('store.partials.panel-bar')
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                    <a href="{{ route('store.kpis.index') }}" class="hover:text-slate-600 transition">{{ __('لوحة التحكم') }}</a>
                    <span>/</span>
                    <span class="text-slate-700">{{ __('الدعم والتذاكر') }}</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>🎫</span>
                    <span>{{ __('مركز الدعم والتذاكر وإدارة النزاعات (Support Desk)') }}</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    {{ __('متابعة استفسارات وشكاوى العملاء والمسوقين، مراقبة مؤشرات اتفاقية مستوى الخدمة (SLA)، وفتح تذاكر مع إدارة المنصة.') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('platform-ticket-modal').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-2 shadow-sm">
                    <span>⚡</span>
                    <span>{{ __('تذكرة جديدة مع إدارة المنصة') }}</span>
                </button>
            </div>
        </div>

        <!-- Alert messages -->
        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
                <span class="text-xs text-slate-500 font-semibold block">{{ __('التذاكر المفتوحة') }}</span>
                <span class="text-3xl font-black text-slate-900">{{ $metrics['open_count'] }}</span>
                <span class="text-[11px] text-slate-400 block">{{ __('تتطلب استجابة أو متابعة') }}</span>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1 {{ $metrics['breached_sla_count'] > 0 ? 'bg-rose-50/30 border-rose-200' : '' }}">
                <span class="text-xs text-slate-500 font-semibold block">{{ __('تجاوزت الـ SLA ⚠️') }}</span>
                <span class="text-3xl font-black {{ $metrics['breached_sla_count'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                    {{ $metrics['breached_sla_count'] }}
                </span>
                <span class="text-[11px] text-slate-400 block">{{ __('تأخر الرد عن الموعد المحدد') }}</span>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
                <span class="text-xs text-slate-500 font-semibold block">{{ __('متوسط زمن أول رد') }}</span>
                <div class="flex items-baseline gap-1">
                    <span class="text-3xl font-black text-slate-900">{{ $metrics['avg_first_response_minutes'] }}</span>
                    <span class="text-xs text-slate-400 font-bold">{{ __('دقيقة') }}</span>
                </div>
                <span class="text-[11px] text-slate-400 block">First Response Time (FRT)</span>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
                <span class="text-xs text-slate-500 font-semibold block">{{ __('تم حلها اليوم') }}</span>
                <div class="flex items-baseline gap-1">
                    <span class="text-3xl font-black text-emerald-600">{{ $metrics['resolved_today_count'] }}</span>
                    <span class="text-xs text-slate-400 font-bold">{{ __('تذكرة') }}</span>
                </div>
                <span class="text-[11px] text-slate-400 block">{{ __('معدل الإغلاق اليومي') }}</span>
            </div>
        </div>

        <!-- Level Tabs: Store vs Platform -->
        <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
            <a href="{{ route('store.support.index', ['level' => 'store']) }}" class="px-4 py-2 text-xs font-bold rounded-xl transition {{ $currentLevel === 'store' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-200/60' }}">
                {{ __('👥 تذاكر المتجر (العملاء والمسوقين)') }}
            </a>
            <a href="{{ route('store.support.index', ['level' => 'platform']) }}" class="px-4 py-2 text-xs font-bold rounded-xl transition {{ $currentLevel === 'platform' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-200/60' }}">
                {{ __('🏢 تذاكر المتجر مع إدارة المنصة') }}
            </a>
        </div>

        <!-- Tickets Table Container -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Filter Bar -->
            <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-1.5 text-xs font-semibold">
                    <a href="{{ route('store.support.index', ['level' => $currentLevel, 'status' => 'all']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'all' ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-slate-200/60' }}">{{ __('الكل') }}</a>
                    <a href="{{ route('store.support.index', ['level' => $currentLevel]) }}" class="px-3 py-1.5 rounded-lg {{ empty($currentStatus) ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-slate-200/60' }}">{{ __('المفتوحة') }}</a>
                    <a href="{{ route('store.support.index', ['level' => $currentLevel, 'status' => 'pending_agent']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'pending_agent' ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-slate-200/60' }}">{{ __('بانتظار الرد') }}</a>
                    <a href="{{ route('store.support.index', ['level' => $currentLevel, 'status' => 'breached']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'breached' ? 'bg-rose-600 text-white' : 'text-rose-600 hover:bg-rose-50' }}">{{ __('تجاوزت SLA') }}</a>
                    <a href="{{ route('store.support.index', ['level' => $currentLevel, 'status' => 'resolved']) }}" class="px-3 py-1.5 rounded-lg {{ $currentStatus === 'resolved' ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-slate-200/60' }}">{{ __('تم الحل') }}</a>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200/60 font-bold">
                        <tr>
                            <th class="py-3 px-4">{{ __('رقم التذكرة') }}</th>
                            <th class="py-3 px-4">{{ __('الموضوع') }}</th>
                            <th class="py-3 px-4">{{ __('المرسل / الطرف') }}</th>
                            <th class="py-3 px-4">{{ __('القسم') }}</th>
                            <th class="py-3 px-4">{{ __('الأولوية') }}</th>
                            <th class="py-3 px-4">{{ __('الحالة') }}</th>
                            <th class="py-3 px-4">{{ __('استحقاق SLA') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('إجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($tickets as $ticket)
                            @php
                                $isBreached = $ticket->isSlaBreached();
                            @endphp
                            <tr class="hover:bg-slate-50/50 transition {{ $isBreached ? 'bg-rose-50/20' : '' }}">
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900">{{ $ticket->ticket_number }}</td>
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <a href="{{ route('store.support.show', $ticket->id) }}" class="hover:text-indigo-600 transition">
                                        {{ $ticket->subject }}
                                    </a>
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($ticket->affiliate)
                                        <span class="inline-flex items-center gap-1 text-indigo-700 font-bold">
                                            <span>{{ __('مسوق:') }}</span>
                                            <span>{{ $ticket->affiliate->name }}</span>
                                        </span>
                                    @elseif($ticket->customer)
                                        <span class="text-slate-700">{{ __('عميل:') }} {{ $ticket->customer->name }}</span>
                                    @else
                                        <span class="text-slate-500">{{ $ticket->createdBy?->name ?? __('التاجر') }}</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-md text-[11px] bg-slate-100 text-slate-700 font-semibold">
                                        {{ $ticket->department }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    @switch($ticket->priority)
                                        @case('urgent')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">{{ __('حرجة') }}</span>
                                            @break
                                        @case('high')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">{{ __('عالية') }}</span>
                                            @break
                                        @case('low')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">{{ __('منخفضة') }}</span>
                                            @break
                                        @default
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">{{ __('متوسطة') }}</span>
                                    @endswitch
                                </td>
                                <td class="py-3.5 px-4">
                                    @switch($ticket->status)
                                        @case('resolved')
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">{{ __('تم الحل') }}</span>
                                            @break
                                        @case('closed')
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">{{ __('مغلقة') }}</span>
                                            @break
                                        @case('pending_agent')
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 animate-pulse">{{ __('بانتظار الرد') }}</span>
                                            @break
                                        @case('pending_customer')
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">{{ __('بانتظار العميل') }}</span>
                                            @break
                                        @default
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-200">{{ __('مفتوحة') }}</span>
                                    @endswitch
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[11px]">
                                    @if($isBreached)
                                        <span class="text-rose-600 font-bold flex items-center gap-1">
                                            <span>⚠️</span>
                                            <span>{{ __('متأخرة!') }}</span>
                                        </span>
                                    @elseif($ticket->sla_due_at)
                                        <span class="text-slate-500">{{ $ticket->sla_due_at->diffForHumans() }}</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <a href="{{ route('store.support.show', $ticket->id) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition">
                                        {{ __('عرض والرد') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400">{{ __('لا توجد تذاكر دعم تطابق معايير البحث الحالية.') }}</td>
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

    <!-- Modal for Opening Platform Ticket -->
    <div id="platform-ticket-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="text-base font-bold text-slate-900">{{ __('فتح تذكرة دعم مع إدارة المنصة') }}</h3>
                <button onclick="document.getElementById('platform-ticket-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
            </div>
            <form action="{{ route('store.support.platform-ticket') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('موضوع التذكرة *') }}</label>
                    <input type="text" name="subject" required placeholder="{{ __('مثال: استفسار عن ربط الدومين المخصص أو الفواتير') }}" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('القسم') }}</label>
                        <select name="department" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                            <option value="technical">{{ __('دعم فني وبرمجي') }}</option>
                            <option value="finance">{{ __('فواتير واشتراكات') }}</option>
                            <option value="general" selected>{{ __('استفسار عام') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('الأولوية') }}</label>
                        <select name="priority" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                            <option value="low">{{ __('منخفضة (SLA 48h)') }}</option>
                            <option value="medium" selected>{{ __('متوسطة (SLA 24h)') }}</option>
                            <option value="high">{{ __('عالية (SLA 8h)') }}</option>
                            <option value="urgent">{{ __('حرجة (SLA 2h)') }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">{{ __('تفاصيل الرسالة أو المشكلة *') }}</label>
                    <textarea name="message" rows="4" required placeholder="{{ __('اشرح مشكلتك أو استفسارك بالتفصيل...') }}" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('platform-ticket-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-600 hover:bg-slate-100">{{ __('إلغاء') }}</button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition">{{ __('إرسال التذكرة') }}</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

