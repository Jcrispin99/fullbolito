<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useWarehouseStore } from "@tenant/stores/warehouse";
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
const warehouseStore = useWarehouseStore();
const { warehouses, meta, isLoading } = storeToRefs(warehouseStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedWarehouses = ref<number[]>([]);

type ColumnKey = "name" | "location" | "company" | "is_active" | "created";

const COLUMN_STORAGE_KEY = "warehouses_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "location", label: "Location" },
    { key: "company", label: "Company" },
    { key: "is_active", label: "Active" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    location: true,
    company: true,
    is_active: true,
    created: true,
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
        warehouses.value.length > 0 &&
        selectedWarehouses.value.length === warehouses.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedWarehouses.value = warehouses.value.map((w) => w.id);
    } else {
        selectedWarehouses.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedWarehouses.value.push(id);
    } else {
        selectedWarehouses.value = selectedWarehouses.value.filter(
            (wid) => wid !== id,
        );
    }
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const currentStatus = ref("active");

const filterLabel = computed(() => {
    switch (currentStatus.value) {
        case "active":
            return "Active Warehouses";
        case "inactive":
        case "archived":
            return "Inactive Warehouses";
        case "all":
            return "All Warehouses";
        default:
            return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadWarehouses(1);
};

const loadWarehouses = (page = 1) => {
    selectedWarehouses.value = [];
    warehouseStore.fetchWarehouses(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadWarehouses();
});

useCompanyFilterRefresh(() => loadWarehouses());

const handleSearch = (value: string) => {
    search.value = value;
    loadWarehouses(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        warehouseStore.fetchWarehouses(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadWarehouses(1);
    }
};

const handlePageChange = (page: number) => {
    loadWarehouses(page);
};

const navigateToCreate = () => {
    router.push("/admin/warehouses/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/warehouses/${id}/edit`);
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedWarehouses.value.length > 0) {
        filters.ids = selectedWarehouses.value;
    }
    return filters;
});

const handleBatchDelete = async () => {
    if (selectedWarehouses.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Warehouses",
        `Are you sure you want to delete ${selectedWarehouses.value.length} warehouses?`,
        async () => {
            await warehouseStore.deleteWarehouses(selectedWarehouses.value);
            loadWarehouses(meta.value.current_page);
            selectedWarehouses.value = [];
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedWarehouses.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Are you sure you want to toggle active for ${selectedWarehouses.value.length} warehouses?`,
        async () => {
            await Promise.all(
                selectedWarehouses.value.map((id) =>
                    warehouseStore.toggleActive(id),
                ),
            );
            await loadWarehouses(meta.value.current_page);
            selectedWarehouses.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Warehouses' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Warehouses"
                :items-count="warehouses.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedWarehouses"
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
                                <span class="sm:hidden">Filter</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setFilter('active')">
                                Active Warehouses
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Inactive Warehouses
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All Warehouses
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
                                Actions
                                <ChevronDown class="h-4 w-4 opacity-50" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[180px]">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchToggleActive">
                                <Power
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                Toggle Active
                            </DropdownMenuItem>
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
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                @click="handleBatchDelete"
                                class="text-destructive"
                            >
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
                                <Checkbox
                                    :checked="allSelected"
                                    @update:checked="toggleSelectAll"
                                />
                            </TableHead>
                            <TableHead v-if="columnVisibility.name"
                                >Name</TableHead
                            >
                            <TableHead v-if="columnVisibility.location"
                                >Location</TableHead
                            >
                            <TableHead v-if="columnVisibility.company"
                                >Company</TableHead
                            >
                            <TableHead v-if="columnVisibility.is_active"
                                >Active</TableHead
                            >
                            <TableHead v-if="columnVisibility.created"
                                >Created</TableHead
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
                                >Loading...</TableCell
                            >
                        </TableRow>
                        <TableRow v-else-if="warehouses.length === 0">
                            <TableCell
                                :colspan="tableColspan"
                                class="text-center py-8 text-muted-foreground"
                                >No warehouses found.</TableCell
                            >
                        </TableRow>
                        <TableRow
                            v-for="w in warehouses"
                            :key="w.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(w.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedWarehouses.includes(w.id)"
                                    @update:checked="
                                        (checked: boolean) =>
                                            toggleSelectRow(w.id, checked)
                                    "
                                />
                            </TableCell>
                            <TableCell
                                v-if="columnVisibility.name"
                                class="font-medium"
                            >
                                {{ w.name }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.location">{{
                                w.location || "-"
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.company">{{
                                w.company?.business_name || "-"
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.is_active">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium text-white',
                                        w.is_active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ w.is_active ? "Active" : "Inactive" }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.created">
                                {{ formatCreated(w.created_at) }}
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
            resource="warehouse"
            :filters="exportFilters"
            :default-export-columns="[
                'name',
                'location',
                'address_line',
                'establishment_code',
                'is_active',
            ]"
            export-title="Exportar Almacenes"
        />
    </DashboardLayout>
</template>
