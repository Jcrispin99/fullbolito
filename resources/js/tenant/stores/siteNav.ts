import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { SiteNav, SiteNavItem, NavConfig } from '@/types/builder'

export const useSiteNavStore = defineStore('tenant-site-nav', () => {
  const navs = ref<SiteNav[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  function getNav(location: 'header' | 'footer'): SiteNav | undefined {
    return navs.value.find(n => n.location === location)
  }

  async function fetchNavs() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<any>('/v1/builder/navs')
      navs.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching navs'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function updateNavConfig(location: string, config: NavConfig) {
    error.value = null
    try {
      const { data } = await apiClient.put<any>(`/v1/builder/navs/${location}`, { config })
      const idx = navs.value.findIndex(n => n.location === location)
      if (idx !== -1) navs.value[idx] = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error updating nav'
      throw err
    }
  }

  async function addNavItem(location: string, item: Partial<SiteNavItem>) {
    error.value = null
    try {
      const { data } = await apiClient.post<any>(`/v1/builder/navs/${location}/items`, item)
      const nav = navs.value.find(n => n.location === location)
      if (nav) nav.items.push(data.data)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error adding nav item'
      throw err
    }
  }

  async function updateNavItem(location: string, itemId: number, payload: Partial<SiteNavItem>) {
    error.value = null
    try {
      const { data } = await apiClient.put<any>(`/v1/builder/navs/${location}/items/${itemId}`, payload)
      const nav = navs.value.find(n => n.location === location)
      if (nav) {
        const idx = nav.items.findIndex(i => i.id === itemId)
        if (idx !== -1) nav.items[idx] = data.data
      }
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error updating nav item'
      throw err
    }
  }

  async function deleteNavItem(location: string, itemId: number) {
    error.value = null
    try {
      await apiClient.delete(`/v1/builder/navs/${location}/items/${itemId}`)
      const nav = navs.value.find(n => n.location === location)
      if (nav) {
        nav.items = nav.items.filter(i => i.id !== itemId)
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error deleting nav item'
      throw err
    }
  }

  async function reorderNavItems(location: string, ids: number[]) {
    error.value = null
    try {
      const { data } = await apiClient.put<any>(`/v1/builder/navs/${location}/reorder`, { ids })
      const idx = navs.value.findIndex(n => n.location === location)
      if (idx !== -1) navs.value[idx] = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error reordering nav items'
      throw err
    }
  }

  return {
    navs,
    isLoading,
    error,
    getNav,
    fetchNavs,
    updateNavConfig,
    addNavItem,
    updateNavItem,
    deleteNavItem,
    reorderNavItems,
  }
})
