import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { ReceiptTemplateLayout } from '@tenant/components/ReceiptRenderer.vue'

export const useReceiptTemplateStore = defineStore('receiptTemplate', () => {
    const layout = ref<ReceiptTemplateLayout | null>(null)
    const logoUrl = ref<string | null>(null)
    const isLoading = ref(false)

    const applyResponse = (data: any) => {
        layout.value = data?.layout ?? null
        logoUrl.value = data?.logo_url ?? null
    }

    const fetchTemplate = async () => {
        isLoading.value = true
        try {
            const response = await apiClient.get<any>('/v1/receipt-template')
            applyResponse(response.data.data)
            return layout.value
        } finally {
            isLoading.value = false
        }
    }

    const updateTemplate = async (newLayout: ReceiptTemplateLayout) => {
        isLoading.value = true
        try {
            const response = await apiClient.put<any>('/v1/receipt-template', {
                layout: newLayout,
            })
            applyResponse(response.data.data)
            if (!layout.value) layout.value = newLayout
            return layout.value
        } finally {
            isLoading.value = false
        }
    }

    const resetTemplate = async () => {
        isLoading.value = true
        try {
            const response = await apiClient.post<any>(
                '/v1/receipt-template/reset',
            )
            applyResponse(response.data.data)
            return layout.value
        } finally {
            isLoading.value = false
        }
    }

    const uploadLogo = async (file: File) => {
        isLoading.value = true
        try {
            const fd = new FormData()
            fd.append('logo', file)
            const response = await apiClient.post<any>(
                '/v1/receipt-template/logo',
                fd,
                { headers: { 'Content-Type': 'multipart/form-data' } },
            )
            applyResponse(response.data.data)
            return logoUrl.value
        } finally {
            isLoading.value = false
        }
    }

    const deleteLogo = async () => {
        isLoading.value = true
        try {
            const response = await apiClient.delete<any>(
                '/v1/receipt-template/logo',
            )
            applyResponse(response.data.data)
        } finally {
            isLoading.value = false
        }
    }

    return {
        layout,
        logoUrl,
        isLoading,
        fetchTemplate,
        updateTemplate,
        resetTemplate,
        uploadLogo,
        deleteLogo,
    }
})
