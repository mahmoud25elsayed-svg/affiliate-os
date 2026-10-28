import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency } from '../../utils/format';
import { Search, Filter, PlusCircle, CheckCircle, Flame, ChevronLeft } from 'lucide-react';
import { ProductItem } from '../../types/affiliate';

interface Props {
    products: {
        data: ProductItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    categories: Array<{ id: number; name: string }>;
    filters: {
        search?: string | null;
        category_id?: string | null;
    };
    settings: {
        min_selling_markup_minor: number;
        max_selling_markup_minor: number;
    };
}

export default function Catalog({ products, categories, filters, settings }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category_id || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/partners/app/catalog', {
            search: search || undefined,
            category_id: selectedCategory || undefined,
        }, { preserveState: true });
    };

    const handleCategoryClick = (id: string) => {
        const nextId = selectedCategory === id ? '' : id;
        setSelectedCategory(nextId);
        router.get('/partners/app/catalog', {
            search: search || undefined,
            category_id: nextId || undefined,
        }, { preserveState: true });
    };

    return (
        <AppLayout title="كتالوج المنتجات">
            <Head title="كتالوج المنتجات - تطبيق المسوق" />

            <div className="space-y-4">
                {/* Search Bar */}
                <form onSubmit={handleSearch} className="relative">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="ابحث بالاسم أو SKU..."
                        className="w-full h-11 pl-4 pr-10 rounded-2xl bg-slate-900 border border-slate-800 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    />
                    <Search className="w-4 h-4 text-slate-400 absolute right-3.5 top-3.5" />
                </form>

                {/* Category Chips Carousel */}
                {categories.length > 0 && (
                    <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
                        <button
                            type="button"
                            onClick={() => handleCategoryClick('')}
                            className={`px-3 py-1.5 rounded-full text-xs font-semibold shrink-0 transition-colors ${
                                !selectedCategory
                                    ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/30'
                                    : 'bg-slate-900 text-slate-400 border border-slate-800 hover:text-white'
                            }`}
                        >
                            جميع الأقسام
                        </button>
                        {categories.map((cat) => (
                            <button
                                key={cat.id}
                                type="button"
                                onClick={() => handleCategoryClick(String(cat.id))}
                                className={`px-3 py-1.5 rounded-full text-xs font-semibold shrink-0 transition-colors ${
                                    selectedCategory === String(cat.id)
                                        ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/30'
                                        : 'bg-slate-900 text-slate-400 border border-slate-800 hover:text-white'
                                }`}
                            >
                                {cat.name}
                            </button>
                        ))}
                    </div>
                )}

                {/* Products Grid */}
                {products.data.length === 0 ? (
                    <div className="p-8 rounded-3xl bg-slate-900/50 border border-slate-800 text-center space-y-2">
                        <p className="text-sm text-slate-300 font-bold">لا توجد منتجات مطابقة للبحث</p>
                        <p className="text-xs text-slate-500">جرب البحث بكلمات أخرى أو اختر قسماً مختلفاً.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3">
                        {products.data.map((product) => (
                            <div
                                key={product.id}
                                className="rounded-2xl bg-slate-900 border border-slate-800 overflow-hidden flex flex-col justify-between hover:border-slate-700 transition-all group"
                            >
                                <div>
                                    {/* Product Image & Badges */}
                                    <div className="relative aspect-square bg-slate-800 overflow-hidden">
                                        <img
                                            src={product.image_url}
                                            alt={product.name}
                                            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            onError={(e) => {
                                                (e.target as HTMLImageElement).src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="%231e293b"><rect width="100" height="100"/><text x="50" y="55" font-size="12" fill="%2364748b" text-anchor="middle">منتج</text></svg>';
                                            }}
                                        />
                                        <div className="absolute top-2 right-2 flex flex-col gap-1">
                                            <span className="px-2 py-0.5 rounded-md bg-emerald-500/90 text-slate-950 font-black text-[10px] shadow">
                                                تسليم {product.delivery_success_rate}%
                                            </span>
                                        </div>
                                        {!product.is_in_stock && (
                                            <div className="absolute inset-0 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center">
                                                <span className="px-2.5 py-1 rounded-md bg-rose-500/90 text-white font-bold text-xs shadow">
                                                    نفد المخزون
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Product Info */}
                                    <div className="p-3">
                                        <span className="text-[10px] text-slate-500 font-mono block">
                                            {product.sku}
                                        </span>
                                        <h3 className="text-xs font-bold text-white line-clamp-2 mt-0.5 min-h-[32px]">
                                            {product.name}
                                        </h3>

                                        <div className="mt-2 pt-2 border-t border-slate-800/80 space-y-1">
                                            <div className="flex items-center justify-between text-[11px]">
                                                <span className="text-slate-400">سعر الجملة:</span>
                                                <span className="font-mono text-slate-300 font-semibold">
                                                    {formatCurrency(product.wholesale_minor)}
                                                </span>
                                            </div>

                                            <div className="flex items-center justify-between text-[11px]">
                                                <span className="text-slate-400">ربح مقترح:</span>
                                                <span className="font-mono text-emerald-400 font-bold">
                                                    +{formatCurrency(product.suggested_markup_minor)}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="p-3 pt-0 space-y-1.5">
                                    <Link
                                        href={`/partners/app/catalog/${product.id}`}
                                        className="w-full py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold text-center block transition-colors"
                                    >
                                        حاسبة الربح والتفاصيل
                                    </Link>

                                    {product.is_in_stock && (
                                        <Link
                                            href={`/partners/app/orders/create?product_id=${product.id}`}
                                            className="w-full py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold text-center flex items-center justify-center gap-1 shadow-sm shadow-indigo-600/30 transition-colors"
                                        >
                                            <PlusCircle className="w-3.5 h-3.5" />
                                            <span>طلب سريع</span>
                                        </Link>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
