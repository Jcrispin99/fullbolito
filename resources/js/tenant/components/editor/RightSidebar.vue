<script setup lang="ts">
import { ref, computed } from 'vue'
import { useEditorModeStore } from '@tenant/stores/editorMode'
import { useBuilderSectionStore } from '@tenant/stores/builderSection'
import { useBlockCatalogStore } from '@tenant/stores/blockCatalog'
import MediaPickerModal from '@tenant/components/MediaPickerModal.vue'
import RichTextEditor from '@tenant/components/blocks/RichTextEditor.vue'

const props = defineProps<{ pageId: number }>()

const editorMode = useEditorModeStore()
const sectionStore = useBuilderSectionStore()
const catalogStore = useBlockCatalogStore()

const tab = ref<'content' | 'style' | 'layout'>('content')
const showMediaPicker = ref(false)
const mediaPickerField = ref<string | null>(null)

const section = computed(() => {
  if (!editorMode.selectedSectionId) return null
  return sectionStore.sections.find(s => s.id === editorMode.selectedSectionId)
})

const blockType = computed(() => {
  if (!section.value) return null
  return catalogStore.getBlockType(section.value.block_type_key)
})

function updateContent(key: string, value: any) {
  if (!section.value) return
  sectionStore.updateContentLocal(section.value.id, { ...section.value.content, [key]: value })
}

function updateStyleOverride(key: string, value: any) {
  if (!section.value) return
  sectionStore.updateStyleLocal(section.value.id, { ...(section.value.style_overrides || {}), [key]: value })
}

function updateLayout(key: string, value: any) {
  if (!section.value) return
  sectionStore.updateLayoutLocal(section.value.id, { ...section.value.layout, [key]: value })
}

function updatePosition(key: string, value: any) {
  if (!section.value) return
  sectionStore.updateSectionLocal(section.value.id, { [key]: value })
}

const mediaPickerMultiple = ref(false)

function openMediaPicker(fieldKey: string, multiple = false) {
  mediaPickerField.value = fieldKey
  mediaPickerMultiple.value = multiple
  showMediaPicker.value = true
}

function onMediaSelected(asset: { asset_id: number; url: string; alt: string }) {
  if (mediaPickerField.value && section.value) {
    updateContent(mediaPickerField.value, asset)
  }
  showMediaPicker.value = false
  mediaPickerField.value = null
  mediaPickerMultiple.value = false
}

function onMediaSelectedMany(assets: { asset_id: number; url: string; alt: string }[]) {
  if (mediaPickerField.value && section.value) {
    const current = Array.isArray(section.value.content[mediaPickerField.value])
      ? section.value.content[mediaPickerField.value]
      : []
    updateContent(mediaPickerField.value, [...current, ...assets])
  }
  showMediaPicker.value = false
  mediaPickerField.value = null
  mediaPickerMultiple.value = false
}

function removeFromAssetList(fieldKey: string, idx: number) {
  if (!section.value) return
  const list = Array.isArray(section.value.content[fieldKey]) ? [...section.value.content[fieldKey]] : []
  list.splice(idx, 1)
  updateContent(fieldKey, list)
}

function moveAssetInList(fieldKey: string, idx: number, dir: -1 | 1) {
  if (!section.value) return
  const list = Array.isArray(section.value.content[fieldKey]) ? [...section.value.content[fieldKey]] : []
  const target = idx + dir
  if (target < 0 || target >= list.length) return
  ;[list[idx], list[target]] = [list[target], list[idx]]
  updateContent(fieldKey, list)
}

const spacingOptions = [
  { value: 'none', label: 'Ninguno' },
  { value: 'xs', label: 'XS' },
  { value: 'sm', label: 'S' },
  { value: 'md', label: 'M' },
  { value: 'lg', label: 'L' },
  { value: 'xl', label: 'XL' },
  { value: '2xl', label: '2XL' },
]
</script>

<template>
  <div class="w-72 bg-background border-l flex flex-col h-full overflow-hidden">
    <!-- No selection state -->
    <div v-if="!section" class="flex-1 flex items-center justify-center p-6">
      <p class="text-sm text-muted-foreground text-center">Selecciona un bloque para editar sus propiedades</p>
    </div>

    <template v-else>
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 border-b flex-shrink-0">
        <h3 class="text-xs font-semibold truncate">{{ blockType?.name || section.block_type_key }}</h3>
        <button class="text-muted-foreground hover:text-foreground" @click="editorMode.selectSection(null)">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Tabs -->
      <div class="flex border-b flex-shrink-0">
        <button
          v-for="t in (['content', 'style', 'layout'] as const)"
          :key="t"
          class="flex-1 py-2 text-xs font-medium text-center border-b-2 transition-colors"
          :class="tab === t ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
          @click="tab = t"
        >
          {{ t === 'content' ? 'Contenido' : t === 'style' ? 'Estilo' : 'Layout' }}
        </button>
      </div>

      <!-- Tab content -->
      <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <!-- CONTENT TAB -->
        <template v-if="tab === 'content' && blockType">
          <div v-for="(field, key) in blockType.schema.fields" :key="key" class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">{{ field.label }}</label>

            <input
              v-if="field.type === 'string' || field.type === 'url'"
              :value="section.content[key]"
              type="text"
              class="w-full px-2.5 py-1.5 border rounded text-sm bg-background"
              @change="updateContent(key as string, ($event.target as HTMLInputElement).value)"
            />

            <RichTextEditor
              v-else-if="field.type === 'rich_text'"
              :model-value="section.content[key]"
              :placeholder="field.label"
              minimal
              @update:model-value="updateContent(key as string, $event)"
            />

            <input
              v-else-if="field.type === 'number'"
              :value="section.content[key]"
              type="number"
              :min="field.min"
              :max="field.max"
              class="w-full px-2.5 py-1.5 border rounded text-sm bg-background"
              @change="updateContent(key as string, Number(($event.target as HTMLInputElement).value))"
            />

            <select
              v-else-if="field.type === 'enum'"
              :value="section.content[key]"
              class="w-full px-2.5 py-1.5 border rounded text-sm bg-background"
              @change="updateContent(key as string, ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
            </select>

            <div v-else-if="field.type === 'color'" class="flex items-center gap-2">
              <input
                :value="section.content[key]"
                type="color"
                class="w-8 h-8 rounded border cursor-pointer"
                @change="updateContent(key as string, ($event.target as HTMLInputElement).value)"
              />
              <span class="text-xs text-muted-foreground">{{ section.content[key] }}</span>
            </div>

            <label v-else-if="field.type === 'boolean'" class="flex items-center gap-2 cursor-pointer">
              <input
                :checked="section.content[key]"
                type="checkbox"
                class="rounded"
                @change="updateContent(key as string, ($event.target as HTMLInputElement).checked)"
              />
              <span class="text-xs">{{ field.label }}</span>
            </label>

            <!-- Asset picker -->
            <div v-else-if="field.type === 'asset'" class="space-y-1.5">
              <div v-if="section.content[key]?.url" class="relative group">
                <img :src="section.content[key].url" :alt="section.content[key].alt || ''" class="w-full h-20 object-cover rounded border" />
                <button
                  class="absolute top-1 right-1 p-1 bg-red-600 text-white rounded opacity-0 group-hover:opacity-100 transition-opacity"
                  @click="updateContent(key as string, null)"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
              <button
                class="w-full px-2.5 py-1.5 border border-dashed rounded text-xs text-muted-foreground hover:border-primary hover:text-primary transition-colors"
                @click="openMediaPicker(key as string)"
              >
                {{ section.content[key]?.url ? 'Cambiar imagen' : 'Seleccionar imagen' }}
              </button>
            </div>

            <!-- Asset list (gallery) -->
            <div v-else-if="field.type === 'asset_list'" class="space-y-1.5">
              <div v-if="Array.isArray(section.content[key]) && section.content[key].length" class="grid grid-cols-3 gap-1.5">
                <div v-for="(item, idx) in section.content[key]" :key="idx" class="relative group aspect-square rounded border overflow-hidden bg-muted">
                  <img v-if="item?.url" :src="item.url" :alt="item.alt || ''" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center text-[10px] text-muted-foreground">{{ idx + 1 }}</div>
                  <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1">
                    <button v-if="idx > 0" class="p-1 bg-white/90 rounded text-zinc-800 hover:bg-white" title="Mover arriba" @click="moveAssetInList(key as string, idx, -1)">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <button v-if="idx < section.content[key].length - 1" class="p-1 bg-white/90 rounded text-zinc-800 hover:bg-white" title="Mover abajo" @click="moveAssetInList(key as string, idx, 1)">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                    <button class="p-1 bg-red-600 rounded text-white hover:bg-red-700" title="Eliminar" @click="removeFromAssetList(key as string, idx)">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                  </div>
                </div>
              </div>
              <button
                class="w-full px-2.5 py-1.5 border border-dashed rounded text-xs text-muted-foreground hover:border-primary hover:text-primary transition-colors flex items-center justify-center gap-1"
                @click="openMediaPicker(key as string, true)"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                {{ Array.isArray(section.content[key]) && section.content[key].length ? `Agregar más (${section.content[key].length})` : 'Agregar imágenes' }}
              </button>
            </div>
          </div>
        </template>

        <!-- STYLE TAB -->
        <template v-if="tab === 'style' && blockType">
          <div v-if="!blockType.schema.style_fields || !Object.keys(blockType.schema.style_fields).length" class="text-center py-4 text-xs text-muted-foreground">
            Este bloque no tiene opciones de estilo
          </div>
          <div v-else v-for="(field, key) in blockType.schema.style_fields" :key="key" class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">{{ field.label }}</label>
            <div v-if="field.type === 'color'" class="flex items-center gap-2">
              <input
                :value="section.style_overrides?.[key] || ''"
                type="color"
                class="w-8 h-8 rounded border cursor-pointer"
                @change="updateStyleOverride(key as string, ($event.target as HTMLInputElement).value)"
              />
              <span class="text-xs text-muted-foreground">{{ section.style_overrides?.[key] || 'theme' }}</span>
            </div>
            <select
              v-else-if="field.type === 'enum'"
              :value="section.style_overrides?.[key] || ''"
              class="w-full px-2.5 py-1.5 border rounded text-sm bg-background"
              @change="updateStyleOverride(key as string, ($event.target as HTMLSelectElement).value)"
            >
              <option value="">Default</option>
              <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>
        </template>

        <!-- LAYOUT TAB -->
        <template v-if="tab === 'layout'">
          <!-- Width -->
          <div class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">Ancho</label>
            <select :value="section.layout.width" class="w-full px-2.5 py-1.5 border rounded text-sm bg-background" @change="updateLayout('width', ($event.target as HTMLSelectElement).value)">
              <option value="full">Completo</option>
              <option value="contained">Contenido</option>
              <option value="narrow">Estrecho</option>
            </select>
          </div>

          <!-- Alignment -->
          <div class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">Alineacion texto</label>
            <div class="flex gap-1">
              <button v-for="a in ['left','center','right']" :key="a" class="flex-1 py-1.5 border rounded text-xs transition-colors" :class="(section.layout.text_align || 'left') === a ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'" @click="updateLayout('text_align', a)">
                {{ a === 'left' ? '&#8676;' : a === 'center' ? '&#8596;' : '&#8677;' }}
              </button>
            </div>
          </div>

          <!-- Padding -->
          <div class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">Padding</label>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <span class="text-xs text-muted-foreground">Arriba</span>
                <select :value="section.layout.padding_top || section.layout.padding_y || 'md'" class="w-full px-2 py-1 border rounded text-xs bg-background" @change="updateLayout('padding_top', ($event.target as HTMLSelectElement).value)">
                  <option v-for="o in spacingOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>
              <div>
                <span class="text-xs text-muted-foreground">Abajo</span>
                <select :value="section.layout.padding_bottom || section.layout.padding_y || 'md'" class="w-full px-2 py-1 border rounded text-xs bg-background" @change="updateLayout('padding_bottom', ($event.target as HTMLSelectElement).value)">
                  <option v-for="o in spacingOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Background -->
          <div class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">Fondo</label>
            <select :value="section.layout.bg_type || 'none'" class="w-full px-2.5 py-1.5 border rounded text-sm bg-background" @change="updateLayout('bg_type', ($event.target as HTMLSelectElement).value)">
              <option value="none">Ninguno</option>
              <option value="color">Color</option>
              <option value="image">Imagen</option>
              <option value="gradient">Gradiente</option>
            </select>
            <input
              v-if="section.layout.bg_type === 'color'"
              :value="section.layout.bg_value || '#ffffff'"
              type="color"
              class="w-full h-8 rounded border cursor-pointer"
              @change="updateLayout('bg_value', ($event.target as HTMLInputElement).value)"
            />
            <input
              v-if="section.layout.bg_type === 'gradient'"
              :value="section.layout.bg_value || ''"
              type="text"
              class="w-full px-2.5 py-1.5 border rounded text-xs bg-background"
              placeholder="linear-gradient(135deg, #667eea, #764ba2)"
              @change="updateLayout('bg_value', ($event.target as HTMLInputElement).value)"
            />
          </div>

          <!-- Position mode -->
          <div class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">Modo de posicion</label>
            <div class="flex gap-1">
              <button
                class="flex-1 py-1.5 border rounded text-xs transition-colors"
                :class="section.position_mode === 'flow' || !section.position_mode ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                @click="updatePosition('position_mode', 'flow')"
              >
                Flujo
              </button>
              <button
                class="flex-1 py-1.5 border rounded text-xs transition-colors"
                :class="section.position_mode === 'absolute' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                @click="updatePosition('position_mode', 'absolute')"
              >
                Libre
              </button>
            </div>
          </div>

          <!-- Position controls (absolute mode only) -->
          <template v-if="section.position_mode === 'absolute'">
            <div class="space-y-1">
              <label class="block text-xs font-medium text-muted-foreground">Posicion (% del contenedor)</label>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <span class="text-xs text-muted-foreground">X</span>
                  <input :value="Math.round(section.position_x || 0)" type="number" min="0" max="100" class="w-full px-2 py-1 border rounded text-xs bg-background" @change="updatePosition('position_x', Number(($event.target as HTMLInputElement).value))" />
                </div>
                <div>
                  <span class="text-xs text-muted-foreground">Y</span>
                  <input :value="Math.round(section.position_y || 0)" type="number" min="0" class="w-full px-2 py-1 border rounded text-xs bg-background" @change="updatePosition('position_y', Number(($event.target as HTMLInputElement).value))" />
                </div>
              </div>
            </div>

            <div class="space-y-1">
              <label class="block text-xs font-medium text-muted-foreground">Tamano</label>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <span class="text-xs text-muted-foreground">Ancho %</span>
                  <input :value="Math.round(section.element_width || 50)" type="number" min="10" max="100" class="w-full px-2 py-1 border rounded text-xs bg-background" @change="updatePosition('element_width', Number(($event.target as HTMLInputElement).value))" />
                </div>
                <div>
                  <span class="text-xs text-muted-foreground">Alto px</span>
                  <input :value="Math.round(section.element_height || 0)" type="number" min="0" class="w-full px-2 py-1 border rounded text-xs bg-background" placeholder="Auto" @change="updatePosition('element_height', Number(($event.target as HTMLInputElement).value) || null)" />
                </div>
              </div>
            </div>

            <div class="space-y-1">
              <label class="block text-xs font-medium text-muted-foreground">Rotacion</label>
              <div class="flex items-center gap-2">
                <input :value="Math.round(section.rotation || 0)" type="range" min="-180" max="180" class="flex-1" @input="updatePosition('rotation', Number(($event.target as HTMLInputElement).value))" />
                <span class="text-xs text-muted-foreground w-10 text-right">{{ Math.round(section.rotation || 0) }}°</span>
              </div>
            </div>
          </template>

          <!-- Visibility -->
          <div class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">Visibilidad</label>
            <select :value="section.layout.visibility || 'all'" class="w-full px-2.5 py-1.5 border rounded text-sm bg-background" @change="updateLayout('visibility', ($event.target as HTMLSelectElement).value)">
              <option value="all">Todos</option>
              <option value="desktop">Solo desktop</option>
              <option value="tablet">Solo tablet</option>
              <option value="mobile">Solo movil</option>
            </select>
          </div>

          <!-- Anchor -->
          <div class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">Anchor ID</label>
            <input :value="section.layout.anchor_id || ''" type="text" class="w-full px-2.5 py-1.5 border rounded text-xs bg-background" placeholder="ej: contacto" @change="updateLayout('anchor_id', ($event.target as HTMLInputElement).value || null)" />
          </div>
        </template>
      </div>
    </template>

    <MediaPickerModal
      v-if="showMediaPicker"
      :multiple="mediaPickerMultiple"
      @select="onMediaSelected"
      @select-many="onMediaSelectedMany"
      @close="showMediaPicker = false"
    />
  </div>
</template>
