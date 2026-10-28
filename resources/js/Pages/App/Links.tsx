import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency } from '../../utils/format';
import {
    Link2,
    PlusCircle,
    Copy,
    Check,
    QrCode,
    MousePointerClick,
    ShoppingBag,
    TrendingUp,
    Trash2,
    ExternalLink,
} from 'lucide-react';
import { TrackingLinkItem } from '../../types/affiliate';

interface Props {
    links: TrackingLinkItem[];
    products: Array<{ id: number; name: string }>;
    defaultStorefrontUrl: string;
    defaultQrUrl: string;
    settings: {
        min_selling_markup_minor: number;
        max_selling_markup_minor: number;
    };
}

export default function Links({ links, products, defaultStorefrontUrl, defaultQrUrl, settings }: Props) {
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [selectedQrUrl, setSelectedQrUrl] = useState<string | null>(null);
    const [copiedId, setCopiedId] = useState<string | number | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        product_id: '',
        title: '',
        custom_selling_price: '',
        custom_code: '',
    });

    const copyUrl = (url: string, id: string | number) => {
        navigator.clipboard.writeText(url);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 2000);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/partners/app/links', {
            onSuccess: () => {
                setCreateModalOpen(false);
                reset();
            },
        });
    };

    const handleDeleteLink = (id: number) => {
        if (!confirm('هل أنت متأكد من حذف هذا الرابط؟')) return;
        router.delete(`/partners/app/links/${id}`);
    };

    return (
        <AppLayout title="روابط التتبع و QR">
            <Head title="روابط التتبع - تطبيق المسوق" />

            <div className="space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-base font-bold text-white flex items-center gap-1.5">
                            <Link2 className="w-5 h-5 text-indigo-400" />
                            <span>روابط التتبع وكود QR</span>
                        </h1>
                        <p className="text-xs text-slate-400">
                            أنشئ روابط مخصصة لحملاتك الإعلانية مع تتبع النقرات والتحويلات.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => setCreateModalOpen(true)}
                        className="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold flex items-center gap-1 shadow-sm shadow-indigo-600/30"
                    >
                        <PlusCircle className="w-3.5 h-3.5" />
                        <span>رابط جديد</span>
                    </button>
                </div>

                {/* Default Storefront Referral Card */}
                <div className="p-4 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950/40 to-slate-900 border border-indigo-500/30 shadow-xl space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-white flex items-center gap-1.5">
                            <ShoppingBag className="w-4 h-4 text-indigo-400" />
                            رابط المتجر العام (الرئيسي)
                        </span>
                        <button
                            type="button"
                            onClick={() => setSelectedQrUrl(defaultQrUrl)}
                            className="p-1.5 rounded-lg bg-slate-800 text-slate-300 hover:text-white"
                            title="عرض كود QR"
                        >
                            <QrCode className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="text"
                            readOnly
                            dir="ltr"
                            value={defaultStorefrontUrl}
                            className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs font-mono text-slate-300 focus:outline-none"
                        />
                        <button
                            type="button"
                            onClick={() => copyUrl(defaultStorefrontUrl, 'default')}
                            className="h-10 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold flex items-center gap-1 shrink-0 transition-colors"
                        >
                            {copiedId === 'default' ? <Check className="w-4 h-4 text-emerald-300" /> : <Copy className="w-4 h-4" />}
                            <span>{copiedId === 'default' ? 'تم النسخ' : 'نسخ'}</span>
                        </button>
                    </div>
                </div>

                {/* Tracking Links List */}
                <div className="space-y-2">
                    <h2 className="text-xs font-bold text-slate-300">الروابط المخصصة للحملات ({links.length})</h2>

                    {links.length === 0 ? (
                        <div className="p-6 rounded-2xl bg-slate-900/50 border border-slate-800 text-center text-xs text-slate-400">
                            لا توجد روابط تتبع مخصصة بعد. أنشئ أول رابط لحملتك على فيسبوك أو تيك توك الآن!
                        </div>
                    ) : (
                        <div className="space-y-2.5">
                            {links.map((link) => (
                                <div
                                    key={link.id}
                                    className="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 space-y-2.5"
                                >
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <span className="font-bold text-xs text-white block">{link.title}</span>
                                            <span className="text-[10px] text-slate-400 font-mono">
                                                {link.product_name} • كود: {link.code}
                                            </span>
                                        </div>

                                        <div className="flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => setSelectedQrUrl(link.qr_url)}
                                                className="p-1.5 rounded-lg bg-slate-800 text-slate-300 hover:text-white"
                                                title="عرض كود QR"
                                            >
                                                <QrCode className="w-4 h-4" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => handleDeleteLink(link.id)}
                                                className="p-1.5 rounded-lg bg-slate-800 text-rose-400 hover:text-rose-300"
                                                title="حذف الرابط"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    {/* Stats grid */}
                                    <div className="grid grid-cols-3 gap-2 p-2 rounded-xl bg-slate-800/40 text-center text-xs">
                                        <div>
                                            <span className="text-[10px] text-slate-400 flex items-center justify-center gap-1">
                                                <MousePointerClick className="w-3 h-3 text-indigo-400" />
                                                النقرات
                                            </span>
                                            <span className="font-mono font-bold text-white mt-0.5 block">{link.clicks_count}</span>
                                        </div>
                                        <div>
                                            <span className="text-[10px] text-slate-400 flex items-center justify-center gap-1">
                                                <ShoppingBag className="w-3 h-3 text-emerald-400" />
                                                الطلبات
                                            </span>
                                            <span className="font-mono font-bold text-emerald-400 mt-0.5 block">{link.orders_count}</span>
                                        </div>
                                        <div>
                                            <span className="text-[10px] text-slate-400 flex items-center justify-center gap-1">
                                                <TrendingUp className="w-3 h-3 text-violet-400" />
                                                التحويل
                                            </span>
                                            <span className="font-mono font-bold text-violet-300 mt-0.5 block">{link.conversion_rate}%</span>
                                        </div>
                                    </div>

                                    {/* Copy bar */}
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="text"
                                            readOnly
                                            dir="ltr"
                                            value={link.target_url}
                                            className="w-full h-9 px-3 rounded-xl bg-slate-800/80 border border-slate-700 text-xs font-mono text-slate-300 focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => copyUrl(link.target_url, link.id)}
                                            className="h-9 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold flex items-center gap-1 shrink-0 transition-colors"
                                        >
                                            {copiedId === link.id ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                                            <span>{copiedId === link.id ? 'تم النسخ' : 'نسخ'}</span>
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Create Link Modal */}
                {createModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                        <div className="w-full max-w-sm rounded-3xl bg-slate-900 border border-slate-800 p-5 space-y-4 shadow-2xl animate-in fade-in slide-in-from-bottom duration-200">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-bold text-white flex items-center gap-1.5">
                                    <PlusCircle className="w-4 h-4 text-indigo-400" />
                                    <span>إنشاء رابط تتبع مخصص</span>
                                </h3>
                                <button
                                    type="button"
                                    onClick={() => setCreateModalOpen(false)}
                                    className="text-slate-400 hover:text-white text-xs"
                                >
                                    إلغاء
                                </button>
                            </div>

                            <form onSubmit={handleCreateSubmit} className="space-y-3">
                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">عنوان الرابط / الحملة</label>
                                    <input
                                        type="text"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        placeholder="مثال: حملة فيسبوك العيد"
                                        className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                                    />
                                    {errors.title && <p className="text-[10px] text-rose-400 mt-1">{errors.title}</p>}
                                </div>

                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">المنتج المستهدف (اختياري)</label>
                                    <select
                                        value={data.product_id}
                                        onChange={(e) => setData('product_id', e.target.value)}
                                        className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                                    >
                                        <option value="">رابط للمتجر ككل</option>
                                        {products.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">سعر بيع مخصص للرابط (ج.م) اختياري</label>
                                    <input
                                        type="number"
                                        value={data.custom_selling_price}
                                        onChange={(e) => setData('custom_selling_price', e.target.value)}
                                        placeholder="اتركه فارغاً للسعر الافتراضي"
                                        className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                                    />
                                    {errors.custom_selling_price && (
                                        <p className="text-[10px] text-rose-400 mt-1">{errors.custom_selling_price}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">كود مخصص للرابط (Slug) اختياري</label>
                                    <input
                                        type="text"
                                        dir="ltr"
                                        value={data.custom_code}
                                        onChange={(e) => setData('custom_code', e.target.value)}
                                        placeholder="PROMO2026"
                                        className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs font-mono text-white focus:outline-none focus:border-indigo-500"
                                    />
                                    {errors.custom_code && (
                                        <p className="text-[10px] text-rose-400 mt-1">{errors.custom_code}</p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full h-11 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/20 disabled:opacity-50 transition-all"
                                >
                                    {processing ? 'جاري الإنشاء...' : 'إنشاء الرابط'}
                                </button>
                            </form>
                        </div>
                    </div>
                )}

                {/* QR Code Preview Modal */}
                {selectedQrUrl && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                        <div className="w-full max-w-xs rounded-3xl bg-slate-900 border border-slate-800 p-6 space-y-4 text-center shadow-2xl">
                            <h3 className="text-sm font-bold text-white">رمز الاستجابة السريعة (QR Code)</h3>
                            <div className="p-4 bg-white rounded-2xl inline-block shadow-inner">
                                <img src={selectedQrUrl} alt="QR Code" className="w-48 h-48 mx-auto" />
                            </div>
                            <p className="text-[11px] text-slate-400">
                                يمكن للعملاء مسح الرمز بكاميرا الهاتف للشراء واحتساب العمولة لك فوراً.
                            </p>
                            <button
                                type="button"
                                onClick={() => setSelectedQrUrl(null)}
                                className="w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold"
                            >
                                إغلاق
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
