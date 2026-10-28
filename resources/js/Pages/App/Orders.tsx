import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency, getOrderStatusLabel } from '../../utils/format';
import { ClipboardList, PlusCircle, ChevronLeft, Package, User, MapPin } from 'lucide-react';
import { OrderListItem } from '../../types/affiliate';

interface Props {
    orders: {
        data: Array<OrderListItem & { affiliate_profit_minor?: number }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    currentStatus?: string | null;
}

export default function Orders({ orders, currentStatus }: Props) {
    const tabs = [
        { label: 'الكل', value: '' },
        { label: 'قيد المعالجة', value: 'in_progress' },
        { label: 'تم التسليم', value: 'delivered' },
        { label: 'ملغي', value: 'cancelled' },
        { label: 'مرتجع', value: 'returned' },
    ];

    const handleTabClick = (val: string) => {
        router.get('/partners/app/orders', {
            status: val || undefined,
        }, { preserveState: true });
    };

    return (
        <AppLayout title="قائمة الطلبات">
            <Head title="طلباتي - تطبيق المسوق" />

            <div className="space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-base font-bold text-white flex items-center gap-1.5">
                            <ClipboardList className="w-5 h-5 text-indigo-400" />
                            <span>متابعة الطلبات ({orders.total})</span>
                        </h1>
                        <p className="text-xs text-slate-400">
                            تابع حالة الشحن والتسليم وأسباب الإلغاء لحظة بلحظة.
                        </p>
                    </div>

                    <Link
                        href="/partners/app/orders/create"
                        className="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold flex items-center gap-1 shadow-sm shadow-indigo-600/30"
                    >
                        <PlusCircle className="w-3.5 h-3.5" />
                        <span>طلب جديد</span>
                    </Link>
                </div>

                {/* Filter Tabs */}
                <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
                    {tabs.map((tab) => {
                        const active = (currentStatus || '') === tab.value;
                        return (
                            <button
                                key={tab.value}
                                type="button"
                                onClick={() => handleTabClick(tab.value)}
                                className={`px-3.5 py-1.5 rounded-full text-xs font-semibold shrink-0 transition-colors ${
                                    active
                                        ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/30'
                                        : 'bg-slate-900 text-slate-400 border border-slate-800 hover:text-white'
                                }`}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {/* Orders List */}
                {orders.data.length === 0 ? (
                    <div className="p-8 rounded-3xl bg-slate-900/50 border border-slate-800 text-center space-y-3">
                        <Package className="w-8 h-8 text-slate-500 mx-auto" />
                        <p className="text-sm font-bold text-slate-300">لا توجد طلبات في هذا القسم</p>
                        <p className="text-xs text-slate-500">ابدأ بإنشاء طلب جديد أو شارك روابط المنتجات لزيادة مبيعاتك.</p>
                        <Link
                            href="/partners/app/orders/create"
                            className="inline-flex items-center gap-1 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold"
                        >
                            <PlusCircle className="w-3.5 h-3.5" />
                            <span>إنشاء طلب سريع</span>
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-2.5">
                        {orders.data.map((order) => {
                            const status = getOrderStatusLabel(order.status);
                            const profit = order.affiliate_profit_minor ?? order.profit_minor ?? 0;

                            return (
                                <Link
                                    key={order.id}
                                    href={`/partners/app/orders/${order.reference}`}
                                    className="block p-3.5 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition-all space-y-2"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-xs font-bold text-white">#{order.reference}</span>
                                            <span className="text-[10px] text-slate-500">{order.created_at}</span>
                                        </div>
                                        <span className={`text-[10px] px-2 py-0.5 rounded-full border ${status.color}`}>
                                            {status.label}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between text-xs text-slate-300">
                                        <div className="flex items-center gap-1.5">
                                            <User className="w-3.5 h-3.5 text-slate-400" />
                                            <span className="font-medium">{order.customer_name}</span>
                                        </div>
                                        <div className="flex items-center gap-1 text-slate-400">
                                            <MapPin className="w-3 h-3 text-slate-500" />
                                            <span>{order.customer_city}</span>
                                        </div>
                                    </div>

                                    <div className="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs">
                                        <div>
                                            <span className="text-slate-500 text-[11px]">مبلغ التحصيل: </span>
                                            <span className="font-mono font-bold text-slate-200">
                                                {formatCurrency(order.total_minor)}
                                            </span>
                                        </div>

                                        <div className="flex items-center gap-1">
                                            <span className="text-slate-500 text-[11px]">عمولتك: </span>
                                            <span className="font-mono font-black text-emerald-400">
                                                +{formatCurrency(profit)}
                                            </span>
                                            <ChevronLeft className="w-4 h-4 text-slate-500" />
                                        </div>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
