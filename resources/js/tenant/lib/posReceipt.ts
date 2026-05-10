import type { ReceiptPayload } from '@tenant/types/pos'

export const buildReceiptPayload = (
    saleData: any,
    paymentsSnapshot: Array<{ method_name: string; amount: number }>,
): ReceiptPayload => ({
    sale: {
        serie: saleData.serie ?? null,
        correlative: saleData.correlative ?? null,
        date: saleData.date ?? saleData.created_at ?? null,
        subtotal: Number(saleData.subtotal ?? 0),
        tax_amount: Number(saleData.tax_amount ?? 0),
        total: Number(saleData.total ?? 0),
        journal: saleData.journal ?? null,
        partner: saleData.partner ?? null,
        seller: saleData.seller ?? saleData.user ?? null,
        products: (saleData.products ?? []).map((p: any) => ({
            quantity: Number(p.quantity ?? 0),
            price: Number(p.price ?? 0),
            subtotal: Number(p.subtotal ?? 0),
            total: Number(p.total ?? p.subtotal ?? 0),
            product: p.product ?? null,
            uom: p.uom ?? null,
        })),
        payments: paymentsSnapshot,
        loyalty_transactions: saleData.loyalty_transactions ?? [],
    },
    company: {
        business_name: saleData.company?.business_name ?? null,
        trade_name: saleData.company?.trade_name ?? null,
        ruc: saleData.company?.ruc ?? null,
        address: saleData.company?.address ?? null,
        phone: saleData.company?.phone ?? null,
        email: saleData.company?.email ?? null,
        logo_url: null,
    },
})
