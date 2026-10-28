@extends('layouts.affiliate')

@section('title', 'سحب الأرباح')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">سحب الأرباح والمستحقات</h1>
            <p class="text-xs text-slate-500 mt-1">
                إدارة طلبات السحب، مواعيد التحويل المجدولة، وسجل الحوالات السابقة
            </p>
        </div>
        <div class="flex items-center space-x-2 space-x-reverse">
            <a href="{{ route('affiliate.wallet.index') }}" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition">
                كشف حساب المحفظة
            </a>
            <a href="{{ route('affiliate.payout-settings.show') }}" class="px-4 py-2 bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 rounded-xl text-xs font-bold transition">
                بيانات استلام الأرباح ⚙️
            </a>
        </div>
    </div>

    <!-- Warnings / Eligibility Alerts -->
    @if($hasDebt)
        <div class="bg-rose-50 border border-rose-300 rounded-2xl p-5 flex items-start space-x-3 space-x-reverse shadow-sm">
            <span class="text-2xl ml-3">⚠️</span>
            <div>
                <h3 class="font-bold text-rose-900 text-sm">عمليات السحب محجوبة: يوجد رصيد مدين مستحق</h3>
                <p class="text-xs text-rose-700 mt-1">
                    المبلغ المدين المستحق عليك: <strong class="font-mono text-sm">{{ number_format($debtMinor / 100, 2) }} ج.م</strong> بسبب مرتجعات متأخرة بعد سحب الأرباح.
                    سيتم فتح السحب تلقائياً بمجرد تغطية الدين من أرباح طلباتك القادمة.
                </p>
            </div>
        </div>
    @elseif($affiliate->isPayoutFrozen())
        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 flex items-center space-x-3 space-x-reverse">
            <span class="text-2xl ml-3">🔒</span>
            <div>
                <h3 class="font-bold text-rose-900 text-sm">عمليات السحب مجمدة مؤقتاً لأسباب أمنية</h3>
                <p class="text-xs text-rose-700 mt-0.5">
                    تم تجميد السحب حتى <span class="font-bold font-mono">{{ $affiliate->payouts_frozen_until?->format('d/m/Y H:i') }}</span> بسبب تعديل بيانات استلام الأرباح مؤخراً.
                </p>
            </div>
        </div>
    @elseif(!$affiliate->hasApprovedKyc())
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-center justify-between">
            <div class="flex items-center space-x-3 space-x-reverse">
                <span class="text-2xl ml-3">🪪</span>
                <div>
                    <h3 class="font-bold text-amber-900 text-sm">توثيق الهوية (KYC) مطلوب لإتمام عمليات السحب</h3>
                    <p class="text-xs text-amber-700 mt-0.5">
                        يرجى رفع صورة بطاقة الرقم القومي واعتمادها لتفعيل سحب الأرباح.
                    </p>
                </div>
            </div>
            <a href="{{ route('affiliate.kyc.show') }}" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-sm">
                توثيق الهوية الآن
            </a>
        </div>
    @endif

    <!-- Balances Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Available -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-500 block mb-1">الرصيد المتاح للسحب الآن</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold {{ $availableBalance < 0 ? 'text-rose-600' : 'text-emerald-600' }} font-mono">
                    {{ number_format($availableBalance / 100, 2) }}
                </span>
                <span class="text-xs font-bold text-slate-400">ج.م</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2 block">
                الحد الأدنى: {{ number_format($settings->min_payout_minor / 100, 2) }} ج.م
            </span>
        </div>

        <!-- Locked -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-500 block mb-1">أرباح محجوزة قيد التحويل</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-indigo-600 font-mono">
                    {{ number_format($lockedBalance / 100, 2) }}
                </span>
                <span class="text-xs font-bold text-slate-400">ج.م</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2 block">
                في انتظار اعتماد وتنفيذ التحويل
            </span>
        </div>

        <!-- Withdrawn -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold text-slate-500 block mb-1">إجمالي ما تم تحويله</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-slate-800 font-mono">
                    {{ number_format($withdrawnBalance / 100, 2) }}
                </span>
                <span class="text-xs font-bold text-slate-400">ج.م</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-2 block">
                متوسط زمن الدفع: {{ $averageTurnaround }}
            </span>
        </div>
    </div>

    <!-- Payout Request Form Box -->
    @if($canWithdraw && $availableBalance >= $settings->min_payout_minor)
        <div class="bg-white rounded-3xl border border-indigo-100 shadow-md p-6">
            <h2 class="text-sm font-bold text-slate-900 mb-1 flex items-center space-x-2 space-x-reverse">
                <span>⚡</span>
                <span>طلب سحب أرباح جديد</span>
            </h2>
            <p class="text-xs text-slate-500 mb-5">
                سيتم حجز المبلغ من رصيدك المتاح وتحويله إلى وسيلة الدفع المعتمدة لحسابك.
            </p>

            <form action="{{ route('affiliate.payouts.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            المبلغ المطلوب سحبه (ج.م) *
                        </label>
                        <div class="relative">
                            <input type="number" step="0.01" name="amount"
                                   min="{{ $settings->min_payout_minor / 100 }}"
                                   max="{{ $availableBalance / 100 }}"
                                   value="{{ old('amount', number_format($availableBalance / 100, 2, '.', '')) }}"
                                   class="w-full text-base font-bold font-mono rounded-xl border border-slate-200 p-3 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
                                   required>
                            <span class="absolute left-3 top-3.5 text-xs text-slate-400 font-bold">ج.م</span>
                        </div>
                        <span class="text-[11px] text-slate-400 mt-1 block">
                            المتاح لك سحبه حالياً: {{ number_format($availableBalance / 100, 2) }} ج.م
                        </span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            وسيلة التحويل المعتمدة
                        </label>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">
                                    {{ match($affiliate->payout_method) {
                                        'wallet_phone' => 'محفظة إلكترونية (فودافون كاش / اتصالات / أورانج)',
                                        'instapay' => 'تحويل إنستاباي (InstaPay)',
                                        'bank_account' => 'حساب بنكي',
                                        default => 'لم تحدد وسيلة استلام'
                                    } }}
                                </span>
                                <span class="text-[11px] text-slate-500 font-mono">
                                    @if($affiliate->payout_method === 'wallet_phone')
                                        {{ $affiliate->payout_details['phone'] ?? $affiliate->phone }}
                                    @elseif($affiliate->payout_method === 'instapay')
                                        {{ $affiliate->payout_details['instapay_handle'] ?? '' }}
                                    @elseif($affiliate->payout_method === 'bank_account')
                                        {{ $affiliate->payout_details['bank_name'] ?? '' }} - {{ $affiliate->payout_details['iban'] ?? '' }}
                                    @endif
                                </span>
                            </div>
                            <a href="{{ route('affiliate.payout-settings.show') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold">
                                تغيير الوسيلة
                            </a>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end">
                    <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center space-x-2 space-x-reverse">
                        <span>إرسال طلب السحب</span>
                        <span>←</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Payout Requests History Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-900 text-sm">سجل طلبات السحب والحوالات</h2>
            <span class="text-xs text-slate-400">إجمالي الطلبات: {{ $payoutRequests->total() }}</span>
        </div>

        @if($payoutRequests->isEmpty())
            <div class="py-16 text-center text-slate-400 text-xs">
                لم تقم بتقديم أي طلبات سحب حتى الآن.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-right">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                            <th class="p-4 font-semibold">رقم الطلب</th>
                            <th class="p-4 font-semibold">تاريخ الطلب</th>
                            <th class="p-4 font-semibold">المبلغ المطلوب</th>
                            <th class="p-4 font-semibold">وسيلة الاستلام</th>
                            <th class="p-4 font-semibold">الحالة</th>
                            <th class="p-4 font-semibold">المرجع / الإيصال</th>
                            <th class="p-4 font-semibold">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($payoutRequests as $req)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 font-mono font-bold text-slate-900">
                                    #{{ $req->id }}
                                </td>
                                <td class="p-4 font-mono text-slate-600">
                                    {{ $req->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="p-4 font-mono font-extrabold text-sm text-slate-900">
                                    {{ number_format($req->amount_minor / 100, 2) }} ج.م
                                </td>
                                <td class="p-4">
                                    <span class="font-medium text-slate-700">
                                        {{ match($req->payment_method) {
                                            'wallet_phone' => 'محفظة إلكترونية',
                                            'instapay' => 'إنستاباي',
                                            'bank_account' => 'حساب بنكي',
                                            default => $req->payment_method
                                        } }}
                                    </span>
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
                                            'paid' => 'تم التحويل بنجاح ✓',
                                            'rejected' => 'مرفوض',
                                            'cancelled' => 'ملغي من قبلك',
                                            default => $req->status
                                        } }}
                                    </span>
                                </td>
                                <td class="p-4 font-mono text-[11px]">
                                    @if($req->reference_number)
                                        <span class="text-slate-700 font-bold block">{{ $req->reference_number }}</span>
                                    @endif
                                    @if($req->receipt_path)
                                        <a href="{{ Storage::url($req->receipt_path) }}" target="_blank" class="text-indigo-600 hover:underline">
                                            عرض الإيصال 📎
                                        </a>
                                    @endif
                                    @if($req->rejection_reason)
                                        <span class="text-rose-600 font-sans block text-[11px]" title="{{ $req->rejection_reason }}">
                                            سبب الرفض: {{ $req->rejection_reason }}
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    @if($req->isRequested())
                                        <form action="{{ route('affiliate.payouts.cancel', $req->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من إلغاء طلب السحب؟');">
                                            @csrf
                                            <button type="submit" class="text-xs text-rose-600 hover:text-rose-800 font-bold hover:underline">
                                                إلغاء الطلب
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-slate-300">-</span>
                                    @endif
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

</div>
@endsection
