<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useMediaStore } from '@tenant/stores/media'
import type { SiteAsset } from '@/types/builder'

const props = withDefaults(defineProps<{
  multiple?: boolean
}>(), {
  multiple: false,
})

const emit = defineEmits<{
  select: [asset: { asset_id: number; url: string; alt: string }]
  selectMany: [assets: { asset_id: number; url: string; alt: string }[]]
  close: []
}>()

const mediaStore = useMediaStore()
const fileInput = ref<HTMLInputElement | null>(null)
const selectedId = ref<number | null>(null)
const selectedIds = ref<number[]>([])
const tab = ref<'browse' | 'upload'>('browse')

onMounted(async () => {
  if (!mediaStore.assets.length) await mediaStore.fetchAssets()
})

const selectedAsset = computed(() =>
  mediaStore.assets.find(a => a.id === selectedId.value)
)
const selectedAssets = computed(() =>
  selectedIds.value.map(id => mediaStore.assets.find(a => a.id === id)).filter(Boolean) as SiteAsset[]
)

function toggleSelection(id: number) {
  if (props.multiple) {
    const i = selectedIds.value.indexOf(id)
    if (i === -1) selectedIds.value.push(id); else selectedIds.value.splice(i, 1)
  } else {
    selectedId.value = id
  }
}

function isSelected(id: number): boolean {
  return props.multiple ? selectedIds.value.includes(id) : selectedId.value === id
}

function selectionOrder(id: number): number {
  return selectedIds.value.indexOf(id) + 1
}

async function handleUpload(event: Event) {
  const files = (event.target as HTMLInputElement).files
  if (!files?.length) return
  for (const file of files) {
    const asset = await mediaStore.uploadAsset(file)
    if (asset) {
      if (props.multiple) {
        if (!selectedIds.value.includes(asset.id)) selectedIds.value.push(asset.id)
      } else {
        selectedId.value = asset.id
      }
    }
  }
  ;(event.target as HTMLInputElement).value = ''
  tab.value = 'browse'
}

function confirmSelection() {
  if (props.multiple) {
    if (!selectedAssets.value.length) return
    emit('selectMany', selectedAssets.value.map(a => ({
      asset_id: a.id,
      url: a.url,
      alt: a.alt_text || '',
    })))
  } else {
    if (!selectedAsset.value) return
    emit('select', {
      asset_id: selectedAsset.value.id,
      url: selectedAsset.value.url,
      alt: selectedAsset.value.alt_text || '',
    })
  }
}

const hasSelection = computed(() => props.multiple ? selectedAssets.value.length > 0 : selectedAsset.value != null)
const selectionCount = computed(() => props.multiple ? selectedAssets.value.length : (selectedAsset.value ? 1 : 0))

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[200] flex items-center justify-center">
      <div class="absolute inset-0 bg-black/60" @click="emit('close')" />
      <div class="relative bg-background border rounded-xl w-full max-w-3xl max-h-[85vh] shadow-2xl flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h2 class="text-lg font-semibold">Seleccionar Media</h2>
          <button class="text-muted-foreground hover:text-foreground" @click="emit('close')">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b px-6">
          <button
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
            :class="tab === 'browse' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground'"
            @click="tab = 'browse'"
          >
            Galeria
          </button>
          <button
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
            :class="tab === 'upload' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground'"
            @click="tab = 'upload'"
          >
            Subir
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6">
          <!-- Browse -->
          <div v-if="tab === 'browse'">
            <div v-if="mediaStore.isLoading" class="text-center py-12 text-muted-foreground text-sm">Cargando...</div>
            <div v-else-if="!mediaStore.assets.length" class="text-center py-12">
              <p class="text-muted-foreground mb-4">No hay archivos subidos</p>
              <button class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm" @click="tab = 'upload'">
                Subir archivo
              </button>
            </div>
            <div v-else class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 gap-3">
              <button
                v-for="asset in mediaStore.assets"
                :key="asset.id"
                class="relative aspect-square rounded-lg overflow-hidden border-2 transition-all hover:opacity-90"
                :class="isSelected(asset.id) ? 'border-primary ring-2 ring-primary/20' : 'border-transparent'"
                @click="toggleSelection(asset.id)"
              >
                <img
                  v-if="asset.mime_type.startsWith('image/')"
                  :src="asset.url"
                  :alt="asset.alt_text || asset.original_name"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full bg-muted flex items-center justify-center text-2xl">&#127916;</div>
                <span
                  v-if="multiple && isSelected(asset.id)"
                  class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-primary text-primary-foreground text-xs font-bold flex items-center justify-center shadow"
                >{{ selectionOrder(asset.id) }}</span>
              </button>
            </div>
          </div>

          <!-- Upload -->
          <div v-else class="flex flex-col items-center justify-center py-12">
            <div
              class="w-full max-w-sm border-2 border-dashed rounded-xl p-8 text-center cursor-pointer hover:border-primary/50 transition-colors"
              @click="fileInput?.click()"
            >
              <svg class="w-12 h-12 mx-auto mb-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <p class="text-sm font-medium mb-1">{{ mediaStore.isUploading ? 'Subiendo...' : 'Click para subir' }}</p>
              <p class="text-xs text-muted-foreground">JPG, PNG, WebP, GIF, SVG, MP4 (max 10MB)</p>
            </div>
            <input ref="fileInput" type="file" class="hidden" accept="image/*,video/*" multiple @change="handleUpload" />
          </div>
        </div>

        <!-- Footer (selection info) -->
        <div v-if="hasSelection" class="border-t px-6 py-3 flex items-center justify-between bg-muted/30">
          <div class="flex items-center gap-3 min-w-0">
            <template v-if="!multiple && selectedAsset">
              <img
                v-if="selectedAsset.mime_type.startsWith('image/')"
                :src="selectedAsset.url"
                class="w-10 h-10 rounded object-cover flex-shrink-0"
              />
              <div class="min-w-0">
                <p class="text-sm font-medium truncate">{{ selectedAsset.original_name }}</p>
                <p class="text-xs text-muted-foreground">
                  {{ selectedAsset.width }}x{{ selectedAsset.height }} &middot; {{ formatSize(selectedAsset.size_bytes) }}
                </p>
              </div>
            </template>
            <template v-else-if="multiple">
              <p class="text-sm font-medium">{{ selectionCount }} {{ selectionCount === 1 ? 'archivo seleccionado' : 'archivos seleccionados' }}</p>
            </template>
          </div>
          <button
            class="px-5 py-2 bg-primary text-primary-foreground rounded-md text-sm font-medium hover:bg-primary/90 flex-shrink-0"
            @click="confirmSelection"
          >
            {{ multiple ? `Agregar (${selectionCount})` : 'Seleccionar' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
