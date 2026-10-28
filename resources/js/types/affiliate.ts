export interface AffiliateUser {
    id: number;
    name: string;
    phone: string;
    email?: string | null;
    code: string;
    status: string;
    status_reason?: string | null;
    kyc_status: 'unverified' | 'pending' | 'approved' | 'rejected';
    kyc_rejection_reason?: string | null;
    national_id_number?: string | null;
    payout_method?: 'wallet_phone' | 'instapay' | 'bank_account' | null;
    payout_details?: Record<string, any> | null;
    can_withdraw?: boolean;
    is_frozen?: boolean;
    frozen_until?: string | null;
    created_at?: string;
}

export interface WalletBalances {
    available: number;
    pending: number;
    locked: number;
    withdrawn: number;
    debt: number;
    has_debt: boolean;
}

export interface ProgramSettings {
    min_payout_minor: number;
    min_selling_markup_minor?: number;
    max_selling_markup_minor?: number;
    return_window_days?: number;
    daily_limit?: number;
    payout_days?: string[];
}

export interface ProductItem {
    id: number;
    name: string;
    sku: string;
    wholesale_minor: number;
    retail_minor: number;
    suggested_markup_minor: number;
    stock: number;
    is_in_stock: boolean;
    image_url: string;
    delivery_success_rate: number;
}

export interface OrderListItem {
    id: number;
    reference: string;
    status: string;
    customer_city: string;
    customer_name?: string;
    customer_phone?: string;
    items_count?: number;
    profit_minor: number;
    total_minor: number;
    created_at: string;
}

export interface LedgerMovement {
    id: number;
    account_name: string;
    description: string;
    amount_minor: number;
    created_at: string;
}

export interface PayoutRequestItem {
    id: number;
    amount_minor: number;
    status: string;
    payment_method: string;
    reference_number?: string | null;
    rejection_reason?: string | null;
    created_at: string;
}

export interface TrackingLinkItem {
    id: number;
    code: string;
    title: string;
    product_id?: number | null;
    product_name: string;
    custom_price_minor?: number | null;
    clicks_count: number;
    orders_count: number;
    conversion_rate: number;
    is_active: boolean;
    target_url: string;
    qr_url: string;
    created_at: string;
}
