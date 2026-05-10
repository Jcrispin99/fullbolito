<script setup lang="ts">
import { onMounted } from 'vue'
import BuilderDrawer from '@tenant/components/BuilderDrawer.vue'
import { usePageVersionStore } from '@tenant/stores/pageVersion'
import { useBuilderSectionStore } from '@tenant/stores/builderSection'
import { useEditorModeStore } from '@tenant/stores/editorMode'

const props = defineProps<{
  pageId: number
}>()

const versionStore = usePageVersionStore()
const sectionStore = useBuilderSectionStore()
const editorMode = useEditorModeStore()

onMounted(async () => {
  await versionStore.fetchVersions(props.pageId)
})

async function revert(versionId: number) {
  await versionStore.revertToVersion(props.pageId, versionId)
  await sectionStore.fetchSections(props.pageId)
  editorMode.closeDrawer()
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <BuilderDrawer title="Historial de Versiones" @close="editorMode.closeDrawer()">
    <div v-if="versionStore.isLoading" class="text-center py-6 text-muted-foreground text-sm">Cargando...</div>
    <div v-else-if="!versionStore.versions.length" class="text-center py-6 text-muted-foreground text-sm">Sin versiones. Se crean al publicar la pagina.</div>
    <div v-else class="space-y-2">
      <div
        v-for="version in versionStore.versions"
        :key="version.id"
        class="border rounded p-3 hover:bg-muted/50"
      >
        <div class="flex items-center justify-between mb-1">
          <span class="text-sm font-medium">v{{ version.version_number }}</span>
          <span class="text-xs text-muted-foreground">{{ formatDate(version.created_at) }}</span>
        </div>
        <div class="text-xs text-muted-foreground mb-2">
          {{ version.title }} &middot; {{ version.sections_snapshot.length }} secciones
        </div>
        <button
          class="text-xs px-3 py-1 border rounded hover:bg-muted"
          @click="revert(version.id)"
        >
          Restaurar esta version
        </button>
      </div>
    </div>
  </BuilderDrawer>
</template>
