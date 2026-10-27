import React from 'react';
import { Head } from '@inertiajs/react';
import { ShoppingBag, Users, ShieldCheck, Zap, ArrowLeft, Store, BarChart3, Database } from 'lucide-react';

interface WelcomeProps {
    appName: string;
    version: string;
    locale: string;
    direction: string;
}

export default function Welcome({ appName, version, locale, direction }: WelcomeProps) {
    const isRtl = direction === 'rtl' || locale === 'ar';

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 selection:bg-indigo-500 selection:text-white" dir={isRtl ? 'rtl' : 'ltr'}>
            <Head title="المنصة المتكاملة للتجارة الإلكترونية والتسويق بالعمولة" />

            {/* Header */}
            <header className="border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md sticky top-0 z-50">
                <div className="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                            <ShoppingBag className="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <span className="text-xl font-bold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white via-slate-200 to-indigo-200">
                                {appName}
                            </span>
                            <span className="mr-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                {version}
                            </span>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <a
                            href="/lunar"
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm transition-all duration-200 shadow-lg shadow-indigo-600/25"
                        >
                            <span>لوحة التحكم (Lunar Admin)</span>
                            <ArrowLeft className={`w-4 h-4 ${isRtl ? '' : 'rotate-180'}`} />
                        </a>
                    </div>
                </div>
            </header>

            {/* Hero Section */}
            <main className="max-w-7xl mx-auto px-6 pt-16 pb-24">
                <div className="text-center max-w-3xl mx-auto">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-950/60 border border-indigo-800/40 text-indigo-300 text-xs font-semibold mb-6">
                        <Zap className="w-3.5 h-3.5 text-indigo-400" />
                        <span>منصة SaaS متعددة المستأجرين معزولة بالكامل</span>
                    </div>

                    <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-[1.2]">
                        متجر إلكتروني متكامل + نظام أفلييت متطور لكل تاجر
                    </h1>

                    <p className="mt-6 text-lg text-slate-400 leading-relaxed">
                        بنية تحتية نظيفة مبنية على <span className="text-indigo-400 font-semibold">Laravel 12</span> و{' '}
                        <span className="text-indigo-400 font-semibold">Lunar</span> و{' '}
                        <span className="text-indigo-400 font-semibold">stancl/tenancy</span> مع عزل تام لقواعد البيانات ودفتر قيد مزدوج صارم.
                    </p>
                </div>

                {/* Features Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-16">
                    <div className="p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-indigo-500/40 transition-colors duration-200">
                        <div className="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 mb-4">
                            <Store className="w-6 h-6" />
                        </div>
                        <h3 className="text-lg font-bold text-white mb-2">متجر لكل تاجر (Multi-Tenant)</h3>
                        <p className="text-sm text-slate-400 leading-relaxed">
                            قاعدة بيانات مستقلة بالكامل لكل تاجر، مع كتالوج منتجات متقدم، وإدارة متغيرات، وسلة تسوق مخصصة.
                        </p>
                    </div>

                    <div className="p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-violet-500/40 transition-colors duration-200">
                        <div className="w-12 h-12 rounded-xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-violet-400 mb-4">
                            <Users className="w-6 h-6" />
                        </div>
                        <h3 className="text-lg font-bold text-white mb-2">شبكة المسوقين والعمولات</h3>
                        <p className="text-sm text-slate-400 leading-relaxed">
                            برنامج أفلييت متكامل داخل المتجر بروابط تتبع مخصصة، واحتساب آلي للعمولات عند التسليم الفعلي.
                        </p>
                    </div>

                    <div className="p-6 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-emerald-500/40 transition-colors duration-200">
                        <div className="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 mb-4">
                            <ShieldCheck className="w-6 h-6" />
                        </div>
                        <h3 className="text-lg font-bold text-white mb-2">دفتر مالي بقيد مزدوج</h3>
                        <p className="text-sm text-slate-400 leading-relaxed">
                            دقة مالية متناهية: كل معاملة تُحسب مرتين، تعامل صارم بالأعداد الصحيحة (القروش)، ولا رصيد دون قيد مالي مبرر.
                        </p>
                    </div>
                </div>

                {/* Metrics / Status Banner */}
                <div className="mt-12 p-6 rounded-2xl bg-gradient-to-r from-indigo-950/40 via-slate-900/40 to-slate-950/40 border border-indigo-900/30 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Database className="w-5 h-5 text-indigo-400" />
                        <span className="text-sm text-slate-300 font-medium">
                            بيئة معمارية جاهزة: Filament v4 + Pest Arch + Larastan Level 8 + Horizon
                        </span>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-slate-400">
                        <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>جميع الاختبارات المعمارية والوظيفية خضراء</span>
                    </div>
                </div>
            </main>

            {/* Footer */}
            <footer className="border-t border-slate-900 py-8 text-center text-xs text-slate-500">
                <p>© 2026 {appName}. كود نظيف 100% (Clean-room Architecture). مرخص بـ MIT.</p>
            </footer>
        </div>
    );
}
