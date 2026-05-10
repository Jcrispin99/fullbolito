import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const STORAGE_KEY = 'selected_company_ids'

function loadFromStorage(): number[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

export const COMPANY_FILTER_CHANGED = 'company-filter-changed'

export const useCompanyFilterStore = defineStore('companyFilter', () => {
  const selectedIds = ref<number[]>(loadFromStorage())

  const headerValue = computed(() =>
    selectedIds.value.length > 0 ? selectedIds.value.join(',') : '',
  )

  function toggle(id: number) {
    const idx = selectedIds.value.indexOf(id)
    if (idx === -1) {
      selectedIds.value.push(id)
    } else {
      selectedIds.value.splice(idx, 1)
    }
    persist()
  }

  function setAll(ids: number[]) {
    selectedIds.value = [...ids]
    persist()
  }

  function clear() {
    selectedIds.value = []
    persist()
  }

  function isSelected(id: number) {
    return selectedIds.value.includes(id)
  }

  function persist() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(selectedIds.value))
    window.dispatchEvent(new CustomEvent(COMPANY_FILTER_CHANGED))
  }

  return { selectedIds, headerValue, toggle, setAll, clear, isSelected }
})
