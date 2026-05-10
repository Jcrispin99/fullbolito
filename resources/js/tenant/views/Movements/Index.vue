<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useRoute, useRouter } from "vue-router";
import {
    useMovementStore,
    type Movement,
    type MovementStatus,
    type MovementType,
} from "@tenant/stores/movement";
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
    Undo2,
    Pencil,
    Trash2,
    ArrowDownToLine,
    ArrowUpFromLine,
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
const route = useRoute();
const movementStore = useMovementStore();
const { movements, meta, isLoading } = storeToRefs(movementStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

// Read initial type filter from query (?type=entry|exit) so submenu links prefill it.
const readTypeFromQuery = (): "all" | MovementType => {
    const q = route.query.type;
    if (q === "entry" || q === "exit") return q;
    return "all";
};

const perPage = ref<number | "total">(15);
const search = ref("");
const selectedMovements = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");
const currentStatus = ref("all");
const currentType = ref<"all" | MovementType>(readTypeFromQuery());
const standaloneOnly = ref(true);

// Re-sync filter when navigating between Entradas/Salidas submenus.
watch(
    () => route.query.type,
    () => {
        const next = readTypeFromQuery();
        if (next !== currentType.value) {
            currentType.value = next;
            loadMovements(1);
        }
    },
);

// ── Column visibility ──────────────────────────────────────────
type ColumnKey =
    | "sequence_code"
    | "type"
    | "warehouse"
    | "company"
    | "date"
    | "status"
    | "transfer"
    | "total";

const COLUMN_STORAGE_KEY = "movements_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "sequence_code", label: "Documento" },
    { key: "type", label: "Tipo" },
    { key: "warehouse", label: "Almacén" },
    { key: "company", label: "Empresa" },
    { key: "date", label: "Fecha" },
    { key: "status", label: "Estado" },
    { key: "transfer", label: "Transferencia" },
    { key: "total", label: "Total" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    sequence_code: true,
    type: true,
    warehouse: true,
    company: false,
    date: true,
    status: true,
    transfer: false,
    total: true,
};

const columnVisibility = ref<Record<ColumnKey, boolean>>({
    ...defaultColumnVisibility,
});

const visibleColumnCount = computed(() =>
    Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 2 + visibleColumnCount.value);

// ── Selection ─────────────────────────────────────────────────
const allSelected = computed(() => {
    return (
        movements.value.length > 0 &&
        selectedMovements.value.length === movements.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedMovements.value = movements.value.map((m) => m.id);
    } else {
        selectedMovements.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedMovements.value.push(id);
    } else {
        selectedMovements.value = selectedMovements.value.filter((mid) => mid !== id);
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

const statusClass = (status: MovementStatus) => {
    switch (status) {
        case "draft":
            return "bg-gray-400";
        case "submitted":
            return "bg-amber-500";
        case "posted":
            return "bg-emerald-500";
        case "rejected":
            return "bg-red-500";
        case "cancelled":
            return "bg-red-700";
        default:
            return "bg-gray-300";
    }
};

const statusLabel = (status: MovementStatus) => {
    switch (status) {
        case "draft":
            return "Borrador";
        case "submitted":
            return "Pendiente";
        case "posted":
            return "Aprobado";
        case "rejected":
            return "Rechazado";
        case "cancelled":
            return "Cancelado";
        default:
            return status;
    }
};

const typeLabel = (t: MovementType) => (t === "entry" ? "Entrada" : "Salida");

// ── Filters ───────────────────────────────────────────────────
const statusFilterLabel = computed(() => {
    switch (currentStatus.value) {
        case "draft":
            return "Borradores";
        case "submitted":
            return "Pendientes";
        case "posted":
            return "Aprobados";
        case "rejected":
            return "Rechazados";
        case "cancelled":
            return "Cancelados";
        case "all":
            return "Todos";
        default:
            return "Filtrar";
    }
});

const typeFilterLabel = computed(() => {
    switch (currentType.value) {
        case "entry":
            return "Entradas";
        case "exit":
            return "Salidas";
        default:
            return "Tipo";
    }
});

const setStatusFilter = (status: string) => {
    currentStatus.value = status;
    loadMovements(1);
};

const setTypeFilter = (type: "all" | MovementType) => {
    currentType.value = type;
    loadMovements(1);
};

const toggleStandalone = () => {
    standaloneOnly.value = !standaloneOnly.value;
    loadMovements(1);
};

// ── Data loading ──────────────────────────────────────────────
const loadMovements = (page = 1) => {
    selectedMovements.value = [];
    movementStore.fetchMovements(
        page,
        perPage.value,
        search.value,
        currentStatus.value,
        currentType.value,
        standaloneOnly.value,
    );
};

onMounted(() => {
    loadMovements();
});

useCompanyFilterRefresh(() => loadMovements());

const handleSearch = (value: string) => {
    search.value = value;
    loadMovements(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        movementStore.fetchMovements(
            1,
            "total",
            search.value,
            currentStatus.value,
            currentType.value,
            standaloneOnly.value,
        );
    } else {
        perPage.value = Number(newPerPage);
        loadMovements(1);
    }
};

const handlePageChange = (page: number) => {
    loadMovements(page);
};

// ── Navigation ────────────────────────────────────────────────
const navigateToCreate = () => {
    router.push("/admin/movements/create");
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);
const ieLinesToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value && currentStatus.value !== "all") {
        filters.status = currentStatus.value;
    }
    if (selectedMovements.value.length > 0) {
        filters.ids = selectedMovements.value;
    }
    return filters;
});

const navigateToEdit = (id: number) => {
    router.push(`/admin/movements/${id}/edit`);
};

const navigateToShow = (id: number) => {
    router.push(`/admin/movements/${id}/edit`);
};

// ── Row actions ───────────────────────────────────────────────
const handleDelete = (id: number) => {
    confirmDialog.value?.show(
        "Eliminar movimiento",
        "¿Seguro que quieres eliminar este movimiento en borrador?",
        async () => {
            await movementStore.deleteMovement(id);
            loadMovements(meta.value.current_page);
        },
    );
};

const handleSubmit = (id: number) => {
    confirmDialog.value?.show(
        "Enviar a aprobación",
        "El movimiento quedará pendiente de aprobación. ¿Continuar?",
        async () => {
            await movementStore.submitMovement(id);
            loadMovements(meta.value.current_page);
        },
    );
};

const handlePost = (id: number, type: MovementType) => {
    const desc =
        type === "entry"
            ? "Esto registrará la entrada en el almacén."
            : "Esto descontará el stock del almacén.";
    confirmDialog.value?.show("Aprobar movimiento", `${desc} ¿Continuar?`, async () => {
        await movementStore.postMovement(id);
        loadMovements(meta.value.current_page);
    });
};

const handleReject = (id: number) => {
    confirmDialog.value?.show(
        "Rechazar movimiento",
        "El movimiento volverá a borrador para corrección. ¿Continuar?",
        async () => {
            await movementStore.rejectMovement(id);
            loadMovements(meta.value.current_page);
        },
    );
};

const handleCancel = (id: number) => {
    confirmDialog.value?.show(
        "Cancelar movimiento",
        "Se generará un contra-asiento que revierte los movimientos de stock. ¿Continuar?",
        async () => {
            await movementStore.cancelMovement(id);
            loadMovements(meta.value.current_page);
        },
    );
};

// ── Row click behaviour ───────────────────────────────────────
const handleRowClick = (m: Movement) => {
    navigateToEdit(m.id);
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Movimientos' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Movimientos"
                :items-count="movements.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedMovements"
                v-model:view-mode="viewMode"
                @create="navigateToCreate"
                @update:per-page="handlePerPageChange"
                @update:search="handleSearch"
                @page-change="handlePageChange"
            >
                <template #filters>
                    <!-- Type filter -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                class="h-9 gap-1 whitespace-nowrap"
                            >
                                <Filter
                                    class="h-3.5 w-3.5 mr-1 text-muted-foreground"
                                />
                                <span>{{ typeFilterLabel }}</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setTypeFilter('entry')">
                                <ArrowDownToLine class="mr-2 h-4 w-4 text-emerald-500" />
                                Entradas
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setTypeFilter('exit')">
                                <ArrowUpFromLine class="mr-2 h-4 w-4 text-red-500" />
                                Salidas
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="setTypeFilter('all')">
                                Todos
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Status filter -->
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
                                    statusFilterLabel
                                }}</span>
                                <span class="sm:hidden">Estado</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setStatusFilter('draft')">
                                Borradores
                                <span
                                    v-if="meta.draft_total !== undefined"
                                    class="ml-auto text-xs text-muted-foreground"
                                >
                                    {{ meta.draft_total }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setStatusFilter('submitted')"
                            >
                                Pendientes
                                <span
                                    v-if="meta.submitted_total !== undefined"
                                    class="ml-auto text-xs text-muted-foreground"
                                >
                                    {{ meta.submitted_total }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setStatusFilter('posted')"
                            >
                                Aprobados
                                <span
                                    v-if="meta.posted_total !== undefined"
                                    class="ml-auto text-xs text-muted-foreground"
                                >
                                    {{ meta.posted_total }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setStatusFilter('rejected')"
                            >
                                Rechazados
                                <span
                                    v-if="meta.rejected_total !== undefined"
                                    class="ml-auto text-xs text-muted-foreground"
                                >
                                    {{ meta.rejected_total }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setStatusFilter('cancelled')"
                            >
                                Cancelados
                                <span
                                    v-if="meta.cancelled_total !== undefined"
                                    class="ml-auto text-xs text-muted-foreground"
                                >
                                    {{ meta.cancelled_total }}
                                </span>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="setStatusFilter('all')">
                                Todos
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Standalone toggle -->
                    <Button
                        variant="outline"
                        class="h-9 whitespace-nowrap"
                        :class="
                            standaloneOnly
                                ? ''
                                : 'bg-amber-50 border-amber-300 text-amber-900'
                        "
                        @click="toggleStandalone"
                        :title="
                            standaloneOnly
                                ? 'Mostrando solo movimientos sueltos. Click para incluir transferencias.'
                                : 'Mostrando todo (incluye transferencias). Click para ocultarlas.'
                        "
                    >
                        {{ standaloneOnly ? "Solo sueltos" : "Incluye transferencias" }}
                    </Button>
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
                                @click="ieLinesToolbar?.openExport()"
                            >
                                <Download
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                Exportar líneas
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
                :is-empty="movements.length === 0"
                empty-message="No hay movimientos."
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
                                <TableHead v-if="columnVisibility.type">
                                    Tipo
                                </TableHead>
                                <TableHead v-if="columnVisibility.warehouse">
                                    Almacén
                                </TableHead>
                                <TableHead v-if="columnVisibility.company">
                                    Empresa
                                </TableHead>
                                <TableHead v-if="columnVisibility.date">
                                    Fecha
                                </TableHead>
                                <TableHead v-if="columnVisibility.status">
                                    Estado
                                </TableHead>
                                <TableHead v-if="columnVisibility.transfer">
                                    Transferencia
                                </TableHead>
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
                            <TableRow v-else-if="movements.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                >
                                    No hay movimientos.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="m in movements"
                                :key="m.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="handleRowClick(m)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedMovements.includes(m.id)"
                                        @update:checked="
                                            (checked: boolean) =>
                                                toggleSelectRow(m.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.sequence_code"
                                    class="font-medium whitespace-nowrap"
                                >
                                    {{ m.sequence_code }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.type">
                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium"
                                        :class="
                                            m.type === 'entry'
                                                ? 'text-emerald-700'
                                                : 'text-red-700'
                                        "
                                    >
                                        <ArrowDownToLine
                                            v-if="m.type === 'entry'"
                                            class="h-3.5 w-3.5"
                                        />
                                        <ArrowUpFromLine
                                            v-else
                                            class="h-3.5 w-3.5"
                                        />
                                        {{ typeLabel(m.type) }}
                                    </span>
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.warehouse"
                                    class="truncate max-w-[200px]"
                                >
                                    {{ m.warehouse?.name || "-" }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.company"
                                    class="truncate max-w-[180px] text-xs text-muted-foreground"
                                >
                                    {{
                                        m.warehouse?.company?.business_name ||
                                        m.warehouse?.company?.name ||
                                        "-"
                                    }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.date"
                                    class="whitespace-nowrap"
                                >
                                    {{ formatCreated(m.date || m.created_at) }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.status">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium uppercase text-[10px] tracking-wider text-white',
                                            statusClass(m.status),
                                        ]"
                                    >
                                        {{ statusLabel(m.status) }}
                                    </span>
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.transfer"
                                    class="text-xs text-muted-foreground"
                                >
                                    <span v-if="m.transfer_id">
                                        TR-{{ m.transfer_id }}
                                    </span>
                                    <span v-else>—</span>
                                </TableCell>
                                <TableCell
                                    class="text-right font-medium"
                                    v-if="columnVisibility.total"
                                >
                                    {{ formatCurrency(m.total) }}
                                </TableCell>
                                <TableCell class="w-[50px]" @click.stop>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                            >
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            class="w-[200px]"
                                        >
                                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                                            <DropdownMenuSeparator />

                                            <!-- Draft: editar, enviar a aprobación, eliminar (solo si no pertenece a transfer) -->
                                            <template
                                                v-if="
                                                    m.status === 'draft' &&
                                                    !m.transfer_id
                                                "
                                            >
                                                <DropdownMenuItem
                                                    @click="navigateToEdit(m.id)"
                                                >
                                                    <Pencil class="mr-2 h-4 w-4" />
                                                    Editar
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="handleSubmit(m.id)"
                                                >
                                                    <Send class="mr-2 h-4 w-4" />
                                                    Enviar a aprobación
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    @click="handleDelete(m.id)"
                                                    class="text-destructive"
                                                >
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Eliminar
                                                </DropdownMenuItem>
                                            </template>

                                            <!-- Submitted: ver, aprobar, rechazar -->
                                            <template
                                                v-else-if="m.status === 'submitted'"
                                            >
                                                <DropdownMenuItem
                                                    @click="navigateToShow(m.id)"
                                                >
                                                    <Pencil class="mr-2 h-4 w-4" />
                                                    Ver detalle
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="
                                                        handlePost(m.id, m.type)
                                                    "
                                                >
                                                    <PackageCheck
                                                        class="mr-2 h-4 w-4"
                                                    />
                                                    Aprobar
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    @click="handleReject(m.id)"
                                                    class="text-destructive"
                                                >
                                                    <Undo2 class="mr-2 h-4 w-4" />
                                                    Rechazar
                                                </DropdownMenuItem>
                                            </template>

                                            <!-- Posted: ver, cancelar -->
                                            <template
                                                v-else-if="m.status === 'posted'"
                                            >
                                                <DropdownMenuItem
                                                    @click="navigateToShow(m.id)"
                                                >
                                                    <Pencil class="mr-2 h-4 w-4" />
                                                    Ver detalle
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    v-if="!m.transfer_id"
                                                    @click="handleCancel(m.id)"
                                                    class="text-destructive"
                                                >
                                                    <Ban class="mr-2 h-4 w-4" />
                                                    Cancelar
                                                </DropdownMenuItem>
                                            </template>

                                            <!-- Rejected/Cancelled/Transfer-bound: ver -->
                                            <template v-else>
                                                <DropdownMenuItem
                                                    @click="navigateToShow(m.id)"
                                                >
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
                        v-for="m in movements"
                        :key="m.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="handleRowClick(m)"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-medium text-base flex items-center gap-2">
                                    <ArrowDownToLine
                                        v-if="m.type === 'entry'"
                                        class="h-4 w-4 text-emerald-600"
                                    />
                                    <ArrowUpFromLine
                                        v-else
                                        class="h-4 w-4 text-red-600"
                                    />
                                    {{ m.sequence_code }}
                                </h3>
                                <div class="text-sm text-muted-foreground">
                                    {{ m.warehouse?.name || "?" }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-medium text-lg block">
                                    {{ formatCurrency(m.total) }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatCreated(m.date || m.created_at) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                    statusClass(m.status),
                                ]"
                            >
                                {{ statusLabel(m.status) }}
                            </span>
                            <span
                                v-if="m.transfer_id"
                                class="text-[10px] text-muted-foreground uppercase tracking-wide"
                            >
                                TR-{{ m.transfer_id }}
                            </span>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="movement"
            :filters="exportFilters"
            :default-export-columns="[
                'date',
                'serie',
                'correlative',
                'type',
                'warehouse.name',
                'reason',
                'total',
                'status',
                'lines_count',
            ]"
            export-title="Exportar Movimientos"
        />

        <ImportExportToolbar
            ref="ieLinesToolbar"
            resource="movement_line"
            :filters="exportFilters"
            :default-export-columns="[
                'productable.date',
                'productable.serie',
                'productable.correlative',
                'productable.type',
                'productable.warehouse.name',
                'productable.reason',
                'productProduct.sku',
                'productProduct.display_name',
                'uom.name',
                'quantity',
                'price',
                'total',
            ]"
            export-title="Exportar líneas de movimiento"
        />
    </DashboardLayout>
</template>
