<script setup lang="ts">
import { onMounted, ref } from 'vue'
import BuilderDrawer from '@tenant/components/BuilderDrawer.vue'
import { useMediaStore } from '@tenant/stores/media'
import { useEditorModeStore } from '@tenant/stores/editorMode'

const mediaStore = useMediaStore()
const editorMode = useEditorModeStore()
const fileInput = ref<HTMLInputElement | null>(null)

onMounted(async () => { if (!mediaStore.assets.length) await mediaStore.fetchAssets() })

async function handleUpload(event: Event) {
  const files = (event.target as HTMLInputElement).files
  if (!files?.length) return
  for (const file of files) { await mediaStore.uploadAsset(file) }
  ;(event.target as HTMLInputElement).value = ''
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
  <BuilderDrawer title="Media" width="w-96" @close="editorMode.closeDrawer()">
    <div class="mb-4">
      <label class="w-full flex items-center justify-center px-4 py-2 bg-primary text-primary-foreground rounded text-sm font-medium hover:bg-primary/90 cursor-pointer">
        {{ mediaStore.isUploading ? 'Subiendo...' : '+ Subir archivo' }}
        <input ref="fileInput" type="file" class="hidden" accept="image/*,video/*" multiple @change="handleUpload" />
      </label>
    </div>

    <div v-if="mediaStore.isLoading" class="text-center py-6 text-muted-foreground text-sm">Cargando...</div>
    <div v-else-if="!mediaStore.assets.length" class="text-center py-6 text-muted-foreground text-sm">Sin archivos</div>
    <div v-else class="grid grid-cols-3 gap-2">
      <div v-for="asset in mediaStore.assets" :key="asset.id" class="group relative border rounded overflow-hidden bg-muted">
        <div class="aspect-square flex items-center justify-center overflow-hidden">
          <img v-if="asset.mime_type.startsWith('image/')" :src="asset.url" :alt="asset.alt_text || asset.original_name" class="w-full h-full object-cover" />
          <div v-else class="text-xl">&#127916;</div>
        </div>
        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-1.5">
          <div class="w-full">
            <p class="text-white text-xs truncate">{{ asset.original_name }}</p>
            <div class="flex items-center justify-between mt-0.5">
              <span class="text-white/70 text-xs">{{ formatSize(asset.size_bytes) }}</span>
              <button class="text-xs text-red-400 hover:text-red-300" @click="mediaStore.deleteAsset(asset.id)">&#x2715;</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BuilderDrawer>
</template>
