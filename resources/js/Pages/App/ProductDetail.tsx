import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency, currencySymbol } from '../../utils/format';
import {
    Calculator,
    CheckCircle2,
    XCircle,
    Copy,
    Check,
    ChevronLeft,
    TrendingUp,
    ShieldAlert,
    Share2,
    PlusCircle,
} from 'lucide-react';

interface Props {
    product: {
        id: number;
        name: string;
        description: string;
        sku: string;
        wholesale_minor: number;
        min_selling_minor: number;
        max_selling_minor: number;
        suggested_price_minor: number;
        stock: number;
        images: string[];
        ad_copies: string[];
        marketing_images: string[];
    };
    settings: {
        min_selling_markup_minor: number;
        max_selling_markup_minor: number;
    };
}

export default function ProductDetail({ product, settings }: Props) {
    const [selectedImageIndex, setSelectedImageIndex] = useState(0);
    const [sellingPricePounds, setSellingPricePounds] = useState(
        Math.round(product.suggested_price_minor / 100)
    );
    const [quantity, setQuantity] = useState(1);
    const [copiedIndex, setCopiedIndex] = useState<number | null>(null);

    const minSellingPounds = Math.ceil(product.min_selling_minor / 100);
    const maxSellingPounds = Math.floor(product.max_selling_minor / 100);
    const wholesalePounds = product.wholesale_minor / 100;

    // Honest Profit Calculation (D4)
    const currentSellingPounds = Math.max(minSellingPounds, Math.min(maxSellingPounds, sellingPricePounds));
    const profitPerUnitPounds = currentSellingPounds - wholesalePounds;
    const totalNetProfitPounds = profitPerUnitPounds * quantity;
    const profitMarginPercent = currentSellingPounds > 0 ? Math.round((profitPerUnitPounds / currentSellingPounds) * 100) : 0;

    // Presets
    const applyMarkupPreset = (markupPounds: number) => {
        const target = Math.round(wholesalePounds + markupPounds);
        setSellingPricePounds(Math.max(minSellingPounds, Math.min(maxSellingPounds, target)));
    };

    const copyAdCopy = (text: string, index: number) => {
        navigator.clipboard.writeText(text);
        setCopiedIndex(index);
        setTimeout(() => setCopiedIndex(null), 2000);
    };

    return (
        <AppLayout title={product.name}>
            <Head title={`${product.name} - تطبيق المسوق`} />

            <div className="space-y-4">
                {/* Back button */}
                <Link
                    href="/partners/app/catalog"
                    className="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-white transition-colors"
                >
                    <ChevronLeft className="w-4 h-4 rotate-180" />
                    <span>العودة للكتالوج</span>
                </Link>

                {/* Image Gallery */}
                <div className="rounded-3xl bg-slate-900 border border-slate-800 overflow-hidden">
                    <div className="aspect-square bg-slate-800 overflow-hidden relative">
                        <img
                            src={product.images[selectedImageIndex] || '/images/placeholder.svg'}
                            alt={product.name}
                            className="w-full h-full object-cover"
                            onError={(e) => {
                                (e.target as HTMLImageElement).src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="%231e293b"><rect width="100" height="100"/><text x="50" y="55" font-size="12" fill="%2364748b" text-anchor="middle">منتج</text></svg>';
                            }}
                        />
                        <div className="absolute top-3 right-3">
                            <span className="px-2.5 py-1 rounded-full bg-slate-900/80 backdrop-blur-md text-emerald-400 font-bold text-xs border border-emerald-500/30">
                                متبقي بالمخزن: {product.stock}
                            </span>
                        </div>
                    </div>

                    {product.images.length > 1 && (
                        <div className="p-2.5 flex gap-2 overflow-x-auto border-t border-slate-800">
                            {product.images.map((img, i) => (
                                <button
                                    key={i}
                                    type="button"
                                    onClick={() => setSelectedImageIndex(i)}
                                    className={`w-14 h-14 rounded-xl overflow-hidden shrink-0 border-2 transition-all ${
                                        selectedImageIndex === i ? 'border-indigo-500 scale-95' : 'border-transparent opacity-70'
                                    }`}
                                >
                                    <img src={img} alt="" className="w-full h-full object-cover" />
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Product Header */}
                <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-2">
                    <span className="text-xs text-indigo-400 font-mono font-semibold">{product.sku}</span>
                    <h1 className="text-base font-bold text-white">{product.name}</h1>
                    <p className="text-xs text-slate-400 leading-relaxed">{product.description}</p>
                    <div className="pt-2 border-t border-slate-800 flex items-center justify-between text-xs">
                        <span className="text-slate-400">سعر جملة المسوق:</span>
                        <span className="text-sm font-bold text-white font-mono">
                            {formatCurrency(product.wholesale_minor)}
                        </span>
                    </div>
                </div>

                {/* Honest Profit Calculator (D4) */}
                <div className="p-4 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950/30 to-slate-900 border border-indigo-500/30 shadow-xl space-y-4">
                    <div className="flex items-center gap-2 text-indigo-400">
                        <Calculator className="w-5 h-5" />
                        <h2 className="text-sm font-bold text-white">حاسبة الربح الصادقة</h2>
                        <span className="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 font-medium mr-auto">
                            D4 Honest Calc
                        </span>
                    </div>

                    {/* Quantity & Selling Price Inputs */}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">الكمية</label>
                            <div className="flex items-center h-11 rounded-2xl bg-slate-800 border border-slate-700 px-2 justify-between">
                                <button
                                    type="button"
                                    onClick={() => setQuantity(Math.max(1, quantity - 1))}
                                    className="w-7 h-7 rounded-lg bg-slate-700 text-white font-bold flex items-center justify-center hover:bg-slate-600"
                                >
                                    -
                                </button>
                                <span className="font-bold font-mono text-sm text-white">{quantity}</span>
                                <button
                                    type="button"
                                    onClick={() => setQuantity(quantity + 1)}
                                    className="w-7 h-7 rounded-lg bg-slate-700 text-white font-bold flex items-center justify-center hover:bg-slate-600"
                                >
                                    +
                                </button>
                            </div>
                        </div>

                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">
                                سعر البيع للقطعة ({currencySymbol()})
                            </label>
                            <input
                                type="number"
                                min={minSellingPounds}
                                max={maxSellingPounds}
                                value={sellingPricePounds}
                                onChange={(e) => setSellingPricePounds(Number(e.target.value))}
                                className="w-full h-11 rounded-2xl bg-slate-800 border border-slate-700 px-3 text-sm font-bold text-white font-mono focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    {/* Selling Price Slider */}
                    <div className="space-y-1">
                        <input
                            type="range"
                            min={minSellingPounds}
                            max={maxSellingPounds}
                            step={10}
                            value={currentSellingPounds}
                            onChange={(e) => setSellingPricePounds(Number(e.target.value))}
                            className="w-full accent-indigo-500 cursor-pointer"
                        />
                        <div className="flex justify-between text-[10px] text-slate-500 font-mono">
                            <span>الحد الأدنى: {minSellingPounds} {currencySymbol()}</span>
                            <span>الحد الأقصى: {maxSellingPounds} {currencySymbol()}</span>
                        </div>
                    </div>

                    {/* Quick Markup Presets */}
                    <div className="flex items-center gap-1.5 overflow-x-auto pb-1">
                        <span className="text-[10px] text-slate-400 shrink-0">ربح سريع:</span>
                        {[50, 100, 150, 200].map((preset) => (
                            <button
                                key={preset}
                                type="button"
                                onClick={() => applyMarkupPreset(preset)}
                                className="px-2.5 py-1 rounded-xl bg-slate-800 hover:bg-slate-700 text-[11px] font-bold text-slate-200 border border-slate-700 shrink-0"
                            >
                                +{preset} {currencySymbol()}
                            </button>
                        ))}
                    </div>

                    {/* Calculation Dual Results: لو سُلّم vs لو رُفض */}
                    <div className="grid grid-cols-2 gap-3 pt-2">
                        {/* Delivered Scenario */}
                        <div className="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 space-y-1">
                            <div className="flex items-center gap-1.5 font-bold text-xs">
                                <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                                <span>لو سُلّم الطلب</span>
                            </div>
                            <div className="text-xl font-extrabold text-emerald-300 font-mono">
                                +{totalNetProfitPounds} {currencySymbol()}
                            </div>
                            <span className="text-[10px] text-emerald-500 block">
                                صافي ربح بحسابك ({profitMarginPercent}% هامش)
                            </span>
                        </div>

                        {/* Refused Scenario */}
                        <div className="p-3 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 space-y-1">
                            <div className="flex items-center gap-1.5 font-bold text-xs">
                                <XCircle className="w-4 h-4 text-rose-400" />
                                <span>لو رُفض الطلب</span>
                            </div>
                            <div className="text-xl font-extrabold text-rose-300 font-mono">
                                0 {currencySymbol()}
                            </div>
                            <span className="text-[10px] text-rose-500 block">
                                لا تُصرف عمولة + شحن المرتجع
                            </span>
                        </div>
                    </div>
                </div>

                {/* Marketing Materials / Ad Copies */}
                {product.ad_copies.length > 0 && (
                    <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-3">
                        <div className="flex items-center gap-2 text-xs font-bold text-white">
                            <Share2 className="w-4 h-4 text-indigo-400" />
                            <span>النصوص الإعلانية الجاهزة للنشر</span>
                        </div>

                        <div className="space-y-2.5">
                            {product.ad_copies.map((copy, index) => (
                                <div key={index} className="p-3 rounded-2xl bg-slate-800/60 border border-slate-700/60 space-y-2">
                                    <p className="text-xs text-slate-300 leading-relaxed whitespace-pre-line">
                                        {copy}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => copyAdCopy(copy, index)}
                                        className="w-full py-1.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-xs font-semibold text-white flex items-center justify-center gap-1.5 transition-colors"
                                    >
                                        {copiedIndex === index ? (
                                            <>
                                                <Check className="w-3.5 h-3.5 text-emerald-400" />
                                                <span>تم نسخ النص بنجاح!</span>
                                            </>
                                        ) : (
                                            <>
                                                <Copy className="w-3.5 h-3.5" />
                                                <span>نسخ النص الإعلاني</span>
                                            </>
                                        )}
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Primary CTA: Order Now */}
                <div className="sticky bottom-20 z-30">
                    <Link
                        href={`/partners/app/orders/create?product_id=${product.id}&selling_price=${currentSellingPounds}&qty=${quantity}`}
                        className="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold text-sm text-center flex items-center justify-center gap-2 shadow-xl shadow-indigo-600/30 transition-all active:scale-[0.98]"
                    >
                        <PlusCircle className="w-4 h-4" />
                        <span>إنشاء طلب سريع لهذا المنتج فوراً</span>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
