export interface PosProduct {
    id: number
    display_name: string
    sku: string
    barcode: string
    price: number
    cost_price: number
    stock: number
    category_id: number
    category_name: string
    image_url: string | null
    uom_id: number
    uom_name: string
    is_pos_visible: boolean
    tracks_inventory: boolean
    is_tracked_by_lot?: boolean
}

export interface PosCategory {
    id: number
    name: string
    parent_id: number | null
}

export interface PosCustomer {
    id: number
    name: string
    display_name: string
    document_number: string | null
    email: string | null
}

export interface PosPaymentMethod {
    id: number
    name: string
    is_active: boolean
}

export interface CartLine {
    product: PosProduct
    quantity: number
    price: number
    taxRate: number
    pricesIncludeTax: boolean
    lot_id?: number | null
    lot_label?: string | null
}

export interface PaymentLine {
    payment_method_id: number
    payment_method_name: string
    amount: number
}

export interface PosJournal {
    id: number
    name: string
    code?: string | null
    document_type: string
    is_default: boolean
}

export interface PosConfigState {
    id: number
    name: string
    warehouse_id: number | null
    warehouse_name?: string | null
    company_name?: string | null
    default_customer_id: number | null
    apply_tax: boolean
    prices_include_tax: boolean
    is_active: boolean
    tax_id?: number | null
    tax_rate?: number
    journals: PosJournal[]
    default_lot_strategy?:
        | 'fefo_auto'
        | 'fefo_suggest_manual'
        | 'manual'
        | null
    lot_scan_mode?: 'product_only' | 'hybrid' | null
    allow_expired_sale_with_override?: boolean
    auto_print_receipt?: boolean
}

export interface PosSessionPayment {
    id: number
    payment_method_id: number
    payment_method_name: string
    amount: number
}

export interface PosSession {
    id: number
    pos_config_id: number
    user_id?: number | null
    user_name: string
    opening_balance: number
    closing_balance?: number | null
    status: 'opening_control' | 'opened' | 'closing_control' | 'closed'
    opened_at: string
    closed_at?: string | null
    payments?: PosSessionPayment[]
}

export interface LoyaltyPreview {
    enabled: boolean
    program: { id: number; name: string; point_name: string } | null
    card: { id: number; code: string; balance: number } | null
    points_to_earn: number
    redeem: { rate: number; max_points: number; max_amount: number } | null
}

export interface ReceiptPayload {
    sale: {
        serie: string | null
        correlative: string | null
        date: string | null
        subtotal: number
        tax_amount: number
        total: number
        journal: any
        partner: any
        seller: any
        products: Array<{
            quantity: number
            price: number
            subtotal: number
            total: number
            product: any
            uom: any
        }>
        payments: Array<{ method_name: string; amount: number }>
        loyalty_transactions: any[]
    }
    company: {
        business_name: string | null
        trade_name: string | null
        ruc: string | null
        address: string | null
        phone: string | null
        email: string | null
        logo_url: string | null
    }
}
