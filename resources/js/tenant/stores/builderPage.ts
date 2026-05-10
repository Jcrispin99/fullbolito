import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import { createFetchAbort } from '@/composables/useFetchAbort'
import type { SitePage } from '@/types/builder'

export const useBuilderPageStore = defineStore('tenant-builder-page', () => {
  const pages = ref<SitePage[]>([])
  const currentPage = ref<SitePage | null>(null)
  const meta = ref({
    current_page: 1,
    from: 0,
    last_page: 1,
    per_page: 15,
    to: 0,
    total: 0,
  })
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const { getSignal, isAbortError } = createFetchAbort()

  async function fetchPages(page = 1, perPage: number | string = 50, search = '', status = '') {
    isLoading.value = true
    error.value = null
    try {
      const qp = new URLSearchParams()
      qp.set('page', String(page))
      qp.set('per_page', String(perPage))
      if (search) qp.set('search', search)
      if (status) qp.set('status', status)
      const { data } = await apiClient.get<any>(
        `/v1/builder/pages?${qp.toString()}`,
        { signal: getSignal() },
      )
      if (data.data?.meta) {
        pages.value = data.data.data
        meta.value = data.data.meta
      } else {
        pages.value = Array.isArray(data.data) ? data.data : data.data.data
      }
    } catch (err: any) {
      if (isAbortError(err)) return
      error.value = err.response?.data?.message || 'Error fetching pages'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function fetchPage(id: number | string) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<any>(`/v1/builder/pages/${id}`)
      currentPage.value = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching page'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function createPage(payload: any) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.post<any>('/v1/builder/pages', payload)
      pages.value.unshift(data.data)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error creating page'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function updatePage(id: number | string, payload: any) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.put<any>(`/v1/builder/pages/${id}`, payload)
      const idx = pages.value.findIndex(p => p.id === Number(id))
      if (idx !== -1) pages.value[idx] = data.data
      currentPage.value = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error updating page'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function deletePage(id: number | string) {
    isLoading.value = true
    error.value = null
    try {
      await apiClient.delete(`/v1/builder/pages/${id}`)
      pages.value = pages.value.filter(p => p.id !== Number(id))
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error deleting page'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function publishPage(id: number | string) {
    try {
      const { data } = await apiClient.post<any>(`/v1/builder/pages/${id}/publish`)
      const idx = pages.value.findIndex(p => p.id === Number(id))
      if (idx !== -1) pages.value[idx] = data.data
      if (currentPage.value?.id === Number(id)) currentPage.value = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error publishing page'
      throw err
    }
  }

  async function unpublishPage(id: number | string) {
    try {
      const { data } = await apiClient.post<any>(`/v1/builder/pages/${id}/unpublish`)
      const idx = pages.value.findIndex(p => p.id === Number(id))
      if (idx !== -1) pages.value[idx] = data.data
      if (currentPage.value?.id === Number(id)) currentPage.value = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error unpublishing page'
      throw err
    }
  }

  async function duplicatePage(id: number | string) {
    try {
      const { data } = await apiClient.post<any>(`/v1/builder/pages/${id}/duplicate`)
      pages.value.unshift(data.data)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error duplicating page'
      throw err
    }
  }

  async function previewPage(id: number | string) {
    try {
      const { data } = await apiClient.get<any>(`/v1/builder/pages/${id}/preview`)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error previewing page'
      throw err
    }
  }

  return {
    pages,
    currentPage,
    meta,
    isLoading,
    error,
    fetchPages,
    fetchPage,
    createPage,
    updatePage,
    deletePage,
    publishPage,
    unpublishPage,
    duplicatePage,
    previewPage,
  }
})
