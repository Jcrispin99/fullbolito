<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import { apiClient } from "@tenant/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";

interface BillingState {
    tenant_id: string;
    has_stripe_customer: boolean;
    subscription: {
        status: string;
        starts_at: string | null;
        ends_at: string | null;
        trial_ends_at: string | null;
        stripe_id: string | null;
    } | null;
    plan: {
        id: number;
        name: string;
        slug: string;
        price: number;
        duration_days: number;
    } | null;
}

interface PlanOption {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: number;
    duration_days: number;
    stripe_price_id: string | null;
}

interface Invoice {
    id: string;
    number: string | null;
    total: number;
    currency: string;
    status: string | null;
    created_at: number;
    hosted_invoice_url: string | null;
    invoice_pdf: string | null;
}

const billing = ref<BillingState | null>(null);
const plans = ref<PlanOption[]>([]);
const invoices = ref<Invoice[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
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
    actionLoading.value = true;
    error.value = null;
    try {
        const { data } = await apiClient.post<{ checkout_url: string }>(
            "/v1/billing/checkout",
            { plan_slug: slug },
        );
        if (data.data.checkout_url) {
            window.location.href = data.data.checkout_url;
        }
    } catch (err: any) {
        error.value = err?.response?.data?.message ?? "No se pudo iniciar el checkout";
    } finally {
        actionLoading.value = false;
    }
}

async function openPortal() {
    actionLoading.value = true;
    error.value = null;
    try {
        const { data } = await apiClient.post<{ portal_url: string }>(
            "/v1/billing/portal",
            {},
        );
        if (data.data.portal_url) {
            window.location.href = data.data.portal_url;
        }
    } catch (err: any) {
        error.value = err?.response?.data?.message ?? "No se pudo abrir el portal";
    } finally {
        actionLoading.value = false;
    }
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

function formatStripeAmount(cents: number, currency: string) {
    const symbol = currency.toLowerCase() === "pen" ? "S/" : currency.toUpperCase();
    return `${symbol} ${(cents / 100).toFixed(2)}`;
}

function formatTimestamp(unix: number) {
    if (!unix) return "—";
    return new Date(unix * 1000).toLocaleDateString();
}

onMounted(load);
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Mi suscripción' }]">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold">Mi suscripción</h1>
                <p class="text-muted-foreground text-sm">
                    Gestioná tu plan y tus pagos.
                </p>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

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
                                <dd>{{ formatDate(billing.subscription?.ends_at ?? null) }}</dd>
                            </div>
                            <div v-if="billing.subscription?.trial_ends_at">
                                <dt class="text-muted-foreground">Fin del trial</dt>
                                <dd>{{ formatDate(billing.subscription.trial_ends_at) }}</dd>
                            </div>
                        </dl>

                        <div v-if="billing.has_stripe_customer" class="pt-2">
                            <Button
                                variant="outline"
                                :disabled="actionLoading"
                                @click="openPortal"
                            >
                                Administrar pago
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="invoices.length">
                    <CardContent class="p-6">
                        <h2 class="text-base font-semibold mb-3">Facturas</h2>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-muted-foreground border-b">
                                    <th class="py-2">Número</th>
                                    <th class="py-2">Fecha</th>
                                    <th class="py-2">Monto</th>
                                    <th class="py-2">Estado</th>
                                    <th class="py-2 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="inv in invoices"
                                    :key="inv.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-2 font-mono text-xs">
                                        {{ inv.number ?? inv.id }}
                                    </td>
                                    <td class="py-2">{{ formatTimestamp(inv.created_at) }}</td>
                                    <td class="py-2">
                                        {{ formatStripeAmount(inv.total, inv.currency) }}
                                    </td>
                                    <td class="py-2">
                                        <span
                                            :class="[
                                                'rounded-full px-2 py-0.5 text-xs',
                                                inv.status === 'paid'
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            {{ inv.status ?? "—" }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-right space-x-2">
                                        <a
                                            v-if="inv.hosted_invoice_url"
                                            :href="inv.hosted_invoice_url"
                                            target="_blank"
                                            class="text-xs underline text-primary"
                                        >
                                            Ver
                                        </a>
                                        <a
                                            v-if="inv.invoice_pdf"
                                            :href="inv.invoice_pdf"
                                            target="_blank"
                                            class="text-xs underline text-primary"
                                        >
                                            PDF
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card v-if="plans.length">
                    <CardContent class="p-6">
                        <h2 class="text-base font-semibold mb-3">
                            {{ billing.has_stripe_customer ? "Cambiar de plan" : "Actualizar a un plan pago" }}
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
                                </div>
                                <div class="flex items-center justify-between mt-3">
                                    <span class="text-sm font-semibold">{{ formatPrice(p) }}</span>
                                    <Button
                                        size="sm"
                                        :disabled="actionLoading || p.id === billing.plan?.id"
                                        @click="upgradeTo(p.slug)"
                                    >
                                        {{ p.id === billing.plan?.id ? "Plan actual" : "Elegir" }}
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
