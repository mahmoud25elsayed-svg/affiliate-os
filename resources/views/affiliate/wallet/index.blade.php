@extends('layouts.affiliate')

@section('title', 'محفظتي وكشف الحساب المالي')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">محفظة الأرباح وكشف الحساب</h1>
            <p class="text-sm text-gray-500 mt-1">دفتر مالي مزدوج دقيق يسجل كل حركة وأرباح كل طلب يتم تسليمه أو تسويته.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('affiliate.wallet.export') }}" class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-semibold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                تصدير كشف الحساب (CSV)
            </a>
            <a href="{{ route('affiliate.payout-settings.show') }}" class="inline-flex items-center gap-2 {{ $canWithdraw ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }} px-5 py-2.5 rounded-lg text-sm font-semibold transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                طلب سحب أرباح
            </a>
        </div>
    </div>

    <!-- Debt Alert Banner -->
    @if($hasDebt)
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl flex items-start gap-3">
            <svg class="w-6 h-6 text-rose-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <h4 class="font-bold text-sm">تنبيه: مديونية مستحقة عليك بمبلغ {{ \App\Support\Money::show($debtMinor) }}</h4>
                <p class="text-xs text-rose-700 mt-1">يوجد رصيد سالب نتيجة مرتجع متأخر بعد تسوية وسحب أرباح سابقة. يتم حجب عمليات السحب مؤقتاً وسيتم خصم هذا المبلغ تلقائياً من أرباحك للطلبات القادمة.</p>
            </div>
        </div>
    @endif

    <!-- Balances Cards (4 balances) -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <!-- Pending Balance -->
        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between text-gray-500 text-xs font-semibold">
                <span>الرصيد المعلق (قيد الانتظار)</span>
                <span class="p-1.5 bg-amber-50 text-amber-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-black text-amber-600 mt-2 font-mono">{{ number_format($pendingMinor / 100, 2) }} <span class="text-xs font-normal text-gray-500">{{ \App\Support\Money::symbol() }}</span></p>
            <p class="text-xs text-gray-400 mt-1">طلبات مسلّمة في فترة نافذة المرتجع ({{ $settings->return_window_days }} يوماً)</p>
        </div>

        <!-- Available Balance -->
        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between text-gray-500 text-xs font-semibold">
                <span>الرصيد المتاح للسحب</span>
                <span class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-black {{ $availableMinor < 0 ? 'text-rose-600' : 'text-emerald-600' }} mt-2 font-mono">
                {{ number_format($availableMinor / 100, 2) }} <span class="text-xs font-normal text-gray-500">{{ \App\Support\Money::symbol() }}</span>
            </p>
            <p class="text-xs text-gray-400 mt-1">الحد الأدنى للسحب: {{ \App\Support\Money::show($settings->min_payout_minor) }}</p>
        </div>

        <!-- Locked Balance -->
        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between text-gray-500 text-xs font-semibold">
                <span>الرصيد المحجوز للسحب</span>
                <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-black text-blue-600 mt-2 font-mono">{{ number_format($lockedMinor / 100, 2) }} <span class="text-xs font-normal text-gray-500">{{ \App\Support\Money::symbol() }}</span></p>
            <p class="text-xs text-gray-400 mt-1">طلبات سحب جارية قيد المراجعة والتحويل</p>
        </div>

        <!-- Withdrawn Balance -->
        <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between text-gray-500 text-xs font-semibold">
                <span>إجمالي المسحوبات</span>
                <span class="p-1.5 bg-purple-50 text-purple-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-black text-purple-600 mt-2 font-mono">{{ number_format($withdrawnMinor / 100, 2) }} <span class="text-xs font-normal text-gray-500">{{ \App\Support\Money::symbol() }}</span></p>
            <p class="text-xs text-gray-400 mt-1">المبالغ المستلمة بنجاح منذ التسجيل</p>
        </div>
    </div>

    <!-- Statement Table -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-900">سجل الحركات المالية (كشف الحساب)</h3>
            <span class="text-xs text-gray-400">سجل غير قابل للتعديل أو الحذف (Append-only Ledger)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm text-gray-600">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4">رقم الحركة</th>
                        <th class="px-6 py-4">التاريخ والوقت</th>
                        <th class="px-6 py-4">نوع الحساب</th>
                        <th class="px-6 py-4">البيان والطلب</th>
                        <th class="px-6 py-4 text-left">المبلغ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($lines as $line)
                        @php
                            $isCredit = $line->amount_minor > 0;
                            $kind = $line->account?->kind;
                        @endphp
                        <tr class="hover:bg-gray-50/70 transition">
                            <td class="px-6 py-4 font-mono text-xs text-gray-400">#{{ $line->id }}</td>
                            <td class="px-6 py-4 text-xs font-mono text-gray-500">
                                {{ $line->created_at ? $line->created_at->format('Y-m-d H:i') : '-' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($kind === 'pending')
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-700">معلق</span>
                                @elseif($kind === 'available')
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700">متاح</span>
                                @elseif($kind === 'locked')
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 text-blue-700">محجوز</span>
                                @elseif($kind === 'withdrawn')
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-purple-50 text-purple-700">مسحوب</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-700">{{ $kind }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $line->journalEntry?->description ?? 'حركة دفترية' }}</div>
                                @if($line->journalEntry?->ref_id)
                                    <div class="text-xs text-indigo-600 mt-0.5 font-mono">مرجع: {{ class_basename($line->journalEntry->ref_type) }} #{{ $line->journalEntry->ref_id }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-left font-mono font-bold {{ $isCredit ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $isCredit ? '+' : '' }}{{ \App\Support\Money::show($line->amount_minor) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-12 text-gray-400">
                                لا توجد حركات مالية مسجلة في محفظتك حتى الآن. ستظهر الحركات تلقائياً عند تسليم الطلبات أو تسويتها.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lines->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $lines->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
