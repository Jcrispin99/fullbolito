<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { usePosStore } from '@tenant/stores/pos'
import type { CartLine } from '@tenant/types/pos'
import { Button } from '@/components/ui/button'
import { SearchSelect } from '@/components/ui/search-select'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Minus, Plus, Trash2, Boxes, MoreVertical } from 'lucide-vue-next'

const props = defineProps<{
    line: CartLine
    index: number
}>()

const store = usePosStore()

const lineTotal = computed(() => store.lineCalc(props.line).total)

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(amount)

const isTrackedByLot = computed(() => !!props.line.product.is_tracked_by_lot)

const lotStrategy = computed(
    () => store.currentConfig?.default_lot_strategy ?? null,
)

// fefo_auto → lock selector; otherwise allow manual change
const lotSelectorDisabled = computed(() => lotStrategy.value === 'fefo_auto')

// Show selector when manual or fefo_suggest_manual; hide when fefo_auto (still show chip).
const showLotSelector = computed(
    () => isTrackedByLot.value && lotStrategy.value !== 'fefo_auto',
)

const lotsLoading = ref(false)

const lotOptions = computed(() => {
    const lots = store.getLotsForProduct(props.line.product.id)
    const options = lots.map((l) => {
        const exp = l.expires_at ? ` · vence ${l.expires_at}` : ''
        const stk = l.total_stock !== null ? ` · stock ${l.total_stock}` : ''
        return { value: l.id, label: `${l.lot_number}${exp}${stk}` }
    })
    if (props.line.lot_id && !options.some((o) => o.value === props.line.lot_id)) {
        options.unshift({
            value: props.line.lot_id,
            label: props.line.lot_label || `Lote #${props.line.lot_id}`,
        })
    }
    return options
})

onMounted(async () => {
    if (!isTrackedByLot.value) return
    lotsLoading.value = true
    try {
        await store.fetchLotsForProduct(props.line.product.id)
    } finally {
        lotsLoading.value = false
    }
})

const onLotChange = (value: string | number | boolean | null | undefined) => {
    const id = value === null || value === undefined || value === '' ? null : Number(value)
    store.setLineLot(props.index, id)
}

const isLotMenuOpen = ref(false)

const onLotMenuToggle = async (val: boolean) => {
    isLotMenuOpen.value = val
    if (!val || !isTrackedByLot.value) return
    if (store.getLotsForProduct(props.line.product.id).length > 0) return
    lotsLoading.value = true
    try {
        await store.fetchLotsForProduct(props.line.product.id)
    } finally {
        lotsLoading.value = false
    }
}

const pickLotFromMenu = (lotId: number) => {
    store.setLineLot(props.index, lotId)
    isLotMenuOpen.value = false
}
</script>

<template>
    <div class="flex flex-col gap-1 px-3 py-2 group hover:bg-muted/50 transition-colors rounded-md">
        <div class="flex items-center gap-2">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate flex items-center gap-1">
                    {{ line.product.display_name }}
                    <Boxes
                        v-if="isTrackedByLot"
                        class="h-3 w-3 text-muted-foreground"
                        title="Producto con trazabilidad por lote"
                    />
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ formatCurrency(line.price) }} c/u
                </p>
            </div>

            <div class="flex items-center gap-1 shrink-0">
                <Button
                    variant="outline"
                    size="icon-sm"
                    class="h-7 w-7"
                    @click="store.updateQuantity(index, line.quantity - 1)"
                >
                    <Minus class="h-3 w-3" />
                </Button>

                <span class="w-8 text-center text-sm font-semibold tabular-nums">
                    {{ line.quantity }}
                </span>

                <Button
                    variant="outline"
                    size="icon-sm"
                    class="h-7 w-7"
                    @click="store.updateQuantity(index, line.quantity + 1)"
                >
                    <Plus class="h-3 w-3" />
                </Button>
            </div>

            <span class="w-20 text-right text-sm font-semibold tabular-nums shrink-0">
                {{ formatCurrency(lineTotal) }}
            </span>

            <DropdownMenu
                v-if="isTrackedByLot"
                :open="isLotMenuOpen"
                @update:open="onLotMenuToggle"
            >
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="h-7 w-7 inline-flex items-center justify-center rounded text-muted-foreground hover:bg-black/10 dark:hover:bg-white/10 transition-colors shrink-0"
                        title="Cambiar lote"
                        aria-label="Cambiar lote"
                    >
                        <MoreVertical class="h-3.5 w-3.5" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-72 p-0">
                    <div class="border-b px-3 py-2 flex items-center gap-2">
                        <Boxes class="h-3.5 w-3.5 text-muted-foreground" />
                        <span class="text-xs font-semibold">Cambiar lote</span>
                    </div>
                    <div
                        v-if="lotsLoading"
                        class="px-3 py-4 text-xs text-muted-foreground text-center"
                    >
                        Cargando…
                    </div>
                    <div
                        v-else-if="store.getLotsForProduct(line.product.id).length === 0"
                        class="px-3 py-4 text-xs text-muted-foreground text-center"
                    >
                        Sin lotes disponibles
                    </div>
                    <ul v-else class="max-h-64 overflow-y-auto divide-y">
                        <li
                            v-for="lot in store.getLotsForProduct(line.product.id)"
                            :key="lot.id"
                        >
                            <button
                                type="button"
                                class="w-full text-left px-3 py-2 hover:bg-muted/60 transition-colors"
                                :class="{ 'bg-accent': lot.id === line.lot_id }"
                                @click="pickLotFromMenu(lot.id)"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-mono truncate">
                                        {{ lot.lot_number }}
                                    </span>
                                    <span
                                        v-if="lot.total_stock !== null"
                                        class="text-[11px] font-medium tabular-nums shrink-0"
                                    >
                                        stock {{ lot.total_stock }}
                                    </span>
                                </div>
                                <p
                                    v-if="lot.expires_at"
                                    class="text-[11px] text-muted-foreground mt-0.5"
                                >
                                    vence {{ lot.expires_at }}
                                </p>
                            </button>
                        </li>
                    </ul>
                </DropdownMenuContent>
            </DropdownMenu>

            <Button
                variant="ghost"
                size="icon-sm"
                class="h-7 w-7 text-muted-foreground hover:text-destructive opacity-0 group-hover:opacity-100 transition-opacity shrink-0"
                @click="store.removeLine(index)"
            >
                <Trash2 class="h-3.5 w-3.5" />
            </Button>
        </div>

        <!-- Lot row: selector or read-only chip -->
        <div
            v-if="isTrackedByLot"
            class="flex items-center gap-2 pl-1"
        >
            <span class="text-[10px] uppercase tracking-wider text-muted-foreground shrink-0">
                Lote
            </span>
            <div v-if="showLotSelector" class="flex-1 min-w-0">
                <SearchSelect
                    :model-value="line.lot_id ?? undefined"
                    :options="lotOptions"
                    :show-create="false"
                    :disabled="lotSelectorDisabled || lotsLoading"
                    clear-on-empty
                    placeholder="Auto (FEFO)"
                    @update:modelValue="onLotChange"
                />
            </div>
            <span
                v-else
                class="text-xs text-muted-foreground truncate"
            >
                {{ line.lot_label || (lotsLoading ? 'Cargando…' : 'Auto (FEFO)') }}
            </span>
        </div>
    </div>
</template>
