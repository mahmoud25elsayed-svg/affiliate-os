<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة سحوبات المسوقين (Maker-Checker) - لوحة المتجر</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900">إدارة سحوبات المسوقين ودفتر الحوالات</h1>
                <p class="text-xs text-slate-500 mt-1">
                    نظام الحوكمة المزدوج (Maker-Checker): موظف يعتمد وآخر ينفذ للمبالغ فوق الحد المالي
                </p>
            </div>
            <div class="flex items-center space-x-2 space-x-reverse">
                <a href="{{ route('store.affiliates.settings.show') }}" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition">
                    إعدادات العمولات ⚙️
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center">
                <span class="ml-2 text-base">✓</span>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-bold flex items-center">
                <span class="ml-2 text-base">⚠️</span>
                {{ session('error') }}
            </div>
        @endif

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 block mb-1">في انتظار الاعتماد</span>
                <span class="text-2xl font-extrabold text-amber-600 font-mono">{{ $requestedCount }}</span>
                <span class="text-[11px] text-slate-400 block mt-1">طلبات جديدة</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 block mb-1">معتمد قيد التنفيذ</span>
                <span class="text-2xl font-extrabold text-indigo-600 font-mono">{{ $approvedCount }}</span>
                <span class="text-[11px] text-slate-400 block mt-1">جاهزة للتحويل</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 block mb-1">إجمالي الحوالات المنفذة</span>
                <span class="text-2xl font-extrabold text-emerald-600 font-mono">{{ number_format($totalPaidMinor / 100, 2) }} ج.م</span>
                <span class="text-[11px] text-slate-400 block mt-1">عدد: {{ $paidCount }} حوالة</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold text-slate-400 block mb-1">متوسط زمن التنفيذ</span>
                <span class="text-lg font-extrabold text-slate-800 block mt-1">{{ $averageTurnaround }}</span>
                <span class="text-[11px] text-slate-400 block mt-1">من الطلب حتى السداد</span>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="flex items-center space-x-2 space-x-reverse border-b border-slate-200 pb-2">
            <a href="{{ route('store.payouts.index') }}" class="px-4 py-2 rounded-xl text-xs font-bold {{ !$currentStatus ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                الكل
            </a>
            <a href="{{ route('store.payouts.index', ['status' => 'requested']) }}" class="px-4 py-2 rounded-xl text-xs font-bold {{ $currentStatus === 'requested' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                قيد الانتظار ({{ $requestedCount }})
            </a>
            <a href="{{ route('store.payouts.index', ['status' => 'approved']) }}" class="px-4 py-2 rounded-xl text-xs font-bold {{ $currentStatus === 'approved' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                معتمدة للتحويل ({{ $approvedCount }})
            </a>
            <a href="{{ route('store.payouts.index', ['status' => 'paid']) }}" class="px-4 py-2 rounded-xl text-xs font-bold {{ $currentStatus === 'paid' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                تم التحويل ({{ $paidCount }})
            </a>
            <a href="{{ route('store.payouts.index', ['status' => 'rejected']) }}" class="px-4 py-2 rounded-xl text-xs font-bold {{ $currentStatus === 'rejected' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                المرفوضة
            </a>
        </div>

        <!-- Payout Requests Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden" x-data="{ selectedIds: [], paymentMethod: 'wallet_phone' }">
            <div class="p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                <h2 class="font-bold text-slate-900 text-sm">قائمة طلبات السحب والحوالات</h2>

                <!-- Batch Actions Form -->
                <form action="{{ route('store.payouts.batches.create') }}" method="POST" class="flex items-center space-x-2 space-x-reverse" x-show="selectedIds.length > 0">
                    @csrf
                    <template x-for="id in selectedIds" :key="id">
                        <input type="hidden" name="request_ids[]" :value="id">
                    </template>
                    <select name="payment_method" x-model="paymentMethod" class="text-xs rounded-xl border border-slate-200 p-2 bg-slate-50">
                        <option value="wallet_phone">محافظ إلكترونية (فودافون كاش)</option>
                        <option value="instapay">إنستاباي (InstaPay)</option>
                        <option value="bank_account">تحويل بنكي</option>
                    </select>
                    <button type="submit" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm">
                        إنشاء دفعة مجمعة (<span x-text="selectedIds.length"></span>)
                    </button>
                </form>
            </div>

            @if($payoutRequests->isEmpty())
                <div class="py-16 text-center text-slate-400 text-xs">
                    لا توجد طلبات سحب تطابق الفلتر المحدد.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-right">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                                <th class="p-4 w-10 text-center">
                                    <input type="checkbox" @change="selectedIds = $event.target.checked ? {{ json_encode($payoutRequests->pluck('id')->all()) }} : []" class="rounded text-indigo-600">
                                </th>
                                <th class="p-4 font-semibold">رقم الطلب</th>
                                <th class="p-4 font-semibold">المسوق</th>
                                <th class="p-4 font-semibold">المبلغ</th>
                                <th class="p-4 font-semibold">طريقة الدفع وبيانات التحويل</th>
                                <th class="p-4 font-semibold">الحالة</th>
                                <th class="p-4 font-semibold">الحوكمة (Maker / Checker)</th>
                                <th class="p-4 font-semibold text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($payoutRequests as $req)
                                <tr class="hover:bg-slate-50 transition" x-data="{ showExecuteModal: false, showRejectModal: false }">
                                    <td class="p-4 text-center">
                                        <input type="checkbox" value="{{ $req->id }}" x-model="selectedIds" class="rounded text-indigo-600">
                                    </td>
                                    <td class="p-4 font-mono font-bold text-slate-900">
                                        #{{ $req->id }}
                                        <span class="block text-[10px] text-slate-400 font-normal">{{ $req->created_at->format('m/d H:i') }}</span>
                                    </td>
                                    <td class="p-4">
                                        <span class="font-bold text-slate-800 block">{{ $req->affiliate?->name }}</span>
                                        <span class="font-mono text-[10px] text-indigo-600">{{ $req->affiliate?->code }}</span>
                                    </td>
                                    <td class="p-4 font-mono font-extrabold text-sm text-slate-900">
                                        {{ number_format($req->amount_minor / 100, 2) }} ج.م
                                    </td>
                                    <td class="p-4">
                                        <span class="font-bold text-slate-700 block">
                                            {{ match($req->payment_method) {
                                                'wallet_phone' => 'محفظة إلكترونية',
                                                'instapay' => 'إنستاباي (InstaPay)',
                                                'bank_account' => 'حساب بنكي',
                                                default => $req->payment_method
                                            } }}
                                        </span>
                                        <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                            @if($req->payment_method === 'wallet_phone')
                                                {{ $req->payout_details['phone'] ?? $req->affiliate?->phone }} ({{ $req->payout_details['wallet_provider'] ?? 'vodafone_cash' }})
                                            @elseif($req->payment_method === 'instapay')
                                                {{ $req->payout_details['instapay_handle'] ?? '' }}
                                            @elseif($req->payment_method === 'bank_account')
                                                {{ $req->payout_details['bank_name'] ?? '' }} - {{ $req->payout_details['account_holder_name'] ?? '' }} - {{ $req->payout_details['iban'] ?? '' }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold
                                            @if($req->status === 'paid') bg-emerald-100 text-emerald-800
                                            @elseif($req->status === 'approved') bg-indigo-100 text-indigo-800
                                            @elseif($req->status === 'requested') bg-amber-100 text-amber-800
                                            @elseif($req->status === 'rejected') bg-rose-100 text-rose-800
                                            @else bg-slate-100 text-slate-600 @endif">
                                            {{ match($req->status) {
                                                'requested' => 'قيد الانتظار',
                                                'approved' => 'معتمد للتحويل',
                                                'paid' => 'تم التحويل ✓',
                                                'rejected' => 'مرفوض',
                                                'cancelled' => 'ملغي',
                                                default => $req->status
                                            } }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-[11px] text-slate-500">
                                        @if($req->approver)
                                            <span class="block">Maker: <strong class="text-slate-700">{{ $req->approver->name }}</strong></span>
                                        @endif
                                        @if($req->payer)
                                            <span class="block">Checker: <strong class="text-slate-700">{{ $req->payer->name }}</strong></span>
                                        @endif
                                        @if($req->reference_number)
                                            <span class="font-mono text-[10px] text-slate-400 block">مرجع: {{ $req->reference_number }}</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center space-x-2 space-x-reverse">
                                            @if($req->isRequested())
                                                <!-- Maker Approve Button -->
                                                <form action="{{ route('store.payouts.approve', $req->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-xs shadow-sm">
                                                        اعتماد (Maker)
                                                    </button>
                                                </form>
                                            @endif

                                            @if($req->canBeExecuted())
                                                <!-- Checker Execute Button Trigger -->
                                                <button @click="showExecuteModal = true" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs shadow-sm">
                                                    تنفيذ التحويل (Checker)
                                                </button>
                                            @endif

                                            @if($req->canBeRejected())
                                                <!-- Reject Button Trigger -->
                                                <button @click="showRejectModal = true" class="px-2 py-1.5 text-rose-600 hover:bg-rose-50 rounded-lg font-bold text-xs">
                                                    رفض
                                                </button>
                                            @endif
                                        </div>

                                        <!-- Execute Modal -->
                                        <div x-show="showExecuteModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 text-right" style="display: none;">
                                            <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-xl" @click.away="showExecuteModal = false">
                                                <h3 class="font-bold text-slate-900 text-base">تنفيذ تحويل طلب السحب #{{ $req->id }}</h3>
                                                <p class="text-xs text-slate-500">
                                                    المبلغ: <strong class="text-slate-900 font-mono">{{ number_format($req->amount_minor / 100, 2) }} ج.م</strong> للمسوق <strong class="text-slate-900">{{ $req->affiliate?->name }}</strong>
                                                </p>

                                                <form action="{{ route('store.payouts.execute', $req->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                                                    @csrf
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-700 mb-1">الرقم المرجعي للحوالة / التحويل *</label>
                                                        <input type="text" name="reference_number" required placeholder="مثال: VF-983274291 أو رقم إشعار البنك" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50 font-mono">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-700 mb-1">إيصال التحويل (اختياري)</label>
                                                        <input type="file" name="receipt" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                                    </div>
                                                    <div class="flex items-center justify-end space-x-2 space-x-reverse pt-2">
                                                        <button type="button" @click="showExecuteModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">إلغاء</button>
                                                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl">تأكيد وقيد التحويل</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div x-show="showRejectModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 text-right" style="display: none;">
                                            <div class="bg-white rounded-3xl max-w-md w-full p-6 space-y-4 shadow-xl" @click.away="showRejectModal = false">
                                                <h3 class="font-bold text-slate-900 text-base">رفض طلب السحب #{{ $req->id }}</h3>
                                                <p class="text-xs text-slate-500">
                                                    سيتم إلغاء الحجز وإعادة المبلغ (<strong class="text-slate-900 font-mono">{{ number_format($req->amount_minor / 100, 2) }} ج.م</strong>) إلى الرصيد المتاح للمسوق فوراً.
                                                </p>

                                                <form action="{{ route('store.payouts.reject', $req->id) }}" method="POST" class="space-y-4">
                                                    @csrf
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-700 mb-1">سبب الرفض *</label>
                                                        <input type="text" name="reason" required placeholder="مثال: رقم المحفظة غير صحيح، يرجى تحديث البيانات" class="w-full text-xs rounded-xl border border-slate-200 p-3 bg-slate-50">
                                                    </div>
                                                    <div class="flex items-center justify-end space-x-2 space-x-reverse pt-2">
                                                        <button type="button" @click="showRejectModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">إلغاء</button>
                                                        <button type="submit" class="px-4 py-2 bg-rose-600 text-white text-xs font-bold rounded-xl">تأكيد الرفض وعكس الرصيد</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-slate-100">
                    {{ $payoutRequests->links() }}
                </div>
            @endif
        </div>

        <!-- Payout Batches Section -->
        @if($batches->isNotEmpty())
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-bold text-slate-900 flex items-center space-x-2 space-x-reverse">
                    <span>📁</span>
                    <span>دفعات الحوالات المجمعة (Payout Batches)</span>
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-right">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                                <th class="p-3 font-semibold">مرجع الدفعة</th>
                                <th class="p-3 font-semibold">طريقة الدفع</th>
                                <th class="p-3 font-semibold">العدد</th>
                                <th class="p-3 font-semibold">إجمالي المبلغ</th>
                                <th class="p-3 font-semibold">الحالة</th>
                                <th class="p-3 font-semibold text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($batches as $b)
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 font-mono font-bold text-slate-900">{{ $b->batch_reference }}</td>
                                    <td class="p-3">{{ $b->payment_method }}</td>
                                    <td class="p-3 font-mono">{{ $b->total_requests }}</td>
                                    <td class="p-3 font-mono font-bold text-emerald-600">{{ number_format($b->total_amount_minor / 100, 2) }} ج.م</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $b->isCompleted() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $b->status }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">
                                        <div class="flex items-center justify-center space-x-2 space-x-reverse">
                                            <a href="{{ route('store.payouts.batches.export', $b->id) }}" class="px-3 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs font-bold">
                                                تحميل CSV 📥
                                            </a>
                                            @if(!$b->isCompleted())
                                                <form action="{{ route('store.payouts.batches.process', $b->id) }}" method="POST" class="inline-flex">
                                                    @csrf
                                                    <input type="hidden" name="batch_reference_number" value="BULK-{{ date('YmdHis') }}">
                                                    <button type="submit" class="px-3 py-1 bg-emerald-600 text-white hover:bg-emerald-700 rounded-lg text-xs font-bold" onclick="return confirm('هل تريد تأكيد تنفيذ الدفعة بالكامل وقيدها بالدفتر المالي؟');">
                                                        تأكيد التنفيذ الجماعي ✓
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
            </div>
        @endif

    </div>
</body>
</html>
