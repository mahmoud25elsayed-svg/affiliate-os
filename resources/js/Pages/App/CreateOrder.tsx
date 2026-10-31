import React, { useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency, currencySymbol } from '../../utils/format';
import { t } from '@/i18n';
import {
    PlusCircle,
    User,
    Phone,
    MapPin,
    Building,
    FileText,
    Calculator,
    AlertCircle,
    CheckCircle2,
    ShieldCheck,
    ChevronLeft,
} from 'lucide-react';

interface Variant {
    id: number;
    sku: string;
    stock: number;
    wholesale_minor: number;
    min_selling_minor: number;
    max_selling_minor: number;
}

interface Product {
    id: number;
    name: string;
    variants: Variant[];
}

interface City {
    id: number;
    name: string;
}

interface Governorate {
    id: number;
    name: string;
    cities: City[];
}

interface Props {
    products: Product[];
    governorates: Governorate[];
    selectedVariantId?: number | null;
    settings: {
        min_selling_markup_minor: number;
        max_selling_markup_minor: number;
    };
}

export default function CreateOrder({ products, governorates, selectedVariantId, settings }: Props) {
    // Find initial variant
    const allVariants = products.flatMap((p) => p.variants);
    const initialVariant = allVariants.find((v) => v.id === selectedVariantId) || allVariants[0];

    const { data, setData, post, processing, errors } = useForm({
        variant_id: initialVariant ? String(initialVariant.id) : '',
        quantity: 1,
        selling_price_minor: initialVariant ? initialVariant.min_selling_minor + 5000 : 15000,
        customer_name: '',
        customer_phone: '',
        customer_email: '',
        governorate_id: governorates[0] ? String(governorates[0].id) : '',
        city_id: '',
        address: '',
        notes: '',
    });

    const [quote, setQuote] = useState<{
        wholesale_total_minor: number;
        shipping_cost_minor: number;
        cod_fee_minor: number;
        affiliate_profit_minor: number;
        customer_total_minor: number;
        warnings: string[];
    } | null>(null);

    const [isQuoting, setIsQuoting] = useState(false);

    // Selected objects
    const currentVariant = allVariants.find((v) => String(v.id) === String(data.variant_id)) || initialVariant;
    const currentGov = governorates.find((g) => String(g.id) === String(data.governorate_id));

    const minSellingPounds = currentVariant ? Math.ceil(currentVariant.min_selling_minor / 100) : 0;
    const maxSellingPounds = currentVariant ? Math.floor(currentVariant.max_selling_minor / 100) : 0;
    const currentSellingPounds = Math.round(data.selling_price_minor / 100);

    // Fetch live quote
    useEffect(() => {
        if (!data.variant_id || !data.quantity || !data.selling_price_minor) return;

        setIsQuoting(true);
        fetch('/partners/app/orders/quote', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                variant_id: Number(data.variant_id),
                quantity: Number(data.quantity),
                selling_price_minor: Number(data.selling_price_minor),
                governorate_id: data.governorate_id ? Number(data.governorate_id) : null,
            }),
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => {
                // A failed quote (validation/session error) must not crash the page with a partial object.
                setQuote(json && Array.isArray(json.warnings) ? json : null);
                setIsQuoting(false);
            })
            .catch(() => setIsQuoting(false));
    }, [data.variant_id, data.quantity, data.selling_price_minor, data.governorate_id]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/partners/app/orders');
    };

    return (
        <AppLayout title={t('إنشاء طلب سريع')}>
            <Head title={t('إنشاء طلب سريع - تطبيق المسوق')} />

            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-base font-bold text-white flex items-center gap-1.5">
                            <PlusCircle className="w-5 h-5 text-indigo-400" />
                            <span>{t('إنشاء طلب سريع للعميل')}</span>
                        </h1>
                        <p className="text-xs text-slate-400">
                            {t('أدخل بيانات العميل وحدد سعر البيع لمشاهدة ربحك فوراً.')}
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Product & Variant Selection */}
                    <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-3">
                        <h2 className="text-xs font-bold text-slate-200">{t('1. اختيار المنتج والكمية')}</h2>

                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">{t('المنتج والموديل')}</label>
                            <select
                                value={data.variant_id}
                                onChange={(e) => {
                                    const vId = e.target.value;
                                    const v = allVariants.find((item) => String(item.id) === vId);
                                    setData({
                                        ...data,
                                        variant_id: vId,
                                        selling_price_minor: v ? v.min_selling_minor + 5000 : data.selling_price_minor,
                                    });
                                }}
                                className="w-full h-11 rounded-2xl bg-slate-800 border border-slate-700 px-3 text-xs text-white focus:outline-none focus:border-indigo-500"
                            >
                                {products.map((p) => (
                                    <optgroup key={p.id} label={p.name}>
                                        {p.variants.map((v) => (
                                            <option key={v.id} value={v.id}>
                                                {p.name} ({v.sku}{t(') - متبقي')} {v.stock}
                                            </option>
                                        ))}
                                    </optgroup>
                                ))}
                            </select>
                            {errors.variant_id && (
                                <p className="text-[10px] text-rose-400 mt-1">{errors.variant_id}</p>
                            )}
                        </div>

                        {/* Quantity and Selling Price */}
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-[11px] text-slate-400 mb-1">{t('الكمية')}</label>
                                <div className="flex items-center h-11 rounded-2xl bg-slate-800 border border-slate-700 px-2 justify-between">
                                    <button
                                        type="button"
                                        onClick={() => setData('quantity', Math.max(1, data.quantity - 1))}
                                        className="w-7 h-7 rounded-lg bg-slate-700 text-white font-bold flex items-center justify-center hover:bg-slate-600"
                                    >
                                        -
                                    </button>
                                    <span className="font-bold font-mono text-sm text-white">{data.quantity}</span>
                                    <button
                                        type="button"
                                        onClick={() => setData('quantity', data.quantity + 1)}
                                        className="w-7 h-7 rounded-lg bg-slate-700 text-white font-bold flex items-center justify-center hover:bg-slate-600"
                                    >
                                        +
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="block text-[11px] text-slate-400 mb-1">{t('سعر البيع للقطعة (')}{currencySymbol()})</label>
                                <input
                                    type="number"
                                    min={minSellingPounds}
                                    max={maxSellingPounds}
                                    value={currentSellingPounds}
                                    onChange={(e) => setData('selling_price_minor', Number(e.target.value) * 100)}
                                    className="w-full h-11 rounded-2xl bg-slate-800 border border-slate-700 px-3 text-sm font-bold text-white font-mono focus:outline-none focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        {/* Bounds info */}
                        <div className="flex justify-between text-[10px] text-slate-500 font-mono">
                            <span>{t('جملة:')} {currentVariant ? formatCurrency(currentVariant.wholesale_minor) : ''}</span>
                            <span>{t('نطاق البيع:')} {minSellingPounds} {t('إلى')} {maxSellingPounds} {currencySymbol()}</span>
                        </div>
                        {errors.selling_price_minor && (
                            <p className="text-[10px] text-rose-400">{errors.selling_price_minor}</p>
                        )}
                    </div>

                    {/* Customer Information */}
                    <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-3">
                        <h2 className="text-xs font-bold text-slate-200">{t('2. بيانات العميل والتوصيل')}</h2>

                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">{t('اسم العميل ثلاثي')}</label>
                            <div className="relative">
                                <input
                                    type="text"
                                    required
                                    value={data.customer_name}
                                    onChange={(e) => setData('customer_name', e.target.value)}
                                    placeholder={t('مثال: أحمد محمد علي')}
                                    className="w-full h-11 pl-3 pr-9 rounded-2xl bg-slate-800 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                                />
                                <User className="w-4 h-4 text-slate-400 absolute right-3 top-3.5" />
                            </div>
                            {errors.customer_name && (
                                <p className="text-[10px] text-rose-400 mt-1">{errors.customer_name}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">{t('رقم موبايل العميل')}</label>
                            <div className="relative">
                                <input
                                    type="tel"
                                    required
                                    dir="ltr"
                                    value={data.customer_phone}
                                    onChange={(e) => setData('customer_phone', e.target.value)}
                                    placeholder="01012345678"
                                    className="w-full h-11 pl-3 pr-9 rounded-2xl bg-slate-800 border border-slate-700 text-xs text-white placeholder-slate-500 text-right font-mono focus:outline-none focus:border-indigo-500"
                                />
                                <Phone className="w-4 h-4 text-slate-400 absolute right-3 top-3.5" />
                            </div>
                            {errors.customer_phone && (
                                <p className="text-[10px] text-rose-400 mt-1">{errors.customer_phone}</p>
                            )}
                        </div>

                        {/* Governorate and City */}
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-[11px] text-slate-400 mb-1">{t('المحافظة')}</label>
                                <select
                                    value={data.governorate_id}
                                    onChange={(e) => {
                                        setData({
                                            ...data,
                                            governorate_id: e.target.value,
                                            city_id: '',
                                        });
                                    }}
                                    className="w-full h-11 rounded-2xl bg-slate-800 border border-slate-700 px-3 text-xs text-white focus:outline-none focus:border-indigo-500"
                                >
                                    {governorates.map((g) => (
                                        <option key={g.id} value={g.id}>
                                            {g.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-[11px] text-slate-400 mb-1">{t('المركز / الحي (اختياري)')}</label>
                                <select
                                    value={data.city_id}
                                    onChange={(e) => setData('city_id', e.target.value)}
                                    className="w-full h-11 rounded-2xl bg-slate-800 border border-slate-700 px-3 text-xs text-white focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="">{t('اختر الحي / المدينة')}</option>
                                    {currentGov?.cities.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">{t('العنوان التفصيلي واسم الشارع والعمارة')}</label>
                            <div className="relative">
                                <input
                                    type="text"
                                    required
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder={t('اسم الشارع، رقم العقار، الدور، الشقة، علامة مميزة')}
                                    className="w-full h-11 pl-3 pr-9 rounded-2xl bg-slate-800 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                                />
                                <MapPin className="w-4 h-4 text-slate-400 absolute right-3 top-3.5" />
                            </div>
                            {errors.address && (
                                <p className="text-[10px] text-rose-400 mt-1">{errors.address}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">{t('ملاحظات لشركة الشحن (اختياري)')}</label>
                            <input
                                type="text"
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder={t('مثال: يرجى الاتصال قبل الوصول بنصف ساعة')}
                                className="w-full h-11 px-3 rounded-2xl bg-slate-800 border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    {/* Live Quote & Profit Summary Card */}
                    {quote && (
                        <div className="p-4 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950/40 to-slate-900 border border-indigo-500/30 space-y-2.5 shadow-xl">
                            <div className="flex items-center justify-between text-xs text-slate-300 font-bold">
                                <span className="flex items-center gap-1.5 text-indigo-400">
                                    <Calculator className="w-4 h-4" />
                                    {t('معاينة الفاتورة والأرباح المباشرة')}
                                </span>
                                {isQuoting && <span className="text-[10px] text-slate-400 animate-pulse">{t('جاري الحساب...')}</span>}
                            </div>

                            <div className="space-y-1.5 text-xs text-slate-400 pt-1">
                                <div className="flex justify-between">
                                    <span>{t('إجمالي سعر المنتجات:')}</span>
                                    <span className="font-mono text-slate-200">{formatCurrency(quote.customer_total_minor - quote.shipping_cost_minor)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>{t('تكلفة الشحن:')}</span>
                                    <span className="font-mono text-slate-200">{formatCurrency(quote.shipping_cost_minor)}</span>
                                </div>
                                <div className="flex justify-between font-bold text-white pt-1 border-t border-slate-800">
                                    <span>{t('المبلغ المطلوب من العميل:')}</span>
                                    <span className="font-mono text-indigo-300">{formatCurrency(quote.customer_total_minor)}</span>
                                </div>
                            </div>

                            {/* Profit Highlight */}
                            <div className="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-between">
                                <div>
                                    <span className="text-[11px] text-emerald-400 block font-bold">{t('صافي عمولتك بعد التسليم:')}</span>
                                    <span className="text-lg font-black text-emerald-300 font-mono">
                                        +{formatCurrency(quote.affiliate_profit_minor)}
                                    </span>
                                </div>
                                <ShieldCheck className="w-7 h-7 text-emerald-400" />
                            </div>

                            {quote.warnings.length > 0 && (
                                <div className="space-y-1">
                                    {quote.warnings.map((w, idx) => (
                                        <p key={idx} className="text-[10px] text-amber-400 flex items-center gap-1">
                                            <AlertCircle className="w-3 h-3 shrink-0" />
                                            <span>{w}</span>
                                        </p>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Submit Button */}
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full h-12 rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold text-sm shadow-xl shadow-indigo-600/30 flex items-center justify-center gap-2 disabled:opacity-50 transition-all active:scale-[0.98]"
                    >
                        {processing ? (
                            <span>{t('جاري تسجيل الطلب وفحص الجودة...')}</span>
                        ) : (
                            <>
                                <PlusCircle className="w-4 h-4" />
                                <span>{t('تأكيد وإرسال الطلب فوراً')}</span>
                            </>
                        )}
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
