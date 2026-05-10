import type {
    PosCategory,
    PosConfigState,
    PosCustomer,
    PosPaymentMethod,
    PosProduct,
    PosSession,
} from '@tenant/types/pos'

export const mapApiConfig = (config: any): PosConfigState => ({
    id: config.id,
    name: config.name,
    warehouse_id: config.warehouse_id ?? config.warehouse?.id ?? null,
    warehouse_name: config.warehouse?.name ?? null,
    company_name: config.company?.name ?? null,
    default_customer_id:
        config.default_customer_id ?? config.default_customer?.id ?? null,
    apply_tax: Boolean(config.apply_tax),
    prices_include_tax: Boolean(config.prices_include_tax),
    is_active: Boolean(config.is_active),
    tax_id: config.tax_id ?? config.tax?.id ?? null,
    tax_rate: Number(config.tax?.rate_percent ?? 0),
    journals: Array.isArray(config.journals)
        ? config.journals
              .filter((journal: any) => journal.document_type !== 'credit_note')
              .map((journal: any) => ({
                  id: journal.id,
                  name: journal.name,
                  code: journal.code ?? null,
                  document_type: journal.document_type,
                  is_default: Boolean(journal.is_default),
              }))
        : [],
    default_lot_strategy: config.default_lot_strategy ?? null,
    lot_scan_mode: config.lot_scan_mode ?? null,
    allow_expired_sale_with_override: Boolean(
        config.allow_expired_sale_with_override,
    ),
    auto_print_receipt: Boolean(config.auto_print_receipt),
})

export const mapApiSession = (session: any): PosSession => ({
    id: session.id,
    pos_config_id: session.pos_config_id,
    user_id: session.user_id ?? null,
    user_name: session.user?.name ?? 'Caja',
    opening_balance: Number(session.opening_balance ?? 0),
    closing_balance:
        session.closing_balance !== null && session.closing_balance !== undefined
            ? Number(session.closing_balance)
            : null,
    status: session.status,
    opened_at: session.opened_at,
    closed_at: session.closed_at ?? null,
    payments: Array.isArray(session.payments)
        ? session.payments.map((payment: any) => ({
              id: payment.id,
              payment_method_id: payment.payment_method_id,
              payment_method_name:
                  payment.payment_method?.name ??
                  `Metodo #${payment.payment_method_id}`,
              amount: Number(payment.amount ?? 0),
          }))
        : [],
})

export const mapApiCategory = (category: any): PosCategory => ({
    id: category.id,
    name: category.name,
    parent_id: category.parent_id ?? null,
})

export const mapApiCustomer = (customer: any): PosCustomer => ({
    id: customer.id,
    name: customer.name,
    display_name: customer.display_name ?? customer.name,
    document_number: customer.document_number ?? null,
    email: customer.email ?? null,
})

export const mapApiProduct = (product: any): PosProduct => ({
    id: product.id,
    display_name: product.display_name,
    sku: product.sku ?? '',
    barcode: product.barcode ?? '',
    price: Number(product.price ?? 0),
    cost_price: Number(product.cost_price ?? 0),
    stock: Number(product.stock ?? 0),
    category_id: Number(product.category_id ?? 0),
    category_name: product.category_name ?? 'Sin categoria',
    image_url: product.image_url ?? null,
    uom_id: Number(product.uom_id ?? 0),
    uom_name: product.uom_name ?? '',
    is_pos_visible: Boolean(product.is_pos_visible ?? true),
    tracks_inventory: Boolean(product.tracks_inventory ?? true),
    is_tracked_by_lot: Boolean(product.is_tracked_by_lot ?? false),
})

export const mapApiPaymentMethod = (method: any): PosPaymentMethod => ({
    id: method.id,
    name: method.name,
    is_active: Boolean(method.is_active ?? true),
})
