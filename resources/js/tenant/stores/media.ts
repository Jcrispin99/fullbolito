import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { SiteAsset } from '@/types/builder'

export const useMediaStore = defineStore('tenant-media', () => {
  const assets = ref<SiteAsset[]>([])
  const meta = ref({
    current_page: 1,
    from: 0,
    last_page: 1,
    per_page: 30,
    to: 0,
    total: 0,
  })
  const isLoading = ref(false)
  const isUploading = ref(false)
  const error = ref<string | null>(null)

  async function fetchAssets(page = 1, folder = '', type = '') {
    isLoading.value = true
    error.value = null
    try {
      const qp = new URLSearchParams()
      qp.set('page', String(page))
      qp.set('per_page', '30')
      if (folder) qp.set('folder', folder)
      if (type) qp.set('type', type)
      const { data } = await apiClient.get<any>(`/v1/builder/media?${qp.toString()}`)
      if (data.data?.meta) {
        assets.value = data.data.data
        meta.value = data.data.meta
      } else {
        assets.value = Array.isArray(data.data) ? data.data : []
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching media'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function uploadAsset(file: File, altText = '', folder = 'general'): Promise<SiteAsset> {
    isUploading.value = true
    error.value = null
    try {
      const formData = new FormData()
      formData.append('file', file)
      if (altText) formData.append('alt_text', altText)
      formData.append('folder', folder)
      const { data } = await apiClient.post<any>('/v1/builder/media', formData)
      assets.value.unshift(data.data)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error uploading file'
      throw err
    } finally {
      isUploading.value = false
    }
  }

  async function updateAsset(id: number, payload: { alt_text?: string; folder?: string }) {
    error.value = null
    try {
      const { data } = await apiClient.put<any>(`/v1/builder/media/${id}`, payload)
      const idx = assets.value.findIndex(a => a.id === id)
      if (idx !== -1) assets.value[idx] = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error updating asset'
      throw err
    }
  }

  async function deleteAsset(id: number) {
    error.value = null
    try {
      await apiClient.delete(`/v1/builder/media/${id}`)
      assets.value = assets.value.filter(a => a.id !== id)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error deleting asset'
      throw err
    }
  }

  return {
    assets,
    meta,
    isLoading,
    isUploading,
    error,
    fetchAssets,
    uploadAsset,
    updateAsset,
    deleteAsset,
  }
})
