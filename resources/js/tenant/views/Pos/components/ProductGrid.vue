<script setup lang="ts">
import { ref } from 'vue'
import { usePosStore } from '@tenant/stores/pos'
import { Package, MoreVertical, Boxes } from 'lucide-vue-next'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { PosProduct } from '@tenant/types/pos'

const store = usePosStore()

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(amount)

const handleClick = (product: PosProduct) => {
    store.addToCart(product)
}

const openProductId = ref<number | null>(null)
const loadingProductId = ref<number | null>(null)

const onLotMenuToggle = async (product: PosProduct, val: boolean) => {
    openProductId.value = val ? product.id : null
    if (!val) return
    if (store.getLotsForProduct(product.id).length > 0) return
    loadingProductId.value = product.id
    try {
        await store.fetchLotsForProduct(product.id)
    } finally {
        loadingProductId.value = null
    }
}

const pickLot = (product: PosProduct, lotId: number) => {
    store.addToCart(product, { lotId })
    openProductId.value = null
}
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 p-2">
        <div
            v-for="product in store.filteredProducts"
            :key="product.id"
            role="button"
            tabindex="0"
            class="relative flex flex-col items-center justify-center gap-1.5 p-3 rounded-lg border border-input bg-card text-card-foreground hover:bg-accent hover:text-accent-foreground transition-colors cursor-pointer select-none active:scale-[0.97] min-h-[90px]"
            @click="handleClick(product)"
            @keydown.enter.prevent="handleClick(product)"
            @keydown.space.prevent="handleClick(product)"
        >
            <!-- Lot kebab: indicator + selector for tracked-by-lot products -->
            <DropdownMenu
                v-if="product.is_tracked_by_lot"
                :open="openProductId === product.id"
                @update:open="(val: boolean) => onLotMenuToggle(product, val)"
            >
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="absolute top-1 right-1 h-6 w-6 inline-flex items-center justify-center rounded hover:bg-black/10 dark:hover:bg-white/10 transition-colors text-muted-foreground"
                        title="Producto con trazabilidad por lote"
                        aria-label="Seleccionar lote"
                        @click.stop
                    >
                        <MoreVertical class="h-3.5 w-3.5" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    class="w-72 p-0"
                    @click.stop
                >
                    <div class="border-b px-3 py-2 flex items-center gap-2">
                        <Boxes class="h-3.5 w-3.5 text-muted-foreground" />
                        <span class="text-xs font-semibold">Lotes disponibles</span>
                    </div>
                    <div
                        v-if="loadingProductId === product.id"
                        class="px-3 py-4 text-xs text-muted-foreground text-center"
                    >
                        Cargando…
                    </div>
                    <div
                        v-else-if="store.getLotsForProduct(product.id).length === 0"
                        class="px-3 py-4 text-xs text-muted-foreground text-center"
                    >
                        Sin lotes disponibles
                    </div>
                    <ul v-else class="max-h-64 overflow-y-auto divide-y">
                        <li
                            v-for="lot in store.getLotsForProduct(product.id)"
                            :key="lot.id"
                        >
                            <button
                                type="button"
                                class="w-full text-left px-3 py-2 hover:bg-muted/60 transition-colors"
                                @click="pickLot(product, lot.id)"
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

            <div
                v-if="product.image_url"
                class="w-10 h-10 rounded-md overflow-hidden bg-muted shrink-0"
            >
                <img :src="product.image_url" :alt="product.display_name" class="w-full h-full object-cover" />
            </div>
            <div
                v-else
                class="w-10 h-10 rounded-md bg-muted flex items-center justify-center shrink-0"
            >
                <Package class="h-5 w-5 text-muted-foreground" />
            </div>

            <span class="text-xs font-medium text-center leading-tight line-clamp-2">
                {{ product.display_name }}
            </span>
            <span class="text-xs font-semibold text-primary">
                {{ formatCurrency(product.price) }}
            </span>
        </div>

        <div
            v-if="store.filteredProducts.length === 0"
            class="col-span-full flex flex-col items-center justify-center py-12 text-muted-foreground"
        >
            <Package class="h-10 w-10 mb-2 opacity-40" />
            <span class="text-sm">No se encontraron productos</span>
        </div>
    </div>
</template>
