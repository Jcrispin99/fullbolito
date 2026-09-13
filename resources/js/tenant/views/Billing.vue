<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import { apiClient } from "@tenant/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";

interface BillingState {
    tenant_id: string;
    billing_provider: string | null;
    has_payment_subscription: boolean;
    subscription: {
        status: string;
        starts_at: string | null;
        ends_at: string | null;
        trial_ends_at: string | null;
        provider: string | null;
        provider_id: string | null;
        provider_status: string | null;
        next_billing_at: string | null;
    } | null;
    plan: {
        id: number;
        name: string;
        slug: string;
        price: number;
        duration_days: number;
        billing_rank: number;
        sunat_worker_slots: number;
        sunat_dedicated_queue: boolean;
    } | null;
    pending_plan_change: {
        id: number;
        kind: "upgrade" | "downgrade" | "cycle_change";
        status: string;
        from_plan: string | null;
        to_plan: string | null;
        proration_amount: number;
        currency: string;
        effective_at: string | null;
        checkout_url: string | null;
    } | null;
    sunat_capacity: {
        worker_slots: number;
        dedicated_queue: boolean;
        dedicated_queue_active: boolean;
        queue_name: string;
    };
}

interface PlanOption {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: number;
    duration_days: number;
    billing_rank: number;
    sunat_worker_slots: number;
    sunat_dedicated_queue: boolean;
}

interface Invoice {
    id: string;
    reference: string | null;
    amount: number;
    currency: string;
    status: string | null;
    paid_at: string | null;
}

const billing = ref<BillingState | null>(null);
const plans = ref<PlanOption[]>([]);
const invoices = ref<Invoice[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const notice = ref<string | null>(null);
const actionLoading = ref(false);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [{ data: bData }, { data: pData }, { data: iData }] = await Promise.all([
            apiClient.get<BillingState>("/v1/billing"),
            apiClient.get<{ data: PlanOption[] }>("/v1/billing/plans").catch(() => ({
                data: { data: { data: [] as PlanOption[] } },
            })),
            apiClient.get<{ data: Invoice[] }>("/v1/billing/invoices").catch(() => ({
                data: { data: { data: [] as Invoice[] } },
            })),
        ]);
        billing.value = bData.data;
        // Server already filters to billable plans only.
        plans.value = ((pData as any)?.data?.data ?? []) as PlanOption[];
        invoices.value = ((iData as any)?.data?.data ?? []) as Invoice[];
    } catch (err: any) {
        error.value = err?.response?.data?.message ?? "No se pudo cargar la facturación";
    } finally {
        loading.value = false;
    }
}

async function upgradeTo(slug: string) {
    const target = plans.value.find((plan) => plan.slug === slug);
    const current = billing.value?.plan;
    if (target && current && billing.value?.has_payment_subscription) {
        const isUpgrade = target.billing_rank > current.billing_rank;
        const message = isUpgrade
            ? "Se cobrará la diferencia prorrateada hasta tu próxima renovación. ¿Continuar?"
            : "No habrá cobro ahora. El cambio se aplicará al finalizar el periodo ya pagado. ¿Programarlo?";
        if (!window.confirm(message)) return;
    }

    actionLoading.value = true;
    error.value = null;
    notice.value = null;
    try {
        const { data } = await apiClient.post<{
            checkout_url: string | null;
            scheduled: boolean;
            effective_at: string | null;
        }>(
            "/v1/billing/checkout",
            { plan_slug: slug },
        );
        if (data.data.checkout_url) {
            window.location.href = data.data.checkout_url;
            return;
        }
        notice.value = data.data.scheduled
            ? `Cambio programado para ${formatDate(data.data.effective_at)}. No se realizó ningún cobro.`
            : "El plan se actualizó correctamente.";
        await load();
    } catch (err: any) {
        error.value = err?.response?.data?.message ?? "No se pudo iniciar el proceso de pago";
    } finally {
        actionLoading.value = false;
    }
}

async function updateSubscriptionStatus(status: "authorized" | "paused" | "cancelled") {
    if (status === "cancelled" && !window.confirm("¿Cancelar la suscripción? Esta acción detendrá los próximos cobros.")) {
        return;
    }

    actionLoading.value = true;
    error.value = null;
    try {
        await apiClient.patch(
            "/v1/billing/subscription/status",
            { status },
        );
        await load();
    } catch (err: any) {
        error.value = err?.response?.data?.message ?? "No se pudo actualizar la suscripción";
    } finally {
        actionLoading.value = false;
    }
}

function continuePendingPayment() {
    const url = billing.value?.pending_plan_change?.checkout_url;
    if (url) window.location.href = url;
}

const statusBadgeClass = computed(() => {
    const status = billing.value?.subscription?.status;
    if (!status) return "bg-muted text-muted-foreground";
    if (status === "active") return "bg-green-100 text-green-700";
    if (status === "trial") return "bg-blue-100 text-blue-700";
    if (status === "past_due") return "bg-amber-100 text-amber-700";
    return "bg-red-100 text-red-700";
});

function formatPrice(p: PlanOption) {
    const interval = p.duration_days >= 365 ? "/año" : "/mes";
    return `S/ ${Number(p.price).toFixed(2)}${interval}`;
}

function formatDate(iso: string | null) {
    if (!iso) return "—";
    return new Date(iso).toLocaleDateString();
}

function formatAmount(amount: number, currency: string) {
    const symbol = currency.toLowerCase() === "pen" ? "S/" : currency.toUpperCase();
    return `${symbol} ${Number(amount).toFixed(2)}`;
}

onMounted(load);
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Mi suscripción' }]">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold">Mi suscripción</h1>
                <p class="text-muted-foreground text-sm">
                    Gestiona tu plan y tus pagos.
                </p>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
            <p v-if="notice" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                {{ notice }}
            </p>

            <Card v-if="loading">
                <CardContent class="p-6 text-sm text-muted-foreground">
                    Cargando…
                </CardContent>
            </Card>

            <template v-else-if="billing">
                <Card>
                    <CardContent class="space-y-3 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs text-muted-foreground">Plan actual</div>
                                <div class="text-lg font-semibold">
                                    {{ billing.plan?.name ?? "Sin plan" }}
                                </div>
                            </div>
                            <span
                                :class="['rounded-full px-3 py-1 text-xs font-medium', statusBadgeClass]"
                            >
                                {{ billing.subscription?.status ?? "—" }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">Inicio</dt>
                                <dd>{{ formatDate(billing.subscription?.starts_at ?? null) }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Próximo cobro</dt>
                                <dd>{{ formatDate(billing.subscription?.next_billing_at ?? billing.subscription?.ends_at ?? null) }}</dd>
                            </div>
                            <div v-if="billing.subscription?.trial_ends_at">
                                <dt class="text-muted-foreground">Fin del trial</dt>
                                <dd>{{ formatDate(billing.subscription.trial_ends_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Canales SUNAT</dt>
                                <dd>
                                    {{ billing.sunat_capacity.worker_slots }} comprobante<span v-if="billing.sunat_capacity.worker_slots !== 1">s</span> simultáneo<span v-if="billing.sunat_capacity.worker_slots !== 1">s</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Procesamiento SUNAT</dt>
                                <dd>
                                    {{
                                        billing.sunat_capacity.dedicated_queue_active
                                            ? "Cola dedicada Enterprise"
                                            : billing.sunat_capacity.dedicated_queue
                                              ? "Cola dedicada pendiente de activación"
                                              : "Pool compartido"
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <div v-if="billing.has_payment_subscription" class="flex flex-wrap gap-2 pt-2">
                            <Button
                                v-if="billing.subscription?.provider_status === 'authorized'"
                                variant="outline"
                                :disabled="actionLoading"
                                @click="updateSubscriptionStatus('paused')"
                            >
                                Pausar cobros
                            </Button>
                            <Button
                                v-if="billing.subscription?.provider_status === 'paused'"
                                variant="outline"
                                :disabled="actionLoading"
                                @click="updateSubscriptionStatus('authorized')"
                            >
                                Reactivar cobros
                            </Button>
                            <Button
                                v-if="!['cancelled', 'canceled'].includes(billing.subscription?.provider_status ?? '')"
                                variant="destructive"
                                :disabled="actionLoading"
                                @click="updateSubscriptionStatus('cancelled')"
                            >
                                Cancelar suscripción
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="billing.pending_plan_change">
                    <CardContent class="space-y-2 border-amber-200 bg-amber-50 p-6 text-sm">
                        <h2 class="font-semibold">Cambio de plan pendiente</h2>
                        <p>
                            {{ billing.pending_plan_change.from_plan }} →
                            {{ billing.pending_plan_change.to_plan }}
                        </p>
                        <p v-if="['scheduled', 'ready'].includes(billing.pending_plan_change.status)">
                            Se aplicará el {{ formatDate(billing.pending_plan_change.effective_at) }}.
                            No se efectuó un cobro inmediato.
                        </p>
                        <p v-else>
                            El upgrade se aplicará cuando Mercado Pago confirme el pago prorrateado.
                        </p>
                        <Button
                            v-if="billing.pending_plan_change.status === 'pending_payment' && billing.pending_plan_change.checkout_url"
                            size="sm"
                            @click="continuePendingPayment"
                        >
                            Continuar pago
                        </Button>
                    </CardContent>
                </Card>

                <Card v-if="invoices.length">
                    <CardContent class="p-6">
                        <h2 class="text-base font-semibold mb-3">Historial de pagos</h2>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-muted-foreground border-b">
                                    <th class="py-2">Número</th>
                                    <th class="py-2">Fecha</th>
                                    <th class="py-2">Monto</th>
                                    <th class="py-2">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="inv in invoices"
                                    :key="inv.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-2 font-mono text-xs">
                                        {{ inv.reference ?? inv.id }}
                                    </td>
                                    <td class="py-2">{{ formatDate(inv.paid_at) }}</td>
                                    <td class="py-2">
                                        {{ formatAmount(inv.amount, inv.currency) }}
                                    </td>
                                    <td class="py-2">
                                        <span
                                            :class="[
                                                'rounded-full px-2 py-0.5 text-xs',
                                                inv.status === 'completed'
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            {{ inv.status ?? "—" }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card v-if="plans.length">
                    <CardContent class="p-6">
                        <h2 class="text-base font-semibold mb-3">
                            {{ billing.has_payment_subscription ? "Cambiar de plan" : "Actualizar a un plan pago" }}
                        </h2>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div
                                v-for="p in plans"
                                :key="p.id"
                                class="rounded-md border p-4 flex flex-col justify-between"
                            >
                                <div>
                                    <div class="font-medium">{{ p.name }}</div>
                                    <p
                                        v-if="p.description"
                                        class="text-xs text-muted-foreground mt-1"
                                    >
                                        {{ p.description }}
                                    </p>
                                    <p class="mt-2 text-xs font-medium text-emerald-700">
                                        {{ p.sunat_worker_slots }} canal<span v-if="p.sunat_worker_slots !== 1">es</span> simultáneo<span v-if="p.sunat_worker_slots !== 1">s</span> de facturación electrónica
                                    </p>
                                    <p
                                        v-if="p.sunat_dedicated_queue"
                                        class="mt-1 text-xs text-purple-700"
                                    >
                                        Cola dedicada Enterprise incluida
                                    </p>
                                </div>
                                <div class="flex items-center justify-between mt-3">
                                    <span class="text-sm font-semibold">{{ formatPrice(p) }}</span>
                                    <Button
                                        size="sm"
                                        :disabled="actionLoading || p.id === billing.plan?.id || billing.pending_plan_change?.status === 'pending_payment'"
                                        @click="upgradeTo(p.slug)"
                                    >
                                        {{
                                            p.id === billing.plan?.id
                                                ? "Plan actual"
                                                : p.billing_rank > (billing.plan?.billing_rank ?? -1)
                                                  ? "Mejorar plan"
                                                  : "Programar cambio"
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>
    </DashboardLayout>
</template>
