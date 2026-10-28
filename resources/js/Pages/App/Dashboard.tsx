import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency, getOrderStatusLabel } from '../../utils/format';
import {
    Wallet,
    TrendingUp,
    Clock,
    Lock,
    ArrowUpRight,
    AlertOctagon,
    Package,
    ChevronLeft,
    CheckCircle,
    XCircle,
    ShoppingBag,
    Link2,
    PlusCircle,
    Bell,
    Award,
} from 'lucide-react';
import { AffiliateUser, WalletBalances, OrderListItem } from '../../types/affiliate';

interface Props {
    affiliate: AffiliateUser;
    balances: WalletBalances;
    stats: {
        total_orders: number;
        today_orders: number;
        delivered_orders: number;
        cancelled_orders: number;
        returned_orders: number;
        delivery_rate: number;
    };
    settings: {
        min_payout_minor: number;
        return_window_days: number;
        daily_limit: number;
        payout_days: string[];
    };
    recentOrders: OrderListItem[];
    stockAlerts: Array<{ id: number; name: string; stock: number; is_low: boolean }>;
    averageTurnaround: string;
}

export default function Dashboard({
    affiliate,
    balances,
    stats,
    settings,
    recentOrders,
    stockAlerts,
    averageTurnaround,
}: Props) {
    return (
        <AppLayout title="الرئيسية">
            <Head title="الرئيسية - تطبيق المسوق" />

            <div className="space-y-4">
                {/* Greeting & Quick Summary */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-white flex items-center gap-1.5">
                            مرحباً، {affiliate.name.split(' ')[0]} 👋
                        </h1>
                        <p className="text-xs text-slate-400">
                            متوسط تحويل الأرباح: <span className="text-emerald-400 font-semibold">{averageTurnaround}</span>
                        </p>
                    </div>

                    <Link
                        href="/partners/app/orders/create"
                        className="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold flex items-center gap-1 shadow-sm shadow-indigo-600/30"
                    >
                        <PlusCircle className="w-3.5 h-3.5" />
                        <span>طلب سريع</span>
                    </Link>
                </div>

                {/* Level & Quality Quick Banner */}
                <Link
                    href="/partners/app/levels"
                    className="p-3 rounded-2xl bg-gradient-to-r from-slate-900 to-indigo-950 border border-indigo-900/50 flex items-center justify-between text-xs hover:border-indigo-600/50 transition-all shadow-sm"
                >
                    <div className="flex items-center gap-2.5">
                        <div className="w-8 h-8 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                            <Award className="w-4 h-4" />
                        </div>
                        <div>
                            <span className="font-bold text-white block">مستواي والمكافآت</span>
                            <span className="text-[11px] text-slate-400">عرض درجة الجودة، بونص الرتب ودعوة الشركاء</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-1 text-indigo-400 font-semibold text-[11px]">
                        <span>تفاصيل</span>
                        <ChevronLeft className="w-3.5 h-3.5" />
                    </div>
                </Link>

                {/* Debt Alert if negative balance exists */}
                {balances.has_debt && (
                    <div className="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400">
                        <div className="flex items-start gap-2.5">
                            <AlertOctagon className="w-5 h-5 text-rose-500 shrink-0 mt-0.5" />
                            <div className="text-xs">
                                <span className="font-bold text-rose-300 block mb-0.5">تنبيه رصيد مدين!</span>
                                رصيدك الحالي سالب بمقدار <span className="font-mono font-bold">{formatCurrency(balances.debt)}</span> نتيجة استرجاع طلبات بعد استلام عمولتها. ستتم تسوية المديونية تلقائياً من أرباح طلباتك القادمة.
                            </div>
                        </div>
                    </div>
                )}

                {/* Wallet Overview Hero Card */}
                <div className="p-4 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950/40 to-slate-900 border border-indigo-500/20 shadow-xl relative overflow-hidden">
                    <div className="flex items-center justify-between text-xs text-slate-400 mb-1">
                        <span className="flex items-center gap-1.5 font-medium">
                            <Wallet className="w-4 h-4 text-indigo-400" />
                            الرصيد المتاح للسحب
                        </span>
                        <Link href="/partners/app/wallet" className="text-indigo-400 hover:underline flex items-center text-[11px]">
                            المحفظة <ChevronLeft className="w-3 h-3" />
                        </Link>
                    </div>

                    <div className="flex items-baseline justify-between my-2">
                        <span className="text-3xl font-extrabold text-white tracking-tight">
                            {formatCurrency(balances.available)}
                        </span>
                        {affiliate.can_withdraw ? (
                            <Link
                                href="/partners/app/wallet"
                                className="px-3 py-1 rounded-xl bg-emerald-500 text-slate-950 font-bold text-xs hover:bg-emerald-400 transition-colors shadow-sm shadow-emerald-500/20"
                            >
                                سحب الأرباح
                            </Link>
                        ) : (
                            <span className="text-[11px] px-2 py-0.5 rounded-md bg-slate-800 text-slate-400">
                                الحد الأدنى {formatCurrency(settings.min_payout_minor)}
                            </span>
                        )}
                    </div>

                    {/* Secondary Balances Grid */}
                    <div className="grid grid-cols-3 gap-2 pt-3 border-t border-slate-800/80 text-center">
                        <div className="p-2 rounded-xl bg-slate-800/40">
                            <span className="block text-[10px] text-slate-400 flex items-center justify-center gap-1">
                                <Clock className="w-3 h-3 text-amber-400" />
                                معلق
                            </span>
                            <span className="text-xs font-bold text-amber-300 font-mono mt-0.5 block">
                                {formatCurrency(balances.pending)}
                            </span>
                        </div>

                        <div className="p-2 rounded-xl bg-slate-800/40">
                            <span className="block text-[10px] text-slate-400 flex items-center justify-center gap-1">
                                <Lock className="w-3 h-3 text-indigo-400" />
                                قيد السحب
                            </span>
                            <span className="text-xs font-bold text-indigo-300 font-mono mt-0.5 block">
                                {formatCurrency(balances.locked)}
                            </span>
                        </div>

                        <div className="p-2 rounded-xl bg-slate-800/40">
                            <span className="block text-[10px] text-slate-400 flex items-center justify-center gap-1">
                                <ArrowUpRight className="w-3 h-3 text-emerald-400" />
                                تم سحبه
                            </span>
                            <span className="text-xs font-bold text-emerald-300 font-mono mt-0.5 block">
                                {formatCurrency(balances.withdrawn)}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Today's Stats Grid */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between text-xs font-bold text-slate-300">
                        <span>إحصائيات الأداء</span>
                        <Link href="/partners/app/analytics" className="text-indigo-400 hover:underline text-[11px] font-normal flex items-center">
                            تفاصيل التحليلات <ChevronLeft className="w-3 h-3" />
                        </Link>
                    </div>

                    <div className="grid grid-cols-2 gap-2.5">
                        <div className="p-3 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] text-slate-400 block">طلبات اليوم</span>
                                <span className="text-xl font-bold text-white mt-0.5 block">{stats.today_orders}</span>
                            </div>
                            <div className="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
                                <Package className="w-4 h-4" />
                            </div>
                        </div>

                        <div className="p-3 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] text-slate-400 block">نسبة التسليم</span>
                                <span className="text-xl font-bold text-emerald-400 mt-0.5 block">{stats.delivery_rate}%</span>
                            </div>
                            <div className="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                                <TrendingUp className="w-4 h-4" />
                            </div>
                        </div>

                        <div className="p-3 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] text-slate-400 block">طلبات تم تسليمها</span>
                                <span className="text-xl font-bold text-emerald-400 mt-0.5 block">{stats.delivered_orders}</span>
                            </div>
                            <div className="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                                <CheckCircle className="w-4 h-4" />
                            </div>
                        </div>

                        <div className="p-3 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] text-slate-400 block">ملغية / مرتجعة</span>
                                <span className="text-xl font-bold text-rose-400 mt-0.5 block">
                                    {stats.cancelled_orders + stats.returned_orders}
                                </span>
                            </div>
                            <div className="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
                                <XCircle className="w-4 h-4" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stock Alerts (if any) */}
                {stockAlerts.length > 0 && (
                    <div className="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20">
                        <div className="flex items-center gap-2 mb-2 text-amber-400 font-bold text-xs">
                            <Bell className="w-4 h-4 shrink-0" />
                            <span>تنبيهات المخزون لمنتجاتك المفضلة</span>
                        </div>
                        <div className="space-y-1.5">
                            {stockAlerts.map((item) => (
                                <div key={item.id} className="flex items-center justify-between text-xs text-slate-300">
                                    <span className="line-clamp-1">{item.name}</span>
                                    <span className="font-mono text-amber-400 font-semibold px-2 py-0.5 rounded bg-amber-500/20 text-[10px]">
                                        متبقي {item.stock} فقط
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Quick Actions Shortcuts */}
                <div className="grid grid-cols-3 gap-2">
                    <Link
                        href="/partners/app/catalog"
                        className="p-3 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 flex flex-col items-center justify-center text-center transition-all group"
                    >
                        <ShoppingBag className="w-5 h-5 text-indigo-400 mb-1 group-hover:scale-110 transition-transform" />
                        <span className="text-[11px] font-medium text-slate-300">تصفح الكتالوج</span>
                    </Link>

                    <Link
                        href="/partners/app/links"
                        className="p-3 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 flex flex-col items-center justify-center text-center transition-all group"
                    >
                        <Link2 className="w-5 h-5 text-violet-400 mb-1 group-hover:scale-110 transition-transform" />
                        <span className="text-[11px] font-medium text-slate-300">روابط التتبع</span>
                    </Link>

                    <Link
                        href="/partners/app/wallet"
                        className="p-3 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 flex flex-col items-center justify-center text-center transition-all group"
                    >
                        <Wallet className="w-5 h-5 text-emerald-400 mb-1 group-hover:scale-110 transition-transform" />
                        <span className="text-[11px] font-medium text-slate-300">كشف الحساب</span>
                    </Link>
                </div>

                {/* Recent Orders List */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between text-xs font-bold text-slate-300">
                        <span>أحدث الطلبات</span>
                        <Link href="/partners/app/orders" className="text-indigo-400 hover:underline text-[11px] font-normal flex items-center">
                            عرض الكل <ChevronLeft className="w-3 h-3" />
                        </Link>
                    </div>

                    {recentOrders.length === 0 ? (
                        <div className="p-6 rounded-2xl bg-slate-900/50 border border-slate-800/80 text-center text-xs text-slate-400">
                            لا توجد طلبات مسجلة بعد. ابدأ بإنشاء أول طلب لك الآن!
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {recentOrders.map((order) => {
                                const status = getOrderStatusLabel(order.status);
                                return (
                                    <Link
                                        key={order.id}
                                        href={`/partners/app/orders/${order.reference}`}
                                        className="block p-3 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition-all"
                                    >
                                        <div className="flex items-center justify-between mb-1.5">
                                            <span className="font-mono text-xs font-bold text-white">#{order.reference}</span>
                                            <span className={`text-[10px] px-2 py-0.5 rounded-full border ${status.color}`}>
                                                {status.label}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-xs text-slate-400">
                                            <span>{order.customer_city}</span>
                                            <div className="text-left">
                                                <span className="text-emerald-400 font-bold font-mono">
                                                    +{formatCurrency(order.profit_minor)}
                                                </span>
                                                <span className="text-[10px] text-slate-500 block">ربح</span>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
