<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تذكرة {{ $ticket->ticket_number }} - بوابة المسوقين</title>
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
    <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                    <a href="{{ route('affiliate.support.index') }}" class="hover:text-slate-600 transition">تذاكر الدعم</a>
                    <span>/</span>
                    <span class="text-slate-700 font-mono">{{ $ticket->ticket_number }}</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900">{{ $ticket->subject }}</h1>
            </div>
            <a href="{{ route('affiliate.support.index') }}" class="px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 bg-white hover:bg-slate-100 transition text-slate-700">
                ← العودة للتذاكر
            </a>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Ticket Status Summary -->
        <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm flex flex-wrap items-center justify-between gap-4 text-xs font-medium">
            <div class="flex items-center gap-4">
                <div>
                    <span class="text-slate-400 block">رقم التذكرة:</span>
                    <span class="font-bold text-slate-900 font-mono">{{ $ticket->ticket_number }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block">الحالة:</span>
                    <span class="font-bold text-indigo-700">{{ $ticket->status }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block">القسم:</span>
                    <span class="font-bold text-slate-800">{{ $ticket->department }}</span>
                </div>
            </div>
            @if($ticket->order)
                <div class="text-left">
                    <span class="text-slate-400 block">طلب مرتبط:</span>
                    <span class="font-bold font-mono text-slate-900">{{ $ticket->order->reference }}</span>
                </div>
            @endif
        </div>

        <!-- Messages Thread -->
        <div class="space-y-4">
            @foreach($messages as $msg)
                @php
                    $isAffiliate = $msg->sender_type === 'affiliate';
                @endphp
                <div class="p-5 rounded-3xl border shadow-sm space-y-2 {{ $isAffiliate ? 'bg-indigo-50/40 border-indigo-100 mr-8' : 'bg-white border-slate-200/80 ml-8' }}">
                    <div class="flex items-center justify-between text-xs pb-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900">{{ $msg->getSenderName() }}</span>
                            @if(! $isAffiliate)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800">فريق دعم المتجر</span>
                            @endif
                        </div>
                        <span class="text-slate-400 font-mono text-[11px]">{{ $msg->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <div class="text-xs leading-relaxed text-slate-700 whitespace-pre-wrap">
                        {{ $msg->message }}
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Reply Box -->
        @if(! $ticket->isClosedOrResolved())
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-3">
                <h3 class="text-sm font-bold text-slate-900">إرسال رد جديد</h3>
                <form action="{{ route('affiliate.support.reply', $ticket->id) }}" method="POST" class="space-y-3">
                    @csrf
                    <textarea name="message" rows="3" required placeholder="اكتب ردك هنا..." class="w-full text-xs font-medium border border-slate-200 rounded-2xl p-4 focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm">
                            إرسال الرد
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="p-4 bg-slate-100 rounded-2xl text-center text-xs text-slate-500 font-bold">
                تم إغلاق أو حل هذه التذكرة. إذا كنت بحاجة لمزيد من المساعدة، يرجى فتح تذكرة جديدة.
            </div>
        @endif

    </div>
</body>
</html>

