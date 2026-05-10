import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { SitePageVersion } from '@/types/builder'

export const usePageVersionStore = defineStore('tenant-page-versions', () => {
  const versions = ref<SitePageVersion[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchVersions(pageId: number | string) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<any>(`/v1/builder/pages/${pageId}/versions`)
      versions.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching versions'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function revertToVersion(pageId: number | string, versionId: number) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.post<any>(
        `/v1/builder/pages/${pageId}/versions/${versionId}/revert`,
      )
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error reverting version'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  function clearVersions() {
    versions.value = []
  }

  return {
    versions,
    isLoading,
    error,
    fetchVersions,
    revertToVersion,
    clearVersions,
  }
})
