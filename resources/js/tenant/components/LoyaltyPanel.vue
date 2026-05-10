<script setup lang="ts">
import { ref, watch, computed } from "vue";
import {
    DialogRoot,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogClose,
} from "radix-vue";
import { Button } from "@/components/ui/button";
import { UnderlineInput } from "@/components/ui/underline-input";
import { X, Star, Tag, Gift, Ticket, Search, Info, CheckCircle2 } from "lucide-vue-next";
import { apiClient } from "@tenant/lib/api";

const props = defineProps<{
    open: boolean;
    mode: "draft" | "posted";
    partnerId?: number | null;
    total?: number;
    totalQty?: number;
    module?: string;
    // Para modo posted — transacciones ya realizadas
    loyaltyTransactions?: any[];
}>();

const emit = defineEmits<{
    (e: "close"): void;
}>();

const isLoading = ref(false);
const simulation = ref<any>(null);
const codeInput = ref("");
const codeResult = ref<any>(null);
const codeError = ref("");

const isDraft = computed(() => props.mode === "draft");
const isPosted = computed(() => props.mode === "posted");

const earnedTransactions = computed(() =>
    (props.loyaltyTransactions || []).filter((t: any) => t.type === "earn"),
);

const adjustTransactions = computed(() =>
    (props.loyaltyTransactions || []).filter((t: any) => t.type === "adjust"),
);

watch(
    () => props.open,
    async (open) => {
        if (open && isDraft.value && props.partnerId) {
            await loadSimulation();
        }
        if (!open) {
            codeInput.value = "";
            codeResult.value = null;
            codeError.value = "";
        }
    },
);

async function loadSimulation() {
    if (!props.partnerId) return;
    isLoading.value = true;
    try {
        const { data } = await apiClient.post<any>("/v1/loyalty/simulate", {
            partner_id: props.partnerId,
            total: props.total ?? 0,
            total_qty: props.totalQty ?? 0,
            module: props.module ?? "sales",
        });
        simulation.value = data.data;
    } catch {
        simulation.value = null;
    } finally {
        isLoading.value = false;
    }
}

async function validateCode() {
    if (!codeInput.value.trim() || !props.partnerId) return;
    codeError.value = "";
    codeResult.value = null;
    try {
        const { data } = await apiClient.post<any>("/v1/loyalty/validate-code", {
            code: codeInput.value.trim(),
            partner_id: props.partnerId,
            total: props.total ?? 0,
        });
        codeResult.value = data.data;
    } catch (err: any) {
        codeError.value = err.response?.data?.message || "Error validating code";
    }
}

const typeIcon = (type: string) => {
    switch (type) {
        case "loyalty": return Star;
        case "promotion": return Tag;
        case "buy_x_get_y": return Gift;
        case "coupon": return Ticket;
        default: return Tag;
    }
};
</script>

<template>
    <DialogRoot :open="open" @update:open="(v) => { if (!v) emit('close') }">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 grid w-full max-w-lg -translate-x-1/2 -translate-y-1/2 border bg-background shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] sm:rounded-lg max-h-[85vh] flex flex-col"
                :trap-focus="false"
                @pointer-down-outside="(e: any) => e.preventDefault()"
                @interact-outside="(e: any) => e.preventDefault()"
                @close-auto-focus="(e: Event) => e.preventDefault()"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <DialogTitle class="text-lg font-semibold flex items-center gap-2">
                        <Star class="h-5 w-5 text-pink-500" />
                        Lealtad
                        <span
                            v-if="isPosted"
                            class="text-xs font-normal text-muted-foreground bg-muted px-2 py-0.5 rounded"
                        >
                            Resumen
                        </span>
                    </DialogTitle>
                    <DialogDescription class="sr-only">Panel de lealtad del cliente</DialogDescription>
                    <DialogClose
                        class="rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    >
                        <X class="h-4 w-4" />
                    </DialogClose>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-5">
                    <!-- ═══ MODO POSTED: Solo informativo ═══ -->
                    <template v-if="isPosted">
                        <div v-if="earnedTransactions.length === 0 && adjustTransactions.length === 0"
                            class="text-sm text-muted-foreground text-center py-8">
                            <Info class="h-8 w-8 mx-auto mb-2 opacity-40" />
                            Esta venta no genero movimientos de lealtad.
                        </div>

                        <div v-if="earnedTransactions.length > 0">
                            <h3 class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
                                Puntos ganados
                            </h3>
                            <div class="space-y-2">
                                <div
                                    v-for="tx in earnedTransactions"
                                    :key="tx.id"
                                    class="flex items-center justify-between rounded-md border border-green-200 bg-green-50 p-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <CheckCircle2 class="h-4 w-4 text-green-600" />
                                        <div>
                                            <div class="text-sm font-medium text-green-800">{{ tx.program_name }}</div>
                                            <div class="text-xs text-green-600 font-mono">{{ tx.card_code }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-green-700">
                                            +{{ tx.points.toLocaleString() }}
                                        </div>
                                        <div class="text-[10px] text-green-600">{{ tx.point_name }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="adjustTransactions.length > 0">
                            <h3 class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
                                Ajustes
                            </h3>
                            <div class="space-y-2">
                                <div
                                    v-for="tx in adjustTransactions"
                                    :key="tx.id"
                                    class="flex items-center justify-between rounded-md border border-orange-200 bg-orange-50 p-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <Info class="h-4 w-4 text-orange-600" />
                                        <div>
                                            <div class="text-sm text-orange-800">{{ tx.program_name }}</div>
                                            <div class="text-xs text-orange-600">{{ tx.description }}</div>
                                        </div>
                                    </div>
                                    <span
                                        class="text-sm font-bold"
                                        :class="tx.points >= 0 ? 'text-green-700' : 'text-red-600'"
                                    >
                                        {{ tx.points >= 0 ? '+' : '' }}{{ tx.points.toLocaleString() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ═══ MODO DRAFT: Interactivo ═══ -->
                    <template v-else>
                        <div v-if="!partnerId" class="text-sm text-muted-foreground text-center py-8">
                            <Info class="h-8 w-8 mx-auto mb-2 opacity-40" />
                            Selecciona un cliente primero.
                        </div>

                        <div v-else-if="isLoading" class="text-sm text-muted-foreground text-center py-8">
                            Cargando...
                        </div>

                        <template v-else-if="simulation">
                            <!-- Promociones automáticas (2x1, descuentos auto) -->
                            <div v-if="simulation.auto_programs.length > 0">
                                <h3 class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
                                    Aplica automaticamente
                                </h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="prog in simulation.auto_programs"
                                        :key="prog.program_id"
                                        class="rounded-md border border-green-200 bg-green-50 p-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <component
                                                :is="typeIcon(prog.program_type)"
                                                class="h-4 w-4 text-green-600"
                                            />
                                            <span class="text-sm font-medium text-green-800">{{ prog.program_name }}</span>
                                        </div>
                                        <div
                                            v-for="reward in prog.rewards"
                                            :key="reward.reward_id"
                                            class="text-xs text-green-700 mt-1 ml-6"
                                        >
                                            {{ reward.description }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Puntos estimados -->
                            <div v-if="simulation.estimated_earn.length > 0">
                                <h3 class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
                                    Puntos que ganara
                                </h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="earn in simulation.estimated_earn"
                                        :key="earn.program_id"
                                        class="flex items-center justify-between rounded-md border border-blue-200 bg-blue-50 p-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Star class="h-4 w-4 text-blue-600" />
                                            <span class="text-sm text-blue-800">{{ earn.program_name }}</span>
                                        </div>
                                        <span class="text-sm font-bold text-blue-700">
                                            +{{ earn.points_earned.toLocaleString() }} {{ earn.point_name }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Tarjetas del cliente -->
                            <div v-if="simulation.cards.length > 0">
                                <h3 class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
                                    Saldo del cliente
                                </h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="card in simulation.cards"
                                        :key="card.card_id"
                                        class="flex items-center justify-between rounded-md border p-3"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <component
                                                :is="typeIcon(card.program_type)"
                                                class="h-4 w-4 shrink-0 text-muted-foreground"
                                            />
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium truncate">{{ card.program_name }}</div>
                                                <div class="text-xs text-muted-foreground font-mono">{{ card.card_code }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0 ml-3">
                                            <div class="text-sm font-bold">{{ card.points.toLocaleString() }}</div>
                                            <div class="text-[10px] text-muted-foreground">{{ card.point_name }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rewards canjeables -->
                                <div
                                    v-for="card in simulation.cards.filter((c: any) => c.available_rewards.length > 0)"
                                    :key="'rw-' + card.card_id"
                                    class="mt-2"
                                >
                                    <div class="text-[10px] text-muted-foreground uppercase tracking-wider mb-1 ml-1">
                                        Canjear en {{ card.program_name }}
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="reward in card.available_rewards"
                                            :key="reward.reward_id"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-pink-50 text-pink-700 border border-pink-200"
                                        >
                                            <Gift class="h-3 w-3" />
                                            {{ reward.description }} ({{ reward.required_points }} {{ card.point_name }})
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Sin programas -->
                            <div
                                v-if="simulation.cards.length === 0 && simulation.auto_programs.length === 0 && simulation.estimated_earn.length === 0"
                                class="text-sm text-muted-foreground text-center py-4"
                            >
                                No hay programas de lealtad que apliquen a esta venta.
                            </div>

                            <!-- Validar código -->
                            <div>
                                <h3 class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
                                    Codigo promo / cupon
                                </h3>
                                <div class="flex gap-2">
                                    <div class="flex-1">
                                        <UnderlineInput
                                            v-model="codeInput"
                                            placeholder="Ej: BIENVENIDO o 044XXXXX"
                                            class="uppercase"
                                            @keyup.enter="validateCode"
                                        />
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-10 px-3"
                                        :disabled="!codeInput.trim()"
                                        @click="validateCode"
                                    >
                                        <Search class="h-4 w-4" />
                                    </Button>
                                </div>

                                <div v-if="codeError" class="mt-2 text-sm text-destructive">
                                    {{ codeError }}
                                </div>

                                <div
                                    v-if="codeResult?.valid"
                                    class="mt-2 rounded-md border border-green-200 bg-green-50 p-3"
                                >
                                    <div class="text-sm font-medium text-green-800">
                                        {{ codeResult.type === 'promo_code' ? codeResult.program?.name : codeResult.card?.program_name }}
                                    </div>
                                    <div
                                        v-for="(reward, i) in (codeResult.type === 'promo_code' ? codeResult.program?.rewards : codeResult.card?.rewards)"
                                        :key="i"
                                        class="text-xs text-green-700 mt-1"
                                    >
                                        {{ reward.description }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>

                <!-- Footer -->
                <div class="flex justify-end border-t px-6 py-3">
                    <Button variant="outline" size="sm" @click="emit('close')">
                        Cerrar
                    </Button>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
