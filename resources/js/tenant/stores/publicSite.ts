import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { PublicSiteData, SitePage } from '@/types/builder'

interface PublicSiteBootstrap {
  site: PublicSiteData
  page: SitePage
}

export const usePublicSiteStore = defineStore('tenant-public-site', () => {
  const siteData = ref<PublicSiteData | null>(null)
  const currentPage = ref<SitePage | null>(null)
  const publishedPages = ref<{ id: number; title: string; slug: string; is_homepage: boolean }[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  let pendingRequests = 0

  function startLoading() {
    pendingRequests += 1
    isLoading.value = true
  }

  function finishLoading() {
    pendingRequests = Math.max(0, pendingRequests - 1)
    isLoading.value = pendingRequests > 0
  }

  async function fetchHomepage() {
    startLoading()
    error.value = null
    currentPage.value = null
    try {
      const { data } = await apiClient.get<PublicSiteBootstrap>('/v1/public/bootstrap')
      siteData.value = data.data.site
      currentPage.value = data.data.page
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error loading homepage'
      return null
    } finally {
      finishLoading()
    }
  }

  async function fetchSite() {
    startLoading()
    error.value = null
    try {
      const { data } = await apiClient.get<any>('/v1/public/site')
      siteData.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error loading site'
    } finally {
      finishLoading()
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
    startLoading()
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
      finishLoading()
    }
  }

  return {
    siteData,
    currentPage,
    publishedPages,
    isLoading,
    error,
    fetchHomepage,
    fetchSite,
    fetchPublishedPages,
    fetchPageBySlug,
  }
})
