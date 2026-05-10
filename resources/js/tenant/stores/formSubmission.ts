import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { FormSubmission, FormSubmissionStats } from '@/types/builder'

export const useFormSubmissionStore = defineStore('tenant-form-submissions', () => {
  const submissions = ref<FormSubmission[]>([])
  const stats = ref<FormSubmissionStats | null>(null)
  const meta = ref({
    current_page: 1,
    from: 0,
    last_page: 1,
    per_page: 20,
    to: 0,
    total: 0,
  })
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchSubmissions(page = 1, status = '', formType = '') {
    isLoading.value = true
    error.value = null
    try {
      const qp = new URLSearchParams()
      qp.set('page', String(page))
      if (status) qp.set('status', status)
      if (formType) qp.set('form_type', formType)
      const { data } = await apiClient.get<any>(`/v1/builder/form-submissions?${qp.toString()}`)
      if (data.data?.meta) {
        submissions.value = data.data.data
        meta.value = data.data.meta
      } else {
        submissions.value = Array.isArray(data.data) ? data.data : data.data.data
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching submissions'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function fetchStats() {
    try {
      const { data } = await apiClient.get<any>('/v1/builder/form-submissions/stats')
      stats.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error fetching stats'
    }
  }

  async function markAsRead(id: number) {
    try {
      const { data } = await apiClient.patch<any>(`/v1/builder/form-submissions/${id}/read`)
      const idx = submissions.value.findIndex(s => s.id === id)
      if (idx !== -1) submissions.value[idx] = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error marking as read'
      throw err
    }
  }

  async function archiveSubmission(id: number) {
    try {
      const { data } = await apiClient.patch<any>(`/v1/builder/form-submissions/${id}/archive`)
      const idx = submissions.value.findIndex(s => s.id === id)
      if (idx !== -1) submissions.value[idx] = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error archiving submission'
      throw err
    }
  }

  async function deleteSubmission(id: number) {
    try {
      await apiClient.delete(`/v1/builder/form-submissions/${id}`)
      submissions.value = submissions.value.filter(s => s.id !== id)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error deleting submission'
      throw err
    }
  }

  async function batchDelete(ids: number[]) {
    try {
      await apiClient.post('/v1/builder/form-submissions/batch-delete', { ids })
      submissions.value = submissions.value.filter(s => !ids.includes(s.id))
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error batch deleting'
      throw err
    }
  }

  async function exportSubmissions(formType = '') {
    try {
      const qp = formType ? `?form_type=${formType}` : ''
      const { data } = await apiClient.get<any>(`/v1/builder/form-submissions/export${qp}`)
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error exporting submissions'
      throw err
    }
  }

  return {
    submissions,
    stats,
    meta,
    isLoading,
    error,
    fetchSubmissions,
    fetchStats,
    markAsRead,
    archiveSubmission,
    deleteSubmission,
    batchDelete,
    exportSubmissions,
  }
})
