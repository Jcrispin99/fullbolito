<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import {
    useReservationStore,
    type ReservationFormOptions,
} from "@tenant/stores/reservation";
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
import { Trash2, ChevronDown, Filter, X, LayoutList, CalendarDays } from "lucide-vue-next";
import type { ViewModeOption } from "@/central/components/ModuleHeader.vue";
import CalendarView from "./CalendarView.vue";
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
import {
    statusMeta,
    formatDateTime,
    formatPrice,
    ALL_STATUSES,
} from "./status";

const router = useRouter();
const store = useReservationStore();
const { reservations, meta, isLoading } = storeToRefs(store);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const VIEW_STORAGE_KEY = "reservations_view_mode";
const viewMode = ref<string>(
    (typeof localStorage !== "undefined" &&
        localStorage.getItem(VIEW_STORAGE_KEY)) ||
        "table",
);
const setViewMode = (m: string) => {
    viewMode.value = m;
    if (typeof localStorage !== "undefined") {
        localStorage.setItem(VIEW_STORAGE_KEY, m);
    }
};

const VIEW_MODES: ViewModeOption[] = [
    { value: "table", icon: LayoutList, label: "Tabla" },
    { value: "calendar", icon: CalendarDays, label: "Calendario" },
];

const perPage = ref<number | string>(25);
const search = ref("");
const selectedIds = ref<number[]>([]);
const currentStatus = ref<string>("");
const currentCourtId = ref<number | "">("");
const dateFrom = ref("");
const dateTo = ref("");

const formOptions = ref<ReservationFormOptions>({
    courts: [],
    statuses: [],
});

type ColumnKey =
    | "code"
    | "court"
    | "customer"
    | "start_at"
    | "end_at"
    | "total"
    | "status"
    | "created";

const COLUMN_STORAGE_KEY = "reservations_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "code", label: "Código" },
    { key: "court", label: "Cancha" },
    { key: "customer", label: "Cliente" },
    { key: "start_at", label: "Inicio" },
    { key: "end_at", label: "Fin" },
    { key: "total", label: "Total" },
    { key: "status", label: "Estado" },
    { key: "created", label: "Creada" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    code: true,
    court: true,
    customer: true,
    start_at: true,
    end_at: false,
    total: true,
    status: true,
    created: false,
};

const columnVisibility = ref<Record<ColumnKey, boolean>>({
    ...defaultColumnVisibility,
});

const visibleColumnCount = computed(() =>
    Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 2 + visibleColumnCount.value);

const allSelected = computed(() => {
    return (
        reservations.value.length > 0 &&
        selectedIds.value.length === reservations.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedIds.value = reservations.value.map((r) => r.id);
    } else {
        selectedIds.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter((sid) => sid !== id);
    }
};

const customerLabel = (
    r: (typeof reservations.value)[number],
): string => {
    return (
        r.partner?.name ||
        r.customer_name ||
        r.customer_phone ||
        r.customer_email ||
        "-"
    );
};

const statusFilterLabel = computed(() => {
    if (!currentStatus.value) return "Todos los estados";
    if (currentStatus.value === "blocking") return "Bloquean horario";
    return statusMeta(currentStatus.value).label;
});

const courtFilterLabel = computed(() => {
    if (!currentCourtId.value) return "Todas las canchas";
    const court = formOptions.value.courts.find(
        (c) => c.id === currentCourtId.value,
    );
    return court ? court.name : "Cancha";
});

const setStatusFilter = (status: string) => {
    currentStatus.value = status;
    loadList(1);
};

const setCourtFilter = (id: number | "") => {
    currentCourtId.value = id;
    loadList(1);
};

const clearDateRange = () => {
    dateFrom.value = "";
    dateTo.value = "";
    loadList(1);
};

const applyDateRange = () => {
    loadList(1);
};

const loadList = (page = 1) => {
    selectedIds.value = [];
    store.fetchReservations(
        page,
        perPage.value,
        search.value,
        currentCourtId.value,
        currentStatus.value,
        dateFrom.value,
        dateTo.value,
    );
};

onMounted(async () => {
    loadList();
    formOptions.value = await store.fetchFormOptions();
});

useCompanyFilterRefresh(() => loadList());

const handleSearch = (value: string) => {
    search.value = value;
    loadList(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        store.fetchReservations(
            1,
            "total",
            search.value,
            currentCourtId.value,
            currentStatus.value,
            dateFrom.value,
            dateTo.value,
        );
    } else {
        perPage.value = Number(newPerPage);
        loadList(1);
    }
};

const handlePageChange = (page: number) => {
    loadList(page);
};

const navigateToCreate = () => {
    router.push("/admin/reservations/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/reservations/${id}/edit`);
};

const handleBatchDelete = () => {
    if (selectedIds.value.length === 0) return;
    confirmDialog.value?.show(
        "Eliminar reservas",
        `¿Seguro que deseas eliminar ${selectedIds.value.length} reserva(s)? Esta acción no se puede deshacer.`,
        async () => {
            await store.deleteReservations(selectedIds.value);
            loadList(meta.value.current_page);
            selectedIds.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Reservas' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Reservas"
                :view-mode="viewMode"
                :view-modes="VIEW_MODES"
                @update:view-mode="setViewMode"
                :items-count="reservations.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedIds"
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
                                    statusFilterLabel
                                }}</span>
                                <span class="sm:hidden">Estado</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            class="max-h-72 overflow-auto"
                        >
                            <DropdownMenuItem @click="setStatusFilter('')">
                                Todos
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setStatusFilter('blocking')"
                            >
                                Solo activas (bloquean horario)
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="s in ALL_STATUSES"
                                :key="s"
                                @click="setStatusFilter(s)"
                            >
                                {{ statusMeta(s).label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu v-if="formOptions.courts.length > 0">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                class="h-9 gap-1 whitespace-nowrap"
                            >
                                <span class="hidden sm:inline">{{
                                    courtFilterLabel
                                }}</span>
                                <span class="sm:hidden">Cancha</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            class="max-h-72 overflow-auto"
                        >
                            <DropdownMenuItem @click="setCourtFilter('')">
                                Todas las canchas
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="c in formOptions.courts"
                                :key="c.id"
                                @click="setCourtFilter(c.id)"
                            >
                                {{ c.name }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <div class="flex items-center gap-1">
                        <input
                            v-model="dateFrom"
                            @change="applyDateRange"
                            type="date"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            title="Desde"
                        />
                        <span class="text-xs text-muted-foreground">—</span>
                        <input
                            v-model="dateTo"
                            @change="applyDateRange"
                            type="date"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            title="Hasta"
                        />
                        <Button
                            v-if="dateFrom || dateTo"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            @click="clearDateRange"
                            title="Limpiar fechas"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
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
                        <DropdownMenuContent align="end" class="w-[200px]">
                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                @click="handleBatchDelete"
                                class="text-destructive"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </ModuleHeader>

            <CalendarView
                v-if="viewMode === 'calendar'"
                :form-options="formOptions"
                :initial-court-id="
                    typeof currentCourtId === 'number'
                        ? currentCourtId
                        : undefined
                "
            />

            <div v-if="viewMode === 'table'" class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[50px]">
                                <Checkbox
                                    :checked="allSelected"
                                    @update:checked="toggleSelectAll"
                                />
                            </TableHead>
                            <TableHead v-if="columnVisibility.code"
                                >Código</TableHead
                            >
                            <TableHead v-if="columnVisibility.court"
                                >Cancha</TableHead
                            >
                            <TableHead v-if="columnVisibility.customer"
                                >Cliente</TableHead
                            >
                            <TableHead v-if="columnVisibility.start_at"
                                >Inicio</TableHead
                            >
                            <TableHead v-if="columnVisibility.end_at"
                                >Fin</TableHead
                            >
                            <TableHead v-if="columnVisibility.total"
                                >Total</TableHead
                            >
                            <TableHead v-if="columnVisibility.status"
                                >Estado</TableHead
                            >
                            <TableHead v-if="columnVisibility.created"
                                >Creada</TableHead
                            >
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
                                >Cargando...</TableCell
                            >
                        </TableRow>
                        <TableRow v-else-if="reservations.length === 0">
                            <TableCell
                                :colspan="tableColspan"
                                class="text-center py-8 text-muted-foreground"
                                >No hay reservas.</TableCell
                            >
                        </TableRow>
                        <TableRow
                            v-for="r in reservations"
                            :key="r.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(r.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedIds.includes(r.id)"
                                    @update:checked="
                                        (checked: boolean) =>
                                            toggleSelectRow(r.id, checked)
                                    "
                                />
                            </TableCell>
                            <TableCell
                                v-if="columnVisibility.code"
                                class="font-medium font-mono text-xs"
                                >{{ r.code }}</TableCell
                            >
                            <TableCell v-if="columnVisibility.court">{{
                                r.court?.name || "-"
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.customer">{{
                                customerLabel(r)
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.start_at">{{
                                formatDateTime(r.start_at)
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.end_at">{{
                                formatDateTime(r.end_at)
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.total">{{
                                formatPrice(r.total)
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.status">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium',
                                        statusMeta(r.status).badgeClass,
                                    ]"
                                >
                                    {{ statusMeta(r.status).label }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.created">{{
                                formatDateTime(r.created_at)
                            }}</TableCell>
                            <TableCell />
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <ConfirmDialog ref="confirmDialog" />
    </DashboardLayout>
</template>
