<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { toast } from 'vue-sonner'

import DashboardLayout from '@tenant/layouts/DashboardLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { Button } from '@/components/ui/button'

import { ArrowLeft, Save, RotateCcw, Printer } from 'lucide-vue-next'

import ReceiptTemplateForm from './Form.vue'
import {
    normalizeLayout,
    type ReceiptTemplateLayout,
} from '@tenant/components/receiptBlocks'
import { useReceiptTemplateStore } from '@tenant/stores/receiptTemplate'
import {
    makeMockReceiptCompany,
    makeMockReceiptSale,
} from '@tenant/lib/mockReceiptSale'

const router = useRouter()
const store = useReceiptTemplateStore()
const { isLoading, logoUrl } = storeToRefs(store)

const formData = ref<ReceiptTemplateLayout>(normalizeLayout({}))

const breadcrumbs = computed(() => [
    { label: 'POS', href: '/admin/pos-configs' },
    { label: 'Diseño de comprobante' },
])

onMounted(async () => {
    try {
        const layout = await store.fetchTemplate()
        if (layout) formData.value = normalizeLayout(layout)
    } catch (e) {
        console.error(e)
        toast.error('No se pudo cargar la plantilla')
    }
})

const handleSave = async () => {
    try {
        await store.updateTemplate(formData.value as any)
        toast.success('Plantilla guardada')
    } catch (e: any) {
        const msg = e?.response?.data?.message ?? 'No se pudo guardar'
        toast.error(msg)
    }
}

const handleReset = async () => {
    try {
        const layout = await store.resetTemplate()
        if (layout) formData.value = normalizeLayout(layout)
        toast.success('Plantilla restablecida')
    } catch (e: any) {
        toast.error(e?.response?.data?.message ?? 'No se pudo restablecer')
    }
}

const handleTestPrint = async () => {
    try {
        const { printReceipt } = await import('@tenant/lib/printReceipt')
        await printReceipt({
            template: formData.value as any,
            sale: makeMockReceiptSale(),
            company: makeMockReceiptCompany(),
            templateLogoUrl: logoUrl.value,
        })
    } catch (e) {
        console.error(e)
        toast.error('No se pudo abrir el diálogo de impresión')
    }
}

const handleUploadLogo = async (file: File) => {
    try {
        await store.uploadLogo(file)
        toast.success('Logo actualizado')
    } catch (e: any) {
        const msg = e?.response?.data?.message
            ?? Object.values(e?.response?.data?.errors ?? {}).flat()[0]
            ?? 'No se pudo subir el logo'
        toast.error(String(msg))
    }
}

const handleDeleteLogo = async () => {
    try {
        await store.deleteLogo()
        toast.success('Logo eliminado')
    } catch (e: any) {
        toast.error(e?.response?.data?.message ?? 'No se pudo eliminar el logo')
    }
}

const handleBack = () => router.push('/admin/pos-configs')
</script>

<template>
    <DashboardLayout :breadcrumbs="breadcrumbs">
        <PageHeader title="Diseño de comprobante">
            <template #leading>
                <Button
                    variant="outline"
                    size="icon"
                    class="h-9 w-9"
                    aria-label="Volver"
                    @click="handleBack"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </template>
            <template #trailing>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9"
                    :disabled="isLoading"
                    @click="handleTestPrint"
                >
                    <Printer class="mr-2 h-4 w-4" />
                    Imprimir prueba
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9"
                    :disabled="isLoading"
                    @click="handleReset"
                >
                    <RotateCcw class="mr-2 h-4 w-4" />
                    Restaurar
                </Button>
                <Button
                    size="sm"
                    class="h-9"
                    :disabled="isLoading"
                    @click="handleSave"
                >
                    <Save class="mr-2 h-4 w-4" />
                    {{ isLoading ? 'Guardando...' : 'Guardar' }}
                </Button>
            </template>
        </PageHeader>

        <ReceiptTemplateForm
            v-model="formData"
            :logo-url="logoUrl"
            :is-uploading-logo="isLoading"
            @upload-logo="handleUploadLogo"
            @delete-logo="handleDeleteLogo"
        />
    </DashboardLayout>
</template>
