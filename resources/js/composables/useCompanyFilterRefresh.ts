import { onMounted, onBeforeUnmount } from 'vue'
import { COMPANY_FILTER_CHANGED } from '@tenant/stores/companyFilter'

/**
 * Calls the provided callback whenever the company filter changes.
 * Usage: useCompanyFilterRefresh(() => loadSales())
 */
export function useCompanyFilterRefresh(callback: () => void) {
  const handler = () => callback()

  onMounted(() => {
    window.addEventListener(COMPANY_FILTER_CHANGED, handler)
  })

  onBeforeUnmount(() => {
    window.removeEventListener(COMPANY_FILTER_CHANGED, handler)
  })
}
