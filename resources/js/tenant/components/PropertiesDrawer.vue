<script setup lang="ts">
import { computed, ref } from 'vue'
import { useEditorModeStore } from '@tenant/stores/editorMode'
import { useBuilderSectionStore } from '@tenant/stores/builderSection'
import { useBlockCatalogStore } from '@tenant/stores/blockCatalog'
import MediaPickerModal from '@tenant/components/MediaPickerModal.vue'

const props = defineProps<{
  pageId: number
}>()

const showMediaPicker = ref(false)
const mediaPickerField = ref<string | null>(null)

function openMediaPicker(fieldKey: string) {
  mediaPickerField.value = fieldKey
  showMediaPicker.value = true
}

function onMediaSelected(asset: { asset_id: number; url: string; alt: string }) {
  if (mediaPickerField.value && section.value) {
    updateContent(mediaPickerField.value, asset)
  }
  showMediaPicker.value = false
  mediaPickerField.value = null
}

const editorMode = useEditorModeStore()
const sectionStore = useBuilderSectionStore()
const catalogStore = useBlockCatalogStore()

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
  sectionStore.updateSection(props.pageId, section.value.id, {
    content: { ...section.value.content, [key]: value },
  })
  editorMode.markDirty()
}

function updateStyleOverride(key: string, value: any) {
  if (!section.value) return
  sectionStore.updateSection(props.pageId, section.value.id, {
    style_overrides: { ...(section.value.style_overrides || {}), [key]: value },
  })
  editorMode.markDirty()
}

function updateLayout(key: string, value: any) {
  if (!section.value) return
  sectionStore.updateSection(props.pageId, section.value.id, {
    layout: { ...section.value.layout, [key]: value },
  })
  editorMode.markDirty()
}

function close() {
  editorMode.selectSection(null)
}
</script>

<template>
  <Transition name="slide">
    <div
      v-if="section && blockType"
      class="fixed top-11 right-0 bottom-0 w-72 bg-background border-l shadow-xl z-[90] overflow-y-auto"
    >
      <!-- Header -->
      <div class="sticky top-0 bg-background border-b px-4 py-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold">{{ blockType.name }}</h3>
        <button class="text-muted-foreground hover:text-foreground" @click="close">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="p-4 space-y-6">
        <!-- Content fields -->
        <div class="space-y-4">
          <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Contenido</h4>
          <div
            v-for="(field, key) in blockType.schema.fields"
            :key="key"
            class="space-y-1"
          >
            <label class="block text-xs font-medium text-muted-foreground">{{ field.label }}</label>

            <input
              v-if="field.type === 'string' || field.type === 'rich_text' || field.type === 'url'"
              :value="section.content[key]"
              type="text"
              class="w-full px-2 py-1.5 border rounded text-sm bg-background"
              @change="updateContent(key as string, ($event.target as HTMLInputElement).value)"
            />

            <input
              v-else-if="field.type === 'number'"
              :value="section.content[key]"
              type="number"
              :min="field.min"
              :max="field.max"
              class="w-full px-2 py-1.5 border rounded text-sm bg-background"
              @change="updateContent(key as string, Number(($event.target as HTMLInputElement).value))"
            />

            <select
              v-else-if="field.type === 'enum'"
              :value="section.content[key]"
              class="w-full px-2 py-1.5 border rounded text-sm bg-background"
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
              <span class="text-sm">{{ field.label }}</span>
            </label>

            <div v-else-if="field.type === 'asset'" class="space-y-2">
              <div v-if="section.content[key]?.url" class="relative group">
                <img :src="section.content[key].url" :alt="section.content[key].alt || ''" class="w-full h-24 object-cover rounded border" />
                <button
                  class="absolute top-1 right-1 p-1 bg-red-600 text-white rounded opacity-0 group-hover:opacity-100 transition-opacity"
                  @click="updateContent(key as string, null)"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <button
                class="w-full px-3 py-2 border border-dashed rounded text-xs text-muted-foreground hover:border-primary hover:text-primary transition-colors"
                @click="openMediaPicker(key as string)"
              >
                {{ section.content[key]?.url ? 'Cambiar imagen' : 'Seleccionar imagen' }}
              </button>
            </div>

            <div v-else-if="field.type === 'asset_list'" class="text-xs text-muted-foreground p-2 border border-dashed rounded">
              Galeria de imagenes (proximamente)
            </div>
          </div>
        </div>

        <!-- Style overrides -->
        <template v-if="blockType.schema.style_fields && Object.keys(blockType.schema.style_fields).length">
          <div class="space-y-4">
            <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Estilos</h4>
            <div
              v-for="(field, key) in blockType.schema.style_fields"
              :key="key"
              class="space-y-1"
            >
              <label class="block text-xs font-medium text-muted-foreground">{{ field.label }}</label>
              <div v-if="field.type === 'color'" class="flex items-center gap-2">
                <input
                  :value="section.style_overrides?.[key] || ''"
                  type="color"
                  class="w-8 h-8 rounded border cursor-pointer"
                  @change="updateStyleOverride(key as string, ($event.target as HTMLInputElement).value)"
                />
                <span class="text-xs text-muted-foreground">
                  {{ section.style_overrides?.[key] || 'theme' }}
                </span>
              </div>
              <select
                v-else-if="field.type === 'enum'"
                :value="section.style_overrides?.[key] || ''"
                class="w-full px-2 py-1.5 border rounded text-sm bg-background"
                @change="updateStyleOverride(key as string, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">Theme default</option>
                <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
          </div>
        </template>

        <!-- Layout -->
        <div class="space-y-4">
          <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Layout</h4>
          <div class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">Ancho</label>
            <select
              :value="section.layout.width"
              class="w-full px-2 py-1.5 border rounded text-sm bg-background"
              @change="updateLayout('width', ($event.target as HTMLSelectElement).value)"
            >
              <option value="full">Full width</option>
              <option value="contained">Contenido</option>
              <option value="narrow">Estrecho</option>
            </select>
          </div>
          <div class="space-y-1">
            <label class="block text-xs font-medium text-muted-foreground">Padding vertical</label>
            <select
              :value="section.layout.padding_y"
              class="w-full px-2 py-1.5 border rounded text-sm bg-background"
              @change="updateLayout('padding_y', ($event.target as HTMLSelectElement).value)"
            >
              <option value="none">Ninguno</option>
              <option value="sm">Pequeño</option>
              <option value="md">Medio</option>
              <option value="lg">Grande</option>
              <option value="xl">Extra grande</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <!-- Media Picker Modal -->
  <MediaPickerModal
    v-if="showMediaPicker"
    @select="onMediaSelected"
    @close="showMediaPicker = false"
  />
</template>

<style scoped>
.slide-enter-active, .slide-leave-active {
  transition: transform 0.2s ease;
}
.slide-enter-from, .slide-leave-to {
  transform: translateX(100%);
}
</style>
