<script setup lang="ts">
import { computed } from 'vue'
import { usePosStore } from '@tenant/stores/pos'
import { Gift, Minus, Plus } from 'lucide-vue-next'

const store = usePosStore()

const pointName = computed(() => store.loyaltyPreview?.program?.point_name ?? 'Puntos')
const balance = computed(() => store.loyaltyPreview?.card?.balance ?? 0)
const toEarn = computed(() => store.loyaltyPreview?.points_to_earn ?? 0)
const redeem = computed(() => store.loyaltyPreview?.redeem ?? null)
const maxPoints = computed(() => redeem.value?.max_points ?? 0)

const decrement = () => {
    if (store.loyaltyRedeemPoints > 0) {
        store.setRedeemPoints(store.loyaltyRedeemPoints - 1)
    }
}

const increment = () => {
    if (store.loyaltyRedeemPoints < maxPoints.value) {
        store.setRedeemPoints(store.loyaltyRedeemPoints + 1)
    }
}

const onInput = (event: Event) => {
    const target = event.target as HTMLInputElement
    store.setRedeemPoints(parseInt(target.value, 10) || 0)
}
</script>

<template>
    <div
        v-if="store.loyaltyPreview?.enabled && store.loyaltyPreview?.program"
        class="px-3 pb-2"
    >
        <div class="rounded-md border border-border bg-muted/30 p-2.5 space-y-2">
            <!-- Header -->
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 text-xs font-semibold">
                    <Gift class="h-3.5 w-3.5 text-primary" />
                    <span>{{ store.loyaltyPreview.program.name }}</span>
                </div>
                <span
                    v-if="store.loyaltyPreview.card"
                    class="text-[10px] text-muted-foreground tabular-nums"
                >
                    Saldo: {{ balance.toFixed(2) }} {{ pointName }}
                </span>
            </div>

            <!-- Ganar -->
            <div
                v-if="toEarn > 0"
                class="flex justify-between text-xs text-muted-foreground"
            >
                <span>Ganará</span>
                <span class="font-medium text-foreground tabular-nums">
                    +{{ toEarn.toFixed(2) }} {{ pointName }}
                </span>
            </div>

            <!-- Canjear -->
            <div v-if="redeem && maxPoints > 0" class="space-y-1.5">
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="h-8 w-8 rounded-md border border-input hover:bg-accent flex items-center justify-center shrink-0 disabled:opacity-40"
                        :disabled="store.loyaltyRedeemPoints <= 0"
                        @click="decrement"
                    >
                        <Minus class="h-3.5 w-3.5" />
                    </button>
                    <input
                        :value="store.loyaltyRedeemPoints"
                        type="number"
                        min="0"
                        :max="maxPoints"
                        step="1"
                        class="flex-1 h-8 px-2 bg-background border border-input rounded-md text-sm text-center focus:outline-none focus:ring-2 focus:ring-ring tabular-nums"
                        @input="onInput"
                    />
                    <button
                        type="button"
                        class="h-8 w-8 rounded-md border border-input hover:bg-accent flex items-center justify-center shrink-0 disabled:opacity-40"
                        :disabled="store.loyaltyRedeemPoints >= maxPoints"
                        @click="increment"
                    >
                        <Plus class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <!-- No card yet -->
            <div
                v-if="!store.loyaltyPreview.card"
                class="text-[11px] text-muted-foreground italic"
            >
                Este cliente aún no tiene tarjeta. Se creará al completar la venta.
            </div>
        </div>
    </div>
</template>
