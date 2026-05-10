<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@tenant/stores/auth'
import { useEditorModeStore } from '@tenant/stores/editorMode'
import { useBuilderPageStore } from '@tenant/stores/builderPage'
import { useBuilderSectionStore } from '@tenant/stores/builderSection'
import { useFormSubmissionStore } from '@tenant/stores/formSubmission'
import { Monitor, Tablet, Smartphone, Undo2, Redo2 } from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const editorMode = useEditorModeStore()
const pageStore = useBuilderPageStore()
const sectionStore = useBuilderSectionStore()
const formStore = useFormSubmissionStore()

const props = defineProps<{
  currentPageId?: number
}>()

const currentPage = computed(() => {
  if (!props.currentPageId) return null
  return pageStore.pages.find(p => p.id === props.currentPageId) || pageStore.currentPage
})

const saveStatus = ref<'idle' | 'saving' | 'saved' | 'error'>('idle')
const hasPending = computed(() => sectionStore.hasPendingChanges())
const canUndo = computed(() => sectionStore.historyStack.length > 0)
const canRedo = computed(() => sectionStore.redoStack.length > 0)

function onKeyDown(e: KeyboardEvent) {
  if (!editorMode.isEditing) return
  const target = e.target as HTMLElement | null
  if (target && (target.isContentEditable || target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')) return
  const meta = e.metaKey || e.ctrlKey
  if (!meta) return
  if (e.key === 'z' || e.key === 'Z') {
    e.preventDefault()
    if (e.shiftKey) sectionStore.redo(); else sectionStore.undo()
  } else if (e.key === 'y' || e.key === 'Y') {
    e.preventDefault()
    sectionStore.redo()
  }
}

onMounted(async () => {
  formStore.fetchStats()
  window.addEventListener('keydown', onKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeyDown)
})

function handleEdit() { editorMode.startEditing() }

async function handleSave() {
  if (!props.currentPageId) return
  saveStatus.value = 'saving'
  try {
    await sectionStore.saveAll(props.currentPageId)
    saveStatus.value = 'saved'
    setTimeout(() => { saveStatus.value = 'idle' }, 2000)
  } catch {
    saveStatus.value = 'error'
    setTimeout(() => { saveStatus.value = 'idle' }, 3000)
  }
}

function handleDiscard() {
  sectionStore.discardAll()
  editorMode.markDirty()
}

async function handlePublish() {
  if (props.currentPageId) await pageStore.publishPage(props.currentPageId)
}

async function handleUnpublish() {
  if (props.currentPageId) await pageStore.unpublishPage(props.currentPageId)
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="h-11 bg-zinc-900 text-white flex items-center justify-between px-4 text-sm z-[100] relative">
    <!-- Left -->
    <div class="flex items-center gap-1">
      <router-link
        to="/admin"
        class="flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white/10 transition-colors text-xs font-medium"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        Admin
      </router-link>

      <div class="w-px h-5 bg-white/20" />

      <!-- Pages panel button -->
      <button
        class="flex items-center gap-1.5 px-2 py-1 rounded text-xs transition-colors"
        :class="editorMode.activeDrawer === 'pages' ? 'bg-white/20' : 'hover:bg-white/10'"
        @click="editorMode.toggleDrawer('pages')"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Paginas
      </button>

      <div class="w-px h-5 bg-white/20" />

      <!-- Builder panel buttons -->
      <button
        class="px-2 py-1 rounded text-xs transition-colors"
        :class="editorMode.activeDrawer === 'theme' ? 'bg-white/20' : 'hover:bg-white/10'"
        title="Tema"
        @click="editorMode.toggleDrawer('theme')"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
        </svg>
      </button>

      <button
        class="px-2 py-1 rounded text-xs transition-colors"
        :class="editorMode.activeDrawer === 'nav' ? 'bg-white/20' : 'hover:bg-white/10'"
        title="Navegacion"
        @click="editorMode.toggleDrawer('nav')"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      <button
        class="px-2 py-1 rounded text-xs transition-colors"
        :class="editorMode.activeDrawer === 'media' ? 'bg-white/20' : 'hover:bg-white/10'"
        title="Media"
        @click="editorMode.toggleDrawer('media')"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </button>

      <!-- Form submissions with badge -->
      <button
        class="relative px-2 py-1 rounded text-xs transition-colors"
        :class="editorMode.activeDrawer === 'formSubmissions' ? 'bg-white/20' : 'hover:bg-white/10'"
        title="Formularios"
        @click="editorMode.toggleDrawer('formSubmissions')"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        <span
          v-if="formStore.stats?.new"
          class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-xs rounded-full w-3.5 h-3.5 flex items-center justify-center leading-none"
          style="font-size: 9px"
        >
          {{ formStore.stats.new > 9 ? '9+' : formStore.stats.new }}
        </span>
      </button>

      <button
        class="px-2 py-1 rounded text-xs transition-colors"
        :class="editorMode.activeDrawer === 'settings' ? 'bg-white/20' : 'hover:bg-white/10'"
        title="Configuracion"
        @click="editorMode.toggleDrawer('settings')"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
        </svg>
      </button>

      <!-- Version history (only when editing a page) -->
      <button
        v-if="currentPageId"
        class="px-2 py-1 rounded text-xs transition-colors"
        :class="editorMode.activeDrawer === 'versionHistory' ? 'bg-white/20' : 'hover:bg-white/10'"
        title="Historial de versiones"
        @click="editorMode.toggleDrawer('versionHistory')"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </button>
    </div>

    <!-- Center: viewport toggle + editing status -->
    <div class="flex items-center gap-3">
      <!-- Viewport switcher (only while editing) -->
      <div v-if="editorMode.isEditing" class="flex items-center bg-white/5 rounded-md p-0.5 border border-white/10">
        <button
          v-for="v in (['desktop', 'tablet', 'mobile'] as const)"
          :key="v"
          class="px-2 py-1 rounded text-xs flex items-center gap-1 transition-colors"
          :class="editorMode.viewport === v ? 'bg-white/15 text-white' : 'text-white/60 hover:text-white'"
          :title="v === 'desktop' ? 'Vista escritorio' : v === 'tablet' ? 'Vista tablet' : 'Vista móvil'"
          @click="editorMode.setViewport(v)"
        >
          <Monitor v-if="v === 'desktop'" :size="13" :stroke-width="1.75" />
          <Tablet v-else-if="v === 'tablet'" :size="13" :stroke-width="1.75" />
          <Smartphone v-else :size="13" :stroke-width="1.75" />
        </button>
      </div>

      <div v-if="editorMode.isEditing" class="flex items-center gap-2">
        <span class="text-yellow-400 text-xs font-medium">
          Editando: {{ currentPage?.title || 'Pagina' }}
        </span>
        <span v-if="hasPending" class="w-1.5 h-1.5 rounded-full bg-yellow-400 animate-pulse" title="Cambios sin guardar" />
        <span v-if="saveStatus === 'saved'" class="text-green-400 text-xs">Guardado</span>
        <span v-if="saveStatus === 'error'" class="text-red-400 text-xs">Error al guardar</span>
      </div>
      <div v-else-if="currentPage" class="text-xs text-white/50">
        {{ currentPage.title }}
      </div>
    </div>

    <!-- Right -->
    <div class="flex items-center gap-2">
      <template v-if="editorMode.isEditing">
        <button
          class="p-1.5 rounded transition-colors"
          :class="canUndo ? 'text-white/80 hover:text-white hover:bg-white/10' : 'text-white/20 cursor-not-allowed'"
          :disabled="!canUndo"
          title="Deshacer (Cmd+Z)"
          @click="sectionStore.undo()"
        >
          <Undo2 :size="14" :stroke-width="1.75" />
        </button>
        <button
          class="p-1.5 rounded transition-colors"
          :class="canRedo ? 'text-white/80 hover:text-white hover:bg-white/10' : 'text-white/20 cursor-not-allowed'"
          :disabled="!canRedo"
          title="Rehacer (Cmd+Shift+Z)"
          @click="sectionStore.redo()"
        >
          <Redo2 :size="14" :stroke-width="1.75" />
        </button>
        <div class="w-px h-5 bg-white/20" />
        <button
          class="px-3 py-1 rounded text-xs transition-colors"
          :class="hasPending ? 'text-white hover:bg-white/10' : 'text-white/30 cursor-not-allowed'"
          :disabled="!hasPending"
          @click="handleDiscard"
        >
          Descartar
        </button>
        <button
          class="px-3 py-1 rounded text-xs font-medium transition-colors"
          :class="hasPending ? 'bg-primary hover:bg-primary/90' : 'bg-white/10 text-white/30 cursor-not-allowed'"
          :disabled="!hasPending || saveStatus === 'saving'"
          @click="handleSave"
        >
          {{ saveStatus === 'saving' ? 'Guardando...' : 'Guardar' }}
        </button>
      </template>

      <template v-else>
        <button
          v-if="currentPage?.status === 'draft'"
          class="px-3 py-1 rounded text-xs border border-white/20 hover:bg-white/10 transition-colors"
          @click="handlePublish"
        >
          Publicar
        </button>
        <button
          v-else-if="currentPage?.status === 'published'"
          class="px-3 py-1 rounded text-xs border border-white/20 hover:bg-white/10 transition-colors"
          @click="handleUnpublish"
        >
          Despublicar
        </button>

        <button
          v-if="currentPageId"
          class="px-3 py-1 bg-primary rounded text-xs font-medium hover:bg-primary/90 transition-colors"
          @click="handleEdit"
        >
          Editar
        </button>
      </template>

      <div class="w-px h-5 bg-white/20 ml-1" />

      <button
        class="flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white/10 transition-colors text-xs"
        @click="handleLogout"
      >
        {{ authStore.user?.name }}
        <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
      </button>
    </div>
  </div>
</template>
