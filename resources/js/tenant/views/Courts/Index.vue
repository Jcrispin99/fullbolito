<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useCourtStore, type CourtFormOptions } from "@tenant/stores/court";
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
import { Trash2, ChevronDown, Power, Filter } from "lucide-vue-next";
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
import { SPORT_OPTIONS, sportLabel, surfaceLabel } from "./sports";

const router = useRouter();
const courtStore = useCourtStore();
const { courts, meta, isLoading } = storeToRefs(courtStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedCourts = ref<number[]>([]);
const currentStatus = ref("active");
const currentSport = ref("");

const formOptions = ref<CourtFormOptions>({ companies: [] });

type ColumnKey =
    | "name"
    | "code"
    | "sport"
    | "surface"
    | "company"
    | "price"
    | "capacity"
    | "is_active"
    | "created";

const COLUMN_STORAGE_KEY = "courts_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Nombre" },
    { key: "code", label: "Código" },
    { key: "sport", label: "Deporte" },
    { key: "surface", label: "Superficie" },
    { key: "company", label: "Sede" },
    { key: "price", label: "Precio" },
    { key: "capacity", label: "Capacidad" },
    { key: "is_active", label: "Activa" },
    { key: "created", label: "Creada" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    code: true,
    sport: true,
    surface: true,
    company: true,
    price: true,
    capacity: false,
    is_active: true,
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
        courts.value.length > 0 &&
        selectedCourts.value.length === courts.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedCourts.value = courts.value.map((c) => c.id);
    } else {
        selectedCourts.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedCourts.value.push(id);
    } else {
        selectedCourts.value = selectedCourts.value.filter((cid) => cid !== id);
    }
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const formatPrice = (value: number | null | undefined) => {
    if (value == null) return "-";
    const num = typeof value === "string" ? parseFloat(value) : value;
    if (Number.isNaN(num)) return "-";
    return `S/ ${num.toFixed(2)}`;
};

const statusFilterLabel = computed(() => {
    switch (currentStatus.value) {
        case "active":
            return "Activas";
        case "inactive":
        case "archived":
            return "Inactivas";
        case "all":
            return "Todas";
        default:
            return "Filtro";
    }
});

const sportFilterLabel = computed(() => {
    if (!currentSport.value) return "Todos los deportes";
    return sportLabel(currentSport.value);
});

const setStatusFilter = (status: string) => {
    currentStatus.value = status;
    loadCourts(1);
};

const setSportFilter = (sport: string) => {
    currentSport.value = sport;
    loadCourts(1);
};

const loadCourts = (page = 1) => {
    selectedCourts.value = [];
    courtStore.fetchCourts(
        page,
        perPage.value,
        search.value,
        currentStatus.value,
        currentSport.value,
    );
};

onMounted(async () => {
    loadCourts();
    formOptions.value = await courtStore.fetchFormOptions();
});

useCompanyFilterRefresh(() => loadCourts());

const handleSearch = (value: string) => {
    search.value = value;
    loadCourts(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        courtStore.fetchCourts(
            1,
            "total",
            search.value,
            currentStatus.value,
            currentSport.value,
        );
    } else {
        perPage.value = Number(newPerPage);
        loadCourts(1);
    }
};

const handlePageChange = (page: number) => {
    loadCourts(page);
};

const navigateToCreate = () => {
    router.push("/admin/courts/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/courts/${id}/edit`);
};

const handleBatchDelete = () => {
    if (selectedCourts.value.length === 0) return;
    confirmDialog.value?.show(
        "Eliminar canchas",
        `¿Seguro que deseas eliminar ${selectedCourts.value.length} cancha(s)?`,
        async () => {
            await courtStore.deleteCourts(selectedCourts.value);
            loadCourts(meta.value.current_page);
            selectedCourts.value = [];
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedCourts.value.length === 0) return;
    confirmDialog.value?.show(
        "Cambiar estado",
        `¿Cambiar estado activo de ${selectedCourts.value.length} cancha(s)?`,
        async () => {
            await Promise.all(
                selectedCourts.value.map((id) => courtStore.toggleActive(id)),
            );
            await loadCourts(meta.value.current_page);
            selectedCourts.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Canchas' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Canchas"
                :items-count="courts.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedCourts"
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
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                @click="setStatusFilter('active')"
                            >
                                Activas
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setStatusFilter('inactive')"
                            >
                                Inactivas
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setStatusFilter('all')">
                                Todas
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                class="h-9 gap-1 whitespace-nowrap"
                            >
                                <span class="hidden sm:inline">{{
                                    sportFilterLabel
                                }}</span>
                                <span class="sm:hidden">Deporte</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setSportFilter('')">
                                Todos los deportes
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="opt in SPORT_OPTIONS"
                                :key="opt.value"
                                @click="setSportFilter(opt.value)"
                            >
                                {{ opt.label }}
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
                        <DropdownMenuContent align="end" class="w-[200px]">
                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchToggleActive">
                                <Power
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                Cambiar estado
                            </DropdownMenuItem>
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

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-[50px]">
                                <Checkbox
                                    :checked="allSelected"
                                    @update:checked="toggleSelectAll"
                                />
                            </TableHead>
                            <TableHead v-if="columnVisibility.name"
                                >Nombre</TableHead
                            >
                            <TableHead v-if="columnVisibility.code"
                                >Código</TableHead
                            >
                            <TableHead v-if="columnVisibility.sport"
                                >Deporte</TableHead
                            >
                            <TableHead v-if="columnVisibility.surface"
                                >Superficie</TableHead
                            >
                            <TableHead v-if="columnVisibility.company"
                                >Sede</TableHead
                            >
                            <TableHead v-if="columnVisibility.price"
                                >Precio</TableHead
                            >
                            <TableHead v-if="columnVisibility.capacity"
                                >Capacidad</TableHead
                            >
                            <TableHead v-if="columnVisibility.is_active"
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
                        <TableRow v-else-if="courts.length === 0">
                            <TableCell
                                :colspan="tableColspan"
                                class="text-center py-8 text-muted-foreground"
                                >No hay canchas.</TableCell
                            >
                        </TableRow>
                        <TableRow
                            v-for="c in courts"
                            :key="c.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(c.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedCourts.includes(c.id)"
                                    @update:checked="
                                        (checked: boolean) =>
                                            toggleSelectRow(c.id, checked)
                                    "
                                />
                            </TableCell>
                            <TableCell
                                v-if="columnVisibility.name"
                                class="font-medium"
                                >{{ c.name }}</TableCell
                            >
                            <TableCell v-if="columnVisibility.code">{{
                                c.code || "-"
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.sport">{{
                                sportLabel(c.sport)
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.surface">{{
                                surfaceLabel(c.surface)
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.company">{{
                                c.company?.trade_name ||
                                c.company?.business_name ||
                                "-"
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.price">{{
                                formatPrice(c.price)
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.capacity">{{
                                c.capacity ?? "-"
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.is_active">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium text-white',
                                        c.is_active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ c.is_active ? "Activa" : "Inactiva" }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.created">{{
                                formatCreated(c.created_at)
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
