import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { BlockType } from '@/types/builder'

export const useBlockCatalogStore = defineStore('tenant-block-catalog', () => {
  const blockTypes = ref<BlockType[]>([])
  const isLoading = ref(false)
  const loaded = ref(false)

  async function fetchBlockTypes() {
    if (loaded.value) return
    isLoading.value = true
    try {
      const { data } = await apiClient.get<any>('/v1/builder/block-types')
      blockTypes.value = data.data
      loaded.value = true
    } catch (err: any) {
      console.error('Error fetching block types', err)
      throw err
    } finally {
      isLoading.value = false
    }
  }

  function getBlockType(key: string): BlockType | undefined {
    return blockTypes.value.find(b => b.key === key)
  }

  function getByCategory(category: string): BlockType[] {
    return blockTypes.value.filter(b => b.category === category)
  }

  const categories = [
    { key: 'headers', label: 'Headers' },
    { key: 'content', label: 'Contenido' },
    { key: 'media', label: 'Media' },
    { key: 'grids', label: 'Grillas' },
    { key: 'interactive', label: 'Interactivo' },
    { key: 'commerce', label: 'Comercio' },
  ]

  return {
    blockTypes,
    isLoading,
    loaded,
    categories,
    fetchBlockTypes,
    getBlockType,
    getByCategory,
  }
})
