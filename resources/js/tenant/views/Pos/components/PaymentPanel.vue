<script setup lang="ts">
import { computed } from 'vue'
import { usePosStore } from '@tenant/stores/pos'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { CheckCircle, Plus, Trash2, Banknote, CreditCard, Smartphone } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const store = usePosStore()

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(amount)

const journalOptions = computed(() => store.currentConfig?.journals ?? [])

const availablePaymentMethods = computed(() =>
    store.paymentMethods.filter((m) => m.is_active),
)

const paymentIcon = (name: string) => {
    const lower = name.toLowerCase()
    if (lower.includes('efectivo') || lower.includes('cash')) return Banknote
    if (lower.includes('tarjeta') || lower.includes('card')) return CreditCard
    return Smartphone
}

const handleConfirm = async () => {
    const success = await store.checkout()
    if (!success) return

    if (store.currentConfig?.auto_print_receipt) {
        toast.success('Venta registrada correctamente')
        return
    }

    toast.success('Venta registrada correctamente', {
        action: {
            label: 'Imprimir',
            onClick: () => {
                store.printLastReceipt().catch(() =>
                    toast.error('No se pudo imprimir el comprobante'),
                )
            },
        },
        duration: 8000,
    })
}
</script>

<template>
    <div class="flex flex-col h-full p-4 space-y-5 overflow-y-auto">
        <!-- Journal -->
        <div class="space-y-1.5">
            <Label class="text-sm font-medium">Comprobante</Label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="journal in journalOptions"
                    :key="journal.id"
                    :class="[
                        'px-3 py-2 rounded-md text-sm font-medium border transition-colors',
                        store.selectedJournal?.id === journal.id
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input bg-background hover:bg-accent hover:text-accent-foreground',
                    ]"
                    @click="store.setJournal(journal)"
                >
                    {{ journal.name }}
                </button>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="space-y-1.5">
            <Label class="text-sm font-medium">Método de pago</Label>

            <div
                v-for="(payment, index) in store.payments"
                :key="index"
                class="flex items-center gap-2 mb-2"
            >
                <div class="flex items-center gap-2 min-w-[120px]">
                    <component :is="paymentIcon(payment.payment_method_name)" class="h-4 w-4 text-muted-foreground shrink-0" />
                    <span class="text-sm font-medium truncate">{{ payment.payment_method_name }}</span>
                </div>

                <div class="relative flex-1">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">S/</span>
                    <input
                        :value="payment.amount"
                        type="number"
                        step="0.01"
                        min="0"
                        class="w-full h-10 pl-8 pr-3 bg-background border border-input rounded-md text-sm text-right focus:outline-none focus:ring-2 focus:ring-ring tabular-nums"
                        @input="store.updatePaymentAmount(index, parseFloat(($event.target as HTMLInputElement).value) || 0)"
                    />
                </div>

                <Button
                    variant="ghost"
                    size="icon-sm"
                    class="h-8 w-8 text-muted-foreground hover:text-destructive shrink-0"
                    @click="store.removePayment(index)"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </Button>
            </div>

            <!-- Add payment method buttons -->
            <div class="flex flex-wrap gap-1.5 pt-1">
                <button
                    v-for="method in availablePaymentMethods"
                    :key="method.id"
                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium border border-dashed border-input hover:bg-accent hover:text-accent-foreground transition-colors"
                    @click="store.addPayment(method.id)"
                >
                    <Plus class="h-3 w-3" />
                    {{ method.name }}
                </button>
            </div>
        </div>

        <!-- Totals -->
        <div class="space-y-2 border-t border-border pt-4 mt-auto">
            <div
                v-if="store.loyaltyDiscount > 0"
                class="flex justify-between text-sm text-green-600 dark:text-green-500"
            >
                <span>Canje de puntos</span>
                <span class="tabular-nums">−{{ formatCurrency(store.loyaltyDiscount) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">Total a cobrar</span>
                <span class="font-semibold tabular-nums">{{ formatCurrency(store.cartFinalTotal) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-muted-foreground">Total pagado</span>
                <span class="font-semibold tabular-nums">{{ formatCurrency(store.totalPaid) }}</span>
            </div>
            <div
                v-if="store.changeAmount > 0"
                class="flex justify-between text-sm text-green-600"
            >
                <span>Cambio</span>
                <span class="font-semibold tabular-nums">{{ formatCurrency(store.changeAmount) }}</span>
            </div>
        </div>

        <!-- Confirm -->
        <Button
            class="w-full h-12 text-base font-semibold gap-2"
            :disabled="!store.canConfirmPayment || store.isLoading"
            @click="handleConfirm"
        >
            <CheckCircle class="h-5 w-5" />
            {{ store.isLoading ? 'Procesando...' : 'Confirmar Venta' }}
            <kbd class="hidden md:inline text-[10px] font-mono px-1 py-0.5 rounded border border-primary-foreground/40 bg-primary-foreground/10">F4</kbd>
        </Button>
    </div>
</template>
