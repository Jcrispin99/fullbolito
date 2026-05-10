<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { usePosStore } from "@tenant/stores/pos";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { DollarSign } from "lucide-vue-next";
import { toast } from "vue-sonner";
import { formatTime } from "@tenant/lib/datetime";

const store = usePosStore();
const router = useRouter();
const route = useRoute();
const closingBalance = ref(0);
const isBootstrapping = ref(true);
const isSubmitting = ref(false);

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(amount);

const goBackToConfigs = () => {
    router.push({ name: "PosConfigsIndex" });
};

onMounted(async () => {
    const configId = Number(route.params.configId);

    try {
        await store.loadConfig(configId);
        const session = await store.fetchOpenSession(configId);

        if (!session) {
            toast.error("No hay una caja abierta para cerrar");
            goBackToConfigs();
            return;
        }

        await store.loadPaymentMethods();
        const detailedSession = await store.fetchSessionDetails(session.id);
        closingBalance.value = detailedSession.opening_balance;
    } catch (error: any) {
        toast.error(
            error?.response?.data?.message ||
                "No se pudo cargar la sesión de caja",
        );
        goBackToConfigs();
    } finally {
        isBootstrapping.value = false;
    }
});

const handleClose = async () => {
    if (!store.currentSession || isSubmitting.value) return;

    isSubmitting.value = true;

    try {
        await store.closeSession(closingBalance.value);
        toast.success("Caja cerrada correctamente");
        store.resetSession();
        goBackToConfigs();
    } catch (error: any) {
        toast.error(
            error?.response?.data?.message || "No se pudo cerrar la caja",
        );
    } finally {
        isSubmitting.value = false;
    }
};

const paymentsSummary = computed(() => store.currentSession?.payments ?? []);
const totalPayments = computed(() =>
    paymentsSummary.value.reduce((sum, payment) => sum + payment.amount, 0),
);
const expectedBalance = computed(
    () => (store.currentSession?.opening_balance ?? 0) + totalPayments.value,
);
const differenceAmount = computed(
    () => closingBalance.value - expectedBalance.value,
);
</script>

<template>
    <div
        class="min-h-screen bg-background flex flex-col items-center justify-center p-6"
    >
        <div class="max-w-md w-full space-y-8">
            <div class="text-center space-y-2">
                <h1 class="text-2xl font-bold tracking-tight">Cerrar Caja</h1>
                <p class="text-muted-foreground">
                    {{ store.currentConfig?.name }}
                </p>
            </div>

            <div class="rounded-xl border border-input bg-card p-6 space-y-6">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Monto de apertura</span
                        >
                        <span class="font-medium tabular-nums">
                            {{
                                formatCurrency(
                                    store.currentSession?.opening_balance ?? 0,
                                )
                            }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Sesión iniciada</span
                        >
                        <span class="font-medium">
                            {{ formatTime(store.currentSession?.opened_at) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Cobros registrados</span
                        >
                        <span class="font-medium tabular-nums">
                            {{ formatCurrency(totalPayments) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Esperado en caja</span
                        >
                        <span class="font-medium tabular-nums">
                            {{ formatCurrency(expectedBalance) }}
                        </span>
                    </div>
                </div>

                <div class="border-t border-border" />

                <div
                    v-if="paymentsSummary.length > 0"
                    class="space-y-2 text-sm"
                >
                    <p class="font-medium">Resumen de cobros</p>
                    <div
                        v-for="payment in paymentsSummary"
                        :key="payment.id"
                        class="flex justify-between"
                    >
                        <span class="text-muted-foreground">
                            {{ payment.payment_method_name }}
                        </span>
                        <span class="font-medium tabular-nums">
                            {{ formatCurrency(payment.amount) }}
                        </span>
                    </div>
                    <div class="border-t border-border" />
                </div>

                <div class="space-y-2">
                    <Label for="closing_balance" class="text-sm font-medium"
                        >Monto de cierre</Label
                    >
                    <div class="relative">
                        <DollarSign
                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                        />
                        <input
                            id="closing_balance"
                            v-model.number="closingBalance"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full h-12 pl-10 pr-4 bg-background border border-input rounded-md text-lg text-right focus:outline-none focus:ring-2 focus:ring-ring tabular-nums"
                            @keydown.enter="handleClose"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Cuente el efectivo en caja e ingrese el monto total
                    </p>
                    <p
                        class="text-xs"
                        :class="
                            differenceAmount === 0
                                ? 'text-muted-foreground'
                                : differenceAmount > 0
                                  ? 'text-emerald-600'
                                  : 'text-destructive'
                        "
                    >
                        Diferencia:
                        {{ formatCurrency(differenceAmount) }}
                    </p>
                </div>

                <Button
                    class="w-full h-11 text-base font-semibold"
                    variant="destructive"
                    :disabled="
                        isBootstrapping || isSubmitting || !store.currentSession
                    "
                    @click="handleClose"
                >
                    {{ isSubmitting ? "Cerrando..." : "Cerrar Caja" }}
                </Button>
            </div>
        </div>
    </div>
</template>
