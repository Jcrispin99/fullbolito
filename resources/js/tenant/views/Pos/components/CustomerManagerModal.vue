<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
    DialogRoot,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogClose,
} from 'radix-vue'
import { usePosStore } from '@tenant/stores/pos'
import { apiClient } from '@tenant/lib/api'
import { Button } from '@/components/ui/button'
import {
    Search,
    Users,
    X,
    Check,
    Mail,
    IdCard,
    Plus,
    Pencil,
    ArrowLeft,
    Save,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import type { PosCustomer } from '@tenant/types/pos'
import CustomerForm from '@tenant/views/Customers/Form.vue'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void
    (e: 'select', customer: PosCustomer): void
}>()

const store = usePosStore()
const searchQuery = ref('')

type View = 'list' | 'form'
const view = ref<View>('list')
const formMode = ref<'create' | 'edit'>('create')
const editingId = ref<number | null>(null)
const formInitialData = ref<any>({})
const formErrors = ref<Record<string, string>>({})
const isSaving = ref(false)
const formRef = ref<InstanceType<typeof CustomerForm> | null>(null)

watch(
    () => props.open,
    (val) => {
        if (val) {
            searchQuery.value = ''
            resetToList()
        }
    },
)

const resetToList = () => {
    view.value = 'list'
    editingId.value = null
    formInitialData.value = {}
    formErrors.value = {}
}

const filteredCustomers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return store.customers
    return store.customers.filter((c) => {
        return (
            c.display_name.toLowerCase().includes(q) ||
            c.name.toLowerCase().includes(q) ||
            (c.document_number ?? '').toLowerCase().includes(q) ||
            (c.email ?? '').toLowerCase().includes(q)
        )
    })
})

const handleSelect = (customer: PosCustomer) => {
    emit('select', customer)
    emit('update:open', false)
}

const openCreate = () => {
    formMode.value = 'create'
    editingId.value = null
    formInitialData.value = {}
    formErrors.value = {}
    view.value = 'form'
}

const openEdit = async (customer: PosCustomer) => {
    formMode.value = 'edit'
    editingId.value = customer.id
    formErrors.value = {}
    view.value = 'form'

    try {
        const { data } = await apiClient.get<any>(`/v1/customers/${customer.id}`)
        formInitialData.value = data.data ?? data
    } catch {
        formInitialData.value = {
            name: customer.name,
            display_name: customer.display_name,
            document_number: customer.document_number,
            email: customer.email,
        }
    }
}

const triggerSave = () => {
    formRef.value?.submit()
}

const handleSubmit = async (formData: any) => {
    isSaving.value = true
    formErrors.value = {}

    try {
        if (formMode.value === 'edit' && editingId.value !== null) {
            await apiClient.patch(`/v1/customers/${editingId.value}`, formData)
            toast.success('Cliente actualizado')
        } else {
            await apiClient.post('/v1/customers', formData)
            toast.success('Cliente creado')
        }

        await store.loadCustomers()
        resetToList()
    } catch (err: any) {
        const e = err?.response?.data
        if (e?.errors) {
            const flat: Record<string, string> = {}
            Object.entries(e.errors).forEach(([k, v]: any) => {
                flat[k] = Array.isArray(v) ? v[0] : String(v)
            })
            formErrors.value = flat
            toast.error('Revisa los campos marcados')
        } else {
            toast.error(e?.message || 'No se pudo guardar el cliente')
        }
    } finally {
        isSaving.value = false
    }
}

const formTitle = computed(() =>
    formMode.value === 'create' ? 'Nuevo cliente' : 'Editar cliente',
)
</script>

<template>
    <DialogRoot :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 w-full max-w-4xl h-[85vh] -translate-x-1/2 -translate-y-1/2 border bg-background shadow-lg sm:rounded-lg flex flex-col data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
                :trap-focus="false"
                @pointer-down-outside="(e: any) => e.preventDefault()"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b px-6 py-4 gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <Button
                            v-if="view === 'form'"
                            variant="ghost"
                            size="sm"
                            class="gap-1.5 -ml-2"
                            :disabled="isSaving"
                            @click="resetToList"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            <span>Volver</span>
                        </Button>
                        <Users
                            v-else
                            class="h-4 w-4 text-muted-foreground"
                        />
                        <DialogTitle class="text-base font-semibold">
                            {{ view === 'list' ? 'Clientes' : formTitle }}
                        </DialogTitle>
                        <DialogDescription class="sr-only">
                            Buscar, seleccionar, crear o editar clientes
                        </DialogDescription>
                    </div>
                    <DialogClose
                        class="rounded-sm opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring shrink-0"
                    >
                        <X class="h-4 w-4" />
                        <span class="sr-only">Cerrar</span>
                    </DialogClose>
                </div>

                <!-- LIST VIEW -->
                <template v-if="view === 'list'">
                    <!-- Search + actions -->
                    <div class="px-6 py-3 border-b flex items-center gap-3">
                        <div class="relative flex-1">
                            <Search
                                class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                            />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por nombre, documento o email…"
                                class="w-full h-9 pl-9 pr-3 bg-background border border-input rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                        <Button
                            size="sm"
                            class="gap-1.5 shrink-0"
                            @click="openCreate"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Nuevo cliente
                        </Button>
                    </div>

                    <!-- List -->
                    <div class="flex-1 overflow-y-auto">
                        <div
                            v-if="filteredCustomers.length === 0"
                            class="px-6 py-12 text-center text-sm text-muted-foreground"
                        >
                            Sin clientes que coincidan
                        </div>

                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="customer in filteredCustomers"
                                :key="customer.id"
                                class="px-6 py-3 hover:bg-muted/40 transition-colors flex items-center justify-between gap-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium truncate">
                                        {{ customer.display_name }}
                                    </p>
                                    <div
                                        class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5 text-xs text-muted-foreground"
                                    >
                                        <span
                                            v-if="customer.document_number"
                                            class="inline-flex items-center gap-1"
                                        >
                                            <IdCard class="h-3 w-3" />
                                            {{ customer.document_number }}
                                        </span>
                                        <span
                                            v-if="customer.email"
                                            class="inline-flex items-center gap-1 truncate"
                                        >
                                            <Mail class="h-3 w-3 shrink-0" />
                                            <span class="truncate">{{ customer.email }}</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Editar cliente"
                                        @click="openEdit(customer)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="gap-1.5"
                                        :class="
                                            store.selectedCustomer?.id === customer.id
                                                ? 'border-primary text-primary'
                                                : ''
                                        "
                                        @click="handleSelect(customer)"
                                    >
                                        <Check
                                            v-if="store.selectedCustomer?.id === customer.id"
                                            class="h-3.5 w-3.5"
                                        />
                                        {{
                                            store.selectedCustomer?.id === customer.id
                                                ? 'Seleccionado'
                                                : 'Seleccionar'
                                        }}
                                    </Button>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Footer -->
                    <div
                        class="border-t px-6 py-2 text-xs text-muted-foreground flex items-center justify-between"
                    >
                        <span>
                            {{ filteredCustomers.length }} de
                            {{ store.customers.length }} cliente(s)
                        </span>
                    </div>
                </template>

                <!-- FORM VIEW -->
                <template v-else>
                    <div class="flex-1 overflow-y-auto px-6 py-4">
                        <CustomerForm
                            ref="formRef"
                            compact
                            :mode="formMode"
                            :initial-data="formInitialData"
                            :is-loading="isSaving"
                            :errors="formErrors"
                            @submit="handleSubmit"
                        />
                    </div>

                    <div
                        class="flex justify-end gap-2 border-t px-6 py-3 shrink-0"
                    >
                        <Button
                            variant="outline"
                            :disabled="isSaving"
                            @click="resetToList"
                        >
                            Cancelar
                        </Button>
                        <Button
                            :disabled="isSaving"
                            class="gap-1.5"
                            @click="triggerSave"
                        >
                            <Save class="h-4 w-4" />
                            {{ isSaving ? 'Guardando…' : 'Guardar' }}
                        </Button>
                    </div>
                </template>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
