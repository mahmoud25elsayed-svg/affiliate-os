import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency, getPayoutStatusLabel, currencySymbol } from '../../utils/format';
import {
    Wallet as WalletIcon,
    ArrowUpRight,
    ArrowDownLeft,
    Clock,
    Lock,
    AlertOctagon,
    CheckCircle2,
    XCircle,
    FileSpreadsheet,
    ShieldAlert,
    CreditCard,
    ChevronDown,
} from 'lucide-react';
import { WalletBalances, LedgerMovement, PayoutRequestItem } from '../../types/affiliate';

interface Props {
    balances: WalletBalances;
    eligibility: {
        can_withdraw: boolean;
        is_frozen: boolean;
        frozen_until?: string | null;
        has_kyc: boolean;
        min_payout_minor: number;
    };
    averageTurnaround: string;
    movements: {
        data: LedgerMovement[];
        current_page: number;
        last_page: number;
        total: number;
    };
    payoutRequests: PayoutRequestItem[];
    payoutMethod?: string | null;
    payoutDetails?: Record<string, string | null> | null;
}

export default function Wallet({
    balances,
    eligibility,
    averageTurnaround,
    movements,
    payoutRequests,
    payoutMethod,
    payoutDetails,
}: Props) {
    const [payoutModalOpen, setPayoutModalOpen] = useState(false);
    const [activeTab, setActiveTab] = useState<'movements' | 'payouts'>('movements');

    const minPayoutPounds = Math.ceil(eligibility.min_payout_minor / 100);
    const availablePounds = Math.floor(balances.available / 100);

    const { data, setData, post, processing, errors, reset } = useForm({
        amount_minor: Math.max(eligibility.min_payout_minor, balances.available),
    });

    const handlePayoutSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/partners/app/wallet/payout', {
            onSuccess: () => {
                setPayoutModalOpen(false);
                reset();
            },
        });
    };

    return (
        <AppLayout title="المحفظة وكشف الحساب">
            <Head title="المحفظة - تطبيق المسوق" />

            <div className="space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-base font-bold text-white flex items-center gap-1.5">
                            <WalletIcon className="w-5 h-5 text-indigo-400" />
                            <span>المحفظة وكشف الحساب</span>
                        </h1>
                        <p className="text-xs text-slate-400">
                            الدفتر المالي ذو القيد المزدوج، دقة كاملة وتتبع لكل قرش.
                        </p>
                    </div>
                </div>

                {/* Debt Alert if negative balance */}
                {balances.has_debt && (
                    <div className="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 flex items-start gap-2.5">
                        <AlertOctagon className="w-5 h-5 text-rose-500 shrink-0 mt-0.5" />
                        <div className="text-xs">
                            <span className="font-bold text-rose-300 block mb-0.5">تنبيه رصيد مدين!</span>
                            رصيدك الحالي سالب بمقدار <span className="font-mono font-bold">{formatCurrency(balances.debt)}</span> نتيجة استرجاع طلبات بعد استلام عمولتها. ستتم تسوية المديونية تلقائياً من أرباح طلباتك القادمة.
                        </div>
                    </div>
                )}

                {/* Balances Card */}
                <div className="p-4 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950/40 to-slate-900 border border-indigo-500/20 shadow-xl space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <span className="text-xs text-slate-400">الرصيد المتاح للسحب</span>
                            <div className="text-3xl font-extrabold text-white font-mono mt-0.5">
                                {formatCurrency(balances.available)}
                            </div>
                        </div>

                        {balances.available >= eligibility.min_payout_minor && eligibility.can_withdraw ? (
                            <button
                                type="button"
                                onClick={() => setPayoutModalOpen(true)}
                                className="px-4 py-2 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs shadow-lg shadow-emerald-500/20 transition-all active:scale-95"
                            >
                                طلب سحب
                            </button>
                        ) : (
                            <div className="text-left">
                                <span className="text-[10px] px-2 py-1 rounded-lg bg-slate-800 text-slate-400 block">
                                    الحد الأدنى: {minPayoutPounds} {currencySymbol()}
                                </span>
                            </div>
                        )}
                    </div>

                    {/* Secondary Balances */}
                    <div className="grid grid-cols-3 gap-2 pt-2 border-t border-slate-800/80 text-center">
                        <div className="p-2 rounded-xl bg-slate-800/40">
                            <span className="text-[10px] text-slate-400 flex items-center justify-center gap-1">
                                <Clock className="w-3 h-3 text-amber-400" />
                                قيد الانتظار
                            </span>
                            <span className="text-xs font-bold text-amber-300 font-mono mt-0.5 block">
                                {formatCurrency(balances.pending)}
                            </span>
                        </div>

                        <div className="p-2 rounded-xl bg-slate-800/40">
                            <span className="text-[10px] text-slate-400 flex items-center justify-center gap-1">
                                <Lock className="w-3 h-3 text-indigo-400" />
                                محجوز للسحب
                            </span>
                            <span className="text-xs font-bold text-indigo-300 font-mono mt-0.5 block">
                                {formatCurrency(balances.locked)}
                            </span>
                        </div>

                        <div className="p-2 rounded-xl bg-slate-800/40">
                            <span className="text-[10px] text-slate-400 flex items-center justify-center gap-1">
                                <ArrowUpRight className="w-3 h-3 text-emerald-400" />
                                مسحوب كلياً
                            </span>
                            <span className="text-xs font-bold text-emerald-300 font-mono mt-0.5 block">
                                {formatCurrency(balances.withdrawn)}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Eligibility Notices */}
                {!eligibility.has_kyc && (
                    <div className="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <ShieldAlert className="w-4 h-4 shrink-0" />
                            <span>يلزم إتمام توثيق الهوية (KYC) قبل تقديم أول طلب سحب.</span>
                        </div>
                        <Link href="/partners/app/profile" className="font-bold underline text-[11px] shrink-0">
                            توثيق الآن
                        </Link>
                    </div>
                )}

                {eligibility.is_frozen && (
                    <div className="p-3 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 text-xs flex items-center gap-2">
                        <Lock className="w-4 h-4 shrink-0" />
                        <span>السحب مجمد مؤقتاً لدواعي الأمان حتى {eligibility.frozen_until}.</span>
                    </div>
                )}

                {/* Tabs: Movements vs Payouts */}
                <div className="flex rounded-2xl bg-slate-900 p-1 border border-slate-800">
                    <button
                        type="button"
                        onClick={() => setActiveTab('movements')}
                        className={`flex-1 py-2 rounded-xl text-xs font-bold transition-all ${
                            activeTab === 'movements'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-slate-400 hover:text-white'
                        }`}
                    >
                        حركات الدفتر المالي
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('payouts')}
                        className={`flex-1 py-2 rounded-xl text-xs font-bold transition-all ${
                            activeTab === 'payouts'
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'text-slate-400 hover:text-white'
                        }`}
                    >
                        طلبات السحب ({payoutRequests.length})
                    </button>
                </div>

                {/* Movements Tab Content */}
                {activeTab === 'movements' && (
                    <div className="space-y-2">
                        {movements.data.length === 0 ? (
                            <div className="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 text-center text-xs text-slate-400">
                                لا توجد حركات مالية مسجلة بعد.
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {movements.data.map((m) => {
                                    const isCredit = m.amount_minor > 0;
                                    return (
                                        <div
                                            key={m.id}
                                            className="p-3 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between text-xs"
                                        >
                                            <div className="flex items-center gap-2.5">
                                                <div
                                                    className={`w-8 h-8 rounded-xl flex items-center justify-center shrink-0 ${
                                                        isCredit ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'
                                                    }`}
                                                >
                                                    {isCredit ? <ArrowDownLeft className="w-4 h-4" /> : <ArrowUpRight className="w-4 h-4" />}
                                                </div>
                                                <div>
                                                    <span className="font-semibold text-white block">{m.description}</span>
                                                    <span className="text-[10px] text-slate-500">{m.account_name} • {m.created_at}</span>
                                                </div>
                                            </div>

                                            <span
                                                className={`font-mono font-bold text-sm ${
                                                    isCredit ? 'text-emerald-400' : 'text-rose-400'
                                                }`}
                                            >
                                                {isCredit ? '+' : ''}{formatCurrency(m.amount_minor)}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}

                {/* Payouts Tab Content */}
                {activeTab === 'payouts' && (
                    <div className="space-y-2">
                        {payoutRequests.length === 0 ? (
                            <div className="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 text-center text-xs text-slate-400">
                                لم تقم بتقديم أي طلبات سحب حتى الآن.
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {payoutRequests.map((req) => {
                                    const status = getPayoutStatusLabel(req.status);
                                    return (
                                        <div
                                            key={req.id}
                                            className="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2 text-xs"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-mono text-sm font-bold text-white">
                                                    {formatCurrency(req.amount_minor)}
                                                </span>
                                                <span className={`text-[10px] px-2 py-0.5 rounded-full border ${status.color}`}>
                                                    {status.label}
                                                </span>
                                            </div>

                                            <div className="flex items-center justify-between text-[11px] text-slate-400">
                                                <span>طريقة السحب: {req.payment_method}</span>
                                                <span>{req.created_at}</span>
                                            </div>

                                            {req.reference_number && (
                                                <div className="p-2 rounded-xl bg-slate-800 text-[10px] font-mono text-emerald-400">
                                                    رقم إيصال التحويل: {req.reference_number}
                                                </div>
                                            )}

                                            {req.rejection_reason && (
                                                <div className="p-2 rounded-xl bg-rose-500/10 text-[10px] text-rose-400">
                                                    سبب الرفض: {req.rejection_reason}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}

                {/* Payout Modal */}
                {payoutModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                        <div className="w-full max-w-sm rounded-3xl bg-slate-900 border border-slate-800 p-5 space-y-4 shadow-2xl animate-in fade-in slide-in-from-bottom duration-200">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-bold text-white flex items-center gap-1.5">
                                    <CreditCard className="w-4 h-4 text-emerald-400" />
                                    <span>طلب سحب أرباح</span>
                                </h3>
                                <button
                                    type="button"
                                    onClick={() => setPayoutModalOpen(false)}
                                    className="text-slate-400 hover:text-white text-xs"
                                >
                                    إلغاء
                                </button>
                            </div>

                            <form onSubmit={handlePayoutSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">
                                        المبلغ المطلوب سحبه ({currencySymbol()})
                                    </label>
                                    <input
                                        type="number"
                                        min={minPayoutPounds}
                                        max={availablePounds}
                                        value={Math.round(data.amount_minor / 100)}
                                        onChange={(e) => setData('amount_minor', Number(e.target.value) * 100)}
                                        className="w-full h-11 rounded-2xl bg-slate-800 border border-slate-700 px-3 text-sm font-bold text-white font-mono focus:outline-none focus:border-indigo-500"
                                    />
                                    <div className="flex justify-between text-[10px] text-slate-500 font-mono mt-1">
                                        <span>الحد الأدنى: {minPayoutPounds} {currencySymbol()}</span>
                                        <span>المتاح: {availablePounds} {currencySymbol()}</span>
                                    </div>
                                    {errors.amount_minor && (
                                        <p className="text-[10px] text-rose-400 mt-1">{errors.amount_minor}</p>
                                    )}
                                </div>

                                <div className="p-3 rounded-2xl bg-slate-800/60 text-xs text-slate-300 space-y-1">
                                    <div className="flex justify-between">
                                        <span className="text-slate-400">طريقة التحويل:</span>
                                        <span className="font-semibold text-white">{payoutMethod || 'لم تُحدد'}</span>
                                    </div>
                                    <p className="text-[10px] text-slate-500">
                                        سيتم حجز المبلغ من رصيدك المتاح بالدفتر المالي لحين مراجعة وتنفيذ التحويل.
                                    </p>
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full h-11 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs shadow-lg shadow-emerald-500/20 disabled:opacity-50 transition-all"
                                >
                                    {processing ? 'جاري تقديم الطلب...' : 'تأكيد طلب السحب'}
                                </button>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
