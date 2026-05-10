import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { SiteSection } from '@/types/builder'

export const useGlobalSectionStore = defineStore('tenant-global-sections', () => {
  const globals = ref<SiteSection[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchGlobals() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<any>('/v1/builder/global-sections')
      globals.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching global sections'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function createGlobal(payload: {
    block_type_key: string
    global_name: string
    layout?: any
    content?: any
    style_overrides?: any
  }) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.post<any>('/v1/builder/global-sections', payload)
      globals.value.push(data.data)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error creating global section'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function updateGlobal(id: number, payload: Partial<SiteSection> & { global_name?: string }) {
    error.value = null
    try {
      const { data } = await apiClient.put<any>(`/v1/builder/global-sections/${id}`, payload)
      const idx = globals.value.findIndex(g => g.id === id)
      if (idx !== -1) globals.value[idx] = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error updating global section'
      throw err
    }
  }

  async function deleteGlobal(id: number) {
    error.value = null
    try {
      await apiClient.delete(`/v1/builder/global-sections/${id}`)
      globals.value = globals.value.filter(g => g.id !== id)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error deleting global section'
      throw err
    }
  }

  async function addToPage(sectionId: number, pageId: number, sortOrder?: number) {
    error.value = null
    try {
      const { data } = await apiClient.post<any>(
        `/v1/builder/global-sections/${sectionId}/add-to-page`,
        { page_id: pageId, sort_order: sortOrder },
      )
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error adding global section to page'
      throw err
    }
  }

  return {
    globals,
    isLoading,
    error,
    fetchGlobals,
    createGlobal,
    updateGlobal,
    deleteGlobal,
    addToPage,
  }
})
