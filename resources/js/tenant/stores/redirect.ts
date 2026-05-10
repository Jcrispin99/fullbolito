import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { SiteRedirect } from '@/types/builder'

export const useRedirectStore = defineStore('tenant-redirects', () => {
  const redirects = ref<SiteRedirect[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchRedirects() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<any>('/v1/builder/redirects')
      redirects.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching redirects'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function createRedirect(payload: { from_slug: string; to_slug: string; type?: number }) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.post<any>('/v1/builder/redirects', payload)
      redirects.value.unshift(data.data)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error creating redirect'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function updateRedirect(id: number, payload: Partial<SiteRedirect>) {
    error.value = null
    try {
      const { data } = await apiClient.put<any>(`/v1/builder/redirects/${id}`, payload)
      const idx = redirects.value.findIndex(r => r.id === id)
      if (idx !== -1) redirects.value[idx] = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error updating redirect'
      throw err
    }
  }

  async function deleteRedirect(id: number) {
    error.value = null
    try {
      await apiClient.delete(`/v1/builder/redirects/${id}`)
      redirects.value = redirects.value.filter(r => r.id !== id)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error deleting redirect'
      throw err
    }
  }

  return {
    redirects,
    isLoading,
    error,
    fetchRedirects,
    createRedirect,
    updateRedirect,
    deleteRedirect,
  }
})
