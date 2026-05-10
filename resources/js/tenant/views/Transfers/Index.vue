<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useTransferStore, type Transfer, type TransferStatus } from "@tenant/stores/transfer";
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
    ChevronDown,
    Filter,
    MoreHorizontal,
    Send,
    PackageCheck,
    Ban,
    Pencil,
    Trash2,
    Download,
    Upload,
} from "lucide-vue-next";
import ImportExportToolbar from "@tenant/components/ImportExport/ImportExportToolbar.vue";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Checkbox } from "@/components/ui/checkbox";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import TableColumnSettingsHead from "@/components/TableColumnSettingsHead.vue";

const router = useRouter();
const transferStore = useTransferStore();
const { transfers, meta, isLoading } = storeToRefs(transferStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | "total">(15);
const search = ref("");
const selectedTransfers = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");
const currentStatus = ref("all");

// ── Column visibility ──────────────────────────────────────────
type ColumnKey =
    | "sequence_code"
    | "from_warehouse"
    | "to_warehouse"
    | "from_company"
    | "to_company"
    | "date"
    | "status"
    | "total";

const COLUMN_STORAGE_KEY = "transfers_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "sequence_code", label: "Documento" },
    { key: "from_warehouse", label: "Origen" },
    { key: "to_warehouse", label: "Destino" },
    { key: "from_company", label: "Empresa origen" },
    { key: "to_company", label: "Empresa destino" },
    { key: "date", label: "Fecha" },
    { key: "status", label: "Estado" },
    { key: "total", label: "Total" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    sequence_code: true,
    from_warehouse: true,
    to_warehouse: true,
    from_company: false,
    to_company: false,
    date: true,
    status: true,
    total: true,
};

const columnVisibility = ref<Record<ColumnKey, boolean>>({
    ...defaultColumnVisibility,
});

const visibleColumnCount = computed(() =>
    Object.values(columnVisibility.value).filter(Boolean).length,
);
// checkbox + columnas visibles + actions cell
const tableColspan = computed(() => 2 + visibleColumnCount.value);

// ── Selection ─────────────────────────────────────────────────
const allSelected = computed(() => {
    return (
        transfers.value.length > 0 &&
        selectedTransfers.value.length === transfers.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedTransfers.value = transfers.value.map((t) => t.id);
    } else {
        selectedTransfers.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedTransfers.value.push(id);
    } else {
        selectedTransfers.value = selectedTransfers.value.filter((tid) => tid !== id);
    }
};

// ── Formatters ────────────────────────────────────────────────
const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const formatCurrency = (amount: string | number) => {
    const num = Number(amount);
    if (Number.isNaN(num)) return "-";
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(num);
};

const statusClass = (status: TransferStatus) => {
    switch (status) {
        case "draft":
            return "bg-gray-400";
        case "pending_exit":
            return "bg-amber-500";
        case "in_transit":
        case "sent":
            return "bg-blue-500";
        case "completed":
        case "received":
            return "bg-emerald-500";
        case "with_observation":
            return "bg-orange-500";
        case "cancelled":
            return "bg-red-500";
        default:
            return "bg-gray-300";
    }
};

const statusLabel = (status: TransferStatus) => {
    switch (status) {
        case "draft":
            return "Borrador";
        case "pending_exit":
            return "Pend. Salida";
        case "in_transit":
        case "sent":
            return "En tránsito";
        case "completed":
        case "received":
            return "Completada";
        case "with_observation":
            return "Con observación";
        case "cancelled":
            return "Cancelada";
        default:
            return status;
    }
};

// ── Filter ────────────────────────────────────────────────────
const filterLabel = computed(() => {
    switch (currentStatus.value) {
        case "draft":
            return "Borradores";
        case "pending_exit":
            return "Pendientes salida";
        case "in_transit":
            return "En tránsito";
        case "completed":
            return "Completadas";
        case "with_observation":
            return "Con observación";
        case "cancelled":
            return "Canceladas";
        case "all":
            return "Todas";
        default:
            return "Filtrar";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadTransfers(1);
};

// ── Data loading ──────────────────────────────────────────────
const loadTransfers = (page = 1) => {
    selectedTransfers.value = [];
    transferStore.fetchTransfers(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadTransfers();
});

useCompanyFilterRefresh(() => loadTransfers());

const handleSearch = (value: string) => {
    search.value = value;
    loadTransfers(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        transferStore.fetchTransfers(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadTransfers(1);
    }
};

const handlePageChange = (page: number) => {
    loadTransfers(page);
};

// ── Navigation ────────────────────────────────────────────────
const navigateToCreate = () => {
    router.push("/admin/transfers/create");
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (selectedTransfers.value.length > 0)
        filters.ids = selectedTransfers.value;
    return filters;
});

const navigateToEdit = (id: number) => {
    router.push(`/admin/transfers/${id}/edit`);
};

const navigateToShow = (id: number) => {
    router.push(`/admin/transfers/${id}/edit`);
};

// ── Row actions ───────────────────────────────────────────────
const handleDelete = async (id: number) => {
    confirmDialog.value?.show(
        "Eliminar transferencia",
        "¿Seguro que quieres eliminar esta transferencia en borrador?",
        async () => {
            await transferStore.deleteTransfer(id);
            loadTransfers(meta.value.current_page);
        },
    );
};

const handleSend = (id: number) => {
    confirmDialog.value?.show(
        "Enviar transferencia",
        "Esto descontará el stock del almacén de origen. ¿Continuar?",
        async () => {
            await transferStore.sendTransfer(id);
            loadTransfers(meta.value.current_page);
        },
    );
};

const handleReceive = (id: number) => {
    confirmDialog.value?.show(
        "Recibir transferencia",
        "Esto sumará el stock al almacén de destino. ¿Continuar?",
        async () => {
            await transferStore.receiveTransfer(id);
            loadTransfers(meta.value.current_page);
        },
    );
};

const handleCancel = (id: number) => {
    confirmDialog.value?.show(
        "Cancelar transferencia",
        "Se revertirán los movimientos de stock realizados. ¿Continuar?",
        async () => {
            await transferStore.cancelTransfer(id);
            loadTransfers(meta.value.current_page);
        },
    );
};

// ── Row click behaviour ───────────────────────────────────────
const handleRowClick = (t: Transfer) => {
    // Draft editable, resto solo lectura (reusa FormPage en modo edición)
    navigateToEdit(t.id);
};

// ── Cross-company column hint ────────────────────────────────
const isCrossCompany = (t: Transfer) =>
    t.from_warehouse?.company_id !== undefined &&
    t.to_warehouse?.company_id !== undefined &&
    t.from_warehouse.company_id !== t.to_warehouse.company_id;
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Transferencias' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Transferencias"
                :items-count="transfers.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedTransfers"
                v-model:view-mode="viewMode"
                @create="navigateToCreate"
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
                                <span class="hidden sm:inline">{{
                                    filterLabel
                                }}</span>
                                <span class="sm:hidden">Filtrar</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setFilter('draft')">
                                Borradores
                                <span
                                    v-if="meta.draft_total !== undefined"
                                    class="ml-auto text-xs text-muted-foreground"
                                >
                                    {{ meta.draft_total }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('pending_exit')">
                                Pendientes de salida
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('in_transit')">
                                En tránsito
                                <span
                                    v-if="meta.in_transit_total !== undefined"
                                    class="ml-auto text-xs text-muted-foreground"
                                >
                                    {{ meta.in_transit_total }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('completed')">
                                Completadas
                                <span
                                    v-if="meta.completed_total !== undefined"
                                    class="ml-auto text-xs text-muted-foreground"
                                >
                                    {{ meta.completed_total }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setFilter('with_observation')"
                            >
                                Con observación
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('cancelled')">
                                Canceladas
                                <span
                                    v-if="meta.cancelled_total !== undefined"
                                    class="ml-auto text-xs text-muted-foreground"
                                >
                                    {{ meta.cancelled_total }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="setFilter('all')">
                                Todas
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
                :is-empty="transfers.length === 0"
                empty-message="No hay transferencias."
            >
                <template #table>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[50px]">
                                    <Checkbox
                                        :checked="allSelected"
                                        @update:checked="toggleSelectAll"
                                    />
                                </TableHead>
                                <TableHead v-if="columnVisibility.sequence_code">
                                    Documento
                                </TableHead>
                                <TableHead v-if="columnVisibility.from_warehouse">
                                    Origen
                                </TableHead>
                                <TableHead v-if="columnVisibility.to_warehouse">
                                    Destino
                                </TableHead>
                                <TableHead v-if="columnVisibility.from_company">
                                    Empresa origen
                                </TableHead>
                                <TableHead v-if="columnVisibility.to_company">
                                    Empresa destino
                                </TableHead>
                                <TableHead v-if="columnVisibility.date">Fecha</TableHead>
                                <TableHead v-if="columnVisibility.status">Estado</TableHead>
                                <TableHead
                                    class="text-right"
                                    v-if="columnVisibility.total"
                                >
                                    Total
                                </TableHead>
                                <TableColumnSettingsHead
                                    v-model="columnVisibility"
                                    :columns="columnOptions"
                                    :storage-key="COLUMN_STORAGE_KEY"
                                />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8"
                                >
                                    Cargando...
                                </TableCell>
                            </TableRow>
                            <TableRow v-else-if="transfers.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                >
                                    No hay transferencias.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="t in transfers"
                                :key="t.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="handleRowClick(t)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedTransfers.includes(t.id)"
                                        @update:checked="
                                            (checked: boolean) =>
                                                toggleSelectRow(t.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.sequence_code"
                                    class="font-medium whitespace-nowrap"
                                >
                                    {{ t.sequence_code }}
                                    <span
                                        v-if="isCrossCompany(t)"
                                        class="ml-1 text-[10px] text-amber-600 uppercase tracking-wide"
                                        title="Transferencia entre empresas"
                                    >
                                        ↔
                                    </span>
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.from_warehouse"
                                    class="truncate max-w-[200px]"
                                >
                                    {{ t.from_warehouse?.name || "-" }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.to_warehouse"
                                    class="truncate max-w-[200px]"
                                >
                                    {{ t.to_warehouse?.name || "-" }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.from_company"
                                    class="truncate max-w-[180px] text-xs text-muted-foreground"
                                >
                                    {{ t.from_warehouse?.company?.business_name || t.from_warehouse?.company?.name || "-" }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.to_company"
                                    class="truncate max-w-[180px] text-xs text-muted-foreground"
                                >
                                    {{ t.to_warehouse?.company?.business_name || t.to_warehouse?.company?.name || "-" }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.date"
                                    class="whitespace-nowrap"
                                >
                                    {{ formatCreated(t.date || t.created_at) }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.status">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium uppercase text-[10px] tracking-wider text-white',
                                            statusClass(t.status),
                                        ]"
                                    >
                                        {{ statusLabel(t.status) }}
                                    </span>
                                </TableCell>
                                <TableCell
                                    class="text-right font-medium"
                                    v-if="columnVisibility.total"
                                >
                                    {{ formatCurrency(t.total) }}
                                </TableCell>
                                <TableCell class="w-[50px]" @click.stop>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-[200px]">
                                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                                            <DropdownMenuSeparator />

                                            <!-- Draft: editar, enviar, eliminar -->
                                            <template v-if="t.status === 'draft'">
                                                <DropdownMenuItem @click="navigateToEdit(t.id)">
                                                    <Pencil class="mr-2 h-4 w-4" />
                                                    Editar
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="handleSend(t.id)">
                                                    <Send class="mr-2 h-4 w-4" />
                                                    Enviar
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    @click="handleDelete(t.id)"
                                                    class="text-destructive"
                                                >
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Eliminar
                                                </DropdownMenuItem>
                                            </template>

                                            <!-- Pending exit / In transit: ver, recibir, cancelar -->
                                            <template
                                                v-else-if="
                                                    t.status === 'pending_exit' ||
                                                    t.status === 'in_transit' ||
                                                    t.status === 'sent'
                                                "
                                            >
                                                <DropdownMenuItem @click="navigateToShow(t.id)">
                                                    <Pencil class="mr-2 h-4 w-4" />
                                                    Ver detalle
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="
                                                        t.status === 'in_transit' ||
                                                        t.status === 'sent'
                                                    "
                                                    @click="handleReceive(t.id)"
                                                >
                                                    <PackageCheck class="mr-2 h-4 w-4" />
                                                    Recibir
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    @click="handleCancel(t.id)"
                                                    class="text-destructive"
                                                >
                                                    <Ban class="mr-2 h-4 w-4" />
                                                    Cancelar
                                                </DropdownMenuItem>
                                            </template>

                                            <!-- Completed / with_observation: ver, cancelar -->
                                            <template
                                                v-else-if="
                                                    t.status === 'completed' ||
                                                    t.status === 'received' ||
                                                    t.status === 'with_observation'
                                                "
                                            >
                                                <DropdownMenuItem @click="navigateToShow(t.id)">
                                                    <Pencil class="mr-2 h-4 w-4" />
                                                    Ver detalle
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    @click="handleCancel(t.id)"
                                                    class="text-destructive"
                                                >
                                                    <Ban class="mr-2 h-4 w-4" />
                                                    Cancelar
                                                </DropdownMenuItem>
                                            </template>

                                            <!-- Cancelled: solo ver detalle (no se restaura a borrador en el modelo nuevo) -->
                                            <template v-else-if="t.status === 'cancelled'">
                                                <DropdownMenuItem @click="navigateToShow(t.id)">
                                                    <Pencil class="mr-2 h-4 w-4" />
                                                    Ver detalle
                                                </DropdownMenuItem>
                                            </template>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="t in transfers"
                        :key="t.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="handleRowClick(t)"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-medium text-base">
                                    {{ t.sequence_code }}
                                </h3>
                                <div class="text-sm text-muted-foreground">
                                    {{ t.from_warehouse?.name || "?" }}
                                    →
                                    {{ t.to_warehouse?.name || "?" }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-medium text-lg block">
                                    {{ formatCurrency(t.total) }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatCreated(t.date || t.created_at) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-2">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                    statusClass(t.status),
                                ]"
                            >
                                {{ statusLabel(t.status) }}
                            </span>
                            <span
                                v-if="isCrossCompany(t)"
                                class="text-[10px] text-amber-600 uppercase tracking-wide"
                            >
                                Inter-empresa ↔
                            </span>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="transfer"
            :filters="exportFilters"
            :default-export-columns="[
                'date',
                'serie',
                'correlative',
                'exitMovement.warehouse.name',
                'entryMovement.warehouse.name',
                'derived_status',
                'total',
                'gre_status',
            ]"
            export-title="Exportar Transferencias"
        />
    </DashboardLayout>
</template>
