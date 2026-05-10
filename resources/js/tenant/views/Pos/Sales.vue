<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { usePosStore } from '@tenant/stores/pos'
import { Search, Receipt, FileText, Undo2, ArrowLeft } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { formatDateTime } from '@tenant/lib/datetime'
import PosShell from './components/PosShell.vue'

const store = usePosStore()

const searchQuery = ref('')
const selectedId = ref<number | null>(null)
let debounceTimer: ReturnType<typeof setTimeout> | null = null

type ViewMode = 'detail' | 'refund'
const viewMode = ref<ViewMode>('detail')
const refundQuantities = ref<Record<number, number>>({})
const refundNotes = ref('')
const isRefunding = ref(false)

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(amount)

const formatDate = formatDateTime

const triggerLoad = () => {
    void store.loadRecentPosSales(searchQuery.value.trim() || undefined)
}

const selectSale = async (saleId: number) => {
    if (selectedId.value === saleId) return
    selectedId.value = saleId
    try {
        await store.loadSaleDetail(saleId)
    } catch (error: any) {
        toast.error(error?.response?.data?.message || 'No se pudo cargar la venta')
    }
}

const refundLines = computed<any[]>(
    () => store.selectedSaleDetail?.refunds_summary?.lines ?? [],
)

const refundEligible = computed(() => {
    const sale = store.selectedSaleDetail
    if (!sale) return false
    if (sale.status !== 'posted') return false
    if (sale.original_sale?.id) return false
    const totals = sale.refunds_summary?.totals
    return Boolean(totals && Number(totals.available_total) > 0)
})

const refundTotal = computed(() => {
    let total = 0
    for (const l of refundLines.value) {
        const qty = Number(refundQuantities.value[l.product_product_id] ?? 0)
        if (qty <= 0 || Number(l.available_quantity) <= 0) continue
        total +=
            (qty / Number(l.available_quantity)) * Number(l.available_total)
    }
    return Math.round(total * 100) / 100
})

const isLineOverflow = (line: any) => {
    const qty = Number(refundQuantities.value[line.product_product_id] ?? 0)
    return qty > Number(line.available_quantity)
}

const hasAnyOverflow = computed(() =>
    refundLines.value.some((l) => isLineOverflow(l)),
)

const canSubmitRefund = computed(
    () =>
        !hasAnyOverflow.value &&
        refundLines.value.some(
            (l) =>
                Number(refundQuantities.value[l.product_product_id] ?? 0) > 0,
        ),
)

const startRefund = () => {
    refundQuantities.value = {}
    for (const l of refundLines.value) {
        refundQuantities.value[l.product_product_id] = 0
    }
    refundNotes.value = ''
    viewMode.value = 'refund'
}

const cancelRefund = () => {
    viewMode.value = 'detail'
    refundQuantities.value = {}
    refundNotes.value = ''
}

const updateRefundQty = (productId: number, value: string) => {
    let n = Number(value)
    if (!Number.isFinite(n) || n < 0) n = 0
    refundQuantities.value[productId] = n
}

const submitRefund = async () => {
    if (!canSubmitRefund.value || !store.selectedSaleDetail || isRefunding.value) {
        return
    }
    isRefunding.value = true
    try {
        const lines = refundLines.value
            .filter(
                (l) =>
                    Number(refundQuantities.value[l.product_product_id] ?? 0) > 0,
            )
            .map((l) => ({
                product_product_id: l.product_product_id,
                quantity: Number(refundQuantities.value[l.product_product_id]),
            }))

        await store.createPosRefund({
            original_sale_id: store.selectedSaleDetail.id,
            lines,
            notes: refundNotes.value.trim() || null,
        })

        toast.success('Nota de crédito generada')
        await store.loadSaleDetail(store.selectedSaleDetail.id)
        await store.loadRecentPosSales(searchQuery.value.trim() || undefined)
        viewMode.value = 'detail'
        refundQuantities.value = {}
        refundNotes.value = ''
    } catch (error: any) {
        toast.error(
            error?.response?.data?.message ||
                'No se pudo generar la nota de crédito',
        )
    } finally {
        isRefunding.value = false
    }
}

onMounted(() => {
    triggerLoad()
})

watch(searchQuery, () => {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(triggerLoad, 300)
})

watch(selectedId, () => {
    viewMode.value = 'detail'
    refundQuantities.value = {}
    refundNotes.value = ''
})
</script>

<template>
    <PosShell>
        <!-- Sub-header: title + search -->
        <div
            class="flex items-center justify-between gap-3 px-4 py-2 border-b border-border bg-muted/20 shrink-0"
        >
            <div class="flex items-center gap-2 min-w-0">
                <Receipt class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm font-semibold">Ventas POS</span>
                <span class="text-xs text-muted-foreground hidden md:inline">
                    Últimos 30 días — todas las cajas
                </span>
            </div>
            <div class="flex-1 max-w-md">
                <div class="relative">
                    <Search
                        class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar por serie, correlativo o cliente…"
                        class="w-full h-9 pl-9 pr-3 bg-background border border-input rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
            </div>
        </div>

        <!-- Body: list + detail -->
        <div class="flex flex-1 overflow-hidden">
            <!-- List -->
            <div
                class="w-[380px] shrink-0 border-r border-border flex flex-col bg-card"
            >
                <div
                    v-if="store.isLoadingRecentSales"
                    class="px-6 py-12 text-center text-sm text-muted-foreground"
                >
                    Cargando…
                </div>

                <div
                    v-else-if="store.recentPosSales.length === 0"
                    class="flex-1 flex flex-col items-center justify-center text-muted-foreground"
                >
                    <Receipt class="h-10 w-10 mb-2 opacity-30" />
                    <span class="text-sm">Sin ventas que coincidan</span>
                </div>

                <ul
                    v-else
                    class="flex-1 overflow-y-auto divide-y divide-border"
                >
                    <li
                        v-for="sale in store.recentPosSales"
                        :key="sale.id"
                        :class="[
                            'px-4 py-3 cursor-pointer transition-colors',
                            selectedId === sale.id
                                ? 'bg-accent'
                                : 'hover:bg-muted/40',
                        ]"
                        @click="selectSale(sale.id)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold font-mono">
                                    {{ sale.serie }}-{{ sale.correlative }}
                                </p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    {{ formatDate(sale.date || sale.created_at) }}
                                </p>
                                <p class="text-xs mt-1 truncate">
                                    {{ sale.partner?.name || sale.partner?.display_name || 'Sin cliente' }}
                                </p>
                                <p
                                    v-if="sale.pos_session?.pos_config_name"
                                    class="text-[11px] text-muted-foreground mt-0.5 truncate"
                                >
                                    {{ sale.pos_session.pos_config_name }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold tabular-nums">
                                    {{ formatCurrency(Number(sale.total ?? 0)) }}
                                </p>
                                <span
                                    v-if="sale.status === 'cancelled'"
                                    class="inline-block text-[10px] uppercase tracking-wide px-1.5 py-0.5 mt-1 rounded bg-destructive/10 text-destructive"
                                >
                                    Anulada
                                </span>
                                <span
                                    v-else-if="sale.journal?.document_type_code === '07'"
                                    class="inline-block text-[10px] uppercase tracking-wide px-1.5 py-0.5 mt-1 rounded bg-amber-500/10 text-amber-600 dark:text-amber-500"
                                >
                                    NC
                                </span>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Detail / Refund -->
            <div class="flex-1 overflow-y-auto">
                <div
                    v-if="!selectedId"
                    class="h-full flex flex-col items-center justify-center text-muted-foreground"
                >
                    <FileText class="h-12 w-12 mb-3 opacity-30" />
                    <span class="text-sm">Selecciona una venta para ver el detalle</span>
                </div>

                <div
                    v-else-if="store.isLoadingSaleDetail"
                    class="h-full flex items-center justify-center text-sm text-muted-foreground"
                >
                    Cargando detalle…
                </div>

                <!-- DETAIL VIEW -->
                <div
                    v-else-if="store.selectedSaleDetail && viewMode === 'detail'"
                    class="p-6 max-w-3xl"
                >
                    <!-- Sale header -->
                    <div class="flex items-start justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-2xl font-bold font-mono">
                                {{ store.selectedSaleDetail.serie }}-{{ store.selectedSaleDetail.correlative }}
                            </h2>
                            <p class="text-sm text-muted-foreground mt-1">
                                {{ formatDate(store.selectedSaleDetail.date || store.selectedSaleDetail.created_at) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="store.selectedSaleDetail.status === 'cancelled'"
                                class="text-xs uppercase tracking-wide px-2 py-1 rounded bg-destructive/10 text-destructive font-semibold"
                            >
                                Anulada
                            </span>
                            <span
                                v-else-if="store.selectedSaleDetail.journal?.document_type_code === '07'"
                                class="text-xs uppercase tracking-wide px-2 py-1 rounded bg-amber-500/10 text-amber-600 dark:text-amber-500 font-semibold"
                            >
                                Nota de Crédito
                            </span>
                            <Button
                                v-if="refundEligible"
                                size="sm"
                                variant="outline"
                                class="gap-1.5"
                                @click="startRefund"
                            >
                                <Undo2 class="h-3.5 w-3.5" />
                                Devolver
                            </Button>
                        </div>
                    </div>

                    <!-- Meta grid -->
                    <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground uppercase tracking-wide">Cliente</p>
                            <p class="font-medium mt-0.5">
                                {{ store.selectedSaleDetail.partner?.name || 'Sin cliente' }}
                            </p>
                            <p
                                v-if="store.selectedSaleDetail.partner?.document_number"
                                class="text-xs text-muted-foreground"
                            >
                                {{ store.selectedSaleDetail.partner.document_number }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground uppercase tracking-wide">Caja</p>
                            <p class="font-medium mt-0.5">
                                {{ store.selectedSaleDetail.pos_session?.pos_config_name || '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground uppercase tracking-wide">Vendedor</p>
                            <p class="font-medium mt-0.5">
                                {{ store.selectedSaleDetail.user?.name || store.selectedSaleDetail.seller?.name || '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground uppercase tracking-wide">Comprobante</p>
                            <p class="font-medium mt-0.5">
                                {{ store.selectedSaleDetail.journal?.name || '-' }}
                            </p>
                        </div>
                    </div>

                    <!-- Products -->
                    <div class="border border-border rounded-lg overflow-hidden mb-6">
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/40 text-xs text-muted-foreground uppercase tracking-wide"
                            >
                                <tr>
                                    <th class="text-left px-4 py-2 font-medium">Producto</th>
                                    <th class="text-right px-4 py-2 font-medium w-20">Cant.</th>
                                    <th class="text-right px-4 py-2 font-medium w-24">Precio</th>
                                    <th class="text-right px-4 py-2 font-medium w-28">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="line in store.selectedSaleDetail.products"
                                    :key="line.id"
                                >
                                    <td class="px-4 py-2">
                                        <p class="font-medium">{{ line.product?.name || '-' }}</p>
                                        <p
                                            v-if="line.product?.sku"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ line.product.sku }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums">
                                        {{ Number(line.quantity).toFixed(2) }}
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums">
                                        {{ formatCurrency(Number(line.price)) }}
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums font-medium">
                                        {{ formatCurrency(Number(line.total)) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="flex justify-end">
                        <div class="w-72 space-y-1 text-sm">
                            <div class="flex justify-between text-muted-foreground">
                                <span>Subtotal</span>
                                <span class="tabular-nums">
                                    {{ formatCurrency(Number(store.selectedSaleDetail.subtotal ?? 0)) }}
                                </span>
                            </div>
                            <div class="flex justify-between text-muted-foreground">
                                <span>IGV</span>
                                <span class="tabular-nums">
                                    {{ formatCurrency(Number(store.selectedSaleDetail.tax_amount ?? 0)) }}
                                </span>
                            </div>
                            <div
                                class="flex justify-between text-base font-semibold border-t border-border pt-2 mt-2"
                            >
                                <span>Total</span>
                                <span class="tabular-nums">
                                    {{ formatCurrency(Number(store.selectedSaleDetail.total ?? 0)) }}
                                </span>
                            </div>
                            <div
                                v-if="store.selectedSaleDetail.refunds_summary && Number(store.selectedSaleDetail.refunds_summary.totals.refunded_total) > 0"
                                class="flex justify-between text-destructive pt-2"
                            >
                                <span>Devuelto</span>
                                <span class="tabular-nums">
                                    −{{ formatCurrency(Number(store.selectedSaleDetail.refunds_summary.totals.refunded_total)) }}
                                </span>
                            </div>
                            <div
                                v-if="store.selectedSaleDetail.refunds_summary && Number(store.selectedSaleDetail.refunds_summary.totals.refunded_total) > 0"
                                class="flex justify-between text-sm font-semibold"
                            >
                                <span>Disponible</span>
                                <span class="tabular-nums">
                                    {{ formatCurrency(Number(store.selectedSaleDetail.refunds_summary.totals.available_total)) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Credit notes linked -->
                    <div
                        v-if="store.selectedSaleDetail.credit_notes && store.selectedSaleDetail.credit_notes.length > 0"
                        class="mt-6 p-3 bg-muted/30 rounded-md text-sm"
                    >
                        <p class="text-xs text-muted-foreground uppercase tracking-wide mb-2">
                            Notas de crédito vinculadas
                        </p>
                        <ul class="space-y-1">
                            <li
                                v-for="cn in store.selectedSaleDetail.credit_notes"
                                :key="cn.id"
                                class="font-mono text-xs"
                            >
                                {{ cn.document }}
                                <span class="text-muted-foreground">— {{ cn.status }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Notes -->
                    <div
                        v-if="store.selectedSaleDetail.notes"
                        class="mt-6 p-3 bg-muted/30 rounded-md text-sm"
                    >
                        <p class="text-xs text-muted-foreground uppercase tracking-wide mb-1">Notas</p>
                        <p>{{ store.selectedSaleDetail.notes }}</p>
                    </div>
                </div>

                <!-- REFUND VIEW -->
                <div
                    v-else-if="store.selectedSaleDetail && viewMode === 'refund'"
                    class="p-6 max-w-3xl"
                >
                    <div class="flex items-start justify-between gap-4 mb-6">
                        <div class="flex items-center gap-3">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="gap-1.5 -ml-2"
                                :disabled="isRefunding"
                                @click="cancelRefund"
                            >
                                <ArrowLeft class="h-4 w-4" />
                                Volver al detalle
                            </Button>
                        </div>
                    </div>

                    <h2 class="text-xl font-bold mb-1">Devolución</h2>
                    <p class="text-sm text-muted-foreground mb-6">
                        Sobre <span class="font-mono">{{ store.selectedSaleDetail.serie }}-{{ store.selectedSaleDetail.correlative }}</span>
                        — {{ store.selectedSaleDetail.partner?.name || 'Sin cliente' }}
                    </p>

                    <div class="border border-border rounded-lg overflow-hidden mb-6">
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/40 text-xs text-muted-foreground uppercase tracking-wide"
                            >
                                <tr>
                                    <th class="text-left px-4 py-2 font-medium">Producto</th>
                                    <th class="text-right px-4 py-2 font-medium w-20">Vendido</th>
                                    <th class="text-right px-4 py-2 font-medium w-20">Devuelto</th>
                                    <th class="text-right px-4 py-2 font-medium w-20">Disponible</th>
                                    <th class="text-right px-4 py-2 font-medium w-28">A devolver</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <template
                                    v-for="line in refundLines"
                                    :key="line.product_product_id"
                                >
                                    <tr>
                                        <td class="px-4 py-2">
                                            <p class="font-medium">
                                                {{
                                                    store.selectedSaleDetail.products?.find(
                                                        (p: any) => p.product_product_id === line.product_product_id,
                                                    )?.product?.name || `Producto #${line.product_product_id}`
                                                }}
                                            </p>
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums">
                                            {{ Number(line.original_quantity).toFixed(2) }}
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-muted-foreground">
                                            {{ Number(line.refunded_quantity).toFixed(2) }}
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium">
                                            {{ Number(line.available_quantity).toFixed(2) }}
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <div class="flex flex-col items-end gap-0.5">
                                                <input
                                                    :value="refundQuantities[line.product_product_id] ?? 0"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    :disabled="Number(line.available_quantity) <= 0"
                                                    :class="[
                                                        'w-24 h-8 px-2 bg-background border rounded text-sm text-right tabular-nums focus:outline-none focus:ring-2 disabled:opacity-50',
                                                        isLineOverflow(line)
                                                            ? 'border-destructive focus:ring-destructive text-destructive'
                                                            : 'border-input focus:ring-ring',
                                                    ]"
                                                    @input="
                                                        (e) =>
                                                            updateRefundQty(
                                                                line.product_product_id,
                                                                (e.target as HTMLInputElement).value,
                                                            )
                                                    "
                                                />
                                                <span
                                                    :class="[
                                                        'text-[10px] tabular-nums',
                                                        isLineOverflow(line)
                                                            ? 'text-destructive font-medium'
                                                            : 'text-muted-foreground',
                                                    ]"
                                                >
                                                    Máx: {{ Number(line.available_quantity).toFixed(2) }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="line.lots && line.lots.length > 0"
                                        class="bg-muted/20"
                                    >
                                        <td colspan="5" class="px-4 py-2">
                                            <div class="pl-4 border-l-2 border-border space-y-1">
                                                <p class="text-[10px] uppercase tracking-wider text-muted-foreground font-medium">
                                                    Lotes
                                                </p>
                                                <div
                                                    v-for="lot in line.lots"
                                                    :key="lot.lot_id"
                                                    class="flex items-center gap-3 text-xs"
                                                >
                                                    <span class="font-mono">{{ lot.lot_number || `#${lot.lot_id}` }}</span>
                                                    <span
                                                        v-if="lot.expires_at"
                                                        class="text-muted-foreground"
                                                    >
                                                        vence {{ lot.expires_at }}
                                                    </span>
                                                    <span class="ml-auto tabular-nums text-muted-foreground">
                                                        vendido {{ Number(lot.sold_quantity).toFixed(2) }}
                                                        · devuelto {{ Number(lot.refunded_quantity).toFixed(2) }}
                                                        ·
                                                        <span
                                                            :class="
                                                                Number(lot.available_quantity) > 0
                                                                    ? 'text-foreground font-medium'
                                                                    : ''
                                                            "
                                                        >
                                                            disponible {{ Number(lot.available_quantity).toFixed(2) }}
                                                        </span>
                                                    </span>
                                                </div>
                                                <p class="text-[10px] text-muted-foreground italic pt-1">
                                                    La devolución se reparte automáticamente entre los lotes en orden FEFO inverso.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-6">
                        <label class="block text-xs text-muted-foreground uppercase tracking-wide mb-1">
                            Notas (opcional)
                        </label>
                        <textarea
                            v-model="refundNotes"
                            rows="2"
                            placeholder="Motivo de la devolución…"
                            class="w-full px-3 py-2 bg-background border border-input rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-between border-t border-border pt-4">
                        <div>
                            <p class="text-xs text-muted-foreground uppercase tracking-wide">Total a devolver</p>
                            <p
                                :class="[
                                    'text-2xl font-bold tabular-nums',
                                    hasAnyOverflow ? 'text-destructive' : '',
                                ]"
                            >
                                {{ formatCurrency(refundTotal) }}
                            </p>
                            <p
                                v-if="hasAnyOverflow"
                                class="text-xs text-destructive mt-1"
                            >
                                Una o más líneas exceden la cantidad disponible.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                :disabled="isRefunding"
                                @click="cancelRefund"
                            >
                                Cancelar
                            </Button>
                            <Button
                                :disabled="!canSubmitRefund || isRefunding"
                                @click="submitRefund"
                            >
                                <Undo2 class="h-4 w-4 mr-1.5" />
                                {{ isRefunding ? 'Generando…' : 'Generar Nota de Crédito' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PosShell>
</template>
