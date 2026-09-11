<script setup lang="ts">
import { onMounted, computed, watch, ref } from 'vue'
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
// Drawer panels for toolbar icons
import ThemePanel from '@tenant/components/panels/ThemePanel.vue'
import NavPanel from '@tenant/components/panels/NavPanel.vue'
import MediaPanel from '@tenant/components/panels/MediaPanel.vue'
import FormSubmissionsPanel from '@tenant/components/panels/FormSubmissionsPanel.vue'
import SettingsPanel from '@tenant/components/panels/SettingsPanel.vue'
import VersionHistoryPanel from '@tenant/components/panels/VersionHistoryPanel.vue'

const authStore = useAuthStore()
const publicSite = usePublicSiteStore()
const pageStore = useBuilderPageStore()
const sectionStore = useBuilderSectionStore()
const catalogStore = useBlockCatalogStore()
const siteConfig = useSiteConfigStore()
const editorMode = useEditorModeStore()

const isAuth = computed(() => authStore.isAuthenticated)
const isEditing = computed(() => editorMode.isEditing)
const theme = computed(() => siteConfig.site?.theme || publicSite.siteData?.theme || null)

const homepage = computed(() => pageStore.pages.find(p => p.is_homepage) || pageStore.pages[0])
const currentPageId = computed(() => homepage.value?.id)

const sections = computed(() => {
  if (isAuth.value && sectionStore.sections.length) return sectionStore.sections
  return publicSite.currentPage?.sections || []
})

onMounted(async () => {
  if (isAuth.value) {
    await Promise.all([
      publicSite.fetchSite(),
      pageStore.fetchPages(1, 50),
      siteConfig.fetchSite(),
      catalogStore.fetchBlockTypes(),
    ])
    if (homepage.value) await sectionStore.fetchSections(homepage.value.id)
  } else {
    await publicSite.fetchHomepage()
  }
})

watch(isAuth, async (val) => {
  if (val && homepage.value) await sectionStore.fetchSections(homepage.value.id)
})

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

// Canvas handlers
function onCanvasUpdate(sectionId: number, payload: Record<string, any>) {
  sectionStore.updateSectionLocal(sectionId, payload)
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
  // If we're not over any specific section, the insertion index is "at the end"
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
    payload.element_width = 60
    payload.position_x = 20
    payload.position_y = 10
  }
  await sectionStore.updatePosition(currentPageId.value, sectionId, payload)
  editorMode.markDirty()
}
</script>

<template>
  <!-- ========== EDITING MODE: 3-column layout ========== -->
  <div v-if="isAuth && isEditing" class="flex flex-col h-screen overflow-hidden">
    <!-- Toolbar -->
    <AdminToolbar :current-page-id="currentPageId" />

    <div class="flex flex-1 overflow-hidden">
      <!-- Left Sidebar -->
      <LeftSidebar v-if="currentPageId" :page-id="currentPageId" />

      <!-- Canvas (center) -->
      <div class="flex-1 overflow-y-auto bg-zinc-100 dark:bg-zinc-900">
        <div
          class="canvas-frame mx-auto my-6 bg-background shadow-lg rounded-lg min-h-[600px] relative transition-[max-width,box-shadow,outline-color] duration-300"
          :class="[canvasWidthClass, isDragOver ? 'canvas-drop-active' : '']"
          @dragover="onCanvasDragOver"
          @dragleave="onCanvasDragLeave"
          @drop="onCanvasDrop"
        >
          <!-- Site header preview -->
          <div v-if="publicSite.siteData" class="border-b px-6 h-12 flex items-center justify-between text-sm rounded-t-lg"
            :style="{ backgroundColor: publicSite.siteData.navs?.[0]?.config?.bg_color, color: publicSite.siteData.navs?.[0]?.config?.text_color }">
            <span class="font-bold text-sm">{{ publicSite.siteData.name }}</span>
            <div class="flex gap-4">
              <span v-for="item in (publicSite.siteData.navs?.[0]?.items || [])" :key="item.id" class="text-xs opacity-75">{{ item.label }}</span>
            </div>
          </div>

          <!-- Sections -->
          <VueDraggable
            v-if="draggableSections.length"
            v-model="draggableSections"
            :animation="180"
            handle=".section-drag-handle"
            ghost-class="section-ghost"
            @update="onCanvasReorder"
          >
            <div
              v-for="(section, idx) in draggableSections"
              :key="section.id"
              class="relative group/section"
              :class="{
                'min-h-[80px]': section.position_mode === 'absolute',
                'ring-2 ring-primary ring-inset': editorMode.selectedSectionId === section.id && section.position_mode !== 'absolute',
                'hover:ring-1 hover:ring-primary/30 hover:ring-inset': editorMode.selectedSectionId !== section.id && section.position_mode !== 'absolute',
                'drop-line-before': dragInsertIndex === idx,
                'drop-line-after': dragInsertIndex === idx + 1 && idx === draggableSections.length - 1,
              }"
              @click="section.position_mode !== 'absolute' ? selectSection(section.id) : undefined"
              @dragover="onSectionDragOver(idx, $event)"
            >
              <FloatingToolbar
                v-if="editorMode.selectedSectionId === section.id"
                :section="section"
                @duplicate="duplicateSection(section.id)"
                @delete="deleteSection(section.id)"
                @toggle-mode="togglePositionMode(section.id)"
              />

              <CanvasElement
                v-if="section.position_mode === 'absolute'"
                :section="section"
                :editing="true"
                :selected="editorMode.selectedSectionId === section.id"
                :container-width="800"
                :container-height="600"
                @select="selectSection"
                @update="(payload) => onCanvasUpdate(section.id, payload)"
              >
                <BlockRenderer :section="section" :theme="theme" :editing="true" @update-field="(p) => onFieldUpdate(section.id, p)" />
              </CanvasElement>

              <BlockRenderer v-else :section="section" :theme="theme" :editing="true" @update-field="(p) => onFieldUpdate(section.id, p)" />
            </div>
          </VueDraggable>

          <!-- Empty -->
          <div v-else class="flex items-center justify-center min-h-[400px]">
            <div class="text-center text-muted-foreground">
              <p class="mb-2">Sin secciones</p>
              <p class="text-xs">Agrega bloques desde el panel izquierdo</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Sidebar -->
      <RightSidebar v-if="currentPageId" :page-id="currentPageId" />
    </div>

    <!-- Overlay drawers (from toolbar icons) -->
    <ThemePanel v-if="editorMode.activeDrawer === 'theme'" />
    <NavPanel v-if="editorMode.activeDrawer === 'nav'" />
    <MediaPanel v-if="editorMode.activeDrawer === 'media'" />
    <FormSubmissionsPanel v-if="editorMode.activeDrawer === 'formSubmissions'" />
    <SettingsPanel v-if="editorMode.activeDrawer === 'settings'" />
    <VersionHistoryPanel v-if="editorMode.activeDrawer === 'versionHistory' && currentPageId" :page-id="currentPageId" />
  </div>

  <!-- ========== PUBLIC MODE: normal layout ========== -->
  <PublicLayout v-else :current-page-id="currentPageId">
    <!-- Admin toolbar for non-editing auth users -->
    <template v-if="isAuth && !isEditing">
      <!-- Handled by PublicLayout -->
    </template>

    <div v-if="publicSite.isLoading && !isAuth" class="flex items-center justify-center min-h-[60vh]">
      <div class="text-muted-foreground">Cargando...</div>
    </div>

    <template v-if="sections.length">
      <BlockRenderer
        v-for="section in sections"
        :key="section.id"
        :section="section"
        :theme="theme"
        :editing="false"
      />
    </template>

    <div v-else-if="!publicSite.isLoading" class="flex items-center justify-center min-h-[60vh]">
      <div class="text-center">
        <h2 class="text-2xl font-bold mb-2">Bienvenido</h2>
        <p class="text-muted-foreground mb-4">Este sitio aun no tiene contenido.</p>
        <button
          v-if="isAuth"
          class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm font-medium"
          @click="editorMode.startEditing()"
        >
          Comenzar a editar
        </button>
      </div>
    </div>
  </PublicLayout>
</template>

<script lang="ts">
import AdminToolbar from '@tenant/components/AdminToolbar.vue'
export default { components: { AdminToolbar } }
</script>
