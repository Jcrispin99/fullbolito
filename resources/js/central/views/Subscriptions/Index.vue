<script setup lang="ts">
import { onMounted, ref } from "vue";
import { apiClient } from "@central/lib/api";
import { Card, CardContent } from "@/components/ui/card";

interface SubscriptionRow {
    id: number;
    tenant_id: string;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    trial_ends_at: string | null;
    tenant: { id: string; business_name: string } | null;
    plan: { id: number; name: string; slug: string; price: number; duration_days: number } | null;
}

interface Summary {
    active_count: number;
    trial_count: number;
    monthly_recurring_revenue: number;
}

const rows = ref<SubscriptionRow[]>([]);
const summary = ref<Summary | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const statusFilter = ref<string>("");

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const params: Record<string, string> = { per_page: "50" };
        if (statusFilter.value) params.status = statusFilter.value;
        const { data } = await apiClient.get<any>("/v1/subscriptions", { params });
        rows.value = data.data.data;
        summary.value = data.data.summary;
    } catch (err: any) {
        error.value = err?.response?.data?.message ?? "No se pudieron cargar las suscripciones";
    } finally {
        loading.value = false;
    }
}

function formatDate(iso: string | null) {
    if (!iso) return "—";
    return new Date(iso).toLocaleDateString("es-PE");
}

function statusLabel(status: string) {
    if (status === "active") return "Activa";
    if (status === "trial") return "En prueba";
    if (status === "past_due") return "Pago pendiente";
    if (status === "cancelled") return "Cancelada";
    if (status === "expired") return "Vencida";
    return status;
}

function badgeClass(status: string) {
    if (status === "active") return "bg-green-100 text-green-700";
    if (status === "trial") return "bg-blue-100 text-blue-700";
    if (status === "past_due") return "bg-amber-100 text-amber-700";
    return "bg-red-100 text-red-700";
}

onMounted(load);
</script>

<template>
    <div class="space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-bold">Suscripciones</h1>
            <p class="text-muted-foreground text-sm">
                Vista global de las suscripciones activas en la plataforma.
            </p>
        </div>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <div v-if="summary" class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardContent class="p-6">
                    <div class="text-xs text-muted-foreground">Activas</div>
                    <div class="text-2xl font-semibold">{{ summary.active_count }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-6">
                    <div class="text-xs text-muted-foreground">En prueba</div>
                    <div class="text-2xl font-semibold">{{ summary.trial_count }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-6">
                    <div class="text-xs text-muted-foreground">Ingresos mensuales estimados</div>
                    <div class="text-2xl font-semibold">
                        S/ {{ Number(summary.monthly_recurring_revenue).toFixed(2) }}
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="flex items-center gap-2">
            <label class="text-sm">Estado:</label>
            <select
                v-model="statusFilter"
                @change="load"
                class="rounded-md border px-2 py-1 text-sm"
            >
                <option value="">Todos</option>
                <option value="active">Activas</option>
                <option value="trial">En prueba</option>
                <option value="past_due">Pago pendiente</option>
                <option value="cancelled">Canceladas</option>
            </select>
        </div>

        <Card>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs text-muted-foreground">
                            <th class="p-3">Negocio</th>
                            <th class="p-3">Plan</th>
                            <th class="p-3">Estado</th>
                            <th class="p-3">Inicio</th>
                            <th class="p-3">Fin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="5" class="p-6 text-center text-muted-foreground">
                                Cargando…
                            </td>
                        </tr>
                        <tr v-else-if="!rows.length">
                            <td colspan="5" class="p-6 text-center text-muted-foreground">
                                Sin suscripciones.
                            </td>
                        </tr>
                        <tr
                            v-else
                            v-for="row in rows"
                            :key="row.id"
                            class="border-b last:border-0"
                        >
                            <td class="p-3">
                                <div class="font-medium">
                                    {{ row.tenant?.business_name ?? row.tenant_id }}
                                </div>
                                <div class="text-xs text-muted-foreground font-mono">
                                    {{ row.tenant_id }}
                                </div>
                            </td>
                            <td class="p-3">{{ row.plan?.name ?? "—" }}</td>
                            <td class="p-3">
                                <span
                                    :class="['rounded-full px-2 py-0.5 text-xs', badgeClass(row.status)]"
                                >
                                    {{ statusLabel(row.status) }}
                                </span>
                            </td>
                            <td class="p-3">{{ formatDate(row.starts_at) }}</td>
                            <td class="p-3">{{ formatDate(row.ends_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
