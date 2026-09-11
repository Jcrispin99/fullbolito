import { readonly, ref } from 'vue'
import type { Router } from 'vue-router'

const isNavigatingState = ref(false)

export const isNavigating = readonly(isNavigatingState)

/**
 * Keeps the global loading overlay in sync with Vue Router navigation.
 */
export function installNavigationLoading(router: Router): void {
  router.beforeEach((to, from) => {
    if (to.fullPath !== from.fullPath) {
      isNavigatingState.value = true
    }
  })

  router.afterEach(() => {
    isNavigatingState.value = false
  })

  router.onError(() => {
    isNavigatingState.value = false
  })
}
