<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { computed, ref } from "vue";
import type { Payment, Subscription, Tenant } from "@/types/models";

const open = ref(false);
const tenant = ref<Partial<Tenant> | null>(null);

const subscriptions = computed<Subscription[]>(() => {
    const t = tenant.value;
    const list = Array.isArray(t?.subscriptions) ? t?.subscriptions ?? [] : [];
    if (list.length > 0) return list;
    if (t?.subscription) return [t.subscription];
    return [];
});

const payments = computed<Payment[]>(() => {
    const flat = subscriptions.value.flatMap((s) =>
        Array.isArray(s.payments) ? s.payments : [],
    );
    const byId = new Map<number, Payment>();
    for (const p of flat) byId.set(p.id, p);
    return [...byId.values()];
});

const sortedPayments = computed(() => {
    return [...payments.value].sort((a, b) => {
        const ad = a.created_at ? Date.parse(a.created_at) : 0;
        const bd = b.created_at ? Date.parse(b.created_at) : 0;
        return bd - ad;
    });
});

const show = (t: Partial<Tenant>) => {
    tenant.value = t;
    if (typeof window !== "undefined") {
        const active = document.activeElement as HTMLElement | null;
        active?.blur?.();
        window.requestAnimationFrame(() => {
            open.value = true;
        });
        return;
    }
    open.value = true;
};

defineExpose({ show });
</script>

<template>
    <AlertDialog :open="open" @update:open="open = $event">
        <AlertDialogContent class="max-w-4xl">
            <AlertDialogHeader>
                <AlertDialogTitle>Historial de pagos</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ tenant?.id ? `Tenant: ${tenant.id}` : "" }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div v-if="sortedPayments.length === 0" class="text-sm text-muted-foreground">
                Sin pagos registrados.
            </div>

            <div v-else class="max-h-[60vh] overflow-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Fecha</TableHead>
                            <TableHead>Monto</TableHead>
                            <TableHead>Método</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Referencia</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="p in sortedPayments" :key="p.id">
                            <TableCell class="font-medium">{{ p.id }}</TableCell>
                            <TableCell>{{ p.created_at }}</TableCell>
                            <TableCell>{{ `${p.amount} ${p.currency}` }}</TableCell>
                            <TableCell>{{ p.method }}</TableCell>
                            <TableCell>{{ p.status }}</TableCell>
                            <TableCell class="max-w-[240px] truncate">
                                {{ p.transaction_id ?? "-" }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel>Cerrar</AlertDialogCancel>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
