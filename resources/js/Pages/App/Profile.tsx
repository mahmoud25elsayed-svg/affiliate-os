import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import {
    User,
    Shield,
    CreditCard,
    Lock,
    Upload,
    CheckCircle2,
    AlertCircle,
    XCircle,
    Clock,
    Phone,
    Mail,
    LogOut,
    KeyRound,
} from 'lucide-react';
import { AffiliateUser } from '../../types/affiliate';

interface Props {
    affiliate: AffiliateUser;
    settings: {
        min_payout_minor: number;
        payout_days?: string[];
    };
}

export default function Profile({ affiliate, settings }: Props) {
    const [payoutOtpRequested, setPayoutOtpRequested] = useState(false);

    // Profile Info Form
    const profileForm = useForm({
        name: affiliate.name,
        email: affiliate.email || '',
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
    });

    // KYC Form
    const kycForm = useForm<{
        national_id_number: string;
        id_card_front: File | null;
        id_card_back: File | null;
    }>({
        national_id_number: affiliate.national_id_number || '',
        id_card_front: null,
        id_card_back: null,
    });

    // Payout Settings Form
    const payoutForm = useForm({
        payout_method: affiliate.payout_method || 'wallet_phone',
        wallet_provider: affiliate.payout_details?.wallet_provider || 'vodafone_cash',
        wallet_phone: affiliate.payout_details?.phone || affiliate.phone,
        instapay_handle: affiliate.payout_details?.instapay_handle || '',
        bank_name: affiliate.payout_details?.bank_name || '',
        account_holder_name: affiliate.payout_details?.account_holder_name || affiliate.name,
        iban: affiliate.payout_details?.iban || '',
        swift_code: affiliate.payout_details?.swift_code || '',
        otp_code: '',
    });

    const handleProfileSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        profileForm.post('/partners/app/profile');
    };

    const handleKycSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        kycForm.post('/partners/app/profile/kyc', {
            forceFormData: true,
        });
    };

    const handleRequestPayoutOtp = () => {
        router.post('/partners/app/profile/payout-otp', {}, {
            onSuccess: () => setPayoutOtpRequested(true),
        });
    };

    const handlePayoutSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        payoutForm.post('/partners/app/profile/payout-settings');
    };

    const getKycBadge = () => {
        switch (affiliate.kyc_status) {
            case 'approved':
                return { text: 'موثق ومعتمد', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20', icon: CheckCircle2 };
            case 'pending':
                return { text: 'قيد المراجعة', color: 'bg-amber-500/10 text-amber-400 border-amber-500/20', icon: Clock };
            case 'rejected':
                return { text: 'مرفوض', color: 'bg-rose-500/10 text-rose-400 border-rose-500/20', icon: XCircle };
            default:
                return { text: 'غير موثق', color: 'bg-slate-700 text-slate-300 border-slate-600', icon: AlertCircle };
        }
    };

    const kycBadge = getKycBadge();
    const KycIcon = kycBadge.icon;

    return (
        <AppLayout title="الملف الشخصي و KYC">
            <Head title="الملف الشخصي - تطبيق المسوق" />

            <div className="space-y-4">
                {/* Header */}
                <div>
                    <h1 className="text-base font-bold text-white flex items-center gap-1.5">
                        <User className="w-5 h-5 text-indigo-400" />
                        <span>الملف الشخصي وإعدادات الأمان</span>
                    </h1>
                    <p className="text-xs text-slate-400">
                        بيانات حسابك، توثيق الهوية، وطرق استلام الأرباح.
                    </p>
                </div>

                {/* Profile Overview Card */}
                <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white font-black text-lg flex items-center justify-center shadow-lg shadow-indigo-500/20">
                                {affiliate.name[0]}
                            </div>
                            <div>
                                <h2 className="text-sm font-bold text-white">{affiliate.name}</h2>
                                <span className="font-mono text-xs text-indigo-400 font-semibold">
                                    كود المسوق: {affiliate.code}
                                </span>
                            </div>
                        </div>

                        <div className={`px-2.5 py-1 rounded-full text-[11px] font-bold border flex items-center gap-1 ${kycBadge.color}`}>
                            <KycIcon className="w-3.5 h-3.5" />
                            <span>{kycBadge.text}</span>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2 text-xs text-slate-400 pt-2 border-t border-slate-800">
                        <div className="flex items-center gap-1.5">
                            <Phone className="w-3.5 h-3.5 text-slate-500" />
                            <span dir="ltr">{affiliate.phone}</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <Mail className="w-3.5 h-3.5 text-slate-500" />
                            <span className="truncate">{affiliate.email || 'لا يوجد بريد'}</span>
                        </div>
                    </div>
                </div>

                {/* KYC Section */}
                <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="text-xs font-bold text-white flex items-center gap-1.5">
                            <Shield className="w-4 h-4 text-emerald-400" />
                            <span>توثيق الهوية (KYC)</span>
                        </h3>
                        <span className={`text-[10px] px-2 py-0.5 rounded-full border ${kycBadge.color}`}>
                            {kycBadge.text}
                        </span>
                    </div>

                    {affiliate.kyc_status === 'approved' ? (
                        <div className="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs flex items-center gap-2">
                            <CheckCircle2 className="w-4 h-4 shrink-0" />
                            <span>تم توثيق هويتك بنجاح وبإمكانك سحب أرباحك في أي وقت.</span>
                        </div>
                    ) : affiliate.kyc_status === 'pending' ? (
                        <div className="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs flex items-center gap-2">
                            <Clock className="w-4 h-4 shrink-0" />
                            <span>وثائق الهوية قيد المراجعة والتدقيق من إدارة المتجر.</span>
                        </div>
                    ) : (
                        <form onSubmit={handleKycSubmit} className="space-y-3 pt-1">
                            {affiliate.kyc_rejection_reason && (
                                <div className="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs">
                                    سبب رفض الوثائق السابقة: {affiliate.kyc_rejection_reason}
                                </div>
                            )}

                            <div>
                                <label className="block text-[11px] text-slate-400 mb-1">الرقم القومي (14 رقم)</label>
                                <input
                                    type="text"
                                    required
                                    dir="ltr"
                                    maxLength={20}
                                    value={kycForm.data.national_id_number}
                                    onChange={(e) => kycForm.setData('national_id_number', e.target.value)}
                                    placeholder="29012345678901"
                                    className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs font-mono text-white focus:outline-none focus:border-indigo-500"
                                />
                                {kycForm.errors.national_id_number && (
                                    <p className="text-[10px] text-rose-400 mt-1">{kycForm.errors.national_id_number}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">صورة وجه البطاقة</label>
                                    <input
                                        type="file"
                                        required
                                        accept="image/*"
                                        onChange={(e) => kycForm.setData('id_card_front', e.target.files?.[0] || null)}
                                        className="w-full text-[10px] text-slate-400 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:bg-slate-800 file:text-slate-200"
                                    />
                                    {kycForm.errors.id_card_front && (
                                        <p className="text-[10px] text-rose-400 mt-1">{kycForm.errors.id_card_front}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">صورة ظهر البطاقة</label>
                                    <input
                                        type="file"
                                        required
                                        accept="image/*"
                                        onChange={(e) => kycForm.setData('id_card_back', e.target.files?.[0] || null)}
                                        className="w-full text-[10px] text-slate-400 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:bg-slate-800 file:text-slate-200"
                                    />
                                    {kycForm.errors.id_card_back && (
                                        <p className="text-[10px] text-rose-400 mt-1">{kycForm.errors.id_card_back}</p>
                                    )}
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={kycForm.processing}
                                className="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 disabled:opacity-50 transition-colors"
                            >
                                {kycForm.processing ? 'جاري الرفع...' : 'رفع وثائق الهوية للتوثيق'}
                            </button>
                        </form>
                    )}
                </div>

                {/* Payout Settings Section */}
                <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="text-xs font-bold text-white flex items-center gap-1.5">
                            <CreditCard className="w-4 h-4 text-indigo-400" />
                            <span>بيانات استلام الأرباح</span>
                        </h3>
                    </div>

                    <form onSubmit={handlePayoutSubmit} className="space-y-3">
                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">طريقة الاستلام</label>
                            <select
                                value={payoutForm.data.payout_method}
                                onChange={(e) => payoutForm.setData('payout_method', e.target.value as NonNullable<AffiliateUser['payout_method']>)}
                                className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                            >
                                <option value="wallet_phone">محفظة كاش (فودافون كاش / أورنج / وي)</option>
                                <option value="instapay">إنستاباي (InstaPay)</option>
                                <option value="bank_account">تحويل بنكي مباشر</option>
                            </select>
                        </div>

                        {payoutForm.data.payout_method === 'wallet_phone' && (
                            <div className="space-y-2">
                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">رقم هاتف المحفظة</label>
                                    <input
                                        type="tel"
                                        dir="ltr"
                                        value={payoutForm.data.wallet_phone}
                                        onChange={(e) => payoutForm.setData('wallet_phone', e.target.value)}
                                        className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs font-mono text-white focus:outline-none focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                        )}

                        {payoutForm.data.payout_method === 'instapay' && (
                            <div>
                                <label className="block text-[11px] text-slate-400 mb-1">معرف إنستاباي (IPA Handle)</label>
                                <input
                                    type="text"
                                    dir="ltr"
                                    placeholder="username@instapay"
                                    value={payoutForm.data.instapay_handle}
                                    onChange={(e) => payoutForm.setData('instapay_handle', e.target.value)}
                                    className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs font-mono text-white focus:outline-none focus:border-indigo-500"
                                />
                            </div>
                        )}

                        {payoutForm.data.payout_method === 'bank_account' && (
                            <div className="space-y-2">
                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">اسم البنك</label>
                                    <input
                                        type="text"
                                        value={payoutForm.data.bank_name}
                                        onChange={(e) => payoutForm.setData('bank_name', e.target.value)}
                                        className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-[11px] text-slate-400 mb-1">رقم الآيبان (IBAN)</label>
                                    <input
                                        type="text"
                                        dir="ltr"
                                        value={payoutForm.data.iban}
                                        onChange={(e) => payoutForm.setData('iban', e.target.value)}
                                        className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs font-mono text-white focus:outline-none focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                        )}

                        {/* OTP Verification for Security */}
                        <div className="p-3 rounded-2xl bg-slate-800/60 border border-slate-700/60 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-[11px] text-slate-300 font-semibold">تأكيد التغيير برمز OTP</span>
                                <button
                                    type="button"
                                    onClick={handleRequestPayoutOtp}
                                    className="text-[11px] text-indigo-400 hover:underline font-bold"
                                >
                                    {payoutOtpRequested ? 'إعادة إرسال الرمز' : 'إرسال الرمز لهاتفي'}
                                </button>
                            </div>

                            <input
                                type="text"
                                required
                                maxLength={6}
                                dir="ltr"
                                placeholder="000000"
                                value={payoutForm.data.otp_code}
                                onChange={(e) => payoutForm.setData('otp_code', e.target.value)}
                                className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-center font-mono text-sm tracking-widest text-white focus:outline-none focus:border-indigo-500"
                            />
                            {payoutForm.errors.otp_code && (
                                <p className="text-[10px] text-rose-400">{payoutForm.errors.otp_code}</p>
                            )}

                            <p className="text-[10px] text-slate-400">
                                تنبيه أمني: عند تغيير بيانات السحب، سيتم تجميد السحب مؤقتاً لمدة 24 ساعة لحماية أرباحك.
                            </p>
                        </div>

                        <button
                            type="submit"
                            disabled={payoutForm.processing}
                            className="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-600/20 disabled:opacity-50 transition-colors"
                        >
                            {payoutForm.processing ? 'جاري الحفظ...' : 'حفظ بيانات استلام الأرباح'}
                        </button>
                    </form>
                </div>

                {/* Profile Edit & Password Change */}
                <div className="p-4 rounded-3xl bg-slate-900 border border-slate-800 space-y-3">
                    <h3 className="text-xs font-bold text-white flex items-center gap-1.5">
                        <KeyRound className="w-4 h-4 text-indigo-400" />
                        <span>البيانات الأساسية وتغيير كلمة المرور</span>
                    </h3>

                    <form onSubmit={handleProfileSubmit} className="space-y-3">
                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">الاسم الكامل</label>
                            <input
                                type="text"
                                required
                                value={profileForm.data.name}
                                onChange={(e) => profileForm.setData('name', e.target.value)}
                                className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        <div>
                            <label className="block text-[11px] text-slate-400 mb-1">البريد الإلكتروني</label>
                            <input
                                type="email"
                                value={profileForm.data.email}
                                onChange={(e) => profileForm.setData('email', e.target.value)}
                                className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        <div className="pt-2 border-t border-slate-800 space-y-2">
                            <span className="text-[11px] text-slate-400 block font-semibold">تغيير كلمة المرور (اختياري)</span>

                            <div>
                                <input
                                    type="password"
                                    placeholder="كلمة المرور الحالية"
                                    value={profileForm.data.current_password}
                                    onChange={(e) => profileForm.setData('current_password', e.target.value)}
                                    className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                                />
                                {profileForm.errors.current_password && (
                                    <p className="text-[10px] text-rose-400 mt-1">{profileForm.errors.current_password}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <input
                                    type="password"
                                    placeholder="كلمة المرور الجديدة"
                                    value={profileForm.data.new_password}
                                    onChange={(e) => profileForm.setData('new_password', e.target.value)}
                                    className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                                />
                                <input
                                    type="password"
                                    placeholder="تأكيد كلمة المرور"
                                    value={profileForm.data.new_password_confirmation}
                                    onChange={(e) => profileForm.setData('new_password_confirmation', e.target.value)}
                                    className="w-full h-10 px-3 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white focus:outline-none focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={profileForm.processing}
                            className="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition-colors"
                        >
                            {profileForm.processing ? 'جاري التحديث...' : 'حفظ التعديلات'}
                        </button>
                    </form>
                </div>

                {/* Logout Button */}
                <div className="pt-2">
                    <Link
                        href="/partners/logout"
                        method="post"
                        as="button"
                        className="w-full py-3 rounded-2xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-bold flex items-center justify-center gap-1.5 transition-colors"
                    >
                        <LogOut className="w-4 h-4" />
                        <span>تسجيل الخروج من الحساب</span>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
