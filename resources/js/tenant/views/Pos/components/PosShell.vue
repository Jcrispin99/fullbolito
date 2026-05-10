<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePosStore } from '@tenant/stores/pos'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { LogOut, Receipt, ShoppingBag, DoorOpen } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const store = usePosStore()
const router = useRouter()
const route = useRoute()

const initialConfigId = Number(route.params.configId)
const isReady = ref(
    !!store.currentConfig &&
        store.currentConfig.id === initialConfigId &&
        !!store.currentSession &&
        store.currentSession.pos_config_id === initialConfigId &&
        store.products.length > 0,
)

const isOnTerminal = computed(() => route.name === 'PosTerminal')
const isOnSales = computed(() => route.name === 'PosSales')

const goToTerminal = () => {
    router.push({
        name: 'PosTerminal',
        params: { configId: route.params.configId },
    })
}

const goToSales = () => {
    router.push({
        name: 'PosSales',
        params: { configId: route.params.configId },
    })
}

const handleExit = () => {
    router.push({ name: 'PosConfigsIndex' })
}

const handleCloseSession = () => {
    router.push({
        name: 'PosCloseSession',
        params: { configId: route.params.configId },
    })
}

onMounted(async () => {
    const configId = Number(route.params.configId)

    try {
        const sameConfig = store.currentConfig?.id === configId

        if (!sameConfig) {
            await store.loadConfig(configId)
        }

        let session = store.currentSession
        if (!session || session.pos_config_id !== configId) {
            session = await store.fetchOpenSession(configId)
        }

        if (!session) {
            router.push({ name: 'PosOpenSession', params: { configId } })
            return
        }

        if (!sameConfig || store.products.length === 0) {
            await store.loadPosReadData()
        }
    } catch (error: any) {
        toast.error(
            error?.response?.data?.message || 'No se pudo cargar la terminal POS',
        )
        router.push({ name: 'PosConfigsIndex' })
        return
    } finally {
        isReady.value = true
    }
})
</script>

<template>
    <div class="h-screen flex flex-col bg-background overflow-hidden">
        <!-- Persistent shell header -->
        <header
            class="flex items-center justify-between px-4 py-2 bg-card border-b border-border shrink-0 gap-3"
        >
            <div class="flex items-center gap-3 min-w-0">
                <span class="text-sm font-bold truncate">
                    {{ store.currentConfig?.name || '—' }}
                </span>
                <span
                    v-if="store.currentSession?.id"
                    class="text-xs text-muted-foreground shrink-0"
                >
                    Sesión #{{ store.currentSession.id }}
                </span>
                <span
                    v-if="store.currentSession?.user_name"
                    class="text-xs text-muted-foreground hidden md:inline truncate"
                >
                    · {{ store.currentSession.user_name }}
                </span>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    :class="
                        cn(
                            'flex items-center gap-1.5 px-3 h-8 text-sm font-medium rounded-md border transition-colors',
                            isOnTerminal
                                ? 'bg-primary text-primary-foreground border-primary'
                                : 'bg-background border-input hover:bg-accent hover:text-accent-foreground',
                        )
                    "
                    @click="goToTerminal"
                >
                    <ShoppingBag class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Caja</span>
                </button>

                <button
                    type="button"
                    :class="
                        cn(
                            'flex items-center gap-1.5 px-3 h-8 text-sm font-medium rounded-md border transition-colors',
                            isOnSales
                                ? 'bg-primary text-primary-foreground border-primary'
                                : 'bg-background border-input hover:bg-accent hover:text-accent-foreground',
                        )
                    "
                    @click="goToSales"
                >
                    <Receipt class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Ventas</span>
                </button>

                <div class="h-6 w-px bg-border mx-1" />

                <Button
                    variant="ghost"
                    size="sm"
                    class="gap-1.5"
                    title="Salir sin cerrar la caja"
                    @click="handleExit"
                >
                    <DoorOpen class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Salir</span>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    @click="handleCloseSession"
                >
                    <LogOut class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Cerrar caja</span>
                </Button>
            </div>
        </header>

        <!-- Body slot -->
        <div v-if="isReady" class="flex-1 overflow-hidden flex flex-col">
            <slot />
        </div>
        <div
            v-else
            class="flex-1 flex items-center justify-center text-sm text-muted-foreground"
        >
            Cargando…
        </div>
    </div>
</template>
