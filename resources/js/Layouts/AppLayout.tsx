import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import type { AppSharedProps } from '../types/affiliate';
import {
    Home,
    ShoppingBag,
    PlusCircle,
    ClipboardList,
    Wallet,
    Link2,
    BarChart3,
    User,
    Award,
    Copy,
    Check,
    AlertTriangle,
    CheckCircle2,
    XCircle,
    ChevronDown,
} from 'lucide-react';

interface Props {
    title?: string;
    children: React.ReactNode;
}

export default function AppLayout({ title, children }: Props) {
    const { auth, tenant, flash, url } = usePage().props as AppSharedProps;
    const affiliate = auth?.affiliate;
    const [copied, setCopied] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);

    const copyAffiliateCode = () => {
        if (!affiliate?.code) return;
        navigator.clipboard.writeText(affiliate.code);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const currentPath = typeof window !== 'undefined' ? window.location.pathname : (url ?? '');

    const navItems = [
        { name: 'الرئيسية', href: '/partners/app', icon: Home, exact: true },
        { name: 'الكتالوج', href: '/partners/app/catalog', icon: ShoppingBag },
        { name: 'طلب سريع', href: '/partners/app/orders/create', icon: PlusCircle, isAction: true },
        { name: 'الطلبات', href: '/partners/app/orders', icon: ClipboardList },
        { name: 'المحفظة', href: '/partners/app/wallet', icon: Wallet },
    ];

    const isCurrent = (item: typeof navItems[0]) => {
        if (item.exact) {
            return currentPath === item.href || currentPath === `${item.href}/`;
        }
        return currentPath.startsWith(item.href);
    };

    return (
        <div dir="rtl" className="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans selection:bg-indigo-500 selection:text-white">
            {/* Top Bar */}
            <header className="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-slate-800">
                <div className="max-w-md mx-auto px-4 h-14 flex items-center justify-between">
                    <div className="flex items-center space-x-2 space-x-reverse">
                        <Link href="/partners/app" className="flex items-center gap-2">
                            <span className="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center font-black text-white text-sm shadow-md shadow-indigo-500/20">
                                {tenant?.name?.[0] || 'A'}
                            </span>
                            <span className="font-bold text-sm tracking-tight text-white line-clamp-1 max-w-[120px]">
                                {tenant?.name || 'تطبيق المسوق'}
                            </span>
                        </Link>
                    </div>

                    <div className="flex items-center gap-2">
                        {affiliate?.code && (
                            <button
                                onClick={copyAffiliateCode}
                                className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-800 border border-slate-700 hover:border-slate-600 text-xs text-slate-300 transition-colors"
                                title="نسخ كود الأفلييت"
                            >
                                <span className="font-mono text-indigo-400 font-semibold">{affiliate.code}</span>
                                {copied ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5 text-slate-400" />}
                            </button>
                        )}

                        <div className="relative">
                            <button
                                onClick={() => setMenuOpen(!menuOpen)}
                                className="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300 hover:text-white"
                            >
                                <User className="w-4 h-4" />
                            </button>

                            {menuOpen && (
                                <>
                                    <div className="fixed inset-0 z-40" onClick={() => setMenuOpen(false)} />
                                    <div className="absolute left-0 mt-2 w-48 rounded-xl bg-slate-900 border border-slate-800 shadow-xl py-1.5 z-50 text-sm">
                                        <div className="px-3 py-1.5 border-b border-slate-800 text-xs text-slate-400">
                                            {affiliate?.name || 'المسوق'}
                                        </div>
                                        <Link
                                            href="/partners/app/levels"
                                            onClick={() => setMenuOpen(false)}
                                            className="flex items-center gap-2 px-3 py-2 text-slate-300 hover:bg-slate-800"
                                        >
                                            <Award className="w-4 h-4 text-amber-400" />
                                            <span>المستويات والمكافآت</span>
                                        </Link>
                                        <Link
                                            href="/partners/app/links"
                                            onClick={() => setMenuOpen(false)}
                                            className="flex items-center gap-2 px-3 py-2 text-slate-300 hover:bg-slate-800"
                                        >
                                            <Link2 className="w-4 h-4 text-indigo-400" />
                                            <span>الروابط و QR</span>
                                        </Link>
                                        <Link
                                            href="/partners/app/analytics"
                                            onClick={() => setMenuOpen(false)}
                                            className="flex items-center gap-2 px-3 py-2 text-slate-300 hover:bg-slate-800"
                                        >
                                            <BarChart3 className="w-4 h-4 text-emerald-400" />
                                            <span>التحليلات والمبيعات</span>
                                        </Link>
                                        <Link
                                            href="/partners/app/profile"
                                            onClick={() => setMenuOpen(false)}
                                            className="flex items-center gap-2 px-3 py-2 text-slate-300 hover:bg-slate-800"
                                        >
                                            <User className="w-4 h-4 text-amber-400" />
                                            <span>الملف الشخصي و KYC</span>
                                        </Link>
                                        <div className="border-t border-slate-800 my-1" />
                                        <Link
                                            href="/partners/logout"
                                            method="post"
                                            as="button"
                                            className="w-full text-right flex items-center gap-2 px-3 py-2 text-rose-400 hover:bg-slate-800"
                                        >
                                            <span>تسجيل الخروج</span>
                                        </Link>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </header>

            {/* Flash Messages */}
            <div className="max-w-md mx-auto w-full px-4 pt-3 space-y-2">
                {flash?.success && (
                    <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs flex items-center gap-2">
                        <CheckCircle2 className="w-4 h-4 shrink-0" />
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-center gap-2">
                        <XCircle className="w-4 h-4 shrink-0" />
                        <span>{flash.error}</span>
                    </div>
                )}
            </div>

            {/* Main Content Area */}
            <main className="max-w-md mx-auto w-full px-4 py-4 flex-1">
                {children}
            </main>

            {/* Bottom Mobile Navigation (Sticky 5 Tabs) */}
            <nav className="fixed bottom-0 inset-x-0 z-40 bg-slate-900/95 backdrop-blur-md border-t border-slate-800 shadow-2xl">
                <div className="max-w-md mx-auto h-16 px-2 flex items-center justify-around">
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        const active = isCurrent(item);

                        if (item.isAction) {
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="flex flex-col items-center justify-center -mt-6 group focus:outline-none"
                                >
                                    <div className="w-13 h-13 p-3 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 text-white shadow-lg shadow-indigo-500/40 group-active:scale-95 transition-transform flex items-center justify-center">
                                        <Icon className="w-6 h-6 stroke-[2.2]" />
                                    </div>
                                    <span className="text-[10px] font-bold text-indigo-400 mt-1">
                                        {item.name}
                                    </span>
                                </Link>
                            );
                        }

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`flex flex-col items-center justify-center w-14 py-1 transition-colors ${
                                    active ? 'text-indigo-400' : 'text-slate-400 hover:text-slate-200'
                                }`}
                            >
                                <Icon className={`w-5 h-5 ${active ? 'stroke-[2.4]' : 'stroke-[1.8]'}`} />
                                <span className={`text-[11px] mt-1 ${active ? 'font-bold' : 'font-normal'}`}>
                                    {item.name}
                                </span>
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}
