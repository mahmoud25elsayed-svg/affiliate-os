<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فتح تذكرة دعم جديدة - بوابة المسوقين</title>
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
    <div class="max-w-3xl mx-auto px-4 py-8 space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                    <a href="{{ route('affiliate.support.index') }}" class="hover:text-slate-600 transition">تذاكر الدعم</a>
                    <span>/</span>
                    <span class="text-slate-700">تذكرة جديدة</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900">فتح تذكرة دعم فني جديدة</h1>
            </div>
            <a href="{{ route('affiliate.support.index') }}" class="px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 bg-white hover:bg-slate-100 transition text-slate-700">
                ← العودة
            </a>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6">
            <form action="{{ route('affiliate.support.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">موضوع التذكرة *</label>
                    <input type="text" name="subject" required placeholder="مثال: استفسار عن عمولة طلب معين أو سحب معلق" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">القسم المختص</label>
                        <select name="department" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                            <option value="orders">الطلبات والشحن</option>
                            <option value="finance">المالية والسحوبات</option>
                            <option value="technical">مشاكل تقنية وروابط التتبع</option>
                            <option value="general" selected>استفسار عام</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الأولوية</label>
                        <select name="priority" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                            <option value="low">منخفضة</option>
                            <option value="medium" selected>عادية</option>
                            <option value="high">عالية</option>
                            <option value="urgent">عاجلة جداً</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">ربط بطلب محدد (اختياري)</label>
                        <select name="order_id" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                            <option value="">-- بدون ربط بطلب --</option>
                            @foreach($recentOrders as $order)
                                <option value="{{ $order->id }}">{{ $order->reference }} ({{ $order->status }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">ربط بسحب أرباح (اختياري)</label>
                        <select name="payout_request_id" class="w-full text-xs font-medium border border-slate-200 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                            <option value="">-- بدون ربط بسحب --</option>
                            @foreach($recentPayouts as $payout)
                                <option value="{{ $payout->id }}">سحب رقم #{{ $payout->id }} ({{ $payout->status }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">تفاصيل الرسالة أو الاستفسار *</label>
                    <textarea name="message" rows="5" required placeholder="اشرح المشكلة بالتفصيل لمساعدتنا في حلها سريعاً..." class="w-full text-xs font-medium border border-slate-200 rounded-xl p-3.5 focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
                </div>

                <div class="flex justify-end pt-3">
                    <button type="submit" class="px-6 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm">
                        إرسال التذكرة الآن
                    </button>
                </div>
            </form>
        </div>

    </div>
</body>
</html>

