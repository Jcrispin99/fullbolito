import type { ReceiptCompany, ReceiptSale } from '@tenant/components/ReceiptRenderer.vue'

export function makeMockReceiptSale(): ReceiptSale {
    return {
        serie: 'B001',
        correlative: '00001234',
        date: new Date().toISOString().replace('T', ' ').slice(0, 19),
        subtotal: 25.42,
        tax_amount: 4.58,
        total: 30.0,
        journal: { document_type_code: '03', name: 'BOLETA' },
        partner: {
            document_type: 'DNI',
            document_number: '12345678',
            name: 'Juan Pérez García',
            address: null,
        },
        seller: { name: 'Cajero demo' },
        products: [
            {
                quantity: 2,
                price: 5.0,
                subtotal: 10.0,
                total: 10.0,
                product: { name: 'Café americano' },
                uom: { symbol: 'u' },
            },
            {
                quantity: 1,
                price: 12.5,
                subtotal: 12.5,
                total: 12.5,
                product: { name: 'Sandwich de pollo' },
                uom: { symbol: 'u' },
            },
            {
                quantity: 3,
                price: 2.5,
                subtotal: 7.5,
                total: 7.5,
                product: { name: 'Galleta integral' },
                uom: { symbol: 'u' },
            },
        ],
        payments: [
            { method_name: 'Efectivo', amount: 30.0 },
        ],
        loyalty_transactions: [
            { type: 'earn', points: 30, point_name: 'pts' },
        ],
        qr_data_url: null,
    }
}

export function makeMockReceiptCompany(): ReceiptCompany {
    return {
        business_name: 'EMPRESA DEMO S.A.C.',
        trade_name: 'Demo Store',
        ruc: '20123456789',
        address: 'Av. Principal 123, Lima',
        phone: '999-888-777',
        email: 'hola@demo.pe',
        logo_url: null,
    }
}
