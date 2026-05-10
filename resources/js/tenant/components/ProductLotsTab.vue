<script setup lang="ts">
import { computed, ref, watch } from "vue";
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from "@/components/ui/card";
import { UnderlineSelect } from "@/components/ui/underline-select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Boxes } from "lucide-vue-next";
import { useLotStore, type Lot } from "@tenant/stores/lot";

interface Variant {
    id: number;
    sku?: string | null;
    is_principal?: boolean;
    attributes?: { value: string }[];
}

const props = defineProps<{
    productName?: string;
    variants: Variant[];
    warehouses?: { id: number; name: string }[];
}>();

const lotStore = useLotStore();

const selectedWarehouseId = ref<number | "">("");
const isLoading = ref(false);
const lotsByVariant = ref<Record<number, Lot[]>>({});
const error = ref<string | null>(null);

const variantLabel = (v: Variant): string => {
    const attrs = v.attributes ?? [];
    if (!attrs.length) return props.productName ?? "Default";
    return attrs.map((a) => a.value).join(" / ");
};

const statusLabels: Record<string, string> = {
    active: "Activo",
    blocked: "Bloqueado",
    expired: "Vencido",
    depleted: "Agotado",
};

const statusBadgeClass = (status: string) => {
    switch (status) {
        case "active":
            return "bg-green-100 text-green-800";
        case "blocked":
            return "bg-yellow-100 text-yellow-800";
        case "expired":
            return "bg-red-100 text-red-700";
        case "depleted":
            return "bg-muted text-muted-foreground";
        default:
            return "bg-muted text-muted-foreground";
    }
};

const stockForLot = (lot: Lot): number => {
    if (selectedWarehouseId.value === "") {
        return lot.total_stock ?? 0;
    }
    const wh = (lot.inventories_by_warehouse ?? []).find(
        (w) => w.warehouse_id === Number(selectedWarehouseId.value),
    );
    return wh?.quantity_balance ?? 0;
};

const visibleLotsForVariant = (variantId: number): Lot[] => {
    const lots = lotsByVariant.value[variantId] ?? [];
    if (selectedWarehouseId.value === "") return lots;
    return lots.filter((l) =>
        (l.inventories_by_warehouse ?? []).some(
            (w) =>
                w.warehouse_id === Number(selectedWarehouseId.value) &&
                w.quantity_balance > 0,
        ),
    );
};

const totalLotsCount = computed(() =>
    props.variants.reduce(
        (sum, v) => sum + (lotsByVariant.value[v.id]?.length ?? 0),
        0,
    ),
);

const reload = async () => {
    if (!props.variants.length) return;
    isLoading.value = true;
    error.value = null;
    try {
        const results = await Promise.all(
            props.variants.map(async (v) => {
                try {
                    await lotStore.fetchLots(1, "total", {
                        product_product_id: v.id,
                    });
                    return { id: v.id, lots: [...lotStore.lots] };
                } catch {
                    return { id: v.id, lots: [] as Lot[] };
                }
            }),
        );
        const map: Record<number, Lot[]> = {};
        for (const r of results) map[r.id] = r.lots;
        lotsByVariant.value = map;
    } catch (e: any) {
        error.value = e?.message || "Error cargando lotes";
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.variants.map((v) => v.id).join(","),
    () => {
        reload();
    },
    { immediate: true },
);
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Boxes class="h-4 w-4" />
                        Lotes
                        <span
                            v-if="totalLotsCount"
                            class="ml-1.5 rounded-full bg-primary/10 px-1.5 py-0.5 text-xs font-medium"
                        >
                            {{ totalLotsCount }}
                        </span>
                    </CardTitle>
                    <CardDescription>
                        Lotes registrados por variante. Filtre por almacén para
                        ver solo los lotes con stock en él.
                    </CardDescription>
                </div>
                <div class="shrink-0">
                    <UnderlineSelect
                        v-model="selectedWarehouseId"
                        class="w-auto h-9"
                    >
                        <option value="">Todos los almacenes</option>
                        <option
                            v-for="wh in warehouses"
                            :key="wh.id"
                            :value="wh.id"
                        >
                            {{ wh.name }}
                        </option>
                    </UnderlineSelect>
                </div>
            </div>
        </CardHeader>
        <CardContent class="p-0">
            <div
                v-if="isLoading"
                class="text-center py-12 text-muted-foreground text-sm"
            >
                Cargando lotes…
            </div>
            <div
                v-else-if="error"
                class="text-center py-12 text-destructive text-sm"
            >
                {{ error }}
            </div>
            <div
                v-else-if="totalLotsCount === 0"
                class="flex flex-col items-center justify-center py-12 text-muted-foreground gap-3"
            >
                <Boxes class="h-10 w-10 opacity-30" />
                <p class="text-sm">
                    Aún no hay lotes para este producto.
                </p>
            </div>
            <div v-else class="space-y-6 p-4">
                <div
                    v-for="v in variants"
                    :key="v.id"
                >
                    <div
                        v-if="visibleLotsForVariant(v.id).length"
                        class="space-y-2"
                    >
                        <div class="text-sm font-medium flex items-center gap-2">
                            {{ variantLabel(v) }}
                            <span
                                v-if="v.is_principal"
                                class="text-xs text-muted-foreground"
                            >
                                (principal)
                            </span>
                            <span
                                v-if="v.sku"
                                class="text-xs text-muted-foreground font-mono"
                            >
                                {{ v.sku }}
                            </span>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Lote</TableHead>
                                    <TableHead>Fabricación</TableHead>
                                    <TableHead>Vencimiento</TableHead>
                                    <TableHead class="text-right">Stock</TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="lot in visibleLotsForVariant(v.id)"
                                    :key="lot.id"
                                >
                                    <TableCell class="font-mono text-xs">
                                        {{ lot.lot_number }}
                                    </TableCell>
                                    <TableCell class="text-xs">
                                        {{ lot.manufactured_at || "—" }}
                                    </TableCell>
                                    <TableCell class="text-xs">
                                        <span
                                            :class="
                                                lot.is_expired
                                                    ? 'text-destructive font-medium'
                                                    : ''
                                            "
                                        >
                                            {{ lot.expires_at || "—" }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-xs font-medium',
                                                stockForLot(lot) > 0
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            {{ stockForLot(lot) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-[11px] font-medium',
                                                statusBadgeClass(lot.status),
                                            ]"
                                        >
                                            {{ statusLabels[lot.status] ?? lot.status }}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
