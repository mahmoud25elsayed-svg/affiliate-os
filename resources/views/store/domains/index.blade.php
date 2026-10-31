<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة النطاقات والدومين المخصص - لوحة التاجر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    @include('store.partials.panel-bar')
    <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-semibold mb-1">
                    <a href="{{ route('store.kpis.index') }}" class="hover:text-slate-600 transition">لوحة التحكم</a>
                    <span>/</span>
                    <span class="text-slate-700">النطاقات المخصصة</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 flex items-center space-x-3 space-x-reverse">
                    <span>🌐</span>
                    <span>إدارة النطاقات والدومين المخصص (Custom Domains)</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    اربط متجرك بعنوان موقعك الخاص (مثل yourstore.com) مع شهادة أمان SSL مجانية وإعادة توجيه تلقائية.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('store.theme.edit') }}"
                   class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 shadow-sm transition">
                    🎨 تخصيص الثيم والمظهر
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2 shadow-sm">
            <span>✅</span>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        @if(session('error'))
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold flex items-center gap-2 shadow-sm">
            <span>⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
        @endif

        @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold space-y-1">
            @foreach($errors->all() as $err)
                <p>• {{ $err }}</p>
            @endforeach
        </div>
        @endif

        <!-- Feature Guard Notice (If not available in plan) -->
        @if(! $isFeatureAvailable)
        <div class="p-6 bg-gradient-to-r from-amber-500/10 via-amber-50 to-amber-100/30 border border-amber-300 rounded-3xl space-y-3">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🔒</span>
                <h3 class="text-sm font-bold text-amber-900">ميزة الدومين المخصص متاحة في باقة المحترف (Pro) والمؤسسات (Enterprise)</h3>
            </div>
            <p class="text-xs text-amber-700 leading-relaxed max-w-3xl">
                أنت حالياً على الباقة الأساسية. قم بترقية اشتراكك لربط دومينك الخاص، إزالة إشارة المنصة (White-label)، والاستفادة من واجهات الربط البرمجي (API).
            </p>
            <a href="#" class="inline-block px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl transition">
                ترقية الباقة الآن ←
            </a>
        </div>
        @endif

        <!-- Domains List Card -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-sm font-black text-slate-900">النطاقات المرتبطة بالمتجر</h2>
                    <p class="text-xs text-slate-500 mt-0.5">النطاق الأساسي هو العنوان الذي يظهر للزوار في محركات البحث وشريط المتصفح</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-semibold border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3">اسم النطاق (Hostname)</th>
                            <th class="px-6 py-3">النوع</th>
                            <th class="px-6 py-3">حالة التحقق</th>
                            <th class="px-6 py-3">شهادة SSL</th>
                            <th class="px-6 py-3">الرئيسي</th>
                            <th class="px-6 py-3 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($domains as $dom)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-bold text-slate-900">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">🌐</span>
                                    <a href="https://{{ $dom->domain }}" target="_blank" class="hover:text-indigo-600 hover:underline flex items-center gap-1">
                                        <span>{{ $dom->domain }}</span>
                                        <span class="text-[10px] text-slate-400">↗</span>
                                    </a>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($dom->is_custom)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">دومين مخصص</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">نطاق فرعي افتراضي</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($dom->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        مؤكد ونشط
                                    </span>
                                @elseif($dom->status === 'pending_verification')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        بانتظار التحقق من DNS
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700">فشل التحقق</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($dom->ssl_status === 'active')
                                    <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold text-[11px]">
                                        <span>🔒</span>
                                        <span>نشطة ومحمية</span>
                                    </span>
                                @elseif($dom->ssl_status === 'installing')
                                    <span class="inline-flex items-center gap-1 text-amber-600 font-semibold text-[11px]">
                                        <span>⏳</span>
                                        <span>جاري التثبيت...</span>
                                    </span>
                                @else
                                    <span class="text-slate-400 text-[11px]">قيد الانتظار</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($dom->is_primary)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-600 text-white">الأساسي</span>
                                @else
                                    <span class="text-slate-400 text-[11px]">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-left">
                                <div class="flex items-center justify-end gap-2">
                                    @if($dom->is_custom && $dom->status !== 'active')
                                        <form method="POST" action="{{ route('store.domains.verify', $dom->id) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-[11px] font-bold transition">
                                                تحقق الآن
                                            </button>
                                        </form>
                                    @endif

                                    @if($dom->status === 'active' && ! $dom->is_primary)
                                        <form method="POST" action="{{ route('store.domains.set-primary', $dom->id) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-[11px] font-bold transition">
                                                تعيين كأساسي
                                            </button>
                                        </form>
                                    @endif

                                    @if($dom->is_custom)
                                        <form method="POST" action="{{ route('store.domains.destroy', $dom->id) }}" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا النطاق المخصص؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg transition" title="حذف النطاق">
                                                🗑️
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Subdomain Redirection Toggle Box -->
            @if($domains->where('is_custom', true)->where('status', 'active')->isNotEmpty())
            <div class="p-6 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h4 class="text-xs font-bold text-slate-800">إعادة التوجيه التلقائي للنطاق الفرعي</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        عند التفعيل، سيتم تحويل أي زائر يفتح النطاق الفرعي ({{ $subdomain?->domain }}) تلقائياً إلى دومينك المخصص الأساسي.
                    </p>
                </div>
                <form method="POST" action="{{ route('store.domains.toggle-redirect') }}" class="flex items-center gap-3">
                    @csrf
                    <input type="hidden" name="redirect" value="{{ $redirectSubdomain ? '0' : '1' }}">
                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $redirectSubdomain ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-200 hover:bg-slate-300 text-slate-700' }}">
                        {{ $redirectSubdomain ? 'مفعل (إيقاف)' : 'معطل (تفعيل الآن)' }}
                    </button>
                </form>
            </div>
            @endif
        </div>

        <!-- Add Custom Domain Card -->
        @if($isFeatureAvailable)
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Form -->
            <div class="lg:col-span-5 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <h3 class="text-sm font-black text-slate-900">ربط نطاق مخصص جديد</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    أدخل النطاق الخاص بك (مثلاً: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-indigo-600">shop.mybrand.com</code> أو <code class="bg-slate-100 px-1.5 py-0.5 rounded text-indigo-600">mybrand.com</code>)
                </p>

                <form method="POST" action="{{ route('store.domains.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="domain" class="block text-xs font-bold text-slate-700 mb-1.5">اسم النطاق</label>
                        <input type="text"
                               name="domain"
                               id="domain"
                               value="{{ old('domain') }}"
                               placeholder="example.com أو shop.example.com"
                               dir="ltr"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-left font-mono">
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                        إضافة النطاق والحصول على سجلات DNS ←
                    </button>
                </form>
            </div>

            <!-- DNS Setup Guide -->
            <div class="lg:col-span-7 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                    <span>⚙️</span>
                    <span>خطوات إعداد DNS لدى مزود النطاق الخاص بك (Cloudflare, Namecheap, GoDaddy...)</span>
                </h3>

                <p class="text-xs text-slate-600 leading-relaxed">
                    توجه إلى لوحة تحكم مزود النطاق لديك وأضف السجلات التالية:
                </p>

                <!-- DNS Record 1: CNAME -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-800">1. سجل CNAME (للنطاقات الفرعية مثل shop أو store)</span>
                        <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded font-mono text-[10px] font-bold">CNAME</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-[11px] font-mono text-slate-700 bg-white p-2.5 rounded-xl border border-slate-100">
                        <div><strong>الاسم (Host):</strong> <code>@</code> أو <code>shop</code></div>
                        <div><strong>القيمة (Target):</strong> <code>{{ $cnameTarget }}</code></div>
                    </div>
                </div>

                <!-- DNS Record 2: TXT Verification -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-800">2. سجل التحقق من الملكية (Verification TXT)</span>
                        <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded font-mono text-[10px] font-bold">TXT</span>
                    </div>
                    <div class="grid grid-cols-1 gap-1 text-[11px] font-mono text-slate-700 bg-white p-2.5 rounded-xl border border-slate-100">
                        <div><strong>الاسم (Host):</strong> <code>_affiliate-os-challenge</code></div>
                        <div><strong>القيمة (Value):</strong> رمز التحقق المخصص لكل نطاق (يظهر في تفاصيل النطاق)</div>
                    </div>
                </div>

                <p class="text-[11px] text-slate-400">
                    💡 ملاحظة: قد يستغرق انتشار سجلات DNS الجديدة ما بين بضع دقائق إلى 24 ساعة كحد أقصى حسب مزود الخدمة.
                </p>
            </div>

        </div>
        @endif

    </div>
</body>
</html>
