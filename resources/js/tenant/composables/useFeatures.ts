import { computed } from 'vue'
import { useAuthStore } from '@tenant/stores/auth'

/**
 * Reactive accessor for the tenant's enabled feature keys.
 *
 * Backed by the `features` array on the authenticated user (set by
 * /api/me + /api/login). Use this to gate UI elements; route-level
 * gating lives in the router guard.
 */
export function useFeatures() {
  const auth = useAuthStore()

  const features = computed<string[]>(() => auth.user?.features ?? [])
  const plan = computed<string | null>(() => auth.user?.plan ?? null)

  function has(key: string): boolean {
    return features.value.includes(key)
  }

  function hasAny(keys: string[]): boolean {
    return keys.some((k) => features.value.includes(k))
  }

  function hasAll(keys: string[]): boolean {
    return keys.every((k) => features.value.includes(k))
  }

  return { features, plan, has, hasAny, hasAll }
}
