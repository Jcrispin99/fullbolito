import type { CartLine } from '@tenant/types/pos'

export interface LineAmounts {
    subtotal: number
    taxAmount: number
    total: number
}

export const lineCalc = (line: CartLine): LineAmounts => {
    const gross = line.quantity * line.price
    const rate = line.taxRate / 100

    if (rate === 0) {
        return { subtotal: gross, taxAmount: 0, total: gross }
    }

    if (line.pricesIncludeTax) {
        const subtotal = gross / (1 + rate)
        const taxAmount = gross - subtotal
        return { subtotal, taxAmount, total: gross }
    }

    const taxAmount = gross * rate
    return { subtotal: gross, taxAmount, total: gross + taxAmount }
}

export const sumCart = (cart: CartLine[]) =>
    cart.reduce(
        (acc, line) => {
            const amounts = lineCalc(line)
            acc.subtotal += amounts.subtotal
            acc.taxAmount += amounts.taxAmount
            acc.total += amounts.total
            return acc
        },
        { subtotal: 0, taxAmount: 0, total: 0 },
    )

export const round2 = (value: number) => Math.round(value * 100) / 100
