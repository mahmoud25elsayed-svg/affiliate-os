<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تذكرة {{ $ticket->ticket_number }} - لوحة التاجر</title>
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

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                    <a href="{{ route('store.support.index') }}" class="hover:text-slate-600 transition">مركز التذاكر</a>
                    <span>/</span>
                    <span class="text-slate-700 font-mono">{{ $ticket->ticket_number }}</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>{{ $ticket->subject }}</span>
                </h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('store.support.index') }}" class="px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 bg-white hover:bg-slate-100 transition text-slate-700">
                    ← العودة للقائمة
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Main Conversation Column (2/3) -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Messages Thread -->
                <div class="space-y-4">
                    @foreach($ticket->messages as $msg)
                        @php
                            $isInternal = $msg->is_internal_note;
                            $isStaff = in_array($msg->sender_type, ['staff', 'platform_admin'], true);
                        @endphp
                        <div class="p-5 rounded-3xl border shadow-sm space-y-2 {{ $isInternal ? 'bg-amber-50/70 border-amber-200' : ($isStaff ? 'bg-indigo-50/40 border-indigo-100 mr-4' : 'bg-white border-slate-200/80 ml-4') }}">
                            <div class="flex items-center justify-between text-xs pb-2 border-b {{ $isInternal ? 'border-amber-200' : 'border-slate-100' }}">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-900">{{ $msg->getSenderName() }}</span>
                                    @if($isInternal)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-200 text-amber-900">ملاحظة داخلية (Staff Only)</span>
                                    @elseif($isStaff)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800">الدعم الفني</span>
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

                <!-- Reply / Add Note Box -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900">إضافة رد أو ملاحظة داخلية</h3>

                    <!-- Canned Responses Quick Select -->
                    @if($cannedResponses->isNotEmpty())
                        <div class="flex items-center gap-2 text-xs">
                            <span class="text-slate-500 font-bold">الردود الجاهزة:</span>
                            <select onchange="insertCanned(this)" class="text-xs border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-700 font-medium">
                                <option value="">-- اختر رداً جاهزاً للإدراج السريع --</option>
                                @foreach($cannedResponses as $cr)
                                    <option value="{{ $cr->render(['ticket_number' => $ticket->ticket_number, 'customer_name' => $ticket->customer?->name ?? 'العميل العزيز']) }}">
                                        {{ $cr->title }} ({{ $cr->shortcut }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <form action="{{ route('store.support.reply', $ticket->id) }}" method="POST" class="space-y-4">
                        @csrf
                        <textarea id="reply-textarea" name="message" rows="4" required placeholder="اكتب ردك للعميل أو المسوق، أو سجّل ملاحظة للفريق..." class="w-full text-xs font-medium border border-slate-200 rounded-2xl p-4 focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>

                        <div class="flex items-center justify-between pt-2">
                            <label class="flex items-center gap-2 text-xs font-bold text-amber-800 cursor-pointer bg-amber-50 px-3 py-1.5 rounded-xl border border-amber-200">
                                <input type="checkbox" name="is_internal_note" value="1" class="rounded text-amber-600 focus:ring-amber-500">
                                <span>🔒 تسجيل كملاحظة داخلية (لن يراها الطرف الآخر)</span>
                            </label>

                            <button type="submit" class="px-6 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm">
                                إرسال
                            </button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- Sidebar Info & Actions Column (1/3) -->
            <div class="space-y-6">

                <!-- Ticket Meta Card -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 border-b pb-2">تفاصيل التذكرة</h3>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-400">رقم التذكرة:</span>
                            <span class="font-mono font-bold text-slate-900">{{ $ticket->ticket_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">المستوى:</span>
                            <span class="font-bold text-slate-800">{{ $ticket->level === 'platform' ? 'مع إدارة المنصة' : 'داخل المتجر' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">الأولوية:</span>
                            <span class="font-bold text-indigo-700 uppercase">{{ $ticket->priority }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">القسم:</span>
                            <span class="font-bold text-slate-800">{{ $ticket->department }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">تاريخ الفتح:</span>
                            <span class="font-mono text-slate-700">{{ $ticket->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">موعد استحقاق SLA:</span>
                            <span class="font-mono {{ $ticket->isSlaBreached() ? 'text-rose-600 font-bold' : 'text-slate-700' }}">
                                {{ $ticket->sla_due_at ? $ticket->sla_due_at->format('Y-m-d H:i') : '-' }}
                            </span>
                        </div>
                        @if($ticket->first_response_at)
                            <div class="flex justify-between">
                                <span class="text-slate-400">زمن أول رد:</span>
                                <span class="font-bold text-slate-900">{{ $ticket->getFirstResponseTimeMinutes() }} دقيقة</span>
                            </div>
                        @endif
                    </div>

                    <!-- Linked Entities -->
                    @if($ticket->order)
                        <div class="pt-3 border-t text-xs space-y-1">
                            <span class="text-slate-400 block font-semibold">الطلب المرتبط:</span>
                            <span class="font-bold text-slate-900 font-mono">{{ $ticket->order->reference }}</span>
                        </div>
                    @endif

                    @if($ticket->payoutRequest)
                        <div class="pt-3 border-t text-xs space-y-1">
                            <span class="text-slate-400 block font-semibold">طلب السحب المرتبط:</span>
                            <span class="font-bold text-slate-900 font-mono">سحب #{{ $ticket->payoutRequest->id }}</span>
                        </div>
                    @endif
                </div>

                <!-- Actions: Change Status & Assign -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 border-b pb-2">إجراءات التذكرة</h3>

                    <!-- Status Form -->
                    <form action="{{ route('store.support.status', $ticket->id) }}" method="POST" class="space-y-2">
                        @csrf
                        <label class="block text-xs font-bold text-slate-700">تغيير الحالة</label>
                        <div class="flex gap-2">
                            <select name="status" class="w-full text-xs font-semibold border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 outline-none">
                                <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>مفتوحة (Open)</option>
                                <option value="pending_customer" {{ $ticket->status === 'pending_customer' ? 'selected' : '' }}>بانتظار العميل</option>
                                <option value="pending_agent" {{ $ticket->status === 'pending_agent' ? 'selected' : '' }}>بانتظار الرد</option>
                                <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>تم الحل (Resolved)</option>
                                <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>مغلقة (Closed)</option>
                            </select>
                            <button type="submit" class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition">
                                حفظ
                            </button>
                        </div>
                    </form>

                    <!-- Assign Form -->
                    <form action="{{ route('store.support.assign', $ticket->id) }}" method="POST" class="space-y-2 pt-2 border-t">
                        @csrf
                        <label class="block text-xs font-bold text-slate-700">تعيين لموظف</label>
                        <div class="flex gap-2">
                            <select name="assigned_to_user_id" class="w-full text-xs font-semibold border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 outline-none">
                                <option value="">-- غير معين --</option>
                                @foreach($staffUsers as $staff)
                                    <option value="{{ $staff->id }}" {{ $ticket->assigned_to_user_id === $staff->id ? 'selected' : '' }}>
                                        {{ $staff->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition">
                                تعيين
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        </div>

    </div>

    <script>
        function insertCanned(select) {
            const val = select.value;
            if (val) {
                document.getElementById('reply-textarea').value = val;
            }
        }
    </script>
</body>
</html>

