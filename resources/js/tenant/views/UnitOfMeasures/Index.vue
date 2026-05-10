<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useUnitOfMeasureStore } from "@tenant/stores/unitOfMeasure";
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
import {
    Trash2,
    ChevronDown,
    Power,
    Filter,
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
const store = useUnitOfMeasureStore();
const { units, meta, isLoading } = storeToRefs(store);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedIds = ref<number[]>([]);

type ColumnKey = "name" | "symbol" | "family" | "factor" | "base_unit" | "is_active";

const COLUMN_STORAGE_KEY = "uom_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "symbol", label: "Symbol" },
    { key: "family", label: "Family" },
    { key: "factor", label: "Factor" },
    { key: "base_unit", label: "Base Unit" },
    { key: "is_active", label: "Active" },
];

const columnVisibility = ref<Record<ColumnKey, boolean>>({
    name: true,
    symbol: true,
    family: true,
    factor: true,
    base_unit: true,
    is_active: true,
});

const visibleColumnCount = computed(() =>
    Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 2 + visibleColumnCount.value);

const allSelected = computed(() =>
    units.value.length > 0 && selectedIds.value.length === units.value.length,
);

const toggleSelectAll = (checked: boolean) => {
    selectedIds.value = checked ? units.value.map((u) => u.id) : [];
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter((uid) => uid !== id);
    }
};

const currentStatus = ref("active");

const filterLabel = computed(() => {
    switch (currentStatus.value) {
        case "active": return "Active Units";
        case "inactive": return "Inactive Units";
        case "all": return "All Units";
        default: return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadUnits(1);
};

const loadUnits = (page = 1) => {
    selectedIds.value = [];
    store.fetchUnits(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => loadUnits());

const handleSearch = (value: string) => {
    search.value = value;
    loadUnits(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        store.fetchUnits(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadUnits(1);
    }
};

const handlePageChange = (page: number) => loadUnits(page);

const navigateToCreate = () => router.push("/admin/unit-of-measures/create");
const navigateToEdit = (id: number) => router.push(`/admin/unit-of-measures/${id}/edit`);

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedIds.value.length > 0) filters.ids = selectedIds.value;
    return filters;
});

const handleBatchDelete = async () => {
    if (selectedIds.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Units",
        `Are you sure you want to delete ${selectedIds.value.length} unit(s)?`,
        async () => {
            await store.deleteUnits(selectedIds.value);
            loadUnits(meta.value.current_page);
            selectedIds.value = [];
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedIds.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Toggle active status for ${selectedIds.value.length} unit(s)?`,
        async () => {
            await Promise.all(selectedIds.value.map((id) => store.toggleActive(id)));
            await loadUnits(meta.value.current_page);
            selectedIds.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Units of Measure' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Units of Measure"
                :items-count="units.length"
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
                            <Button variant="outline" class="h-9 gap-1 whitespace-nowrap">
                                <Filter class="h-3.5 w-3.5 mr-1 text-muted-foreground" />
                                <span class="hidden sm:inline">{{ filterLabel }}</span>
                                <span class="sm:hidden">Filter</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setFilter('active')">
                                Active Units
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Inactive Units
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All Units
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>

                <template #actions>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="h-9 gap-1">
                                Actions
                                <ChevronDown class="h-4 w-4 opacity-50" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[180px]">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchToggleActive">
                                <Power class="mr-2 h-4 w-4 text-muted-foreground" />
                                Toggle Active
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="ieToolbar?.openExport()">
                                <Download class="mr-2 h-4 w-4 text-muted-foreground" />
                                Exportar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="ieToolbar?.supportsImport"
                                @click="ieToolbar?.openImport()"
                            >
                                <Upload class="mr-2 h-4 w-4 text-muted-foreground" />
                                Importar
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchDelete" class="text-destructive">
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete
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
                                <Checkbox :checked="allSelected" @update:checked="toggleSelectAll" />
                            </TableHead>
                            <TableHead v-if="columnVisibility.name">Name</TableHead>
                            <TableHead v-if="columnVisibility.symbol">Symbol</TableHead>
                            <TableHead v-if="columnVisibility.family">Family</TableHead>
                            <TableHead v-if="columnVisibility.factor">Factor</TableHead>
                            <TableHead v-if="columnVisibility.base_unit">Base Unit</TableHead>
                            <TableHead v-if="columnVisibility.is_active">Active</TableHead>
                            <TableColumnSettingsHead
                                v-model="columnVisibility"
                                :columns="columnOptions"
                                :storage-key="COLUMN_STORAGE_KEY"
                            />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="isLoading">
                            <TableCell :colspan="tableColspan" class="text-center py-8">
                                Loading...
                            </TableCell>
                        </TableRow>
                        <TableRow v-else-if="units.length === 0">
                            <TableCell :colspan="tableColspan" class="text-center py-8 text-muted-foreground">
                                No units of measure found.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="u in units"
                            :key="u.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(u.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedIds.includes(u.id)"
                                    @update:checked="(checked: boolean) => toggleSelectRow(u.id, checked)"
                                />
                            </TableCell>
                            <TableCell v-if="columnVisibility.name" class="font-medium">
                                {{ u.name }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.symbol">
                                {{ u.symbol ?? "-" }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.family">
                                {{ u.family ?? "-" }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.factor">
                                {{ u.factor }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.base_unit">
                                {{ u.base_unit?.name ?? "-" }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.is_active">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium text-white',
                                        u.is_active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ u.is_active ? "Active" : "Inactive" }}
                                </span>
                            </TableCell>
                            <TableCell />
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="unit_of_measure"
            :filters="exportFilters"
            :default-export-columns="[
                'name',
                'symbol',
                'family',
                'factor',
                'is_active',
            ]"
            export-title="Exportar Unidades de Medida"
        />
    </DashboardLayout>
</template>
