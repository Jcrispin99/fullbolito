import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { SiteTemplate } from '@/types/builder'

export const useTemplateStore = defineStore('tenant-templates', () => {
  const templates = ref<SiteTemplate[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchTemplates(category = '') {
    isLoading.value = true
    error.value = null
    try {
      const qp = category ? `?category=${category}` : ''
      const { data } = await apiClient.get<any>(`/v1/builder/templates${qp}`)
      templates.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching templates'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function saveAsTemplate(payload: {
    name: string
    slug: string
    description?: string
    category?: string
  }) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.post<any>('/v1/builder/templates/save', payload)
      templates.value.unshift(data.data)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error saving template'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function applyTemplate(templateId: number) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.post<any>(`/v1/builder/templates/${templateId}/apply`)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error applying template'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function duplicateSite() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.post<any>('/v1/builder/site/duplicate')
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error duplicating site'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  return {
    templates,
    isLoading,
    error,
    fetchTemplates,
    saveAsTemplate,
    applyTemplate,
    duplicateSite,
  }
})
