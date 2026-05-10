import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'

export interface AppItem {
  key: string
  label: string
  description: string | null
  icon: string | null
  addon_price: number
  enabled: boolean
  included_in_plan: boolean
  is_addon: boolean
  is_active_addon: boolean
  can_toggle: boolean
}

export interface CurrentPlan {
  name: string
  slug: string
  price: number
  duration_days: number
  status: string
  starts_at: string | null
  ends_at: string | null
  trial_ends_at: string | null
}

export interface PlanOption {
  id: number
  name: string
  slug: string
  price: number
  duration_days: number
  modules: Array<{ key: string; label: string; icon: string | null }>
}

export interface AppsCatalog {
  apps: AppItem[]
  current_plan: CurrentPlan | null
  plans: PlanOption[]
  addon_total: number
  has_stripe_subscription: boolean
}

export const useAppsStore = defineStore('apps', () => {
  const catalog = ref<AppsCatalog | null>(null)
  const isLoading = ref(false)
  const togglingKey = ref<string | null>(null)
  const switchingPlan = ref<string | null>(null)
  const error = ref<string | null>(null)

  async function fetchCatalog(): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<AppsCatalog>('/v1/apps')
      catalog.value = data.data
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? err.message ?? 'Failed to load apps'
    } finally {
      isLoading.value = false
    }
  }

  async function attachAddon(key: string): Promise<void> {
    togglingKey.value = key
    error.value = null
    try {
      const { data } = await apiClient.post<AppsCatalog>(`/v1/apps/${key}/addon`)
      catalog.value = data.data
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? err.message ?? 'Failed to attach addon'
      throw err
    } finally {
      togglingKey.value = null
    }
  }

  async function detachAddon(key: string): Promise<void> {
    togglingKey.value = key
    error.value = null
    try {
      const { data } = await apiClient.delete<AppsCatalog>(`/v1/apps/${key}/addon`)
      catalog.value = data.data
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? err.message ?? 'Failed to detach addon'
      throw err
    } finally {
      togglingKey.value = null
    }
  }

  async function switchPlan(slug: string): Promise<void> {
    switchingPlan.value = slug
    error.value = null
    try {
      const { data } = await apiClient.post<AppsCatalog>('/v1/apps/plan', {
        plan_slug: slug,
      })
      catalog.value = data.data
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? err.message ?? 'Failed to switch plan'
      throw err
    } finally {
      switchingPlan.value = null
    }
  }

  return {
    catalog,
    isLoading,
    togglingKey,
    switchingPlan,
    error,
    fetchCatalog,
    attachAddon,
    detachAddon,
    switchPlan,
  }
})
