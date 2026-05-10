import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { SiteSection } from '@/types/builder'

export const useBuilderSectionStore = defineStore('tenant-builder-section', () => {
  const sections = ref<SiteSection[]>([])
  const originalSections = ref<SiteSection[]>([])
  const dirtyIds = ref<Set<number>>(new Set())
  const selectedSectionId = ref<number | null>(null)
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  // History (undo/redo) for local edits
  const historyStack = ref<SiteSection[][]>([])
  const redoStack = ref<SiteSection[][]>([])
  const HISTORY_LIMIT = 50
  const HISTORY_DEBOUNCE_MS = 400
  let lastPushAt = 0

  function snapshot(): SiteSection[] {
    return JSON.parse(JSON.stringify(sections.value))
  }

  function pushHistory(force = false) {
    const now = Date.now()
    if (!force && now - lastPushAt < HISTORY_DEBOUNCE_MS) return
    lastPushAt = now
    historyStack.value.push(snapshot())
    if (historyStack.value.length > HISTORY_LIMIT) historyStack.value.shift()
    redoStack.value = []
  }

  function undo() {
    const prev = historyStack.value.pop()
    if (!prev) return false
    redoStack.value.push(snapshot())
    sections.value = prev
    rebuildDirtyIds()
    return true
  }

  function redo() {
    const next = redoStack.value.pop()
    if (!next) return false
    historyStack.value.push(snapshot())
    sections.value = next
    rebuildDirtyIds()
    return true
  }

  function rebuildDirtyIds() {
    const orig = new Map(originalSections.value.map(s => [s.id, JSON.stringify(s)]))
    const dirty = new Set<number>()
    for (const s of sections.value) {
      if (orig.get(s.id) !== JSON.stringify(s)) dirty.add(s.id)
    }
    dirtyIds.value = dirty
  }

  function clearHistory() {
    historyStack.value = []
    redoStack.value = []
    lastPushAt = 0
  }

  function setSelectedSection(id: number | null) {
    selectedSectionId.value = id
  }

  function getSelectedSection(): SiteSection | undefined {
    if (!selectedSectionId.value) return undefined
    return sections.value.find(s => s.id === selectedSectionId.value)
  }

  // --- Fetch (also sets the snapshot) ---
  async function fetchSections(pageId: number | string) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<any>(`/v1/builder/pages/${pageId}/sections`)
      sections.value = data.data
      takeSnapshot()
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching sections'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  // --- Snapshot management ---
  function takeSnapshot() {
    originalSections.value = JSON.parse(JSON.stringify(sections.value))
    dirtyIds.value = new Set()
    clearHistory()
  }

  function hasPendingChanges(): boolean {
    return dirtyIds.value.size > 0
  }

  // --- LOCAL-ONLY updates (no API call) ---
  function updateSectionLocal(sectionId: number, payload: Partial<SiteSection>) {
    const idx = sections.value.findIndex(s => s.id === sectionId)
    if (idx !== -1) {
      pushHistory()
      sections.value[idx] = { ...sections.value[idx], ...payload } as SiteSection
      dirtyIds.value.add(sectionId)
    }
  }

  function updateContentLocal(sectionId: number, content: Record<string, any>) {
    const idx = sections.value.findIndex(s => s.id === sectionId)
    if (idx !== -1) {
      pushHistory()
      sections.value[idx] = { ...sections.value[idx], content }
      dirtyIds.value.add(sectionId)
    }
  }

  function updateStyleLocal(sectionId: number, styleOverrides: Record<string, any>) {
    const idx = sections.value.findIndex(s => s.id === sectionId)
    if (idx !== -1) {
      pushHistory()
      sections.value[idx] = { ...sections.value[idx], style_overrides: styleOverrides }
      dirtyIds.value.add(sectionId)
    }
  }

  function updateLayoutLocal(sectionId: number, layout: Record<string, any>) {
    const idx = sections.value.findIndex(s => s.id === sectionId)
    if (idx !== -1) {
      pushHistory()
      sections.value[idx] = { ...sections.value[idx], layout } as SiteSection
      dirtyIds.value.add(sectionId)
    }
  }

  // --- SAVE ALL dirty sections to API ---
  async function saveAll(pageId: number | string) {
    isSaving.value = true
    error.value = null
    try {
      const promises = Array.from(dirtyIds.value).map(id => {
        const section = sections.value.find(s => s.id === id)
        if (!section) return Promise.resolve()
        return apiClient.put<any>(`/v1/builder/pages/${pageId}/sections/${id}`, {
          content: section.content,
          style_overrides: section.style_overrides,
          layout: section.layout,
          is_visible: section.is_visible,
          position_x: section.position_x,
          position_y: section.position_y,
          element_width: section.element_width,
          element_height: section.element_height,
          rotation: section.rotation,
          position_mode: section.position_mode,
        })
      })
      await Promise.all(promises)
      takeSnapshot()
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error saving sections'
      throw err
    } finally {
      isSaving.value = false
    }
  }

  // --- DISCARD all local changes ---
  function discardAll() {
    sections.value = JSON.parse(JSON.stringify(originalSections.value))
    dirtyIds.value = new Set()
    selectedSectionId.value = null
    clearHistory()
  }

  // --- Structural operations (immediate API calls) ---
  async function addSection(pageId: number | string, blockTypeKey: string, insertAtIndex?: number) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.post<any>(
        `/v1/builder/pages/${pageId}/sections`,
        { block_type_key: blockTypeKey },
      )
      // Reassign array so shallow watchers re-trigger
      sections.value = [...sections.value, data.data]
      originalSections.value = [...originalSections.value, JSON.parse(JSON.stringify(data.data))]

      // If a target index is provided, reorder to place the new section there
      if (typeof insertAtIndex === 'number' && insertAtIndex >= 0 && insertAtIndex < sections.value.length - 1) {
        const newId = data.data.id
        const ids = sections.value.map(s => s.id).filter(id => id !== newId)
        ids.splice(insertAtIndex, 0, newId)
        await reorderSections(pageId, ids)
      }
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error adding section'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function deleteSection(pageId: number | string, sectionId: number) {
    error.value = null
    try {
      await apiClient.delete(`/v1/builder/pages/${pageId}/sections/${sectionId}`)
      sections.value = sections.value.filter(s => s.id !== sectionId)
      originalSections.value = originalSections.value.filter(s => s.id !== sectionId)
      dirtyIds.value.delete(sectionId)
      if (selectedSectionId.value === sectionId) selectedSectionId.value = null
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error deleting section'
      throw err
    }
  }

  async function reorderSections(pageId: number | string, ids: number[]) {
    error.value = null
    try {
      const { data } = await apiClient.put<any>(
        `/v1/builder/pages/${pageId}/sections/reorder`,
        { ids },
      )
      sections.value = data.data
      takeSnapshot()
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error reordering sections'
      throw err
    }
  }

  async function duplicateSection(pageId: number | string, sectionId: number) {
    error.value = null
    try {
      const { data } = await apiClient.post<any>(
        `/v1/builder/pages/${pageId}/sections/${sectionId}/duplicate`,
      )
      sections.value = [...sections.value, data.data]
      originalSections.value = [...originalSections.value, JSON.parse(JSON.stringify(data.data))]
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error duplicating section'
      throw err
    }
  }

  // Legacy compat (used by some panels that still call this directly)
  async function updateSection(pageId: number | string, sectionId: number, payload: Partial<SiteSection>) {
    updateSectionLocal(sectionId, payload)
  }

  async function updatePosition(pageId: number | string, sectionId: number, payload: Record<string, any>) {
    updateSectionLocal(sectionId, payload)
  }

  function clearSections() {
    sections.value = []
    originalSections.value = []
    dirtyIds.value = new Set()
    selectedSectionId.value = null
    clearHistory()
  }

  return {
    sections,
    selectedSectionId,
    dirtyIds,
    isLoading,
    isSaving,
    error,
    historyStack,
    redoStack,
    setSelectedSection,
    getSelectedSection,
    fetchSections,
    takeSnapshot,
    hasPendingChanges,
    updateSectionLocal,
    updateContentLocal,
    updateStyleLocal,
    updateLayoutLocal,
    saveAll,
    discardAll,
    addSection,
    deleteSection,
    reorderSections,
    duplicateSection,
    updateSection,
    updatePosition,
    clearSections,
    undo,
    redo,
  }
})
