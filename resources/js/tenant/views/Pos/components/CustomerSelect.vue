<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePosStore } from '@tenant/stores/pos'
import { SearchSelect } from '@/components/ui/search-select'
import { Button } from '@/components/ui/button'
import { Users } from 'lucide-vue-next'
import type { PosCustomer } from '@tenant/types/pos'
import CustomerManagerModal from './CustomerManagerModal.vue'

const store = usePosStore()
const managerOpen = ref(false)

const customerOptions = computed(() =>
    store.customers.map((c: PosCustomer) => ({
        value: c.id,
        label: c.display_name,
        description: c.document_number || null,
    })),
)

const selectedId = computed({
    get: () => store.selectedCustomer?.id ?? undefined,
    set: (val) => {
        if (val === undefined || val === null) {
            store.setCustomer(null)
        } else {
            const customer = store.customers.find((c) => c.id === Number(val))
            store.setCustomer(customer || null)
        }
    },
})

const handleManagerSelect = (customer: PosCustomer) => {
    store.setCustomer(customer)
}
</script>

<template>
    <div class="flex items-center gap-1.5">
        <div class="flex-1 min-w-0">
            <SearchSelect
                v-model="selectedId"
                :options="customerOptions"
                placeholder="Buscar cliente..."
                :limit="10"
            />
        </div>
        <Button
            variant="outline"
            size="icon-sm"
            class="h-9 w-9 shrink-0"
            title="Gestionar clientes"
            @click="managerOpen = true"
        >
            <Users class="h-4 w-4" />
        </Button>

        <CustomerManagerModal
            v-model:open="managerOpen"
            @select="handleManagerSelect"
        />
    </div>
</template>
