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
import type { Subscription, Tenant } from "@/types/models";

const open = ref(false);
const tenant = ref<Partial<Tenant> | null>(null);

const subscriptions = computed<Subscription[]>(() => {
    const t = tenant.value;
    const list = Array.isArray(t?.subscriptions) ? t?.subscriptions ?? [] : [];
    if (list.length > 0) return list;
    if (t?.subscription) return [t.subscription];
    return [];
});

const sortedSubscriptions = computed(() => {
    return [...subscriptions.value].sort((a, b) => {
        const ad = a.created_at ? Date.parse(a.created_at) : 0;
        const bd = b.created_at ? Date.parse(b.created_at) : 0;
        return bd - ad;
    });
});

const formatDate = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return "-";
    return date.toLocaleDateString("es-PE");
};

const statusLabel = (status: string) => {
    if (status === "active") return "Activa";
    if (status === "trial") return "En prueba";
    if (status === "past_due") return "Pago pendiente";
    if (status === "cancelled") return "Cancelada";
    if (status === "expired") return "Vencida";
    return status;
};

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
                <AlertDialogTitle>Historial de suscripciones</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ tenant?.id ? `Negocio: ${tenant.id}` : "" }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div v-if="sortedSubscriptions.length === 0" class="text-sm text-muted-foreground">
                Sin historial de suscripciones.
            </div>

            <div v-else class="max-h-[60vh] overflow-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Plan</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Inicio</TableHead>
                            <TableHead>Fin</TableHead>
                            <TableHead>Fin de prueba</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="s in sortedSubscriptions" :key="s.id">
                            <TableCell class="font-medium">{{ s.id }}</TableCell>
                            <TableCell>{{ s.plan?.name ?? `Plan #${s.plan_id}` }}</TableCell>
                            <TableCell>{{ statusLabel(s.status) }}</TableCell>
                            <TableCell>{{ formatDate(s.starts_at) }}</TableCell>
                            <TableCell>{{ formatDate(s.ends_at) }}</TableCell>
                            <TableCell>{{ formatDate(s.trial_ends_at) }}</TableCell>
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
