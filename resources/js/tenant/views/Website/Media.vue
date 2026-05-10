<script setup lang="ts">
import { onMounted, ref } from 'vue'
import DashboardLayout from '@tenant/layouts/DashboardLayout.vue'
import { useMediaStore } from '@tenant/stores/media'

const mediaStore = useMediaStore()
const fileInput = ref<HTMLInputElement | null>(null)

onMounted(async () => { await mediaStore.fetchAssets() })

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
  <DashboardLayout>
    <div class="max-w-5xl mx-auto p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Media</h1>
        <label class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm font-medium hover:bg-primary/90 cursor-pointer">
          {{ mediaStore.isUploading ? 'Subiendo...' : '+ Subir archivo' }}
          <input ref="fileInput" type="file" class="hidden" accept="image/*,video/*" multiple @change="handleUpload" />
        </label>
      </div>
      <div v-if="mediaStore.isLoading" class="text-center py-12 text-muted-foreground">Cargando...</div>
      <div v-else-if="!mediaStore.assets.length" class="text-center py-20 text-muted-foreground">No hay archivos subidos</div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <div v-for="asset in mediaStore.assets" :key="asset.id" class="group border rounded-lg overflow-hidden bg-card">
          <div class="aspect-square bg-muted flex items-center justify-center overflow-hidden">
            <img v-if="asset.mime_type.startsWith('image/')" :src="asset.url" :alt="asset.alt_text || asset.original_name" class="w-full h-full object-cover" />
            <div v-else class="text-3xl">🎬</div>
          </div>
          <div class="p-2">
            <p class="text-xs truncate">{{ asset.original_name }}</p>
            <div class="flex items-center justify-between mt-1">
              <span class="text-xs text-muted-foreground">{{ formatSize(asset.size_bytes) }}</span>
              <button class="text-xs text-destructive opacity-0 group-hover:opacity-100" @click="mediaStore.deleteAsset(asset.id)">Eliminar</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
