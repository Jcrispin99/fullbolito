import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { MAIN_APPS, type NavApp } from '@tenant/config/navigation'

const STORAGE_KEY = 'active_app'

export const useActiveAppStore = defineStore('activeApp', () => {
  const override = ref<string | null>(sessionStorage.getItem(STORAGE_KEY))

  function setApp(appTitle: string) {
    override.value = appTitle
    sessionStorage.setItem(STORAGE_KEY, appTitle)
  }

  function clear() {
    override.value = null
    sessionStorage.removeItem(STORAGE_KEY)
  }

  function resolve(route: ReturnType<typeof useRoute>): NavApp {
    const name = override.value ?? (route.meta.defaultApp as string | undefined)
    return MAIN_APPS.find(a => a.title === name) ?? MAIN_APPS[0]!
  }

  return { override, setApp, clear, resolve }
})
