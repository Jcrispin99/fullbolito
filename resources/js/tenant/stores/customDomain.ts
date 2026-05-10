import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'

export const useCustomDomainStore = defineStore('tenant-custom-domain', () => {
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const verificationResult = ref<{
    verified: boolean
    domain_status: string
    message?: string
    verification_token?: string
    verification_instructions?: string
  } | null>(null)

  async function setDomain(customDomain: string) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.put<any>('/v1/builder/site/domain', { custom_domain: customDomain })
      verificationResult.value = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error setting domain'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function verifyDomain() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.post<any>('/v1/builder/site/domain/verify')
      verificationResult.value = data.data
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error verifying domain'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function removeDomain() {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.delete<any>('/v1/builder/site/domain')
      verificationResult.value = null
      return data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Error removing domain'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  return {
    isLoading,
    error,
    verificationResult,
    setDomain,
    verifyDomain,
    removeDomain,
  }
})
