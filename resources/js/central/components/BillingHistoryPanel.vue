<script setup lang="ts">
import { computed } from "vue";
import type { Subscription, Tenant } from "@/types/models";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

const props = defineProps<{
    tenant?: Partial<Tenant> | null;
}>();

const subscriptions = computed<Subscription[]>(() => {
    const list = (props.tenant as any)?.subscriptions;
    if (Array.isArray(list)) return list;
    const single = (props.tenant as any)?.subscription;
    return single ? [single] : [];
});

const fmtDate = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const planLabel = (s: any) => {
    const plan = s?.plan;
    return plan?.name || plan?.slug || (s?.plan_id ? `Plan #${s.plan_id}` : "-");
};

const paymentLabel = (p: any) => {
    const amount = p?.amount ? `${p.amount} ${p.currency || ""}`.trim() : "-";
    const method = p?.method || "-";
    const status = p?.status || "-";
    const ref = p?.transaction_id ? ` (${p.transaction_id})` : "";
    return `${amount} · ${method} · ${status}${ref}`;
};
</script>

<template>
    <Card class="h-full">
        <CardHeader>
            <CardTitle class="flex items-center justify-between gap-2">
                <span>Billing</span>
                <span class="text-xs text-muted-foreground">
                    {{ subscriptions.length }}
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent class="text-sm space-y-4">
            <div v-if="subscriptions.length === 0" class="text-muted-foreground">
                No billing history.
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="s in subscriptions"
                    :key="s.id"
                    class="rounded-md border p-3 space-y-2"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <div class="font-semibold">
                            {{ planLabel(s) }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ s.status }}
                        </div>
                    </div>
                    <div class="text-xs text-muted-foreground">
                        <span>Start: {{ fmtDate((s as any).starts_at) }}</span>
                        <span class="mx-2">·</span>
                        <span>End: {{ fmtDate((s as any).ends_at) }}</span>
                        <span class="mx-2">·</span>
                        <span>Trial: {{ fmtDate((s as any).trial_ends_at) }}</span>
                    </div>

                    <div
                        v-if="(s as any).payments?.length > 0"
                        class="space-y-1"
                    >
                        <div class="text-xs font-semibold text-muted-foreground">
                            Payments
                        </div>
                        <ul class="list-disc space-y-1 pl-5 text-muted-foreground">
                            <li v-for="p in (s as any).payments" :key="p.id">
                                {{ paymentLabel(p) }} ·
                                {{ fmtDate(p.created_at) }}
                            </li>
                        </ul>
                    </div>
                    <div v-else class="text-xs text-muted-foreground">
                        No payments.
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

