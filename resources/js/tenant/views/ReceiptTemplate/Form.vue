<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Button } from '@/components/ui/button'
import { UnderlineInput } from '@/components/ui/underline-input'
import { SearchSelect } from '@/components/ui/search-select'

import {
    Trash2,
    Plus,
    Bold,
    Italic,
    Underline,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Upload,
    ImageOff,
    Eye,
    EyeOff,
    Lock,
    Unlock,
    Copy,
    ChevronUp,
    ChevronDown,
    ChevronsUp,
    ChevronsDown,
    Magnet,
} from 'lucide-vue-next'

import ReceiptRenderer from '@tenant/components/ReceiptRenderer.vue'
import {
    BLOCK_LABELS,
    FREE_BLOCK_TYPES,
    TOGGLEABLE_UNIQUE_TYPES,
    findBlock,
    flattenBlocks,
    makeBlock,
    nextDropY,
    nextZ,
    normalizeLayout,
    paperWidthPx,
    type BlockSize,
    type FreeTextBlock,
    type ItemsTableBlock,
    type KeyValueListBlock,
    type ParagraphBlock,
    type ReceiptBlock,
    type ReceiptBlockType,
    type ReceiptColumn,
    type ReceiptTemplateLayout,
} from '@tenant/components/receiptBlocks'
import {
    makeMockReceiptCompany,
    makeMockReceiptSale,
} from '@tenant/lib/mockReceiptSale'

const props = defineProps<{
    modelValue: ReceiptTemplateLayout
    logoUrl?: string | null
    isUploadingLogo?: boolean
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', layout: ReceiptTemplateLayout): void
    (e: 'upload-logo', file: File): void
    (e: 'delete-logo'): void
}>()

const logoInput = ref<HTMLInputElement | null>(null)
function pickLogo(): void {
    logoInput.value?.click()
}
function onLogoChange(ev: Event): void {
    const file = (ev.target as HTMLInputElement).files?.[0]
    if (file) emit('upload-logo', file)
    if (logoInput.value) logoInput.value.value = ''
}

const layout = computed<ReceiptTemplateLayout>({
    get: () => normalizeLayout(props.modelValue),
    set: (v) => emit('update:modelValue', v),
})

const selectedId = ref<string | null>(null)
const snapEnabled = ref(true)
const showGrid = ref(false)

watch(
    () => flattenBlocks(layout.value).map((b) => b.id),
    (ids) => {
        if (selectedId.value && !ids.includes(selectedId.value)) {
            selectedId.value = null
        }
    },
)

const selectedBlock = computed<ReceiptBlock | null>(() => {
    if (!selectedId.value) return null
    return findBlock(layout.value, selectedId.value)
})

const allBlocks = computed(() => flattenBlocks(layout.value))
const existingTypes = computed(() => new Set(allBlocks.value.map((b) => b.type)))
const addableUniqueTypes = computed(() =>
    TOGGLEABLE_UNIQUE_TYPES.filter((t) => !existingTypes.value.has(t)),
)

const paperOptions = [
    { value: 'thermal_58', label: 'Térmico 58 mm' },
    { value: 'thermal_80', label: 'Térmico 80 mm' },
    { value: 'a4', label: 'A4' },
]
const fontOptions = [
    { value: 'sm', label: 'Pequeña' },
    { value: 'md', label: 'Mediana' },
    { value: 'lg', label: 'Grande' },
]
const sizeOverrideOptions = [
    { value: '', label: 'Por defecto' },
    { value: 'sm', label: 'Pequeño' },
    { value: 'md', label: 'Mediano' },
    { value: 'lg', label: 'Grande' },
]
const dividerStyleOptions = [
    { value: 'dashed', label: 'Discontinuo' },
    { value: 'solid', label: 'Sólido' },
    { value: 'dotted', label: 'Punteado' },
]
const columnLabels: Record<ReceiptColumn['key'], string> = {
    qty: 'Cantidad',
    name: 'Descripción',
    unit: 'Precio Unit.',
    sub: 'Subtotal',
}

const mockSale = makeMockReceiptSale()
const mockCompany = makeMockReceiptCompany()

function setLayout(updater: (l: ReceiptTemplateLayout) => void): void {
    const next = JSON.parse(JSON.stringify(layout.value)) as ReceiptTemplateLayout
    updater(next)
    layout.value = next
}

function findBlockMutable(l: ReceiptTemplateLayout, id: string): ReceiptBlock | null {
    return l.blocks.find((b) => b.id === id) ?? null
}

function isLocked(block: ReceiptBlock): boolean {
    return !!block.locked
}

function updateSelected(updater: (b: ReceiptBlock) => void): void {
    if (!selectedBlock.value) return
    const id = selectedBlock.value.id
    setLayout((l) => {
        const b = findBlockMutable(l, id)
        if (b) updater(b)
    })
}

function setStyle<K extends keyof NonNullable<ReceiptBlock['style']>>(key: K, value: NonNullable<ReceiptBlock['style']>[K]): void {
    updateSelected((b) => {
        b.style = b.style ?? {}
        ;(b.style as any)[key] = value
    })
}

function getStyle<K extends keyof NonNullable<ReceiptBlock['style']>>(key: K): NonNullable<ReceiptBlock['style']>[K] | undefined {
    return selectedBlock.value?.style?.[key] as any
}

function alignActive(value: 'left' | 'center' | 'right'): boolean {
    return (selectedBlock.value?.style?.align ?? 'left') === value
}

function setSizeOverride(v: string | number | boolean | null | undefined): void {
    const value: BlockSize | null = !v || v === '' ? null : (String(v) as BlockSize)
    setStyle('size_override', value)
}

function asFreeText(b: ReceiptBlock): FreeTextBlock { return b as FreeTextBlock }
function asKv(b: ReceiptBlock): KeyValueListBlock { return b as KeyValueListBlock }
function asItems(b: ReceiptBlock): ItemsTableBlock { return b as ItemsTableBlock }
function asParagraph(b: ReceiptBlock): ParagraphBlock { return b as ParagraphBlock }

function setParagraphText(text: string): void {
    updateSelected((b) => {
        const p = b as ParagraphBlock
        p.config.text = text
    })
}

function addFreeTextLine(): void {
    updateSelected((b) => { (b as FreeTextBlock).config.lines.push('') })
}
function removeFreeTextLine(i: number): void {
    updateSelected((b) => { (b as FreeTextBlock).config.lines.splice(i, 1) })
}
function setFreeTextLine(i: number, val: string): void {
    updateSelected((b) => {
        const ft = b as FreeTextBlock
        ft.config.lines[i] = val
    })
}

function addKvItem(): void {
    updateSelected((b) => { (b as KeyValueListBlock).config.items.push({ key: '', value: '' }) })
}
function removeKvItem(i: number): void {
    updateSelected((b) => { (b as KeyValueListBlock).config.items.splice(i, 1) })
}
function setKvItem(i: number, field: 'key' | 'value', val: string): void {
    updateSelected((b) => {
        const item = (b as KeyValueListBlock).config.items[i]
        if (item) item[field] = val
    })
}

function setColumnEnabled(idx: number, v: boolean): void {
    updateSelected((b) => {
        const c = (b as ItemsTableBlock).config.columns[idx]
        if (c) c.enabled = v
    })
}
function setColumnLabel(idx: number, v: string): void {
    updateSelected((b) => {
        const c = (b as ItemsTableBlock).config.columns[idx]
        if (c) c.label = v
    })
}

// ---- Block ops ----
function toggleBlock(id: string, enabled: boolean): void {
    setLayout((l) => {
        const b = findBlockMutable(l, id)
        if (b) b.enabled = enabled
    })
}

function deleteBlock(id: string): void {
    setLayout((l) => {
        const idx = l.blocks.findIndex((b) => b.id === id)
        if (idx < 0) return
        const b = l.blocks[idx]
        if (b?.locked) return
        l.blocks.splice(idx, 1)
    })
    if (selectedId.value === id) selectedId.value = null
}

function duplicateBlock(id: string): void {
    let newId: string | null = null
    setLayout((l) => {
        const b = findBlockMutable(l, id)
        if (!b) return
        const clone = JSON.parse(JSON.stringify(b)) as ReceiptBlock
        clone.id = `${b.type}_${Date.now().toString(36)}_${Math.floor(Math.random() * 1000)}`
        clone.locked = false
        clone.position = {
            ...b.position,
            x: Math.min(paperWidthPx(l.paper_width) - clone.position.w, b.position.x + 10),
            y: b.position.y + 10,
            z: nextZ(l),
        }
        l.blocks.push(clone)
        newId = clone.id
    })
    if (newId) selectedId.value = newId
}

function addBlock(type: ReceiptBlockType): void {
    const y = nextDropY(layout.value)
    const b = makeBlock(type, layout.value.paper_width, y)
    setLayout((l) => {
        b.position.z = nextZ(l)
        l.blocks.push(b)
    })
    selectedId.value = b.id
}

defineExpose({ addBlock })

function onUpdatePosition(payload: { id: string; x: number; y: number }): void {
    setLayout((l) => {
        const b = findBlockMutable(l, payload.id)
        if (b) {
            b.position.x = payload.x
            b.position.y = payload.y
        }
    })
}

function onUpdateSize(payload: { id: string; w: number; h: number; x?: number; y?: number }): void {
    setLayout((l) => {
        const b = findBlockMutable(l, payload.id)
        if (!b) return
        b.position.w = payload.w
        b.position.h = payload.h
        if (payload.x !== undefined) b.position.x = payload.x
        if (payload.y !== undefined) b.position.y = payload.y
    })
}

function onUpdateCanvasHeight(h: number): void {
    setLayout((l) => { l.canvas_height = h })
}

function setPositionField(field: 'x' | 'y' | 'w' | 'h', value: number | null): void {
    updateSelected((b) => {
        if (field === 'h') {
            b.position.h = value
        } else if (value !== null) {
            ;(b.position as any)[field] = Math.max(0, Math.round(value))
        }
    })
}

function bringForward(): void {
    if (!selectedId.value) return
    const id = selectedId.value
    setLayout((l) => {
        const b = findBlockMutable(l, id)
        if (b) b.position.z = (b.position.z ?? 0) + 1
    })
}
function sendBackward(): void {
    if (!selectedId.value) return
    const id = selectedId.value
    setLayout((l) => {
        const b = findBlockMutable(l, id)
        if (b) b.position.z = Math.max(0, (b.position.z ?? 0) - 1)
    })
}
function bringToFront(): void {
    if (!selectedId.value) return
    const id = selectedId.value
    setLayout((l) => {
        const b = findBlockMutable(l, id)
        if (b) b.position.z = nextZ(l)
    })
}
function sendToBack(): void {
    if (!selectedId.value) return
    const id = selectedId.value
    setLayout((l) => {
        const b = findBlockMutable(l, id)
        if (b) b.position.z = 0
    })
}

function toggleLock(): void {
    if (!selectedBlock.value) return
    const id = selectedBlock.value.id
    setLayout((l) => {
        const b = findBlockMutable(l, id)
        if (b) b.locked = !b.locked
    })
}

function onCanvasKey(e: KeyboardEvent): void {
    if (!selectedBlock.value) return
    const target = e.target as HTMLElement
    if (target?.matches?.('input, textarea, [contenteditable]')) return
    const step = e.shiftKey ? 10 : 1
    if (e.key === 'ArrowLeft') {
        e.preventDefault()
        updateSelected((b) => { b.position.x = Math.max(0, b.position.x - step) })
    } else if (e.key === 'ArrowRight') {
        e.preventDefault()
        const W = paperWidthPx(layout.value.paper_width)
        updateSelected((b) => { b.position.x = Math.min(W - b.position.w, b.position.x + step) })
    } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        updateSelected((b) => { b.position.y = Math.max(0, b.position.y - step) })
    } else if (e.key === 'ArrowDown') {
        e.preventDefault()
        updateSelected((b) => { b.position.y = b.position.y + step })
    } else if (e.key === 'Delete' || e.key === 'Backspace') {
        if (selectedBlock.value && !selectedBlock.value.locked) {
            e.preventDefault()
            deleteBlock(selectedBlock.value.id)
        }
    }
}

const previewWrapperStyle = computed(() => {
    const W = paperWidthPx(layout.value.paper_width)
    return {
        width: `${W + 60}px`,
    }
})
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-12">
        <!-- LEFT: paper + add palette -->
        <div class="xl:col-span-3 space-y-3">
            <Card>
                <CardContent class="pt-5 pb-4 space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lienzo</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1.5">
                            <Label class="text-[11px]">Ancho papel</Label>
                            <SearchSelect
                                :model-value="layout.paper_width"
                                :options="paperOptions"
                                :show-create="false"
                                @update:model-value="(v: any) => setLayout((l) => { l.paper_width = v })"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[11px]">Fuente</Label>
                            <SearchSelect
                                :model-value="layout.font_size"
                                :options="fontOptions"
                                :show-create="false"
                                @update:model-value="(v: any) => setLayout((l) => { l.font_size = v })"
                            />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-[11px]">Altura del lienzo (px)</Label>
                        <UnderlineInput
                            type="number"
                            :model-value="layout.canvas_height"
                            @update:model-value="(v: any) => setLayout((l) => { l.canvas_height = Math.max(120, Number(v) || 120) })"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-[11px] text-muted-foreground cursor-pointer">
                        <Checkbox :checked="snapEnabled" @update:checked="(v: boolean) => (snapEnabled = v)" />
                        <Magnet class="w-3 h-3" />
                        <span>Snap a la cuadrícula (5 px)</span>
                    </label>
                    <label class="flex items-center gap-2 text-[11px] text-muted-foreground cursor-pointer">
                        <Checkbox :checked="showGrid" @update:checked="(v: boolean) => (showGrid = v)" />
                        <span>Mostrar grilla guía</span>
                    </label>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-5 pb-4 space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Añadir bloque</h3>
                    <p class="text-[11px] text-muted-foreground">
                        Aparece debajo del último. Después arrástralo donde quieras.
                    </p>
                    <div class="grid grid-cols-2 gap-1.5">
                        <Button
                            v-for="t in addableUniqueTypes"
                            :key="`pal-${t}`"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs justify-start"
                            @click="addBlock(t)"
                        >
                            <Plus class="w-3 h-3 mr-1 shrink-0" />
                            <span class="truncate">{{ BLOCK_LABELS[t] }}</span>
                        </Button>
                        <Button
                            v-for="t in FREE_BLOCK_TYPES"
                            :key="`palf-${t}`"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs justify-start"
                            @click="addBlock(t)"
                        >
                            <Plus class="w-3 h-3 mr-1 shrink-0" />
                            <span class="truncate">{{ BLOCK_LABELS[t] }}</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-5 pb-4 space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Cómo usar</h3>
                    <ul class="text-[11px] text-muted-foreground space-y-1.5 list-disc pl-4">
                        <li><b>Click</b> en un bloque → seleccionar</li>
                        <li><b>Arrastrá el bloque</b> → mover libremente (X / Y)</li>
                        <li><b>Cuadritos azules</b> en los bordes/esquinas → redimensionar</li>
                        <li><b>Flechas del teclado</b> → mover 1 px (Shift = 10 px)</li>
                        <li><b>Delete</b> → eliminar bloque seleccionado</li>
                        <li><b>Barra inferior gris</b> del lienzo → cambiar la altura</li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <!-- CENTER: canvas -->
        <div class="xl:col-span-6 order-first xl:order-none">
            <div class="xl:sticky xl:top-4">
                <div class="text-xs text-muted-foreground mb-2 px-1 flex items-center justify-between">
                    <span>Lienzo — {{ paperWidthPx(layout.paper_width) }} × {{ layout.canvas_height }} px</span>
                    <span v-if="selectedBlock" class="text-[11px]">
                        {{ BLOCK_LABELS[selectedBlock.type] }}
                        — X:{{ selectedBlock.position.x }} Y:{{ selectedBlock.position.y }}
                        / W:{{ selectedBlock.position.w }} H:{{ selectedBlock.position.h ?? '—' }}
                    </span>
                </div>
                <div
                    class="rcpt-stage"
                    tabindex="0"
                    :style="previewWrapperStyle"
                    @keydown="onCanvasKey"
                >
                    <ReceiptRenderer
                        :template="layout"
                        :sale="mockSale"
                        :company="mockCompany"
                        :template-logo-url="logoUrl"
                        :editable="true"
                        :selected-id="selectedId"
                        :snap="snapEnabled"
                        :snap-size="5"
                        :show-grid="showGrid"
                        @select="(id: string | null) => (selectedId = id)"
                        @update-position="onUpdatePosition"
                        @update-size="onUpdateSize"
                        @update-canvas-height="onUpdateCanvasHeight"
                    />
                </div>
            </div>
        </div>

        <!-- RIGHT: properties -->
        <div class="xl:col-span-3">
            <Card v-if="selectedBlock" class="xl:sticky xl:top-4">
                <CardContent class="pt-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold">{{ BLOCK_LABELS[selectedBlock.type] }}</h3>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                :class="['shrink-0 p-1 rounded hover:bg-muted', selectedBlock.enabled ? 'text-foreground' : 'text-muted-foreground/50']"
                                @click="toggleBlock(selectedBlock.id, !selectedBlock.enabled)"
                                :aria-label="selectedBlock.enabled ? 'Ocultar' : 'Mostrar'"
                            >
                                <Eye v-if="selectedBlock.enabled" class="w-3.5 h-3.5" />
                                <EyeOff v-else class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                class="shrink-0 p-1 rounded hover:bg-muted"
                                @click="toggleLock"
                                :aria-label="selectedBlock.locked ? 'Desbloquear' : 'Bloquear'"
                            >
                                <Lock v-if="selectedBlock.locked" class="w-3.5 h-3.5" />
                                <Unlock v-else class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                class="shrink-0 p-1 rounded hover:bg-muted"
                                @click="duplicateBlock(selectedBlock.id)"
                                aria-label="Duplicar"
                            >
                                <Copy class="w-3.5 h-3.5" />
                            </button>
                            <button
                                v-if="!isLocked(selectedBlock)"
                                type="button"
                                class="text-destructive/70 hover:text-destructive p-1 rounded hover:bg-destructive/10"
                                @click="deleteBlock(selectedBlock.id)"
                                aria-label="Eliminar"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Position -->
                    <div class="space-y-2 pb-3 border-b">
                        <Label class="text-xs">Posición y tamaño (px)</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground">X</Label>
                                <UnderlineInput type="number" :model-value="selectedBlock.position.x"
                                    @update:model-value="(v: any) => setPositionField('x', Number(v) || 0)" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground">Y</Label>
                                <UnderlineInput type="number" :model-value="selectedBlock.position.y"
                                    @update:model-value="(v: any) => setPositionField('y', Number(v) || 0)" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground">Ancho</Label>
                                <UnderlineInput type="number" :model-value="selectedBlock.position.w"
                                    @update:model-value="(v: any) => setPositionField('w', Number(v) || 0)" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground">Alto</Label>
                                <UnderlineInput type="number" :model-value="selectedBlock.position.h ?? ''"
                                    @update:model-value="(v: any) => setPositionField('h', v === '' ? null : Number(v))" />
                            </div>
                        </div>
                        <div class="flex items-center gap-1 pt-1">
                            <Button type="button" variant="outline" size="icon" class="h-7 w-7" @click="bringToFront" title="Traer al frente">
                                <ChevronsUp class="w-3.5 h-3.5" />
                            </Button>
                            <Button type="button" variant="outline" size="icon" class="h-7 w-7" @click="bringForward" title="Adelantar">
                                <ChevronUp class="w-3.5 h-3.5" />
                            </Button>
                            <Button type="button" variant="outline" size="icon" class="h-7 w-7" @click="sendBackward" title="Atrasar">
                                <ChevronDown class="w-3.5 h-3.5" />
                            </Button>
                            <Button type="button" variant="outline" size="icon" class="h-7 w-7" @click="sendToBack" title="Enviar al fondo">
                                <ChevronsDown class="w-3.5 h-3.5" />
                            </Button>
                            <span class="text-[10px] text-muted-foreground ml-2">Capa: {{ selectedBlock.position.z ?? 0 }}</span>
                        </div>
                    </div>

                    <!-- Style -->
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Texto</Label>
                            <div class="flex items-center gap-1">
                                <Button type="button" :variant="alignActive('left') ? 'default' : 'outline'" size="icon" class="h-8 w-8" @click="setStyle('align', 'left')">
                                    <AlignLeft class="w-3.5 h-3.5" />
                                </Button>
                                <Button type="button" :variant="alignActive('center') ? 'default' : 'outline'" size="icon" class="h-8 w-8" @click="setStyle('align', 'center')">
                                    <AlignCenter class="w-3.5 h-3.5" />
                                </Button>
                                <Button type="button" :variant="alignActive('right') ? 'default' : 'outline'" size="icon" class="h-8 w-8" @click="setStyle('align', 'right')">
                                    <AlignRight class="w-3.5 h-3.5" />
                                </Button>
                                <Button type="button" :variant="getStyle('bold') ? 'default' : 'outline'" size="icon" class="h-8 w-8 ml-1" @click="setStyle('bold', !getStyle('bold'))">
                                    <Bold class="w-3.5 h-3.5" />
                                </Button>
                                <Button type="button" :variant="getStyle('italic') ? 'default' : 'outline'" size="icon" class="h-8 w-8" @click="setStyle('italic', !getStyle('italic'))">
                                    <Italic class="w-3.5 h-3.5" />
                                </Button>
                                <Button type="button" :variant="getStyle('underline') ? 'default' : 'outline'" size="icon" class="h-8 w-8" @click="setStyle('underline', !getStyle('underline'))">
                                    <Underline class="w-3.5 h-3.5" />
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-xs">Tamaño de texto</Label>
                            <SearchSelect
                                :model-value="getStyle('size_override') ?? ''"
                                :options="sizeOverrideOptions"
                                :show-create="false"
                                @update:model-value="setSizeOverride"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground">Color texto</Label>
                                <input type="color" class="w-full h-8 rounded border" :value="getStyle('color') ?? '#000000'"
                                    @input="(e: Event) => setStyle('color', (e.target as HTMLInputElement).value)" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground">Fondo</Label>
                                <input type="color" class="w-full h-8 rounded border" :value="getStyle('background') ?? '#ffffff'"
                                    @input="(e: Event) => setStyle('background', (e.target as HTMLInputElement).value)" />
                            </div>
                        </div>
                        <Button v-if="getStyle('background')" type="button" variant="ghost" size="sm" class="h-6 text-[11px] text-muted-foreground"
                            @click="setStyle('background', null)">Quitar fondo</Button>

                        <div class="grid grid-cols-3 gap-2">
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground">Padding</Label>
                                <UnderlineInput type="number" :model-value="getStyle('padding') ?? 0"
                                    @update:model-value="(v: any) => setStyle('padding', Number(v) || 0)" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground">Borde</Label>
                                <UnderlineInput type="number" :model-value="getStyle('border_width') ?? 0"
                                    @update:model-value="(v: any) => setStyle('border_width', Number(v) || 0)" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] text-muted-foreground">Radio</Label>
                                <UnderlineInput type="number" :model-value="getStyle('border_radius') ?? 0"
                                    @update:model-value="(v: any) => setStyle('border_radius', Number(v) || 0)" />
                            </div>
                        </div>
                    </div>

                    <!-- Type-specific -->
                    <div v-if="selectedBlock.type === 'free_text'" class="space-y-2 pt-2 border-t">
                        <div class="flex items-center justify-between">
                            <Label class="text-xs">Líneas de texto</Label>
                            <Button type="button" variant="outline" size="sm" class="h-7" @click="addFreeTextLine">
                                <Plus class="w-3.5 h-3.5 mr-1" /> Línea
                            </Button>
                        </div>
                        <div v-for="(line, i) in asFreeText(selectedBlock).config.lines" :key="`line-${i}`" class="flex items-center gap-2">
                            <UnderlineInput :model-value="line" placeholder="Texto" class="flex-1"
                                @update:model-value="(v: any) => setFreeTextLine(i, String(v ?? ''))" />
                            <Button type="button" variant="ghost" size="icon" class="h-7 w-7 text-destructive" @click="removeFreeTextLine(i)">
                                <Trash2 class="w-3.5 h-3.5" />
                            </Button>
                        </div>
                    </div>

                    <div v-else-if="selectedBlock.type === 'paragraph'" class="space-y-2 pt-2 border-t">
                        <Label class="text-xs">Texto del párrafo</Label>
                        <textarea
                            class="w-full min-h-[120px] rounded-md border bg-background px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"
                            :value="asParagraph(selectedBlock).config.text"
                            placeholder="Escribe políticas, condiciones o notas largas."
                            @input="(e: Event) => setParagraphText((e.target as HTMLTextAreaElement).value)"
                        />
                    </div>

                    <div v-else-if="selectedBlock.type === 'key_value_list'" class="space-y-2 pt-2 border-t">
                        <div class="flex items-center justify-between">
                            <Label class="text-xs">Pares clave-valor</Label>
                            <Button type="button" variant="outline" size="sm" class="h-7" @click="addKvItem">
                                <Plus class="w-3.5 h-3.5 mr-1" /> Ítem
                            </Button>
                        </div>
                        <div v-for="(item, i) in asKv(selectedBlock).config.items" :key="`kv-${i}`" class="flex items-center gap-1">
                            <UnderlineInput :model-value="item.key" placeholder="Clave" class="flex-1"
                                @update:model-value="(v: any) => setKvItem(i, 'key', String(v ?? ''))" />
                            <UnderlineInput :model-value="item.value" placeholder="Valor" class="flex-1"
                                @update:model-value="(v: any) => setKvItem(i, 'value', String(v ?? ''))" />
                            <Button type="button" variant="ghost" size="icon" class="h-7 w-7 text-destructive" @click="removeKvItem(i)">
                                <Trash2 class="w-3.5 h-3.5" />
                            </Button>
                        </div>
                    </div>

                    <div v-else-if="selectedBlock.type === 'divider'" class="space-y-2 pt-2 border-t">
                        <Label class="text-xs">Estilo de línea</Label>
                        <SearchSelect
                            :model-value="(selectedBlock as any).config?.style ?? 'dashed'"
                            :options="dividerStyleOptions"
                            :show-create="false"
                            @update:model-value="(v: any) => updateSelected((b) => { (b as any).config = { ...((b as any).config ?? {}), style: v } })"
                        />
                    </div>

                    <div v-else-if="selectedBlock.type === 'items_table'" class="space-y-2 pt-2 border-t">
                        <Label class="text-xs">Columnas</Label>
                        <div v-for="(col, idx) in asItems(selectedBlock).config.columns" :key="`col-${col.key}`" class="flex items-center gap-2">
                            <Checkbox :checked="col.enabled" @update:checked="(v: boolean) => setColumnEnabled(idx, v)" />
                            <span class="text-xs w-24 text-muted-foreground">
                                {{ columnLabels[col.key] }}
                            </span>
                            <UnderlineInput :model-value="col.label" class="flex-1"
                                @update:model-value="(v: any) => setColumnLabel(idx, String(v ?? ''))" />
                        </div>
                    </div>

                    <div v-else-if="selectedBlock.type === 'totals'" class="space-y-2 pt-2 border-t">
                        <label class="flex items-center space-x-2 text-xs">
                            <Checkbox
                                :checked="(selectedBlock as any).config?.show_tax_breakdown !== false"
                                @update:checked="(v: boolean) => updateSelected((b) => { (b as any).config = { ...((b as any).config ?? {}), show_tax_breakdown: v } })"
                            />
                            <span>Mostrar Subtotal + IGV</span>
                        </label>
                    </div>

                    <div v-else-if="selectedBlock.type === 'doc_number'" class="space-y-2 pt-2 border-t">
                        <label class="flex items-center space-x-2 text-xs">
                            <Checkbox :checked="(selectedBlock as any).config?.show_date !== false"
                                @update:checked="(v: boolean) => updateSelected((b) => { (b as any).config = { ...((b as any).config ?? {}), show_date: v } })" />
                            <span>Mostrar fecha</span>
                        </label>
                        <label class="flex items-center space-x-2 text-xs">
                            <Checkbox :checked="(selectedBlock as any).config?.show_seller !== false"
                                @update:checked="(v: boolean) => updateSelected((b) => { (b as any).config = { ...((b as any).config ?? {}), show_seller: v } })" />
                            <span>Mostrar cajero</span>
                        </label>
                    </div>

                    <div v-else-if="selectedBlock.type === 'logo'" class="space-y-2 pt-2 border-t">
                        <Label class="text-xs">Logo del comprobante</Label>
                        <p class="text-[11px] text-muted-foreground">PNG, JPG, SVG o WEBP. Máx. 1 MB.</p>
                        <div class="flex items-center gap-3">
                            <div class="h-16 w-24 rounded border bg-muted/30 flex items-center justify-center overflow-hidden shrink-0">
                                <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="max-h-full max-w-full object-contain" />
                                <ImageOff v-else class="w-5 h-5 text-muted-foreground" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Button type="button" variant="outline" size="sm" class="h-7" :disabled="isUploadingLogo" @click="pickLogo">
                                    <Upload class="w-3.5 h-3.5 mr-1" />
                                    {{ logoUrl ? 'Cambiar' : 'Subir logo' }}
                                </Button>
                                <Button v-if="logoUrl" type="button" variant="ghost" size="sm" class="h-7 text-destructive"
                                    :disabled="isUploadingLogo" @click="$emit('delete-logo')">
                                    <Trash2 class="w-3.5 h-3.5 mr-1" /> Quitar
                                </Button>
                            </div>
                            <input ref="logoInput" type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                class="hidden" @change="onLogoChange" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-else class="xl:sticky xl:top-4">
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground text-center">
                        Selecciona un bloque para editarlo, o arrastrá uno desde la paleta.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<style scoped>
.rcpt-stage {
    background: #f1f5f9;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 24px 30px 36px 30px;
    overflow: auto;
    max-height: calc(100vh - 8rem);
    margin: 0 auto;
    outline: none;
}
.rcpt-stage:focus {
    border-color: #94a3b8;
}
</style>
