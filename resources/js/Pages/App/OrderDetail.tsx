import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency, getOrderStatusLabel } from '../../utils/format';
import {
    ChevronLeft,
    Package,
    User,
    Phone,
    MapPin,
    Calendar,
    Clock,
    AlertCircle,
    CheckCircle2,
    ShieldCheck,
    XCircle,
} from 'lucide-react';

interface TimelineEvent {
    id: number;
    event_type: string;
    description: string;
    reason_code?: string | null;
    created_at: string;
}

interface Props {
    order: {
        id: number;
        reference: string;
        status: string;
        customer_name: string;
        customer_phone: string;
        customer_governorate: string;
        customer_city: string;
        customer_address: string;
        product_name: string;
        quantity: number;
        selling_price_minor: number;
        wholesale_price_minor: number;
        shipping_cost_minor: number;
        customer_total_minor: number;
        affiliate_profit_minor: number;
        created_at: string;
        can_cancel: boolean;
    };
    timeline: TimelineEvent[];
}

export default function OrderDetail({ order, timeline }: Props) {
    const status = getOrderStatusLabel(order.status);

    const handleCancel = () => {
        if (!confirm('هل أنت متأكد من إلغاء هذا الطلب؟')) return;
        router.post(`/partners/app/orders/${order.reference}/cancel`);
    };

    return (
        <AppLayout title={`تفاصيل الطلب #${order.reference}`}>
            <Head title={`الطلب #${order.reference} - تطبيق المسوق`} />

            <div className="space-y-4">
                {/* Back Link */}
                <Link
                    href="/partners/app/orders"
                    className="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-white transition-colors"
                >
                    <ChevronLeft className="w-4 h-4 rotate-180" />
                    <span>العودة لقائمة الطلبات</span>
                </Link>

                {/* Order Header Card */}
                <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <span className="text-[10px] text-slate-500 font-mono">رقم المرجع</span>
                            <h1 className="text-base font-extrabold text-white font-mono">#{order.reference}</h1>
                        </div>
                        <span className={`text-xs px-3 py-1 rounded-full border font-bold ${status.color}`}>
                            {status.label}
                        </span>
                    </div>

                    <div className="flex items-center gap-2 text-xs text-slate-400 pt-2 border-t border-slate-800">
                        <Calendar className="w-3.5 h-3.5 text-slate-500" />
                        <span>تاريخ الطلب: {order.created_at}</span>
                    </div>
                </div>

                {/* Affiliate Profit Card */}
                <div className="p-4 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950/40 to-slate-900 border border-indigo-500/30 flex items-center justify-between shadow-xl">
                    <div>
                        <span className="text-[11px] text-emerald-400 block font-bold">ربحك المستحق من هذا الطلب:</span>
                        <span className="text-2xl font-black text-emerald-300 font-mono">
                            +{formatCurrency(order.affiliate_profit_minor)}
                        </span>
                        <span className="text-[10px] text-slate-400 block mt-0.5">
                            يُضاف للرصيد المتاح فور تأكيد التسليم للعميل
                        </span>
                    </div>
                    <ShieldCheck className="w-8 h-8 text-emerald-400 shrink-0" />
                </div>

                {/* Customer & Shipping Details */}
                <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-2.5">
                    <h2 className="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                        <User className="w-4 h-4 text-indigo-400" />
                        <span>بيانات العميل والشحن</span>
                    </h2>

                    <div className="space-y-1.5 text-xs text-slate-300 pt-1">
                        <div className="flex items-center justify-between">
                            <span className="text-slate-400">الاسم:</span>
                            <span className="font-semibold text-white">{order.customer_name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-slate-400">الهاتف:</span>
                            <span className="font-mono text-slate-200" dir="ltr">{order.customer_phone}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-slate-400">المحافظة / المدينة:</span>
                            <span>{order.customer_governorate} {order.customer_city ? `- ${order.customer_city}` : ''}</span>
                        </div>
                        <div className="flex items-start justify-between gap-4">
                            <span className="text-slate-400 shrink-0">العنوان:</span>
                            <span className="text-left text-slate-200">{order.customer_address}</span>
                        </div>
                    </div>
                </div>

                {/* Items & Financial Breakdown */}
                <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-2.5">
                    <h2 className="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                        <Package className="w-4 h-4 text-indigo-400" />
                        <span>تفاصيل المنتج والمبالغ</span>
                    </h2>

                    <div className="space-y-1.5 text-xs text-slate-400 pt-1">
                        <div className="flex justify-between text-white font-medium">
                            <span>{order.product_name} × {order.quantity}</span>
                            <span className="font-mono">{formatCurrency(order.selling_price_minor * order.quantity)}</span>
                        </div>
                        <div className="flex justify-between text-[11px]">
                            <span>سعر الجملة للقطعة:</span>
                            <span className="font-mono">{formatCurrency(order.wholesale_price_minor)}</span>
                        </div>
                        <div className="flex justify-between text-[11px]">
                            <span>تكلفة الشحن:</span>
                            <span className="font-mono">{formatCurrency(order.shipping_cost_minor)}</span>
                        </div>
                        <div className="flex justify-between font-bold text-white pt-2 border-t border-slate-800 text-sm">
                            <span>إجمالي تحصيل الفاتورة:</span>
                            <span className="font-mono text-indigo-300">{formatCurrency(order.customer_total_minor)}</span>
                        </div>
                    </div>
                </div>

                {/* Timeline Events (ORD-09) */}
                <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-3">
                    <h2 className="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                        <Clock className="w-4 h-4 text-indigo-400" />
                        <span>الجدول الزمني للطلب (Timeline)</span>
                    </h2>

                    {timeline.length === 0 ? (
                        <p className="text-xs text-slate-500">تم تسجيل الطلب وفي انتظار معالجة شركة الشحن.</p>
                    ) : (
                        <div className="space-y-3 relative pr-3 border-r-2 border-slate-800 mr-2">
                            {timeline.map((ev) => (
                                <div key={ev.id} className="relative">
                                    <div className="absolute -right-[17px] top-1 w-2.5 h-2.5 rounded-full bg-indigo-500 ring-4 ring-slate-900" />
                                    <div className="text-xs">
                                        <div className="flex items-center justify-between text-[10px] text-slate-500 mb-0.5">
                                            <span className="font-semibold text-slate-300">{ev.event_type}</span>
                                            <span>{ev.created_at}</span>
                                        </div>
                                        <p className="text-slate-300 text-xs">{ev.description}</p>
                                        {ev.reason_code && (
                                            <span className="text-[10px] px-2 py-0.5 rounded bg-rose-500/10 text-rose-400 inline-block mt-1">
                                                سبب: {ev.reason_code}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Cancel Action if Eligible */}
                {order.can_cancel && (
                    <button
                        type="button"
                        onClick={handleCancel}
                        className="w-full py-3 rounded-2xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 font-bold text-xs flex items-center justify-center gap-1.5 transition-colors"
                    >
                        <XCircle className="w-4 h-4" />
                        <span>إلغاء هذا الطلب</span>
                    </button>
                )}
            </div>
        </AppLayout>
    );
}
