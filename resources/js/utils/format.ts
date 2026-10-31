import { t } from '@/i18n';
export interface CurrencyInfo {
    code: string;
    symbol: string;
    decimals: number;
}

let currency: CurrencyInfo = { code: 'EGP', symbol: 'ج.م', decimals: 2 };

/** Called by app.tsx with the store currency from the shared Inertia props. */
export function setCurrency(next?: CurrencyInfo | null): void {
    if (next && next.symbol) {
        currency = next;
    }
}

/** The current store's currency symbol, e.g. "ج.م", "ر.س", "$". */
export function currencySymbol(): string {
    return currency.symbol;
}

/**
 * Formats minor units (hundredths of the store currency) with the store's symbol.
 */
export function formatCurrency(minor: number = 0): string {
    const fixed = (minor / 100).toFixed(Math.max(0, Math.min(currency.decimals, 3)));
    // Remove trailing zero fraction for a clean look
    const clean = /\.0+$/.test(fixed) ? fixed.replace(/\.0+$/, '') : fixed;
    return `${clean} ${currency.symbol}`;
}

/**
 * Returns Arabic human label for Order Status.
 */
export function getOrderStatusLabel(status: string): { label: string; color: string } {
    switch (status) {
        case 'pending':
            return { label: t('قيد الانتظار'), color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' };
        case 'quality_hold':
            return { label: t('فحص الجودة'), color: 'bg-purple-500/10 text-purple-400 border-purple-500/20' };
        case 'confirmed':
            return { label: t('تم التأكيد'), color: 'bg-blue-500/10 text-blue-400 border-blue-500/20' };
        case 'processing':
            return { label: t('جاري التجهيز'), color: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' };
        case 'shipped':
            return { label: t('تم الشحن'), color: 'bg-sky-500/10 text-sky-400 border-sky-500/20' };
        case 'out_for_delivery':
            return { label: t('خرج للتوصيل'), color: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' };
        case 'delivered':
            return { label: t('تم التسليم'), color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' };
        case 'cancelled_by_customer':
            return { label: t('ملغي من العميل'), color: 'bg-rose-500/10 text-rose-400 border-rose-500/20' };
        case 'cancelled_no_answer':
            return { label: t('ملغي (لا رد)'), color: 'bg-rose-500/10 text-rose-400 border-rose-500/20' };
        case 'return_requested':
            return { label: t('طلب استرجاع'), color: 'bg-orange-500/10 text-orange-400 border-orange-500/20' };
        case 'returning':
            return { label: t('جاري الاسترجاع'), color: 'bg-orange-500/10 text-orange-400 border-orange-500/20' };
        case 'returned':
            return { label: t('مرتجع'), color: 'bg-red-500/10 text-red-400 border-red-500/20' };
        default:
            return { label: status, color: 'bg-slate-700/50 text-slate-300 border-slate-600' };
    }
}

/**
 * Returns Arabic human label for Payout Status.
 */
export function getPayoutStatusLabel(status: string): { label: string; color: string } {
    switch (status) {
        case 'pending':
            return { label: t('قيد المراجعة'), color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' };
        case 'approved':
            return { label: t('تمت الموافقة'), color: 'bg-blue-500/10 text-blue-400 border-blue-500/20' };
        case 'processing':
            return { label: t('جاري التحويل'), color: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' };
        case 'paid':
            return { label: t('تم التحويل'), color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' };
        case 'rejected':
            return { label: t('مرفوض'), color: 'bg-rose-500/10 text-rose-400 border-rose-500/20' };
        case 'cancelled':
            return { label: t('ملغي'), color: 'bg-slate-500/10 text-slate-400 border-slate-500/20' };
        default:
            return { label: status, color: 'bg-slate-700/50 text-slate-300 border-slate-600' };
    }
}
