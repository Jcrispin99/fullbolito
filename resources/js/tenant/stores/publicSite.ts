import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { PublicSiteData, SitePage } from '@/types/builder'

export const usePublicSiteStore = defineStore('tenant-public-site', () => {
  const siteData = ref<PublicSiteData | null>(null)
  const currentPage = ref<SitePage | null>(null)
  const publishedPages = ref<{ id: number; title: string; slug: string; is_homepage: boolean }[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchSite() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<any>('/v1/public/site')
      siteData.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error loading site'
    } finally {
      isLoading.value = false
    }
  }

  async function fetchPublishedPages() {
    try {
      const { data } = await apiClient.get<any>('/v1/public/pages')
      publishedPages.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error loading pages'
    }
  }

  async function fetchPageBySlug(slug: string) {
    isLoading.value = true
    error.value = null
    currentPage.value = null
    try {
      const { data } = await apiClient.get<any>(`/v1/public/pages/${slug}`)
      currentPage.value = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Page not found'
      return null
    } finally {
      isLoading.value = false
    }
  }

  return {
    siteData,
    currentPage,
    publishedPages,
    isLoading,
    error,
    fetchSite,
    fetchPublishedPages,
    fetchPageBySlug,
  }
})
