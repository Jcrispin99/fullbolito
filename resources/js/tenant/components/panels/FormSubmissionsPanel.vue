<script setup lang="ts">
import { onMounted, ref } from 'vue'
import BuilderDrawer from '@tenant/components/BuilderDrawer.vue'
import { useFormSubmissionStore } from '@tenant/stores/formSubmission'
import { useEditorModeStore } from '@tenant/stores/editorMode'

const store = useFormSubmissionStore()
const editorMode = useEditorModeStore()
const filterStatus = ref('')
const showDetail = ref<number | null>(null)

onMounted(async () => {
  await Promise.all([store.fetchSubmissions(), store.fetchStats()])
})

async function onFilter() { await store.fetchSubmissions(1, filterStatus.value) }

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('es', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

async function viewDetail(id: number) {
  showDetail.value = showDetail.value === id ? null : id
  await store.markAsRead(id)
  await store.fetchStats()
}

const getSubmission = (id: number) => store.submissions.find(s => s.id === id)
</script>

<template>
  <BuilderDrawer title="Formularios" width="w-96" @close="editorMode.closeDrawer()">
    <!-- Stats -->
    <div v-if="store.stats" class="grid grid-cols-3 gap-2 mb-4">
      <div class="border rounded p-2 text-center">
        <div class="text-lg font-bold text-blue-600">{{ store.stats.new }}</div>
        <div class="text-xs text-muted-foreground">Nuevos</div>
      </div>
      <div class="border rounded p-2 text-center">
        <div class="text-lg font-bold text-green-600">{{ store.stats.read }}</div>
        <div class="text-xs text-muted-foreground">Leidos</div>
      </div>
      <div class="border rounded p-2 text-center">
        <div class="text-lg font-bold text-zinc-400">{{ store.stats.archived }}</div>
        <div class="text-xs text-muted-foreground">Archivados</div>
      </div>
    </div>

    <!-- Filter -->
    <select v-model="filterStatus" class="w-full px-2 py-1.5 border rounded text-sm bg-background mb-3" @change="onFilter">
      <option value="">Todos</option>
      <option value="new">Nuevos</option>
      <option value="read">Leidos</option>
      <option value="archived">Archivados</option>
    </select>

    <!-- List -->
    <div v-if="store.isLoading" class="text-center py-6 text-muted-foreground text-sm">Cargando...</div>
    <div v-else-if="!store.submissions.length" class="text-center py-6 text-muted-foreground text-sm">Sin envios</div>
    <div v-else class="space-y-1.5">
      <div
        v-for="sub in store.submissions"
        :key="sub.id"
        class="border rounded p-3 cursor-pointer hover:bg-muted/50"
        :class="{ 'border-blue-300 dark:border-blue-700': sub.status === 'new' }"
        @click="viewDetail(sub.id)"
      >
        <div class="flex items-center justify-between mb-1">
          <div class="flex items-center gap-1.5">
            <span v-if="sub.status === 'new'" class="w-1.5 h-1.5 rounded-full bg-blue-500" />
            <span class="text-xs font-medium">{{ sub.form_type }}</span>
          </div>
          <span class="text-xs text-muted-foreground">{{ formatDate(sub.created_at) }}</span>
        </div>
        <div class="text-xs text-muted-foreground truncate">{{ sub.data?.name || sub.data?.email || `#${sub.id}` }}</div>

        <!-- Expanded detail -->
        <div v-if="showDetail === sub.id" class="mt-3 pt-3 border-t space-y-2">
          <div v-for="(value, key) in sub.data" :key="key" class="text-xs">
            <span class="text-muted-foreground uppercase">{{ key }}:</span> {{ value }}
          </div>
          <div class="flex gap-1.5 pt-2">
            <button
              v-if="sub.status !== 'archived'"
              class="text-xs px-2 py-1 border rounded hover:bg-muted"
              @click.stop="store.archiveSubmission(sub.id)"
            >Archivar</button>
            <button
              class="text-xs px-2 py-1 border border-destructive text-destructive rounded hover:bg-destructive/10"
              @click.stop="store.deleteSubmission(sub.id)"
            >Eliminar</button>
          </div>
        </div>
      </div>
    </div>
  </BuilderDrawer>
</template>
