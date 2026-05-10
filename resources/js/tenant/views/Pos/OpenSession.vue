<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { usePosStore } from "@tenant/stores/pos";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { ArrowLeft, DollarSign } from "lucide-vue-next";
import { toast } from "vue-sonner";

const store = usePosStore();
const router = useRouter();
const route = useRoute();
const openingBalance = ref(0);
const isBootstrapping = ref(true);
const isSubmitting = ref(false);

const goBackToConfigs = () => {
    router.push({ name: "PosConfigsIndex" });
};

onMounted(async () => {
    const configId = Number(route.params.configId);

    try {
        const config = await store.loadConfig(configId);

        if (!config.is_active) {
            toast.error("La caja seleccionada está inactiva");
            goBackToConfigs();
            return;
        }

        const session = await store.fetchOpenSession(configId);

        if (session) {
            router.push({ name: "PosTerminal", params: { configId } });
        }
    } catch (error: any) {
        toast.error(
            error?.response?.data?.message || "No se pudo cargar la caja",
        );
        goBackToConfigs();
    } finally {
        isBootstrapping.value = false;
    }
});

const handleOpen = async () => {
    if (!store.currentConfig || isSubmitting.value) return;

    isSubmitting.value = true;

    try {
        await store.openSession(openingBalance.value);
        toast.success("Caja abierta correctamente");
        router.push({
            name: "PosTerminal",
            params: { configId: route.params.configId },
        });
    } catch (error: any) {
        toast.error(
            error?.response?.data?.message || "No se pudo abrir la caja",
        );
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div
        class="min-h-screen bg-background flex flex-col items-center justify-center p-6"
    >
        <div class="max-w-md w-full space-y-8">
            <div class="text-center space-y-2">
                <h1 class="text-2xl font-bold tracking-tight">Abrir Caja</h1>
                <p class="text-muted-foreground">
                    {{ store.currentConfig?.name }}
                </p>
            </div>

            <div class="rounded-xl border border-input bg-card p-6 space-y-6">
                <div class="space-y-2">
                    <Label for="opening_balance" class="text-sm font-medium"
                        >Monto de apertura</Label
                    >
                    <div class="relative">
                        <DollarSign
                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                        />
                        <input
                            id="opening_balance"
                            v-model.number="openingBalance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full h-12 pl-10 pr-4 bg-background border border-input rounded-md text-lg text-right focus:outline-none focus:ring-2 focus:ring-ring tabular-nums"
                            @keydown.enter="handleOpen"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Ingrese el monto en efectivo con el que inicia la caja
                    </p>
                </div>

                <Button
                    class="w-full h-11 text-base font-semibold"
                    :disabled="
                        isBootstrapping || isSubmitting || !store.currentConfig
                    "
                    @click="handleOpen"
                >
                    {{ isSubmitting ? "Abriendo..." : "Abrir Caja" }}
                </Button>
            </div>

            <div class="text-center">
                <Button variant="ghost" @click="goBackToConfigs">
                    <ArrowLeft class="h-4 w-4 mr-1" />
                    Volver a cajas
                </Button>
            </div>
        </div>
    </div>
</template>
