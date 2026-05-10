<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useLotStore, type Lot, type LotStatus } from "@tenant/stores/lot";
import { useCompanyFilterRefresh } from "@/composables/useCompanyFilterRefresh";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import ModuleHeader from "@/central/components/ModuleHeader.vue";
import { Button } from "@/components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import DataView from "@/components/ui/data-view/DataView.vue";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    Pencil,
    Power,
    Trash2,
    Filter,
    ChevronDown,
    AlertTriangle,
    XCircle,
    CheckCircle2,
    Lock,
    Download,
    Upload,
} from "lucide-vue-next";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ImportExportToolbar from "@tenant/components/ImportExport/ImportExportToolbar.vue";
import LotEditDialog from "@tenant/components/LotEditDialog.vue";
import { toast } from "vue-sonner";

const lotStore = useLotStore();
const { lots, meta, isLoading } = storeToRefs(lotStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(25);
const search = ref("");
const viewMode = ref<"table" | "grid">("table");

// Filtros principales
const statusFilter = ref<LotStatus | "all">("all");
const expiringDays = ref<number | null>(null);
const onlyExpired = ref(false);
const onlyInStock = ref(false);

// Estado del modal de edición
const editDialogOpen = ref(false);
const editingLot = ref<Lot | null>(null);
const savingLot = ref(false);
const editErrors = ref<Record<string, string>>({});

const filterLabel = computed(() => {
    const parts: string[] = [];
    if (statusFilter.value !== "all")
        parts.push(statusLabels[statusFilter.value]);
    if (expiringDays.value) parts.push(`vence ≤${expiringDays.value}d`);
    if (onlyExpired.value) parts.push("vencidos");
    if (onlyInStock.value) parts.push("con stock");
    return parts.length > 0 ? parts.join(" · ") : "Filtros";
});

const statusLabels: Record<LotStatus, string> = {
    active: "Activo",
    blocked: "Bloqueado",
    expired: "Vencido",
    depleted: "Agotado",
};

const statusBadgeClass: Record<LotStatus, string> = {
    active: "bg-green-100 text-green-800",
    blocked: "bg-amber-100 text-amber-800",
    expired: "bg-red-100 text-red-800",
    depleted: "bg-gray-100 text-gray-800",
};

const loadLots = (page = 1) => {
    lotStore.fetchLots(page, perPage.value, {
        search: search.value,
        status: statusFilter.value,
        expiring_in_days: expiringDays.value,
        expired: onlyExpired.value,
        in_stock: onlyInStock.value,
    });
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (statusFilter.value && statusFilter.value !== "all") {
        filters.status = statusFilter.value;
    }
    return filters;
});

onMounted(() => loadLots());
useCompanyFilterRefresh(() => loadLots());

const handleSearch = (value: string) => {
    search.value = value;
    loadLots(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    perPage.value = newPerPage === "total" ? "total" : Number(newPerPage);
    loadLots(1);
};

const handlePageChange = (page: number) => loadLots(page);

const setStatusFilter = (s: LotStatus | "all") => {
    statusFilter.value = s;
    loadLots(1);
};

const toggleExpiringFilter = (days: number | null) => {
    expiringDays.value = days;
    onlyExpired.value = false;
    loadLots(1);
};

const toggleExpiredFilter = () => {
    onlyExpired.value = !onlyExpired.value;
    if (onlyExpired.value) expiringDays.value = null;
    loadLots(1);
};

const toggleInStockFilter = () => {
    onlyInStock.value = !onlyInStock.value;
    loadLots(1);
};

const openEdit = (lot: Lot) => {
    editingLot.value = lot;
    editErrors.value = {};
    editDialogOpen.value = true;
};

const closeEdit = () => {
    editDialogOpen.value = false;
    editingLot.value = null;
    editErrors.value = {};
};

const saveEdit = async (payload: Record<string, any>) => {
    if (!editingLot.value) return;
    savingLot.value = true;
    editErrors.value = {};
    try {
        await lotStore.updateLot(editingLot.value.id, payload);
        toast.success("Lote actualizado");
        closeEdit();
        loadLots(meta.value?.current_page || 1);
    } catch (err: any) {
        const data = err?.response?.data;
        if (data?.errors) {
            const flat: Record<string, string> = {};
            for (const k of Object.keys(data.errors)) {
                flat[k] = Array.isArray(data.errors[k])
                    ? data.errors[k][0]
                    : String(data.errors[k]);
            }
            editErrors.value = flat;
        } else {
            toast.error(data?.message || "Error al actualizar el lote");
        }
    } finally {
        savingLot.value = false;
    }
};

const handleToggleStatus = (lot: Lot) => {
    if (!["active", "blocked"].includes(lot.status)) {
        toast.error(
            `No se puede alternar desde estado '${statusLabels[lot.status]}'.`,
        );
        return;
    }
    confirmDialog.value?.show(
        lot.status === "active" ? "Bloquear lote" : "Activar lote",
        lot.status === "active"
            ? `¿Bloquear el lote ${lot.lot_number}? No se podrá vender hasta reactivarlo.`
            : `¿Reactivar el lote ${lot.lot_number}?`,
        async () => {
            try {
                await lotStore.toggleStatus(lot.id);
                toast.success("Estado actualizado");
                loadLots(meta.value?.current_page || 1);
            } catch (err: any) {
                toast.error(
                    err?.response?.data?.message || "No se pudo cambiar el estado",
                );
            }
        },
    );
};

const handleDelete = (lot: Lot) => {
    confirmDialog.value?.show(
        "Eliminar lote",
        `¿Eliminar definitivamente el lote ${lot.lot_number}? Solo es posible si no tiene stock ni movimientos.`,
        async () => {
            try {
                await lotStore.deleteLot(lot.id);
                toast.success("Lote eliminado");
                loadLots(meta.value?.current_page || 1);
            } catch (err: any) {
                toast.error(
                    err?.response?.data?.message || "No se pudo eliminar el lote",
                );
            }
        },
    );
};

const formatDate = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleDateString("es", { dateStyle: "medium" });
};

const expiryHint = (lot: Lot) => {
    if (!lot.expires_at) return null;
    if (lot.is_expired) return { class: "text-red-600", text: "Vencido" };
    if (lot.days_to_expire !== null && lot.days_to_expire <= 30)
        return {
            class: "text-amber-600",
            text: `${lot.days_to_expire}d restantes`,
        };
    return null;
};

const productLabel = (lot: Lot) => {
    return (
        lot.product_product?.template?.name ||
        lot.product_product?.sku ||
        `#${lot.product_product_id}`
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Lotes' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Lotes"
                :items-count="lots.length"
                :total-items="meta?.total || 0"
                :per-page="meta?.per_page || 25"
                :current-page="meta?.current_page || 1"
                :loading="isLoading"
                :can-create="false"
                :search="search"
                v-model:view-mode="viewMode"
                @update:per-page="handlePerPageChange"
                @update:search="handleSearch"
                @page-change="handlePageChange"
            >
                <template #filters>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                class="h-9 gap-1 whitespace-nowrap"
                            >
                                <Filter
                                    class="h-3.5 w-3.5 mr-1 text-muted-foreground"
                                />
                                <span>{{ filterLabel }}</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel>Estado</DropdownMenuLabel>
                            <DropdownMenuItem @click="setStatusFilter('all')">
                                Todos los estados
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setStatusFilter('active')">
                                Solo activos
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setStatusFilter('blocked')">
                                Solo bloqueados
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setStatusFilter('expired')">
                                Solo vencidos
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setStatusFilter('depleted')">
                                Solo agotados
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel>Vencimiento</DropdownMenuLabel>
                            <DropdownMenuItem
                                @click="toggleExpiringFilter(7)"
                            >
                                Vencen en ≤ 7 días
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="toggleExpiringFilter(30)"
                            >
                                Vencen en ≤ 30 días
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="toggleExpiringFilter(90)"
                            >
                                Vencen en ≤ 90 días
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="toggleExpiredFilter">
                                {{ onlyExpired ? "✓ " : "" }}Solo vencidos
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="toggleInStockFilter">
                                {{ onlyInStock ? "✓ " : "" }}Solo con stock
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>

                <template #actions>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-9 gap-1"
                            >
                                Acciones
                                <ChevronDown class="h-4 w-4 opacity-50" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[180px]">
                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="ieToolbar?.openExport()">
                                <Download
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                Exportar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="ieToolbar?.supportsImport"
                                @click="ieToolbar?.openImport()"
                            >
                                <Upload
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                Importar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </ModuleHeader>

            <DataView
                :view-mode="viewMode"
                :is-loading="isLoading"
                :is-empty="lots.length === 0"
                empty-message="No se encontraron lotes."
            >
                <template #table>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Lote</TableHead>
                                <TableHead>Producto</TableHead>
                                <TableHead>Vencimiento</TableHead>
                                <TableHead>Stock</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Compra</TableHead>
                                <TableHead class="w-[60px]"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell colspan="7" class="text-center py-8">
                                    Cargando...
                                </TableCell>
                            </TableRow>
                            <TableRow v-else-if="lots.length === 0">
                                <TableCell
                                    colspan="7"
                                    class="text-center py-8 text-muted-foreground"
                                >
                                    No se encontraron lotes.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="lot in lots"
                                :key="lot.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="openEdit(lot)"
                            >
                                <TableCell class="font-mono text-sm font-medium">
                                    {{ lot.lot_number }}
                                </TableCell>
                                <TableCell>{{ productLabel(lot) }}</TableCell>
                                <TableCell>
                                    <div>{{ formatDate(lot.expires_at) }}</div>
                                    <div
                                        v-if="expiryHint(lot)"
                                        class="text-xs"
                                        :class="expiryHint(lot)!.class"
                                    >
                                        {{ expiryHint(lot)!.text }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="lot.total_stock !== null"
                                        :class="
                                            lot.total_stock > 0
                                                ? 'font-medium'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ lot.total_stock }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell>
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs',
                                            statusBadgeClass[lot.status],
                                        ]"
                                    >
                                        {{ statusLabels[lot.status] }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-xs font-mono text-muted-foreground">
                                    {{ lot.purchase?.sequence_code || "—" }}
                                </TableCell>
                                <TableCell @click.stop>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8">
                                                <ChevronDown class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem @click="openEdit(lot)">
                                                <Pencil class="h-4 w-4 mr-2" />
                                                Editar
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="['active', 'blocked'].includes(lot.status)"
                                                @click="handleToggleStatus(lot)"
                                            >
                                                <component
                                                    :is="lot.status === 'active' ? Lock : Power"
                                                    class="h-4 w-4 mr-2"
                                                />
                                                {{ lot.status === "active" ? "Bloquear" : "Activar" }}
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                class="text-destructive"
                                                @click="handleDelete(lot)"
                                            >
                                                <Trash2 class="h-4 w-4 mr-2" />
                                                Eliminar
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="lot in lots"
                        :key="lot.id"
                        class="border rounded-lg p-4 shadow-sm bg-card hover:bg-muted/50 cursor-pointer flex flex-col gap-3 transition-all"
                        @click="openEdit(lot)"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-mono font-medium">
                                    {{ lot.lot_number }}
                                </div>
                                <div class="text-sm text-muted-foreground truncate">
                                    {{ productLabel(lot) }}
                                </div>
                            </div>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-medium uppercase',
                                    statusBadgeClass[lot.status],
                                ]"
                            >
                                {{ statusLabels[lot.status] }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-xs text-muted-foreground block mb-0.5">
                                    Vencimiento
                                </span>
                                <div>{{ formatDate(lot.expires_at) }}</div>
                                <div
                                    v-if="expiryHint(lot)"
                                    class="text-xs"
                                    :class="expiryHint(lot)!.class"
                                >
                                    {{ expiryHint(lot)!.text }}
                                </div>
                            </div>
                            <div>
                                <span class="text-xs text-muted-foreground block mb-0.5">
                                    Stock
                                </span>
                                <div class="font-medium">
                                    {{ lot.total_stock ?? "—" }}
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <LotEditDialog
            :open="editDialogOpen"
            :lot="editingLot"
            :saving="savingLot"
            :errors="editErrors"
            @close="closeEdit"
            @save="saveEdit"
        />

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="lot"
            :filters="exportFilters"
            :default-export-columns="[
                'lot_number',
                'productProduct.sku',
                'productProduct.product.name',
                'manufactured_at',
                'expires_at',
                'initial_quantity',
                'current_stock',
                'supplier.name',
                'status',
            ]"
            export-title="Exportar Lotes"
        />
    </DashboardLayout>
</template>
