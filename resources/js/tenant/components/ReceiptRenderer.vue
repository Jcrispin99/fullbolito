<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ensureReceiptStylesInjected } from './receiptStyles'
import { formatDateTime } from '@tenant/lib/datetime'
import {
    normalizeLayout,
    paperWidthPx,
    type ReceiptBlock,
    type ReceiptColumn,
    type ReceiptTemplateLayout,
    type BlockStyle,
    type ItemsTableBlock,
    type FreeTextBlock,
    type ParagraphBlock,
    type KeyValueListBlock,
    type DividerBlock,
    type DocNumberBlock,
    type TotalsBlock,
} from './receiptBlocks'

export type {
    ReceiptColumn,
    ReceiptTemplateLayout,
} from './receiptBlocks'

export interface ReceiptCompany {
    business_name?: string | null
    trade_name?: string | null
    ruc?: string | null
    address?: string | null
    phone?: string | null
    email?: string | null
    logo_url?: string | null
}

export interface ReceiptSaleLine {
    quantity: number
    price: number
    subtotal: number
    total: number
    product?: { name?: string | null; sku?: string | null } | null
    uom?: { symbol?: string | null } | null
}

export interface ReceiptPayment {
    method_name: string
    amount: number
}

export interface ReceiptLoyaltyTx {
    type: 'earn' | 'redeem' | string
    points: number
    point_name?: string
}

export interface ReceiptSale {
    serie?: string | null
    correlative?: string | null
    date?: string | null
    subtotal: number
    tax_amount: number
    total: number
    journal?: { document_type_code?: string | null; name?: string | null } | null
    partner?: {
        document_type?: string | null
        document_number?: string | null
        name?: string | null
        address?: string | null
    } | null
    seller?: { name?: string | null } | null
    products: ReceiptSaleLine[]
    payments?: ReceiptPayment[]
    loyalty_transactions?: ReceiptLoyaltyTx[]
    qr_data_url?: string | null
}

const props = defineProps<{
    template: ReceiptTemplateLayout | unknown
    sale: ReceiptSale
    company: ReceiptCompany
    templateLogoUrl?: string | null
    editable?: boolean
    selectedId?: string | null
    snap?: boolean
    snapSize?: number
    showGrid?: boolean
}>()

const emit = defineEmits<{
    (e: 'select', id: string | null): void
    (e: 'update-position', payload: { id: string; x: number; y: number }): void
    (e: 'update-size', payload: { id: string; w: number; h: number; x?: number; y?: number }): void
    (e: 'update-canvas-height', height: number): void
}>()

const effectiveLogoUrl = computed<string | null>(
    () => props.templateLogoUrl || props.company.logo_url || null,
)

onMounted(() => {
    ensureReceiptStylesInjected()
    nextTick(measureBlocks)
})

const layout = computed<ReceiptTemplateLayout>(() => normalizeLayout(props.template))
const canvasWidth = computed(() => paperWidthPx(layout.value.paper_width))
const snapEnabled = computed(() => props.snap !== false)
const snapSize = computed(() => props.snapSize ?? 5)

function snap(v: number): number {
    if (!snapEnabled.value) return Math.round(v)
    const s = snapSize.value
    return Math.round(v / s) * s
}

const canvasRef = ref<HTMLDivElement | null>(null)
const measuredHeights = ref<Record<string, number>>({})

function measureBlocks(): void {
    if (!canvasRef.value) return
    const next: Record<string, number> = {}
    const els = canvasRef.value.querySelectorAll<HTMLElement>('[data-block-id]')
    els.forEach((el) => {
        const id = el.dataset.blockId
        if (!id) return
        next[id] = el.offsetHeight
    })
    measuredHeights.value = next
}

function effectiveHeight(block: ReceiptBlock): number {
    if (block.position.h !== null && block.position.h !== undefined) return block.position.h
    return measuredHeights.value[block.id] ?? 24
}

watch(
    () => [props.template, props.selectedId],
    () => nextTick(measureBlocks),
    { deep: true },
)

// ---- Drag move ----
type Drag =
    | { kind: 'move'; id: string; startX: number; startY: number; origX: number; origY: number }
    | { kind: 'resize'; id: string; corner: HandleId; startX: number; startY: number; orig: { x: number; y: number; w: number; h: number } }
    | { kind: 'canvas'; startY: number; origH: number }

type HandleId = 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw'

const drag = ref<Drag | null>(null)

function onBlockMouseDown(e: MouseEvent, block: ReceiptBlock): void {
    if (!props.editable) return
    e.stopPropagation()
    emit('select', block.id)
    drag.value = {
        kind: 'move',
        id: block.id,
        startX: e.clientX,
        startY: e.clientY,
        origX: block.position.x,
        origY: block.position.y,
    }
    window.addEventListener('mousemove', onMouseMove)
    window.addEventListener('mouseup', onMouseUp)
}

function onHandleMouseDown(e: MouseEvent, block: ReceiptBlock, corner: HandleId): void {
    if (!props.editable) return
    e.preventDefault()
    e.stopPropagation()
    const h = block.position.h ?? measuredHeights.value[block.id] ?? 24
    drag.value = {
        kind: 'resize',
        id: block.id,
        corner,
        startX: e.clientX,
        startY: e.clientY,
        orig: {
            x: block.position.x,
            y: block.position.y,
            w: block.position.w,
            h,
        },
    }
    window.addEventListener('mousemove', onMouseMove)
    window.addEventListener('mouseup', onMouseUp)
}

function onCanvasResizeStart(e: MouseEvent): void {
    if (!props.editable) return
    e.preventDefault()
    e.stopPropagation()
    drag.value = {
        kind: 'canvas',
        startY: e.clientY,
        origH: layout.value.canvas_height,
    }
    window.addEventListener('mousemove', onMouseMove)
    window.addEventListener('mouseup', onMouseUp)
}

function onMouseMove(e: MouseEvent): void {
    const d = drag.value
    if (!d) return
    if (d.kind === 'canvas') {
        const next = Math.max(120, snap(d.origH + (e.clientY - d.startY)))
        emit('update-canvas-height', next)
        return
    }
    if (d.kind === 'move') {
        const dx = e.clientX - d.startX
        const dy = e.clientY - d.startY
        let x = snap(d.origX + dx)
        let y = snap(d.origY + dy)
        x = Math.max(0, Math.min(canvasWidth.value - 10, x))
        y = Math.max(0, y)
        emit('update-position', { id: d.id, x, y })
        return
    }
    if (d.kind === 'resize') {
        const dx = e.clientX - d.startX
        const dy = e.clientY - d.startY
        let { x, y, w, h } = d.orig
        const corner = d.corner
        const minW = 10
        const minH = 8
        if (corner.includes('e')) w = Math.max(minW, snap(d.orig.w + dx))
        if (corner.includes('s')) h = Math.max(minH, snap(d.orig.h + dy))
        if (corner.includes('w')) {
            const nx = Math.min(d.orig.x + d.orig.w - minW, snap(d.orig.x + dx))
            w = d.orig.w + (d.orig.x - nx)
            x = nx
        }
        if (corner.includes('n')) {
            const ny = Math.min(d.orig.y + d.orig.h - minH, snap(d.orig.y + dy))
            h = d.orig.h + (d.orig.y - ny)
            y = ny
        }
        if (x < 0) { w += x; x = 0 }
        if (x + w > canvasWidth.value) w = canvasWidth.value - x
        if (y < 0) { h += y; y = 0 }
        emit('update-size', { id: d.id, w, h, x, y })
        return
    }
}

function onMouseUp(): void {
    drag.value = null
    window.removeEventListener('mousemove', onMouseMove)
    window.removeEventListener('mouseup', onMouseUp)
}

onBeforeUnmount(() => {
    window.removeEventListener('mousemove', onMouseMove)
    window.removeEventListener('mouseup', onMouseUp)
})

function onCanvasMouseDown(e: MouseEvent): void {
    if (!props.editable) return
    if (e.target !== e.currentTarget) return
    emit('select', null)
}

// ---- Style helpers ----
const docTitle = computed(() => {
    const code = props.sale.journal?.document_type_code
    if (code === '01') return 'FACTURA ELECTRÓNICA'
    if (code === '03') return 'BOLETA DE VENTA ELECTRÓNICA'
    if (code === '07') return 'NOTA DE CRÉDITO ELECTRÓNICA'
    if (code === '08') return 'NOTA DE DÉBITO ELECTRÓNICA'
    return props.sale.journal?.name ?? 'COMPROBANTE'
})

const docNumber = computed(() => {
    const serie = props.sale.serie ?? ''
    const corr = props.sale.correlative ?? ''
    return serie && corr ? `${serie} - ${corr}` : serie || corr || ''
})

const formattedDate = computed(() => {
    if (!props.sale.date) return ''
    return formatDateTime(props.sale.date)
})

const fmt = (n: number) => `S/ ${(n ?? 0).toFixed(2)}`

const customerDocLabel = (type?: string | null) => {
    if (!type) return 'Doc.'
    const t = type.toUpperCase()
    if (t === 'DNI') return 'DNI'
    if (t === 'RUC') return 'RUC'
    if (t === 'CE') return 'CE'
    return t
}

const cellValue = (line: ReceiptSaleLine, key: ReceiptColumn['key']) => {
    if (key === 'qty') {
        const sym = line.uom?.symbol ? ` ${line.uom.symbol}` : ''
        return `${line.quantity}${sym}`
    }
    if (key === 'name') return line.product?.name ?? '—'
    if (key === 'unit') return fmt(line.price)
    if (key === 'sub') return fmt(line.total ?? line.subtotal)
    return ''
}

const isNumCol = (key: ReceiptColumn['key']) => key !== 'name'

const earnedPoints = computed(() => (props.sale.loyalty_transactions ?? []).filter((t) => t.type === 'earn'))
const redeemedPoints = computed(() => (props.sale.loyalty_transactions ?? []).filter((t) => t.type === 'redeem'))

function blockClasses(block: ReceiptBlock): string[] {
    const s: BlockStyle = block.style ?? {}
    const classes = ['rcpt-block', `rcpt-blk-${block.type}`]
    if (s.align) classes.push(`rcpt-align-${s.align}`)
    if (s.bold) classes.push('rcpt-bold')
    if (s.italic) classes.push('rcpt-italic')
    if (s.underline) classes.push('rcpt-underline')
    if (s.size_override) classes.push(`rcpt-size-${s.size_override}`)
    if (props.editable && !block.enabled) classes.push('is-disabled')
    if (props.editable && props.selectedId === block.id) classes.push('is-selected')
    if (block.type === 'divider') {
        const dStyle = (block as DividerBlock).config?.style ?? 'dashed'
        classes.push(`rcpt-divider-style-${dStyle}`)
    }
    if (props.editable && block.locked) classes.push('is-locked')
    if (drag.value && drag.value.kind === 'move' && drag.value.id === block.id) classes.push('is-dragging')
    return classes
}

function blockBoxStyle(block: ReceiptBlock): Record<string, string> {
    const p = block.position
    const s = block.style ?? {}
    const out: Record<string, string> = {
        left: `${p.x}px`,
        top: `${p.y}px`,
        width: `${p.w}px`,
    }
    if (p.h !== null && p.h !== undefined) out.height = `${p.h}px`
    if (typeof p.z === 'number') out['z-index'] = String(p.z)
    if (s.color) out.color = s.color
    if (s.background) out['background-color'] = s.background
    if (s.padding) out.padding = `${s.padding}px`
    if (s.border_width) out.border = `${s.border_width}px solid ${s.border_color ?? '#000'}`
    if (s.border_radius) out['border-radius'] = `${s.border_radius}px`
    return out
}

function styleString(block: ReceiptBlock): string {
    return Object.entries(blockBoxStyle(block)).map(([k, v]) => `${k}:${v}`).join(';')
}

function asFreeText(b: ReceiptBlock): FreeTextBlock { return b as FreeTextBlock }
function asParagraph(b: ReceiptBlock): ParagraphBlock { return b as ParagraphBlock }
function asKv(b: ReceiptBlock): KeyValueListBlock { return b as KeyValueListBlock }
function asItems(b: ReceiptBlock): ItemsTableBlock { return b as ItemsTableBlock }
function asTotals(b: ReceiptBlock): TotalsBlock { return b as TotalsBlock }
function asDocNumber(b: ReceiptBlock): DocNumberBlock { return b as DocNumberBlock }

function enabledColumns(block: ItemsTableBlock): ReceiptColumn[] {
    return block.config.columns.filter((c) => c.enabled)
}

function totalsShowTax(block: TotalsBlock): boolean {
    return block.config?.show_tax_breakdown !== false
}

function docNumberShowDate(block: DocNumberBlock): boolean {
    return block.config?.show_date !== false
}
function docNumberShowSeller(block: DocNumberBlock): boolean {
    return block.config?.show_seller !== false
}

const visibleBlocks = computed(() => {
    if (props.editable) return layout.value.blocks
    return layout.value.blocks.filter((b) => b.enabled !== false)
})

const HANDLES: HandleId[] = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w']
</script>

<template>
    <div
        ref="canvasRef"
        class="rcpt-canvas"
        :class="[`rcpt-w-${layout.paper_width}`, `rcpt-fs-${layout.font_size}`, editable ? 'is-editable' : '', editable && showGrid ? 'show-grid' : '']"
        :style="{ height: layout.canvas_height + 'px' }"
        @mousedown="onCanvasMouseDown"
    >
        <template v-for="block in visibleBlocks" :key="block.id">
            <!-- Logo -->
            <div
                v-if="block.type === 'logo'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <img v-if="effectiveLogoUrl" :src="effectiveLogoUrl" alt="Logo" />
                <div v-else class="rcpt-block-empty">[Logo]</div>
            </div>

            <div
                v-else-if="block.type === 'company_info'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <div class="rcpt-company-name">
                    {{ company.trade_name || company.business_name || '—' }}
                </div>
                <div
                    v-if="company.business_name && company.trade_name && company.business_name !== company.trade_name"
                    class="rcpt-meta"
                >
                    {{ company.business_name }}
                </div>
                <div v-if="company.ruc" class="rcpt-meta">RUC: {{ company.ruc }}</div>
                <div v-if="company.address" class="rcpt-meta">{{ company.address }}</div>
                <div v-if="company.phone" class="rcpt-meta">Telf: {{ company.phone }}</div>
                <div v-if="company.email" class="rcpt-meta">{{ company.email }}</div>
            </div>

            <div
                v-else-if="block.type === 'doc_title'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                {{ docTitle }}
            </div>

            <div
                v-else-if="block.type === 'doc_number'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <div v-if="docNumber" class="rcpt-doc-num"><b>{{ docNumber }}</b></div>
                <div v-if="docNumberShowDate(asDocNumber(block)) && formattedDate" class="rcpt-info-row">
                    <span class="rcpt-label">Fecha:</span>
                    <span>{{ formattedDate }}</span>
                </div>
                <div v-if="docNumberShowSeller(asDocNumber(block)) && sale.seller?.name" class="rcpt-info-row">
                    <span class="rcpt-label">Cajero:</span>
                    <span>{{ sale.seller.name }}</span>
                </div>
            </div>

            <div
                v-else-if="block.type === 'customer'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <template v-if="sale.partner">
                    <div class="rcpt-info-row">
                        <span class="rcpt-label">{{ customerDocLabel(sale.partner.document_type) }}:</span>
                        <span>{{ sale.partner.document_number || '—' }}</span>
                    </div>
                    <div class="rcpt-meta">
                        <span class="rcpt-bold">Cliente:</span> {{ sale.partner.name || '—' }}
                    </div>
                    <div v-if="sale.partner.address" class="rcpt-meta">{{ sale.partner.address }}</div>
                </template>
                <div v-else class="rcpt-block-empty">[Datos del cliente]</div>
            </div>

            <div
                v-else-if="block.type === 'items_table'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <table class="rcpt-items">
                    <thead>
                        <tr>
                            <th
                                v-for="col2 in enabledColumns(asItems(block))"
                                :key="`h-${col2.key}`"
                                :class="{ 'rcpt-num': isNumCol(col2.key) }"
                            >
                                {{ col2.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, idx) in sale.products" :key="`l-${idx}`">
                            <td
                                v-for="col2 in enabledColumns(asItems(block))"
                                :key="`c-${idx}-${col2.key}`"
                                :class="[col2.key === 'name' ? 'rcpt-name' : '', isNumCol(col2.key) ? 'rcpt-num rcpt-tabular' : '']"
                            >
                                {{ cellValue(line, col2.key) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else-if="block.type === 'totals'"
                :class="[...blockClasses(block), 'rcpt-tabular']"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <div v-if="totalsShowTax(asTotals(block))" class="rcpt-total-row">
                    <span>Subtotal</span>
                    <span>{{ fmt(sale.subtotal) }}</span>
                </div>
                <div v-if="totalsShowTax(asTotals(block))" class="rcpt-total-row">
                    <span>IGV</span>
                    <span>{{ fmt(sale.tax_amount) }}</span>
                </div>
                <div class="rcpt-total-row rcpt-grand">
                    <span>TOTAL</span>
                    <span>{{ fmt(sale.total) }}</span>
                </div>
            </div>

            <div
                v-else-if="block.type === 'payments'"
                :class="[...blockClasses(block), 'rcpt-tabular']"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <template v-if="sale.payments && sale.payments.length > 0">
                    <div v-for="(p, i) in sale.payments" :key="`p-${i}`" class="rcpt-total-row">
                        <span>{{ p.method_name }}</span>
                        <span>{{ fmt(p.amount) }}</span>
                    </div>
                </template>
                <div v-else class="rcpt-block-empty">[Métodos de pago]</div>
            </div>

            <div
                v-else-if="block.type === 'loyalty'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <template v-if="earnedPoints.length > 0 || redeemedPoints.length > 0">
                    <div v-for="(t, i) in earnedPoints" :key="`pe-${i}`" class="rcpt-info-row">
                        <span>Puntos ganados:</span>
                        <span>+{{ t.points }} {{ t.point_name || 'pts' }}</span>
                    </div>
                    <div v-for="(t, i) in redeemedPoints" :key="`pr-${i}`" class="rcpt-info-row">
                        <span>Puntos canjeados:</span>
                        <span>−{{ Math.abs(t.points) }} {{ t.point_name || 'pts' }}</span>
                    </div>
                </template>
                <div v-else class="rcpt-block-empty">[Puntos / fidelización]</div>
            </div>

            <div
                v-else-if="block.type === 'qr'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <img v-if="sale.qr_data_url" :src="sale.qr_data_url" alt="QR" />
                <div v-else class="rcpt-block-empty">[QR]</div>
            </div>

            <div
                v-else-if="block.type === 'free_text'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <div v-for="(line, i) in asFreeText(block).config.lines" :key="`ft-${i}`" class="rcpt-meta">
                    {{ line }}
                </div>
            </div>

            <div
                v-else-if="block.type === 'paragraph'"
                :class="[...blockClasses(block), 'rcpt-paragraph']"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >{{ asParagraph(block).config.text }}</div>

            <div
                v-else-if="block.type === 'key_value_list'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <div v-for="(item, i) in asKv(block).config.items" :key="`kv-${i}`" class="rcpt-kv-row">
                    <span class="rcpt-label">{{ item.key }}</span>
                    <span>{{ item.value }}</span>
                </div>
            </div>

            <div
                v-else-if="block.type === 'divider'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            >
                <hr class="rcpt-divider-line" />
            </div>

            <div
                v-else-if="block.type === 'spacer'"
                :class="blockClasses(block)"
                :style="styleString(block)"
                :data-block-id="block.id"
                @mousedown="(e: MouseEvent) => onBlockMouseDown(e, block)"
            ><div class="rcpt-block-empty" v-if="editable">[Espacio]</div></div>

            <!-- Selection frame + resize handles -->
            <template v-if="editable && selectedId === block.id">
                <div
                    class="rcpt-selection-frame"
                    :style="{
                        left: block.position.x + 'px',
                        top: block.position.y + 'px',
                        width: block.position.w + 'px',
                        height: effectiveHeight(block) + 'px',
                    }"
                ></div>
            </template>
            <template v-if="editable && selectedId === block.id">
                <span
                    v-for="h in HANDLES"
                    :key="`hnd-${block.id}-${h}`"
                    class="rcpt-handle"
                    :class="`h-${h}`"
                    :style="{
                        position: 'absolute',
                        left: (h.includes('w') ? block.position.x - 5 : (h === 'n' || h === 's' ? block.position.x + block.position.w / 2 - 5 : block.position.x + block.position.w - 5)) + 'px',
                        top: (h.includes('n') ? block.position.y - 5 : (h === 'e' || h === 'w' ? block.position.y + effectiveHeight(block) / 2 - 5 : block.position.y + effectiveHeight(block) - 5)) + 'px',
                    }"
                    @mousedown="(e: MouseEvent) => onHandleMouseDown(e, block, h)"
                ></span>
            </template>
        </template>

        <!-- Canvas height resize -->
        <div
            v-if="editable"
            class="rcpt-canvas-resize-handle"
            @mousedown="onCanvasResizeStart"
            title="Arrastra para cambiar la altura del lienzo"
        ></div>
    </div>
</template>
