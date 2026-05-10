<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import { useRouter } from "vue-router";
import { Bell, Check, Trash2, AlertTriangle, ShieldAlert, Clock } from "lucide-vue-next";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { useLotAlertStore, type LotAlert } from "@tenant/stores/lotAlert";

const router = useRouter();
const store = useLotAlertStore();

const isOpen = ref(false);
let pollHandle: ReturnType<typeof setInterval> | null = null;

const loadList = async () => {
    try {
        await store.fetchAlerts(1, 10, { status: "unread" });
    } catch {
        // silent
    }
};

const onOpenChange = async (val: boolean) => {
    isOpen.value = val;
    if (val) {
        await loadList();
    }
};

const onMarkRead = async (alert: LotAlert) => {
    try {
        await store.markRead(alert.id);
    } catch {
        // silent
    }
};

const onMarkAllRead = async () => {
    try {
        await store.markAllRead();
    } catch {
        // silent
    }
};

const onDelete = async (alert: LotAlert) => {
    try {
        await store.destroy(alert.id);
    } catch {
        // silent
    }
};

const goToLots = () => {
    isOpen.value = false;
    router.push("/admin/lots");
};

const iconForType = (type: string) => {
    switch (type) {
        case "expired":
            return AlertTriangle;
        case "blocked":
            return ShieldAlert;
        case "expiring":
        default:
            return Clock;
    }
};

const colorForType = (type: string) => {
    switch (type) {
        case "expired":
            return "text-destructive";
        case "blocked":
            return "text-yellow-600";
        case "expiring":
        default:
            return "text-orange-500";
    }
};

onMounted(async () => {
    try {
        await store.fetchBadge();
    } catch {
        // silent
    }
    pollHandle = setInterval(() => {
        store.fetchBadge().catch(() => {});
    }, 60_000);
});

onUnmounted(() => {
    if (pollHandle) {
        clearInterval(pollHandle);
        pollHandle = null;
    }
});
</script>

<template>
    <DropdownMenu :open="isOpen" @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <button
                class="relative hover:text-foreground transition-colors p-1"
                aria-label="Lot alerts"
            >
                <Bell class="w-[18px] h-[18px]" />
                <span
                    v-if="store.badge.unread_count > 0"
                    class="absolute -top-1 -right-1 bg-destructive text-destructive-foreground text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none min-w-[18px] text-center"
                >
                    {{ store.badge.unread_count > 99 ? "99+" : store.badge.unread_count }}
                </span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-96 p-0">
            <!-- Header -->
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <p class="text-sm font-semibold">Alertas de lotes</p>
                    <p class="text-[11px] text-muted-foreground">
                        {{ store.badge.unread_count }} sin leer ·
                        {{ store.badge.by_type.expired }} vencidos ·
                        {{ store.badge.by_type.expiring }} por vencer
                    </p>
                </div>
                <Button
                    v-if="store.badge.unread_count > 0"
                    variant="ghost"
                    size="sm"
                    class="text-xs"
                    @click="onMarkAllRead"
                >
                    <Check class="h-3.5 w-3.5 mr-1" />
                    Marcar todas
                </Button>
            </div>

            <!-- List -->
            <div class="max-h-[420px] overflow-y-auto">
                <div
                    v-if="store.isLoading && store.alerts.length === 0"
                    class="text-center py-8 text-xs text-muted-foreground"
                >
                    Cargando…
                </div>
                <div
                    v-else-if="store.alerts.length === 0"
                    class="text-center py-8 text-xs text-muted-foreground"
                >
                    No hay alertas pendientes.
                </div>
                <ul v-else class="divide-y">
                    <li
                        v-for="alert in store.alerts"
                        :key="alert.id"
                        class="px-4 py-3 hover:bg-muted/40 transition-colors"
                    >
                        <div class="flex items-start gap-3">
                            <component
                                :is="iconForType(alert.alert_type)"
                                :class="['h-4 w-4 mt-0.5 shrink-0', colorForType(alert.alert_type)]"
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm leading-snug">
                                    {{ alert.message }}
                                </p>
                                <p
                                    v-if="alert.lot"
                                    class="text-[11px] text-muted-foreground mt-1"
                                >
                                    Lote
                                    <span class="font-mono">{{ alert.lot.lot_number }}</span>
                                    <span v-if="alert.lot.expires_at">
                                        · vence {{ alert.lot.expires_at }}
                                    </span>
                                </p>
                                <p
                                    class="text-[10px] text-muted-foreground mt-1"
                                >
                                    {{ alert.alert_date }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <button
                                    v-if="alert.status === 'unread'"
                                    class="text-muted-foreground hover:text-primary p-1"
                                    title="Marcar como leída"
                                    @click="onMarkRead(alert)"
                                >
                                    <Check class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    class="text-muted-foreground hover:text-destructive p-1"
                                    title="Eliminar"
                                    @click="onDelete(alert)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Footer -->
            <div class="border-t px-4 py-2">
                <Button
                    variant="ghost"
                    size="sm"
                    class="w-full text-xs"
                    @click="goToLots"
                >
                    Ver todos los lotes
                </Button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
