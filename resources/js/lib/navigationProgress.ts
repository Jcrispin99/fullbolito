import { BProgress } from '@bprogress/core'
import '@bprogress/core/css'
import type { Router } from 'vue-router'

BProgress.configure({
  minimum: 0.12,
  easing: 'ease-out',
  speed: 320,
  trickleSpeed: 180,
  showSpinner: false,
})

/**
 * Shows the global progress bar while Vue Router resolves guards and lazy views.
 */
export function installNavigationProgress(router: Router): void {
  router.beforeEach((to, from) => {
    if (to.fullPath !== from.fullPath) {
      BProgress.start()
    }
  })

  router.afterEach(() => {
    BProgress.done()
  })

  router.onError(() => {
    BProgress.done()
  })
}
