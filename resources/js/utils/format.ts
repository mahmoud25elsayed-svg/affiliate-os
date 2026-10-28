/**
 * Formats minor currency units (piastres / cents) to formatted EGP string.
 */
export function formatCurrency(minor: number = 0): string {
    const pounds = (minor / 100).toFixed(2);
    // Remove trailing .00 for clean look
    const clean = pounds.endsWith('.00') ? pounds.slice(0, -3) : pounds;
    return `${clean} ج.م`;
}

/**
 * Returns Arabic human label for Order Status.
 */
export function getOrderStatusLabel(status: string): { label: string; color: string } {
    switch (status) {
        case 'pending':
            return { label: 'قيد الانتظار', color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' };
        case 'quality_hold':
            return { label: 'فحص الجودة', color: 'bg-purple-500/10 text-purple-400 border-purple-500/20' };
        case 'confirmed':
            return { label: 'تم التأكيد', color: 'bg-blue-500/10 text-blue-400 border-blue-500/20' };
        case 'processing':
            return { label: 'جاري التجهيز', color: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' };
        case 'shipped':
            return { label: 'تم الشحن', color: 'bg-sky-500/10 text-sky-400 border-sky-500/20' };
        case 'out_for_delivery':
            return { label: 'خرج للتوصيل', color: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' };
        case 'delivered':
            return { label: 'تم التسليم', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' };
        case 'cancelled_by_customer':
            return { label: 'ملغي من العميل', color: 'bg-rose-500/10 text-rose-400 border-rose-500/20' };
        case 'cancelled_no_answer':
            return { label: 'ملغي (لا رد)', color: 'bg-rose-500/10 text-rose-400 border-rose-500/20' };
        case 'return_requested':
            return { label: 'طلب استرجاع', color: 'bg-orange-500/10 text-orange-400 border-orange-500/20' };
        case 'returning':
            return { label: 'جاري الاسترجاع', color: 'bg-orange-500/10 text-orange-400 border-orange-500/20' };
        case 'returned':
            return { label: 'مرتجع', color: 'bg-red-500/10 text-red-400 border-red-500/20' };
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
            return { label: 'قيد المراجعة', color: 'bg-amber-500/10 text-amber-400 border-amber-500/20' };
        case 'approved':
            return { label: 'تمت الموافقة', color: 'bg-blue-500/10 text-blue-400 border-blue-500/20' };
        case 'processing':
            return { label: 'جاري التحويل', color: 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' };
        case 'paid':
            return { label: 'تم التحويل', color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' };
        case 'rejected':
            return { label: 'مرفوض', color: 'bg-rose-500/10 text-rose-400 border-rose-500/20' };
        case 'cancelled':
            return { label: 'ملغي', color: 'bg-slate-500/10 text-slate-400 border-slate-500/20' };
        default:
            return { label: status, color: 'bg-slate-700/50 text-slate-300 border-slate-600' };
    }
}
