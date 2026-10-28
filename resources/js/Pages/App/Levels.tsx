import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { formatCurrency } from '../../utils/format';
import { 
    Award, 
    TrendingUp, 
    ShieldCheck, 
    Users, 
    Copy, 
    Check, 
    Share2, 
    Lock, 
    Unlock, 
    Sparkles, 
    ChevronRight,
    AlertCircle,
    Star
} from 'lucide-react';

interface LevelItem {
    id: number;
    name: string;
    slug: string;
    tier_order: number;
    badge_color: string;
    min_delivered_orders: number;
    min_delivery_rate: number;
    min_quality_score: number;
    bonus_per_order_minor: number;
    commission_bonus_percent: number;
    daily_order_limit: number;
    is_current: boolean;
    is_unlocked: boolean;
}

interface NextLevelData {
    id: number;
    name: string;
    badge_color: string;
    remaining_orders: number;
    target_delivered_orders: number;
    target_delivery_rate: number;
    target_quality_score: number;
    bonus_per_order_minor: number;
    commission_bonus_percent: number;
    daily_order_limit: number;
}

interface ReferralItem {
    id: number;
    name: string;
    code: string;
    joined_at: string;
    delivered_orders: number;
    target_orders: number;
    is_unlocked: boolean;
    reward_earned_minor: number;
}

interface Props {
    currentLevel: {
        id: number;
        name: string;
        slug: string;
        badge_color: string;
        tier_order: number;
        bonus_per_order_minor: number;
        commission_bonus_percent: number;
        daily_order_limit: number;
    };
    nextLevel: NextLevelData | null;
    allLevels: LevelItem[];
    stats: {
        quality_score: number;
        rating: string;
        total_delivered_orders: number;
        delivery_rate_pct: number;
        reasons: {
            delivery_component?: number;
            gate_component?: number;
            volume_component?: number;
            delivery_rate_pct?: number;
            notice?: string;
        };
    };
    referralProgram: {
        is_enabled: boolean;
        referral_code: string;
        invite_url: string;
        threshold_orders: number;
        reward_amount_minor: number;
        total_earnings_minor: number;
        referrals_count: number;
        referrals: ReferralItem[];
    };
}

export default function Levels({ currentLevel, nextLevel, allLevels, stats, referralProgram }: Props) {
    const [copiedCode, setCopiedCode] = useState(false);
    const [copiedLink, setCopiedLink] = useState(false);
    const [activeTab, setActiveTab] = useState<'levels' | 'quality' | 'referrals'>('levels');

    const handleCopyCode = () => {
        navigator.clipboard.writeText(referralProgram.referral_code);
        setCopiedCode(true);
        setTimeout(() => setCopiedCode(false), 2000);
    };

    const handleCopyLink = () => {
        navigator.clipboard.writeText(referralProgram.invite_url);
        setCopiedLink(true);
        setTimeout(() => setCopiedLink(false), 2000);
    };

    const handleShareLink = () => {
        if (navigator.share) {
            navigator.share({
                title: 'انضم لبرنامج المسوقين وشارك الأرباح',
                text: `سجل في برنامج التسويق بالعمولة باستخدام كودي: ${referralProgram.referral_code}`,
                url: referralProgram.invite_url,
            }).catch(() => {});
        } else {
            handleCopyLink();
        }
    };

    return (
        <AppLayout title="مستواي ومكافآتي">
            <Head title="المستويات ودرجة الجودة والإحالات" />

            <div className="p-4 space-y-5">
                {/* Current Level Hero Card */}
                <div 
                    className="rounded-2xl p-5 text-white shadow-lg relative overflow-hidden"
                    style={{
                        background: `linear-gradient(135deg, ${currentLevel.badge_color} 0%, #1e293b 100%)`
                    }}
                >
                    <div className="absolute top-0 left-0 w-32 h-32 bg-white/10 rounded-full blur-2xl -translate-x-12 -translate-y-12"></div>
                    <div className="relative z-10">
                        <div className="flex items-center justify-between mb-3">
                            <div className="flex items-center gap-2">
                                <Award className="w-6 h-6 text-amber-300" />
                                <span className="text-xs font-semibold bg-white/20 backdrop-blur-sm px-2.5 py-1 rounded-full">
                                    المستوى الحالي
                                </span>
                            </div>
                            <div className="flex items-center gap-1.5 bg-emerald-500/20 text-emerald-300 text-xs px-2.5 py-1 rounded-full border border-emerald-400/30">
                                <ShieldCheck className="w-3.5 h-3.5" />
                                <span>جودة: {stats.quality_score}/100 ({stats.rating})</span>
                            </div>
                        </div>

                        <h2 className="text-2xl font-black mb-1">المستوى {currentLevel.name}</h2>
                        <p className="text-xs text-white/80 mb-4">
                            تم تسليم {stats.total_delivered_orders} طلب بنجاح بنسبة تسليم {stats.delivery_rate_pct}%
                        </p>

                        <div className="grid grid-cols-2 gap-2 pt-2 border-t border-white/20 text-xs">
                            <div className="bg-black/20 rounded-xl p-2.5">
                                <span className="text-white/70 block mb-0.5">بونص إضافي لكل طلب:</span>
                                <span className="text-base font-bold text-amber-300">
                                    {currentLevel.bonus_per_order_minor > 0 
                                        ? `+${formatCurrency(currentLevel.bonus_per_order_minor)}` 
                                        : (currentLevel.commission_bonus_percent > 0 ? `+${currentLevel.commission_bonus_percent}%` : 'لا يوجد')}
                                </span>
                            </div>
                            <div className="bg-black/20 rounded-xl p-2.5">
                                <span className="text-white/70 block mb-0.5">الحد اليومي المتاح:</span>
                                <span className="text-base font-bold text-white">
                                    {currentLevel.daily_order_limit} طلب/يوم
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Next Level Progression Card */}
                {nextLevel && (
                    <div className="bg-white rounded-2xl p-4 border border-indigo-100 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <div className="flex items-center gap-2">
                                <Sparkles className="w-5 h-5 text-indigo-600" />
                                <span className="text-sm font-bold text-slate-800">
                                    الترقية إلى المستوى {nextLevel.name}
                                </span>
                            </div>
                            <span className="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-full">
                                متبقي {nextLevel.remaining_orders} طلب
                            </span>
                        </div>

                        {/* Progress bar */}
                        <div className="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden mb-3">
                            <div 
                                className="bg-gradient-to-r from-indigo-500 to-indigo-600 h-full rounded-full transition-all duration-500"
                                style={{
                                    width: `${Math.min(100, Math.round((stats.total_delivered_orders / Math.max(1, nextLevel.target_delivered_orders)) * 100))}%`
                                }}
                            />
                        </div>

                        <div className="grid grid-cols-3 gap-2 text-center text-xs">
                            <div className="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                <span className="text-slate-500 block text-[10px]">الطلبات المطلوبة</span>
                                <span className="font-bold text-slate-800">{stats.total_delivered_orders} / {nextLevel.target_delivered_orders}</span>
                            </div>
                            <div className="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                <span className="text-slate-500 block text-[10px]">نسبة التسليم</span>
                                <span className={`font-bold ${stats.delivery_rate_pct >= nextLevel.target_delivery_rate ? 'text-emerald-600' : 'text-slate-800'}`}>
                                    {stats.delivery_rate_pct}% / {nextLevel.target_delivery_rate}%
                                </span>
                            </div>
                            <div className="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                <span className="text-slate-500 block text-[10px]">نقاط الجودة</span>
                                <span className={`font-bold ${stats.quality_score >= nextLevel.target_quality_score ? 'text-emerald-600' : 'text-slate-800'}`}>
                                    {stats.quality_score} / {nextLevel.target_quality_score}
                                </span>
                            </div>
                        </div>

                        <div className="mt-3 p-2.5 bg-indigo-50/70 rounded-xl text-xs text-indigo-900 flex items-center justify-between">
                            <span>مميزات الترقية:</span>
                            <span className="font-bold text-indigo-700">
                                {nextLevel.bonus_per_order_minor > 0 && `+${formatCurrency(nextLevel.bonus_per_order_minor)} بونص/طلب`}
                                {` • حد ${nextLevel.daily_order_limit} طلب/يوم`}
                            </span>
                        </div>
                    </div>
                )}

                {/* Sub-navigation Tabs */}
                <div className="flex border-b border-slate-200">
                    <button
                        onClick={() => setActiveTab('levels')}
                        className={`flex-1 pb-2.5 text-xs font-bold text-center border-b-2 transition-all ${
                            activeTab === 'levels'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        خارطة المستويات
                    </button>
                    <button
                        onClick={() => setActiveTab('quality')}
                        className={`flex-1 pb-2.5 text-xs font-bold text-center border-b-2 transition-all ${
                            activeTab === 'quality'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        شفافية درجة الجودة
                    </button>
                    <button
                        onClick={() => setActiveTab('referrals')}
                        className={`flex-1 pb-2.5 text-xs font-bold text-center border-b-2 transition-all ${
                            activeTab === 'referrals'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        دعوة المسوقين
                    </button>
                </div>

                {/* TAB 1: Levels Roadmap */}
                {activeTab === 'levels' && (
                    <div className="space-y-3">
                        <div className="text-xs text-slate-600 px-1">
                            كلما ارتفع مستواك، حصلت على بونص مالي إضافي على كل طلب مسلَّم وارتفع حدك اليومي لإرسال الطلبات:
                        </div>

                        {allLevels.map((lvl) => (
                            <div 
                                key={lvl.id}
                                className={`rounded-2xl p-4 border transition-all ${
                                    lvl.is_current 
                                        ? 'border-indigo-500 bg-indigo-50/20 shadow-sm ring-1 ring-indigo-500' 
                                        : lvl.is_unlocked
                                            ? 'border-slate-200 bg-white'
                                            : 'border-slate-100 bg-slate-50/70 opacity-80'
                                }`}
                            >
                                <div className="flex items-center justify-between mb-2">
                                    <div className="flex items-center gap-2">
                                        <div 
                                            className="w-4 h-4 rounded-full" 
                                            style={{ backgroundColor: lvl.badge_color }} 
                                        />
                                        <h3 className="font-bold text-slate-900 text-sm">المستوى {lvl.name}</h3>
                                        {lvl.is_current && (
                                            <span className="bg-indigo-600 text-white text-[10px] px-2 py-0.5 rounded-full font-bold">
                                                أنت هنا
                                            </span>
                                        )}
                                    </div>
                                    <div>
                                        {lvl.is_unlocked ? (
                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                                                <Unlock className="w-3 h-3" /> مفتوح
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                                                <Lock className="w-3 h-3" /> مقفول
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-2 text-xs mb-3">
                                    <div className="bg-white p-2 rounded-xl border border-slate-100">
                                        <span className="text-slate-400 block text-[10px]">بونص الطلب المسلَّم</span>
                                        <span className="font-bold text-amber-600">
                                            {lvl.bonus_per_order_minor > 0 
                                                ? `+${formatCurrency(lvl.bonus_per_order_minor)}` 
                                                : (lvl.commission_bonus_percent > 0 ? `+${lvl.commission_bonus_percent}%` : 'لا يوجد')}
                                        </span>
                                    </div>
                                    <div className="bg-white p-2 rounded-xl border border-slate-100">
                                        <span className="text-slate-400 block text-[10px]">الحد اليومي للطلبات</span>
                                        <span className="font-bold text-slate-800">{lvl.daily_order_limit} طلب/يوم</span>
                                    </div>
                                </div>

                                <div className="text-[11px] text-slate-500 bg-slate-50 p-2 rounded-xl flex items-center justify-between">
                                    <span>شروط المستوى:</span>
                                    <span className="font-medium text-slate-700">
                                        {lvl.min_delivered_orders} طلب مسلَّم • نسبة تسليم {lvl.min_delivery_rate}% • جودة {lvl.min_quality_score}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* TAB 2: Quality Score Transparency */}
                {activeTab === 'quality' && (
                    <div className="space-y-4">
                        <div className="bg-white rounded-2xl p-4 border border-slate-200 space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="font-bold text-slate-900 text-sm">درجة الجودة الحالية</h3>
                                    <p className="text-xs text-slate-500">مقياس موثوقية الطلبات ونسبة التسليم</p>
                                </div>
                                <div className="text-left">
                                    <span className="text-3xl font-black text-indigo-600">{stats.quality_score}</span>
                                    <span className="text-xs text-slate-400"> / 100</span>
                                </div>
                            </div>

                            {/* Meter breakdown */}
                            <div className="space-y-2.5 pt-2 border-t border-slate-100 text-xs">
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-600">نسبة التسليم (70 نقطة كحد أقصى):</span>
                                    <span className="font-bold text-slate-900">
                                        {stats.reasons.delivery_component ?? 70} / 70
                                    </span>
                                </div>
                                <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div 
                                        className="bg-emerald-500 h-full rounded-full" 
                                        style={{ width: `${Math.round(((stats.reasons.delivery_component ?? 70) / 70) * 100)}%` }} 
                                    />
                                </div>

                                <div className="flex items-center justify-between pt-1">
                                    <span className="text-slate-600">فحص الجودة وعدم الإلغاء (20 نقطة كحد أقصى):</span>
                                    <span className="font-bold text-slate-900">
                                        {stats.reasons.gate_component ?? 20} / 20
                                    </span>
                                </div>
                                <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div 
                                        className="bg-indigo-500 h-full rounded-full" 
                                        style={{ width: `${Math.round(((stats.reasons.gate_component ?? 20) / 20) * 100)}%` }} 
                                    />
                                </div>

                                <div className="flex items-center justify-between pt-1">
                                    <span className="text-slate-600">حجم ونشاط الطلبات (10 نقاط كحد أقصى):</span>
                                    <span className="font-bold text-slate-900">
                                        {stats.reasons.volume_component ?? 10} / 10
                                    </span>
                                </div>
                                <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div 
                                        className="bg-amber-500 h-full rounded-full" 
                                        style={{ width: `${Math.round(((stats.reasons.volume_component ?? 10) / 10) * 100)}%` }} 
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Rules of Transparency */}
                        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-900 space-y-2">
                            <div className="flex items-center gap-1.5 font-bold">
                                <AlertCircle className="w-4 h-4 text-amber-700" />
                                <span>كيف تؤثر درجة الجودة على حسابك؟</span>
                            </div>
                            <ul className="list-disc list-inside space-y-1 text-amber-800 text-[11px] leading-relaxed">
                                <li><strong>جودة 70–100:</strong> حسابك في أفضل أداء، تستفيد من كامل الحد اليومي وبونص مستواك.</li>
                                <li><strong>جودة أقل من 40:</strong> يتم تخفيض الحد اليومي للطلبات لحماية التاجر ومنع المرتجعات.</li>
                                <li><strong>جودة أقل من 20:</strong> يتم تجميد إرسال الطلبات الجديدة مؤقتاً لحين مراجعة التاجر.</li>
                                <li>تُحسب الدرجة يومياً في منتصف الليل استناداً لآخر 30 يوماً من نشاطك.</li>
                            </ul>
                        </div>
                    </div>
                )}

                {/* TAB 3: Referral Program */}
                {activeTab === 'referrals' && (
                    <div className="space-y-4">
                        {/* Hero Invite Card */}
                        <div className="bg-gradient-to-br from-indigo-900 to-indigo-700 text-white rounded-2xl p-4 shadow-sm space-y-3">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Users className="w-5 h-5 text-indigo-300" />
                                    <h3 className="font-bold text-sm">دعوة مسوقين شركاء</h3>
                                </div>
                                <span className="bg-emerald-500/20 text-emerald-300 text-xs px-2.5 py-1 rounded-full border border-emerald-400/30 font-bold">
                                    أرباح: {formatCurrency(referralProgram.total_earnings_minor)}
                                </span>
                            </div>

                            <p className="text-xs text-indigo-100 leading-relaxed">
                                شارك كود الدعوة الخاص بك مع مسوقين آخرين. عندما يكمل أي مسوق تدعوه أول {referralProgram.threshold_orders} طلبات مسلّمة، ستحصل فوراً على مكافأة بقيمة <strong className="text-amber-300">{formatCurrency(referralProgram.reward_amount_minor)}</strong> تضاف إلى محفظتك!
                            </p>

                            <div className="bg-black/20 rounded-xl p-3 space-y-2">
                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-white/70">كود الإحالة الخاص بك:</span>
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono font-bold text-base tracking-wider text-amber-300">
                                            {referralProgram.referral_code}
                                        </span>
                                        <button 
                                            onClick={handleCopyCode}
                                            className="p-1.5 bg-white/10 hover:bg-white/20 rounded-lg text-white"
                                        >
                                            {copiedCode ? <Check className="w-4 h-4 text-emerald-300" /> : <Copy className="w-4 h-4" />}
                                        </button>
                                    </div>
                                </div>

                                <div className="pt-2 border-t border-white/10 flex gap-2">
                                    <button
                                        onClick={handleCopyLink}
                                        className="flex-1 py-2 px-3 bg-white text-indigo-900 text-xs font-bold rounded-xl flex items-center justify-center gap-1.5 shadow-sm active:scale-95 transition-all"
                                    >
                                        {copiedLink ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4" />}
                                        <span>نسخ رابط الدعوة</span>
                                    </button>
                                    <button
                                        onClick={handleShareLink}
                                        className="py-2 px-3 bg-indigo-600/80 hover:bg-indigo-600 text-white text-xs font-bold rounded-xl flex items-center justify-center gap-1.5 border border-white/20 active:scale-95 transition-all"
                                    >
                                        <Share2 className="w-4 h-4" />
                                        <span>مشاركة</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {/* Referrals List */}
                        <div className="space-y-2">
                            <h4 className="text-xs font-bold text-slate-700 px-1">
                                المسوقون المدعوون ({referralProgram.referrals_count})
                            </h4>

                            {referralProgram.referrals.length === 0 ? (
                                <div className="bg-white rounded-2xl p-6 border border-slate-200 text-center text-xs text-slate-500">
                                    <Users className="w-8 h-8 text-slate-300 mx-auto mb-2" />
                                    <p>لم تدعُ أي مسوق حتى الآن.</p>
                                    <p className="text-[11px] text-slate-400 mt-1">
                                        شارك كودك لتبدأ في جني مكافآت الإحالة مباشرة في محفظتك!
                                    </p>
                                </div>
                            ) : (
                                referralProgram.referrals.map((ref) => (
                                    <div key={ref.id} className="bg-white rounded-2xl p-3.5 border border-slate-200 flex items-center justify-between">
                                        <div>
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className="font-bold text-slate-900 text-xs">{ref.name}</span>
                                                <span className="text-[10px] text-slate-400">انضم في {ref.joined_at}</span>
                                            </div>
                                            <div className="flex items-center gap-2 text-[11px] text-slate-500">
                                                <span>الطلبات المسلّمة:</span>
                                                <span className="font-bold text-slate-800">
                                                    {ref.delivered_orders} / {ref.target_orders}
                                                </span>
                                            </div>
                                        </div>

                                        <div>
                                            {ref.is_unlocked ? (
                                                <span className="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-100">
                                                    <Check className="w-3.5 h-3.5" /> تم كسب {formatCurrency(ref.reward_earned_minor)}
                                                </span>
                                            ) : (
                                                <span className="text-[11px] font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">
                                                    متبقي {Math.max(0, ref.target_orders - ref.delivered_orders)} طلب
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
