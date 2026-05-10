<script setup lang="ts">
import { usePosStore } from '@tenant/stores/pos'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { ShoppingCart, Trash2, CreditCard } from 'lucide-vue-next'
import CartLine from './CartLine.vue'
import OrderSummary from './OrderSummary.vue'
import CustomerSelect from './CustomerSelect.vue'
import PosLoyaltyBox from './PosLoyaltyBox.vue'

const store = usePosStore()

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(amount)
</script>

<template>
    <div class="flex flex-col h-full bg-card border-r border-border">
        <!-- Header -->
        <div class="flex items-center justify-between px-3 py-3 border-b border-border">
            <div class="flex items-center gap-2">
                <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm font-semibold">Orden</span>
                <span
                    v-if="store.cartItemCount > 0"
                    class="text-xs bg-primary text-primary-foreground rounded-full px-1.5 py-0.5 leading-none"
                >
                    {{ store.cartItemCount }}
                </span>
            </div>
            <Button
                v-if="store.cart.length > 0"
                variant="ghost"
                size="icon-sm"
                class="h-7 w-7 text-muted-foreground hover:text-destructive"
                @click="store.clearCart()"
            >
                <Trash2 class="h-3.5 w-3.5" />
            </Button>
        </div>

        <!-- Customer -->
        <div class="px-3 py-2 border-b border-border">
            <Label class="text-xs text-muted-foreground mb-1 block">Cliente</Label>
            <CustomerSelect />
        </div>

        <!-- Cart Lines -->
        <div class="flex-1 overflow-y-auto">
            <div v-if="store.cart.length === 0" class="flex flex-col items-center justify-center h-full text-muted-foreground">
                <ShoppingCart class="h-8 w-8 mb-2 opacity-30" />
                <span class="text-sm">Carrito vacío</span>
            </div>

            <div v-else class="py-1">
                <CartLine
                    v-for="(line, index) in store.cart"
                    :key="line.product.id"
                    :line="line"
                    :index="index"
                />
            </div>
        </div>

        <!-- Totals + Loyalty + Pay Button -->
        <div class="border-t border-border pt-3 pb-3 space-y-3">
            <OrderSummary />

            <PosLoyaltyBox />

            <div class="px-3">
                <Button
                    class="w-full h-12 text-base font-semibold gap-2"
                    :disabled="store.cart.length === 0"
                    @click="store.goToPayment()"
                >
                    <CreditCard class="h-5 w-5" />
                    Pagar {{ store.cart.length > 0 ? formatCurrency(store.cartFinalTotal) : '' }}
                </Button>
            </div>
        </div>
    </div>
</template>
