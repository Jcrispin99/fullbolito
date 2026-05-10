import { computed } from 'vue'
import { MAIN_APPS, type NavApp } from '@tenant/config/navigation'
import { useFeatures } from '@tenant/composables/useFeatures'

/**
 * Returns the subset of MAIN_APPS the current tenant can see, given its
 * active feature set. Apps without a `feature` key are always shown.
 */
export function useNav() {
  const { has } = useFeatures()

  const visibleApps = computed<NavApp[]>(() =>
    MAIN_APPS.filter((app) => !app.feature || has(app.feature)),
  )

  return { visibleApps }
}
