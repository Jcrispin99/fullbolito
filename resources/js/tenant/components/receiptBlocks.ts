export type ReceiptColumnKey = 'qty' | 'name' | 'unit' | 'sub'

export interface ReceiptColumn {
    key: ReceiptColumnKey
    label: string
    enabled: boolean
}

export type BlockAlign = 'left' | 'center' | 'right'
export type BlockSize = 'sm' | 'md' | 'lg'
export type PaperWidth = 'thermal_58' | 'thermal_80' | 'a4'

export interface BlockStyle {
    align?: BlockAlign
    bold?: boolean
    italic?: boolean
    underline?: boolean
    size_override?: BlockSize | null
    color?: string | null
    background?: string | null
    padding?: number
    border_width?: number
    border_color?: string | null
    border_radius?: number
}

export interface BlockPosition {
    x: number
    y: number
    w: number
    h: number | null
    z?: number
}

export interface BlockBase {
    id: string
    enabled: boolean
    locked?: boolean
    style?: BlockStyle
    position: BlockPosition
}

export interface LogoBlock extends BlockBase {
    type: 'logo'
    config?: Record<string, never>
}
export interface CompanyInfoBlock extends BlockBase {
    type: 'company_info'
    config?: Record<string, never>
}
export interface DocTitleBlock extends BlockBase {
    type: 'doc_title'
    config?: Record<string, never>
}
export interface DocNumberBlock extends BlockBase {
    type: 'doc_number'
    config?: { show_date?: boolean; show_seller?: boolean }
}
export interface CustomerBlock extends BlockBase {
    type: 'customer'
    config?: Record<string, never>
}
export interface ItemsTableBlock extends BlockBase {
    type: 'items_table'
    config: { columns: ReceiptColumn[] }
}
export interface TotalsBlock extends BlockBase {
    type: 'totals'
    config?: { show_tax_breakdown?: boolean }
}
export interface PaymentsBlock extends BlockBase {
    type: 'payments'
    config?: Record<string, never>
}
export interface LoyaltyBlock extends BlockBase {
    type: 'loyalty'
    config?: Record<string, never>
}
export interface QrBlock extends BlockBase {
    type: 'qr'
    config?: Record<string, never>
}
export interface FreeTextBlock extends BlockBase {
    type: 'free_text'
    config: { lines: string[] }
}
export interface ParagraphBlock extends BlockBase {
    type: 'paragraph'
    config: { text: string }
}
export interface KeyValueListBlock extends BlockBase {
    type: 'key_value_list'
    config: { items: { key: string; value: string }[] }
}
export interface DividerBlock extends BlockBase {
    type: 'divider'
    config?: { style?: 'solid' | 'dashed' | 'dotted' }
}
export interface SpacerBlock extends BlockBase {
    type: 'spacer'
    config?: Record<string, never>
}

export type ReceiptBlock =
    | LogoBlock
    | CompanyInfoBlock
    | DocTitleBlock
    | DocNumberBlock
    | CustomerBlock
    | ItemsTableBlock
    | TotalsBlock
    | PaymentsBlock
    | LoyaltyBlock
    | QrBlock
    | FreeTextBlock
    | ParagraphBlock
    | KeyValueListBlock
    | DividerBlock
    | SpacerBlock

export type ReceiptBlockType = ReceiptBlock['type']

export interface ReceiptTemplateLayout {
    version: 3
    paper_width: PaperWidth
    font_size: 'sm' | 'md' | 'lg'
    canvas_height: number
    blocks: ReceiptBlock[]
}

export const PAPER_WIDTHS: Record<PaperWidth, number> = {
    thermal_58: 220,
    thermal_80: 302,
    a4: 794,
}

export function paperWidthPx(p: PaperWidth): number {
    return PAPER_WIDTHS[p]
}

export const SYSTEM_BLOCK_TYPES: ReceiptBlockType[] = [
    'company_info',
    'doc_title',
    'doc_number',
    'items_table',
    'totals',
]

export const TOGGLEABLE_UNIQUE_TYPES: ReceiptBlockType[] = [
    'logo',
    'customer',
    'payments',
    'loyalty',
    'qr',
]

export const FREE_BLOCK_TYPES: ReceiptBlockType[] = [
    'free_text',
    'paragraph',
    'key_value_list',
    'divider',
    'spacer',
]

export const BLOCK_LABELS: Record<ReceiptBlockType, string> = {
    logo: 'Logo',
    company_info: 'Datos de la empresa',
    doc_title: 'Título del documento',
    doc_number: 'Número y fecha',
    customer: 'Cliente',
    items_table: 'Tabla de productos',
    totals: 'Totales',
    payments: 'Pagos',
    loyalty: 'Fidelización',
    qr: 'Código QR',
    free_text: 'Texto libre',
    paragraph: 'Notas / párrafo',
    key_value_list: 'Lista clave-valor',
    divider: 'Separador',
    spacer: 'Espacio',
}

export const DEFAULT_BLOCK_HEIGHT: Record<ReceiptBlockType, number> = {
    logo: 70,
    company_info: 90,
    doc_title: 24,
    doc_number: 50,
    customer: 60,
    items_table: 110,
    totals: 60,
    payments: 36,
    loyalty: 32,
    qr: 110,
    free_text: 24,
    paragraph: 70,
    key_value_list: 40,
    divider: 8,
    spacer: 16,
}

export function defaultStyle(overrides: Partial<BlockStyle> = {}): BlockStyle {
    return {
        align: 'left',
        bold: false,
        italic: false,
        underline: false,
        size_override: null,
        color: null,
        background: null,
        padding: 0,
        border_width: 0,
        border_color: null,
        border_radius: 0,
        ...overrides,
    }
}

let idSeq = 0
export function newBlockId(type: ReceiptBlockType): string {
    idSeq += 1
    return `${type}_${Date.now().toString(36)}_${idSeq}`
}

const FIXED_HEIGHT_TYPES: ReceiptBlockType[] = ['logo', 'qr', 'divider', 'spacer']

export function defaultPosition(type: ReceiptBlockType, paper: PaperWidth, atY = 0): BlockPosition {
    const W = paperWidthPx(paper)
    const h = FIXED_HEIGHT_TYPES.includes(type) ? (DEFAULT_BLOCK_HEIGHT[type] ?? 24) : null
    return { x: 0, y: atY, w: W, h, z: 0 }
}

export function makeBlock(type: ReceiptBlockType, paper: PaperWidth = 'thermal_80', atY = 0): ReceiptBlock {
    const id = newBlockId(type)
    const baseStyle = defaultStyle()
    const position = defaultPosition(type, paper, atY)
    switch (type) {
        case 'logo':
            return { id, type, enabled: true, position, style: defaultStyle({ align: 'center' }), config: {} }
        case 'company_info':
            return { id, type, enabled: true, position, style: defaultStyle({ align: 'center' }), config: {} }
        case 'doc_title':
            return { id, type, enabled: true, position, style: defaultStyle({ align: 'center', bold: true, size_override: 'lg' }), config: {} }
        case 'doc_number':
            return { id, type, enabled: true, position, style: defaultStyle({ align: 'center', bold: true }), config: {} }
        case 'customer':
            return { id, type, enabled: true, position, style: baseStyle, config: {} }
        case 'items_table':
            return {
                id, type, enabled: true, position,
                style: baseStyle,
                config: {
                    columns: [
                        { key: 'qty', label: 'Cant', enabled: true },
                        { key: 'name', label: 'Descripción', enabled: true },
                        { key: 'unit', label: 'P.U.', enabled: true },
                        { key: 'sub', label: 'Sub', enabled: true },
                    ],
                },
            }
        case 'totals':
            return { id, type, enabled: true, position, style: defaultStyle({ align: 'right' }), config: { show_tax_breakdown: true } }
        case 'payments':
            return { id, type, enabled: true, position, style: baseStyle, config: {} }
        case 'loyalty':
            return { id, type, enabled: true, position, style: baseStyle, config: {} }
        case 'qr':
            return { id, type, enabled: true, position, style: defaultStyle({ align: 'center' }), config: {} }
        case 'free_text':
            return { id, type, enabled: true, position, style: defaultStyle({ align: 'center' }), config: { lines: ['Texto'] } }
        case 'paragraph':
            return {
                id, type, enabled: true, position,
                style: defaultStyle({ align: 'left', size_override: 'sm' }),
                config: { text: 'Escribe aquí tus políticas, condiciones o notas largas.' },
            }
        case 'key_value_list':
            return { id, type, enabled: true, position, style: baseStyle, config: { items: [{ key: 'Clave', value: 'Valor' }] } }
        case 'divider':
            return { id, type, enabled: true, position, style: baseStyle, config: { style: 'dashed' } }
        case 'spacer':
            return { id, type, enabled: true, position, style: baseStyle, config: {} }
    }
}

export function isV3Layout(layout: unknown): layout is ReceiptTemplateLayout {
    return (
        typeof layout === 'object' &&
        layout !== null &&
        (layout as { version?: number }).version === 3 &&
        Array.isArray((layout as { blocks?: unknown }).blocks)
    )
}

interface LegacyV2Column {
    id?: string
    width_pct?: number
    blocks?: LegacyV2Block[]
}
interface LegacyV2Row {
    id?: string
    columns?: LegacyV2Column[]
}
interface LegacyV2Block {
    id?: string
    type?: ReceiptBlockType
    enabled?: boolean
    locked?: boolean
    style?: any
    config?: any
}
interface LegacyV2Layout {
    version?: 2
    paper_width?: PaperWidth
    font_size?: 'sm' | 'md' | 'lg'
    rows?: LegacyV2Row[]
}

interface LegacyV1Layout {
    version?: 1
    paper_width?: PaperWidth
    font_size?: 'sm' | 'md' | 'lg'
    blocks?: LegacyV2Block[]
}

interface LegacyFlatLayout {
    paper_width?: PaperWidth
    font_size?: 'sm' | 'md' | 'lg'
    show_logo?: boolean
    header_lines?: string[]
    footer_lines?: string[]
    show_customer?: boolean
    show_tax_breakdown?: boolean
    show_payments?: boolean
    show_loyalty?: boolean
    show_qr?: boolean
    columns?: ReceiptColumn[]
}

function blockFromLegacy(raw: LegacyV2Block, fallbackPos: BlockPosition): ReceiptBlock {
    const t = (raw.type ?? 'free_text') as ReceiptBlockType
    const id = raw.id ?? newBlockId(t)
    const enabled = raw.enabled !== false
    const locked = !!raw.locked
    const style = defaultStyle({
        align: raw.style?.align ?? 'left',
        bold: !!raw.style?.bold,
        size_override: raw.style?.size_override ?? null,
    })
    const config = raw.config ?? {}
    const position = { ...fallbackPos }
    switch (t) {
        case 'items_table': {
            const cols: ReceiptColumn[] = Array.isArray(config.columns) && config.columns.length > 0
                ? config.columns
                : [
                    { key: 'qty', label: 'Cant', enabled: true },
                    { key: 'name', label: 'Descripción', enabled: true },
                    { key: 'unit', label: 'P.U.', enabled: true },
                    { key: 'sub', label: 'Sub', enabled: true },
                ]
            return { id, type: 'items_table', enabled, locked, style, position, config: { columns: cols } }
        }
        case 'free_text':
            return { id, type: 'free_text', enabled, locked, style, position, config: { lines: Array.isArray(config.lines) ? config.lines : ['Texto'] } }
        case 'paragraph':
            return { id, type: 'paragraph', enabled, locked, style, position, config: { text: typeof config.text === 'string' ? config.text : '' } }
        case 'key_value_list':
            return { id, type: 'key_value_list', enabled, locked, style, position, config: { items: Array.isArray(config.items) ? config.items : [] } }
        case 'divider':
            return { id, type: 'divider', enabled, locked, style, position, config: { style: config.style ?? 'dashed' } }
        case 'spacer':
            return { id, type: 'spacer', enabled, locked, style, position, config: {} }
        case 'totals':
            return { id, type: 'totals', enabled, locked, style, position, config: { show_tax_breakdown: config.show_tax_breakdown !== false } }
        case 'doc_number':
            return { id, type: 'doc_number', enabled, locked, style, position, config: { show_date: config.show_date !== false, show_seller: config.show_seller !== false } }
        default:
            return { id, type: t, enabled, locked, style, position, config: {} } as ReceiptBlock
    }
}

function migrateV2ToV3(v2: LegacyV2Layout): ReceiptTemplateLayout {
    const paper = v2.paper_width ?? 'thermal_80'
    const W = paperWidthPx(paper)
    const blocks: ReceiptBlock[] = []
    let y = 0
    for (const row of v2.rows ?? []) {
        for (const col of row.columns ?? []) {
            for (const rawBlock of col.blocks ?? []) {
                const t = (rawBlock.type ?? 'free_text') as ReceiptBlockType
                const isFixed = FIXED_HEIGHT_TYPES.includes(t)
                const estH = DEFAULT_BLOCK_HEIGHT[t] ?? 24
                const pos: BlockPosition = {
                    x: 0,
                    y,
                    w: W,
                    h: isFixed ? estH : null,
                    z: 0,
                }
                const b = blockFromLegacy(rawBlock, pos)
                b.locked = false
                blocks.push(b)
                y += estH + 4
            }
        }
    }
    return {
        version: 3,
        paper_width: paper,
        font_size: v2.font_size ?? 'md',
        canvas_height: Math.max(400, y + 40),
        blocks,
    }
}

function migrateV1ToV3(v1: LegacyV1Layout): ReceiptTemplateLayout {
    const paper = v1.paper_width ?? 'thermal_80'
    const W = paperWidthPx(paper)
    const blocks: ReceiptBlock[] = []
    let y = 0
    for (const raw of v1.blocks ?? []) {
        const t = (raw.type ?? 'free_text') as ReceiptBlockType
        const isFixed = FIXED_HEIGHT_TYPES.includes(t)
        const estH = DEFAULT_BLOCK_HEIGHT[t] ?? 24
        const pos: BlockPosition = { x: 0, y, w: W, h: isFixed ? estH : null, z: 0 }
        blocks.push(blockFromLegacy(raw, pos))
        y += estH + 4
    }
    return {
        version: 3,
        paper_width: paper,
        font_size: v1.font_size ?? 'md',
        canvas_height: Math.max(400, y + 40),
        blocks,
    }
}

function migrateLegacyFlatToV3(legacy: LegacyFlatLayout): ReceiptTemplateLayout {
    const paper = legacy.paper_width ?? 'thermal_80'
    const types: ReceiptBlockType[] = []
    if (legacy.show_logo) types.push('logo')
    types.push('company_info')
    if (legacy.header_lines?.length) types.push('free_text')
    types.push('divider', 'doc_title', 'doc_number')
    if (legacy.show_customer !== false) types.push('customer')
    types.push('items_table', 'totals')
    if (legacy.show_payments !== false) types.push('payments')
    if (legacy.show_loyalty !== false) types.push('loyalty')
    if (legacy.show_qr !== false) types.push('qr')
    if (legacy.footer_lines?.length) types.push('free_text')
    let y = 0
    const blocks: ReceiptBlock[] = []
    for (const t of types) {
        const b = makeBlock(t, paper, y)
        if (t === 'free_text' && legacy.header_lines?.length && blocks.filter((bb) => bb.type === 'free_text').length === 0) {
            ;(b as FreeTextBlock).config = { lines: legacy.header_lines }
        } else if (t === 'free_text' && legacy.footer_lines?.length) {
            ;(b as FreeTextBlock).config = { lines: legacy.footer_lines }
        }
        if (t === 'items_table' && legacy.columns?.length) {
            ;(b as ItemsTableBlock).config = { columns: legacy.columns }
        }
        if (t === 'totals') {
            ;(b as TotalsBlock).config = { show_tax_breakdown: legacy.show_tax_breakdown !== false }
        }
        blocks.push(b)
        y += (b.position.h ?? DEFAULT_BLOCK_HEIGHT[t]) + 4
    }
    return {
        version: 3,
        paper_width: paper,
        font_size: legacy.font_size ?? 'md',
        canvas_height: Math.max(400, y + 40),
        blocks,
    }
}

export function normalizeLayout(input: unknown): ReceiptTemplateLayout {
    if (isV3Layout(input)) {
        const paper = input.paper_width ?? 'thermal_80'
        const W = paperWidthPx(paper)
        const blocks = input.blocks.map((b) => {
            const merged = { ...({ x: 0, y: 0, w: W, h: null as number | null, z: 0 }), ...(b.position ?? {}) }
            const x = Math.max(0, Math.min(W - 10, Math.round(merged.x)))
            const w = Math.max(10, Math.min(W - x, Math.round(merged.w)))
            return {
                ...b,
                position: {
                    x,
                    y: Math.max(0, Math.round(merged.y)),
                    w,
                    h: merged.h === null || merged.h === undefined ? null : Math.max(1, Math.round(merged.h)),
                    z: merged.z ?? 0,
                },
                style: defaultStyle(b.style ?? {}),
                enabled: b.enabled !== false,
                locked: !!b.locked,
            }
        })
        return {
            version: 3,
            paper_width: paper,
            font_size: input.font_size ?? 'md',
            canvas_height: input.canvas_height ?? 600,
            blocks,
        }
    }
    if (
        typeof input === 'object' &&
        input !== null &&
        (input as { version?: number }).version === 2 &&
        Array.isArray((input as { rows?: unknown }).rows)
    ) {
        return migrateV2ToV3(input as LegacyV2Layout)
    }
    if (
        typeof input === 'object' &&
        input !== null &&
        (input as { version?: number }).version === 1 &&
        Array.isArray((input as { blocks?: unknown }).blocks)
    ) {
        return migrateV1ToV3(input as LegacyV1Layout)
    }
    return migrateLegacyFlatToV3((input as LegacyFlatLayout) ?? {})
}

export function findBlock(layout: ReceiptTemplateLayout, blockId: string): ReceiptBlock | null {
    return layout.blocks.find((b) => b.id === blockId) ?? null
}

export function flattenBlocks(layout: ReceiptTemplateLayout): ReceiptBlock[] {
    return layout.blocks
}

export function nextZ(layout: ReceiptTemplateLayout): number {
    return Math.max(0, ...layout.blocks.map((b) => b.position.z ?? 0)) + 1
}

export function nextDropY(layout: ReceiptTemplateLayout): number {
    if (layout.blocks.length === 0) return 8
    return Math.max(...layout.blocks.map((b) => b.position.y + (b.position.h ?? 24))) + 8
}
