<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { VueDraggable } from 'vue-draggable-plus'
import { useAuthStore } from '@tenant/stores/auth'
import { usePublicSiteStore } from '@tenant/stores/publicSite'
import { useBuilderPageStore } from '@tenant/stores/builderPage'
import { useBuilderSectionStore } from '@tenant/stores/builderSection'
import { useBlockCatalogStore } from '@tenant/stores/blockCatalog'
import { useSiteConfigStore } from '@tenant/stores/siteConfig'
import { useEditorModeStore } from '@tenant/stores/editorMode'
import PublicLayout from '@tenant/layouts/PublicLayout.vue'
import BlockRenderer from '@tenant/components/blocks/BlockRenderer.vue'
import CanvasElement from '@tenant/components/blocks/CanvasElement.vue'
import FloatingToolbar from '@tenant/components/editor/FloatingToolbar.vue'
import LeftSidebar from '@tenant/components/editor/LeftSidebar.vue'
import RightSidebar from '@tenant/components/editor/RightSidebar.vue'
import ThemePanel from '@tenant/components/panels/ThemePanel.vue'
import NavPanel from '@tenant/components/panels/NavPanel.vue'
import MediaPanel from '@tenant/components/panels/MediaPanel.vue'
import FormSubmissionsPanel from '@tenant/components/panels/FormSubmissionsPanel.vue'
import SettingsPanel from '@tenant/components/panels/SettingsPanel.vue'
import VersionHistoryPanel from '@tenant/components/panels/VersionHistoryPanel.vue'

const route = useRoute()
const authStore = useAuthStore()
const publicSite = usePublicSiteStore()
const pageStore = useBuilderPageStore()
const sectionStore = useBuilderSectionStore()
const catalogStore = useBlockCatalogStore()
const siteConfig = useSiteConfigStore()
const editorMode = useEditorModeStore()

const isAuth = computed(() => authStore.isAuthenticated)
const isEditing = computed(() => editorMode.isEditing)
const slug = computed(() => route.params.slug as string)
const theme = computed(() => siteConfig.site?.theme || publicSite.siteData?.theme || null)

const currentPage = computed(() => pageStore.pages.find(p => p.slug === slug.value))
const currentPageId = computed(() => currentPage.value?.id)

const sections = computed(() => {
  if (isAuth.value && sectionStore.sections.length) return sectionStore.sections
  return publicSite.currentPage?.sections || []
})

async function loadPage() {
  const s = slug.value
  if (!s) return
  if (isAuth.value) {
    if (!pageStore.pages.length) await pageStore.fetchPages(1, 50)
    const page = pageStore.pages.find(p => p.slug === s)
    if (page) await sectionStore.fetchSections(page.id)
  } else {
    await publicSite.fetchPageBySlug(s)
  }
}

onMounted(async () => {
  if (!publicSite.siteData) await publicSite.fetchSite()
  if (isAuth.value) await Promise.all([siteConfig.fetchSite(), catalogStore.fetchBlockTypes()])
  await loadPage()
})

watch(slug, () => { sectionStore.clearSections(); loadPage() })

function selectSection(id: number) {
  if (!isEditing.value) return
  editorMode.selectSection(editorMode.selectedSectionId === id ? null : id)
}

async function deleteSection(id: number) {
  if (!currentPageId.value) return
  await sectionStore.deleteSection(currentPageId.value, id)
  editorMode.markDirty()
}

async function duplicateSection(id: number) {
  if (!currentPageId.value) return
  await sectionStore.duplicateSection(currentPageId.value, id)
  editorMode.markDirty()
}

const saveTimeout = ref<Record<number, ReturnType<typeof setTimeout>>>({})

function onCanvasUpdate(sectionId: number, payload: Record<string, any>) {
  sectionStore.updateSectionLocal(sectionId, payload)
  editorMode.markDirty()
  if (saveTimeout.value[sectionId]) clearTimeout(saveTimeout.value[sectionId])
  saveTimeout.value[sectionId] = setTimeout(() => {
    if (currentPageId.value) sectionStore.updatePosition(currentPageId.value, sectionId, payload)
  }, 300)
}

function onFieldUpdate(sectionId: number, payload: { key: string; value: any }) {
  const section = sections.value.find(s => s.id === sectionId)
  if (!section) return
  sectionStore.updateContentLocal(sectionId, { ...section.content, [payload.key]: payload.value })
  editorMode.markDirty()
}

const draggableSections = ref<typeof sectionStore.sections>([])
watch(() => sectionStore.sections, (val) => { draggableSections.value = [...val] }, { immediate: true, deep: false })

async function onCanvasReorder() {
  if (!currentPageId.value) return
  const ids = draggableSections.value.map(s => s.id)
  await sectionStore.reorderSections(currentPageId.value, ids)
  editorMode.markDirty()
}

const canvasWidthClass = computed(() => {
  switch (editorMode.viewport) {
    case 'mobile': return 'max-w-[400px]'
    case 'tablet': return 'max-w-[768px]'
    default: return 'max-w-5xl'
  }
})

const isDragOver = ref(false)
const dragInsertIndex = ref<number | null>(null)

function isBlockDrag(e: DragEvent): boolean {
  return !!e.dataTransfer?.types.includes('application/x-block-key')
}

function onCanvasDragOver(e: DragEvent) {
  if (!isBlockDrag(e)) return
  e.preventDefault()
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy'
  isDragOver.value = true
  if (dragInsertIndex.value === null) dragInsertIndex.value = draggableSections.value.length
}

function onCanvasDragLeave(e: DragEvent) {
  const related = e.relatedTarget as Node | null
  if (related && (e.currentTarget as HTMLElement).contains(related)) return
  isDragOver.value = false
  dragInsertIndex.value = null
}

function onSectionDragOver(idx: number, e: DragEvent) {
  if (!isBlockDrag(e)) return
  e.preventDefault()
  e.stopPropagation()
  const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
  const isTop = (e.clientY - rect.top) < rect.height / 2
  dragInsertIndex.value = isTop ? idx : idx + 1
}

async function onCanvasDrop(e: DragEvent) {
  if (!e.dataTransfer || !currentPageId.value) {
    isDragOver.value = false
    dragInsertIndex.value = null
    return
  }
  const key = e.dataTransfer.getData('application/x-block-key')
  if (!key) {
    isDragOver.value = false
    dragInsertIndex.value = null
    return
  }
  e.preventDefault()
  const targetIndex = dragInsertIndex.value
  isDragOver.value = false
  dragInsertIndex.value = null
  await sectionStore.addSection(currentPageId.value, key, targetIndex ?? undefined)
  editorMode.markDirty()
}

async function togglePositionMode(sectionId: number) {
  if (!currentPageId.value) return
  const section = sections.value.find(s => s.id === sectionId)
  if (!section) return
  const newMode = section.position_mode === 'absolute' ? 'flow' : 'absolute'
  const payload: Record<string, any> = { position_mode: newMode }
  if (newMode === 'absolute' && !section.element_width) {
    payload.element_width = 60; payload.position_x = 20; payload.position_y = 10
  }
  await sectionStore.updatePosition(currentPageId.value, sectionId, payload)
  editorMode.markDirty()
}
</script>

<template>
  <!-- EDITING MODE -->
  <div v-if="isAuth && isEditing" class="flex flex-col h-screen overflow-hidden">
    <AdminToolbar :current-page-id="currentPageId" />
    <div class="flex flex-1 overflow-hidden">
      <LeftSidebar v-if="currentPageId" :page-id="currentPageId" />
      <div class="flex-1 overflow-y-auto bg-zinc-100 dark:bg-zinc-900">
        <div
          class="canvas-frame mx-auto my-6 bg-background shadow-lg rounded-lg min-h-[600px] relative transition-[max-width,box-shadow,outline-color] duration-300"
          :class="[canvasWidthClass, isDragOver ? 'canvas-drop-active' : '']"
          @dragover="onCanvasDragOver"
          @dragleave="onCanvasDragLeave"
          @drop="onCanvasDrop"
        >
          <div v-if="publicSite.siteData" class="border-b px-6 h-12 flex items-center justify-between text-sm rounded-t-lg"
            :style="{ backgroundColor: publicSite.siteData.navs?.[0]?.config?.bg_color, color: publicSite.siteData.navs?.[0]?.config?.text_color }">
            <span class="font-bold text-sm">{{ publicSite.siteData.name }}</span>
          </div>
          <VueDraggable v-if="draggableSections.length" v-model="draggableSections" :animation="180" handle=".section-drag-handle" ghost-class="section-ghost" @update="onCanvasReorder">
            <div v-for="(section, idx) in draggableSections" :key="section.id" class="relative group/section"
              :class="{
                'ring-2 ring-primary ring-inset': editorMode.selectedSectionId === section.id && section.position_mode !== 'absolute',
                'hover:ring-1 hover:ring-primary/30 hover:ring-inset': editorMode.selectedSectionId !== section.id && section.position_mode !== 'absolute',
                'drop-line-before': dragInsertIndex === idx,
                'drop-line-after': dragInsertIndex === idx + 1 && idx === draggableSections.length - 1,
              }"
              @click="section.position_mode !== 'absolute' ? selectSection(section.id) : undefined"
              @dragover="onSectionDragOver(idx, $event)">
              <FloatingToolbar v-if="editorMode.selectedSectionId === section.id" :section="section"
                @duplicate="duplicateSection(section.id)" @delete="deleteSection(section.id)" @toggle-mode="togglePositionMode(section.id)" />
              <CanvasElement v-if="section.position_mode === 'absolute'" :section="section" :editing="true"
                :selected="editorMode.selectedSectionId === section.id" :container-width="800" :container-height="600"
                @select="selectSection" @update="(p) => onCanvasUpdate(section.id, p)">
                <BlockRenderer :section="section" :theme="theme" :editing="true" @update-field="(p) => onFieldUpdate(section.id, p)" />
              </CanvasElement>
              <BlockRenderer v-else :section="section" :theme="theme" :editing="true" @update-field="(p) => onFieldUpdate(section.id, p)" />
            </div>
          </VueDraggable>
          <div v-else class="flex items-center justify-center min-h-[400px] text-muted-foreground text-sm">
            Agrega bloques desde el panel izquierdo
          </div>
        </div>
      </div>
      <RightSidebar v-if="currentPageId" :page-id="currentPageId" />
    </div>
    <ThemePanel v-if="editorMode.activeDrawer === 'theme'" />
    <NavPanel v-if="editorMode.activeDrawer === 'nav'" />
    <MediaPanel v-if="editorMode.activeDrawer === 'media'" />
    <FormSubmissionsPanel v-if="editorMode.activeDrawer === 'formSubmissions'" />
    <SettingsPanel v-if="editorMode.activeDrawer === 'settings'" />
    <VersionHistoryPanel v-if="editorMode.activeDrawer === 'versionHistory' && currentPageId" :page-id="currentPageId" />
  </div>

  <!-- PUBLIC MODE -->
  <PublicLayout v-else :current-page-id="currentPageId">
    <div v-if="publicSite.isLoading" class="flex items-center justify-center min-h-[60vh]">
      <div class="text-muted-foreground">Cargando...</div>
    </div>
    <div v-else-if="!sections.length && !currentPage" class="flex items-center justify-center min-h-[60vh]">
      <div class="text-center"><h1 class="text-4xl font-bold mb-4">404</h1><p class="text-muted-foreground">Pagina no encontrada</p></div>
    </div>
    <template v-else>
      <BlockRenderer v-for="section in sections" :key="section.id" :section="section" :theme="theme" :editing="false" />
    </template>
  </PublicLayout>
</template>

<script lang="ts">
import AdminToolbar from '@tenant/components/AdminToolbar.vue'
export default { components: { AdminToolbar } }
</script>
