import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency } from '../../utils/format';
import {
    BarChart3,
    TrendingUp,
    Package,
    MapPin,
    Share2,
    CheckCircle2,
    XCircle,
    ArrowUpRight,
    PieChart,
} from 'lucide-react';

interface Props {
    overview: {
        total_orders: number;
        delivered_orders: number;
        cancelled_orders: number;
        returned_orders: number;
        delivery_rate: number;
        cancellation_rate: number;
        return_rate: number;
        total_profit_minor: number;
    };
    byProduct: Array<{
        product_id: number;
        product_name: string;
        orders_count: number;
        delivered_count: number;
        returned_count: number;
        profit_minor: number;
        delivery_rate: number;
        return_rate: number;
    }>;
    byGovernorate: Array<{
        governorate: string;
        orders_count: number;
        delivered_count: number;
        cancelled_count: number;
        delivery_rate: number;
    }>;
    bySource: Array<{
        source: string;
        orders_count: number;
        delivered_count: number;
        delivery_rate: number;
    }>;
}

export default function Analytics({ overview, byProduct, byGovernorate, bySource }: Props) {
    const [tab, setTab] = useState<'product' | 'governorate' | 'source'>('product');

    return (
        <AppLayout title="التحليلات والمبيعات">
            <Head title="التحليلات - تطبيق المسوق" />

            <div className="space-y-4">
                {/* Header */}
                <div>
                    <h1 className="text-base font-bold text-white flex items-center gap-1.5">
                        <BarChart3 className="w-5 h-5 text-emerald-400" />
                        <span>تحليلات المبيعات والأداء</span>
                    </h1>
                    <p className="text-xs text-slate-400">
                        تحليل تفصيلي لمعدلات التسليم حسب المنتج والمحافظة ومصدر الحملات.
                    </p>
                </div>

                {/* Overview KPIs Cards */}
                <div className="p-4 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950/40 to-slate-900 border border-indigo-500/20 shadow-xl space-y-3">
                    <span className="text-xs text-slate-400">إجمالي الأرباح المحققة من الطلبات المسلمة</span>
                    <div className="text-3xl font-black text-emerald-300 font-mono">
                        +{formatCurrency(overview.total_profit_minor)}
                    </div>

                    <div className="grid grid-cols-3 gap-2 pt-3 border-t border-slate-800 text-center">
                        <div className="p-2 rounded-xl bg-slate-800/40">
                            <span className="text-[10px] text-slate-400 block">إجمالي الطلبات</span>
                            <span className="text-sm font-bold text-white font-mono mt-0.5 block">
                                {overview.total_orders}
                            </span>
                        </div>
                        <div className="p-2 rounded-xl bg-slate-800/40">
                            <span className="text-[10px] text-emerald-400 block">نسبة التسليم</span>
                            <span className="text-sm font-bold text-emerald-400 font-mono mt-0.5 block">
                                {overview.delivery_rate}%
                            </span>
                        </div>
                        <div className="p-2 rounded-xl bg-slate-800/40">
                            <span className="text-[10px] text-rose-400 block">نسبة الارتجاع</span>
                            <span className="text-sm font-bold text-rose-400 font-mono mt-0.5 block">
                                {overview.return_rate}%
                            </span>
                        </div>
                    </div>
                </div>

                {/* Breakdown Navigation Tabs */}
                <div className="flex rounded-2xl bg-slate-900 p-1 border border-slate-800">
                    <button
                        type="button"
                        onClick={() => setTab('product')}
                        className={`flex-1 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 ${
                            tab === 'product' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'
                        }`}
                    >
                        <Package className="w-3.5 h-3.5" />
                        <span>حسب المنتج</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab('governorate')}
                        className={`flex-1 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 ${
                            tab === 'governorate' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'
                        }`}
                    >
                        <MapPin className="w-3.5 h-3.5" />
                        <span>حسب المحافظة</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab('source')}
                        className={`flex-1 py-2 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 ${
                            tab === 'source' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'
                        }`}
                    >
                        <Share2 className="w-3.5 h-3.5" />
                        <span>المصدر</span>
                    </button>
                </div>

                {/* Tab: By Product */}
                {tab === 'product' && (
                    <div className="space-y-2">
                        {byProduct.length === 0 ? (
                            <div className="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 text-center text-xs text-slate-400">
                                لا توجد بيانات مبيعات للمنتجات بعد.
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {byProduct.map((p) => (
                                    <div
                                        key={p.product_id}
                                        className="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2 text-xs"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-bold text-white line-clamp-1">{p.product_name}</span>
                                            <span className="font-mono font-black text-emerald-400 shrink-0">
                                                +{formatCurrency(p.profit_minor)}
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-3 gap-2 text-center text-[11px] pt-1 border-t border-slate-800/80">
                                            <div className="text-slate-400">
                                                <span>طلبات: </span>
                                                <span className="font-mono text-white font-bold">{p.orders_count}</span>
                                            </div>
                                            <div className="text-emerald-400">
                                                <span>تسليم: </span>
                                                <span className="font-mono font-bold">{p.delivery_rate}%</span>
                                            </div>
                                            <div className="text-rose-400">
                                                <span>ارتجاع: </span>
                                                <span className="font-mono font-bold">{p.return_rate}%</span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Tab: By Governorate */}
                {tab === 'governorate' && (
                    <div className="space-y-2">
                        {byGovernorate.length === 0 ? (
                            <div className="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 text-center text-xs text-slate-400">
                                لا توجد طلبات جغرافية مسجلة بعد.
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {byGovernorate.map((g, idx) => (
                                    <div
                                        key={idx}
                                        className="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between text-xs"
                                    >
                                        <div>
                                            <span className="font-bold text-white block">{g.governorate}</span>
                                            <span className="text-[10px] text-slate-500">
                                                {g.orders_count} طلب • {g.delivered_count} مسلّم
                                            </span>
                                        </div>

                                        <div className="text-left">
                                            <span className="text-emerald-400 font-mono font-bold text-sm block">
                                                {g.delivery_rate}%
                                            </span>
                                            <span className="text-[10px] text-slate-400 block">نسبة التسليم</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Tab: By Source */}
                {tab === 'source' && (
                    <div className="space-y-2">
                        <div className="space-y-2">
                            {bySource.map((s, idx) => (
                                <div
                                    key={idx}
                                    className="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between text-xs"
                                >
                                    <div className="flex items-center gap-2">
                                        <div className="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold">
                                            {s.source[0]}
                                        </div>
                                        <div>
                                            <span className="font-bold text-white block">{s.source}</span>
                                            <span className="text-[10px] text-slate-500">{s.orders_count} طلب مسجل</span>
                                        </div>
                                    </div>

                                    <div className="text-left">
                                        <span className="text-indigo-400 font-mono font-bold text-sm block">
                                            {s.delivery_rate}%
                                        </span>
                                        <span className="text-[10px] text-slate-400 block">معدل التسليم</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
