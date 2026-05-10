<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { apiClient } from "@tenant/lib/api";
import type { PaginatedResponse } from "@/types/api";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";

type ActivityItem = {
    id: number;
    log_name: string | null;
    event: string | null;
    description: string;
    subject_type: string;
    subject_id: number;
    causer: null | {
        id: number;
        name: string | null;
        email: string | null;
    };
    properties: any;
    created_at: string | null;
};

const props = withDefaults(
    defineProps<{
        subject: string;
        subjectId?: number | string | null;
        perPage?: number;
    }>(),
    {
        subjectId: null,
        perPage: 20,
    },
);

const isLoading = ref(false);
const error = ref<string | null>(null);
const items = ref<ActivityItem[]>([]);
const total = ref<number | null>(null);

const canLoad = computed(() => !!props.subjectId);

const rtf = new Intl.RelativeTimeFormat("es", { numeric: "auto" });
const df = new Intl.DateTimeFormat("es", {
    day: "numeric",
    month: "long",
    year: "numeric",
});

const toRelativeTime = (iso: string | null) => {
    if (!iso) return "";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "";
    const diffSeconds = Math.round((d.getTime() - Date.now()) / 1000);
    const abs = Math.abs(diffSeconds);
    if (abs < 60) return rtf.format(diffSeconds, "second");
    const diffMinutes = Math.round(diffSeconds / 60);
    if (Math.abs(diffMinutes) < 60) return rtf.format(diffMinutes, "minute");
    const diffHours = Math.round(diffMinutes / 60);
    if (Math.abs(diffHours) < 24) return rtf.format(diffHours, "hour");
    const diffDays = Math.round(diffHours / 24);
    if (Math.abs(diffDays) < 30) return rtf.format(diffDays, "day");
    const diffMonths = Math.round(diffDays / 30);
    if (Math.abs(diffMonths) < 12) return rtf.format(diffMonths, "month");
    const diffYears = Math.round(diffMonths / 12);
    return rtf.format(diffYears, "year");
};

const causerName = (it: ActivityItem) => {
    if (!it.causer) return "System";
    return it.causer.name || it.causer.email || `User ${it.causer.id}`;
};

const causerInitials = (it: ActivityItem) => {
    const label = causerName(it).trim();
    const cleaned = label.replace(/<[^>]*>/g, "").trim();
    if (cleaned === "") return "?";
    return cleaned[0]?.toUpperCase() ?? "?";
};

const toDayLabel = (iso: string | null) => {
    if (!iso) return "Sin fecha";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "Sin fecha";
    return df.format(d);
};

const humanizeKey = (key: string) => {
    const map: Record<string, string> = {
        user_id: "Propietario",
        business_name: "Razón Social",
        trade_name: "Nombre Comercial",
        ruc: "RUC",
        address: "Dirección",
        phone: "Teléfono",
        email: "Email",
        ubigeo: "Ubigeo",
        branch_code: "Código Sucursal",
        is_main: "Sede Principal",
        active: "Estado",
    };
    return map[key] || key.replace(/_/g, " ");
};

const formatValue = (key: string, value: any) => {
    if (value === null || value === undefined) return "-";
    if (key === "active") return value ? "Activo" : "Inactivo";
    if (key === "is_main") return value ? "Sí" : "No";
    return String(value);
};

const getChanges = (it: ActivityItem) => {
    const props = it.properties;
    const attrs = props?.attributes;
    const old = props?.old;
    if (!attrs || typeof attrs !== "object") return [];
    if (!old || typeof old !== "object") return [];

    const keys = Object.keys(attrs).filter((k) => old[k] !== attrs[k]);
    return keys.map((k) => ({
        key: k,
        label: humanizeKey(k),
        from: formatValue(k, old[k]),
        to: formatValue(k, attrs[k]),
    }));
};

const mainMessage = (it: ActivityItem) => {
    if (
        it.description &&
        it.description !== "updated" &&
        it.description !== "created" &&
        it.description !== "deleted"
    ) {
        return it.description;
    }
    if (it.event === "created") return "Registro creado.";
    if (it.event === "deleted") return "Registro eliminado.";
    if (it.event === "updated") return "Se actualizó el registro.";
    return it.description || "Actividad registrada.";
};

const grouped = computed(() => {
    const groups: Array<{ label: string; items: ActivityItem[] }> = [];
    for (const it of items.value) {
        const label = toDayLabel(it.created_at);
        const last = groups[groups.length - 1];
        if (!last || last.label !== label) {
            groups.push({ label, items: [it] });
        } else {
            last.items.push(it);
        }
    }
    return groups;
});

const load = async () => {
    if (!props.subjectId) return;
    isLoading.value = true;
    error.value = null;
    try {
        const { data } = await apiClient.get<PaginatedResponse<ActivityItem>>(
            "/v1/activity",
            {
                params: {
                    subject: props.subject,
                    subject_id: props.subjectId,
                    per_page: props.perPage,
                },
            },
        );
        items.value = data.data.data;
        total.value = data.data.meta.total;
    } catch (e: any) {
        error.value =
            e?.response?.data?.message || e?.message || "Error loading logs";
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    if (canLoad.value) load();
});

watch(
    () => [props.subject, props.subjectId, props.perPage] as const,
    () => {
        items.value = [];
        total.value = null;
        if (canLoad.value) load();
    },
);

// Expose load method so parent can trigger a refresh manually
defineExpose({ load });
</script>

<template>
    <div class="h-full flex flex-col">
        <div class="text-sm">
            <div v-if="!canLoad" class="text-muted-foreground">
                No logs yet.
            </div>
            <div v-else-if="isLoading" class="text-muted-foreground">
                Loading...
            </div>
            <div v-else-if="error" class="text-destructive">
                {{ error }}
            </div>
            <div v-else class="space-y-5">
                <div v-if="items.length === 0" class="text-muted-foreground">
                    No logs found.
                </div>
                <div
                    v-for="group in grouped"
                    :key="group.label"
                    class="space-y-3 border-b border-border/40 pb-4 last:border-b-0 last:pb-0"
                >
                    <div
                        class="text-center text-[10px] font-medium uppercase tracking-wider text-muted-foreground"
                    >
                        {{ group.label }}
                    </div>
                    <div
                        v-for="it in group.items"
                        :key="it.id"
                        class="flex gap-3"
                    >
                        <Avatar class="h-7 w-7 rounded-full mt-0.5">
                            <AvatarFallback
                                class="rounded-full bg-secondary text-[10px] font-semibold text-secondary-foreground"
                            >
                                {{ causerInitials(it) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="truncate text-xs font-semibold text-foreground"
                                >
                                    {{ causerName(it) }}
                                </span>
                                <span
                                    class="text-[10px] text-muted-foreground whitespace-nowrap"
                                    :title="it.created_at || undefined"
                                >
                                    {{ toRelativeTime(it.created_at) }}
                                </span>
                            </div>
                            <div class="mt-0.5 text-xs text-foreground/80">
                                {{ mainMessage(it) }}
                            </div>
                            <ul
                                v-if="getChanges(it).length > 0"
                                class="mt-2 space-y-1.5"
                            >
                                <li
                                    v-for="c in getChanges(it)"
                                    :key="c.key"
                                    class="text-[11px] leading-snug"
                                >
                                    <span class="font-medium text-foreground/70"
                                        >{{ c.label }}:</span
                                    >
                                    <div
                                        class="flex items-center gap-1.5 mt-0.5"
                                    >
                                        <span
                                            class="text-muted-foreground line-through decoration-muted-foreground/30"
                                            >{{ c.from }}</span
                                        >
                                        <span class="text-muted-foreground"
                                            >→</span
                                        >
                                        <span
                                            class="text-foreground/90 font-medium"
                                            >{{ c.to }}</span
                                        >
                                    </div>
                                </li>
                            </ul>
                            <div
                                v-else-if="it.event"
                                class="mt-1 text-[10px] uppercase font-medium tracking-wider text-muted-foreground"
                            >
                                {{ it.event }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
