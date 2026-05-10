import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { Site, SiteTheme } from '@/types/builder'

export const useSiteConfigStore = defineStore('tenant-site-config', () => {
  const site = ref<Site | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchSite() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<any>('/v1/builder/site')
      site.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching site'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function updateSite(payload: Partial<Site>) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.put<any>('/v1/builder/site', payload)
      site.value = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error updating site'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function updateTheme(payload: Partial<SiteTheme>) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.put<any>('/v1/builder/site/theme', payload)
      if (site.value) {
        site.value.theme = data.data
      }
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error updating theme'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  return {
    site,
    isLoading,
    error,
    fetchSite,
    updateSite,
    updateTheme,
  }
})
