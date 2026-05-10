import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export type DrawerPanel = 'pages' | 'theme' | 'nav' | 'media' | 'formSubmissions' | 'redirects' | 'globalSections' | 'settings' | 'versionHistory' | null

export type Viewport = 'desktop' | 'tablet' | 'mobile'

export const useEditorModeStore = defineStore('tenant-editor-mode', () => {
  const isEditing = ref(false)
  const selectedSectionId = ref<number | null>(null)
  const isDirty = ref(false)
  const showPagesDropdown = ref(false)
  const showBlockPalette = ref(false)
  const activeDrawer = ref<DrawerPanel>(null)
  const viewport = ref<Viewport>('desktop')

  const hasSelection = computed(() => selectedSectionId.value !== null)

  function startEditing() {
    isEditing.value = true
    showBlockPalette.value = true
  }

  function stopEditing() {
    isEditing.value = false
    selectedSectionId.value = null
    isDirty.value = false
    showBlockPalette.value = false
    activeDrawer.value = null
  }

  function selectSection(id: number | null) {
    selectedSectionId.value = id
    // Close drawers when selecting a section to show PropertiesDrawer
    if (id !== null) activeDrawer.value = null
  }

  function openDrawer(panel: DrawerPanel) {
    activeDrawer.value = panel
    selectedSectionId.value = null // deselect section when opening a drawer
  }

  function closeDrawer() {
    activeDrawer.value = null
  }

  function toggleDrawer(panel: DrawerPanel) {
    if (activeDrawer.value === panel) {
      activeDrawer.value = null
    } else {
      openDrawer(panel)
    }
  }

  function togglePagesDropdown() {
    showPagesDropdown.value = !showPagesDropdown.value
  }

  function markDirty() {
    isDirty.value = true
  }

  function setViewport(v: Viewport) {
    viewport.value = v
  }

  return {
    isEditing,
    selectedSectionId,
    isDirty,
    showPagesDropdown,
    showBlockPalette,
    activeDrawer,
    viewport,
    hasSelection,
    startEditing,
    stopEditing,
    selectSection,
    openDrawer,
    closeDrawer,
    toggleDrawer,
    togglePagesDropdown,
    markDirty,
    setViewport,
  }
})
