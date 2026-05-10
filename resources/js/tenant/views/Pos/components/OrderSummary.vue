<script setup lang="ts">
import { usePosStore } from '@tenant/stores/pos'

const store = usePosStore()

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(amount)
</script>

<template>
    <div class="space-y-1.5 text-sm px-3">
        <div class="flex justify-between text-muted-foreground">
            <span>Subtotal</span>
            <span class="tabular-nums">{{ formatCurrency(store.cartSubtotal) }}</span>
        </div>
        <div v-if="store.cartTaxAmount > 0" class="flex justify-between text-muted-foreground">
            <span>IGV (18%)</span>
            <span class="tabular-nums">{{ formatCurrency(store.cartTaxAmount) }}</span>
        </div>
        <div
            v-if="store.loyaltyDiscount > 0"
            class="flex justify-between text-green-600 dark:text-green-500"
        >
            <span>Canje de puntos</span>
            <span class="tabular-nums">−{{ formatCurrency(store.loyaltyDiscount) }}</span>
        </div>
        <div class="flex justify-between font-bold text-base border-t border-border pt-2 mt-1">
            <span>Total</span>
            <span class="tabular-nums">{{ formatCurrency(store.cartFinalTotal) }}</span>
        </div>
    </div>
</template>
