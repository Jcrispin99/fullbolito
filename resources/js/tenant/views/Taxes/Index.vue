<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useTaxStore } from "@tenant/stores/tax";
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
const taxStore = useTaxStore();
const { taxes, meta, isLoading } = storeToRefs(taxStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedTaxes = ref<number[]>([]);

type ColumnKey = "name" | "tax_type" | "rate_percent" | "is_price_inclusive" | "is_default" | "is_active" | "created";

const COLUMN_STORAGE_KEY = "taxes_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "tax_type", label: "Type" },
    { key: "rate_percent", label: "Rate %" },
    { key: "is_price_inclusive", label: "Inclusive" },
    { key: "is_default", label: "Default" },
    { key: "is_active", label: "Active" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    tax_type: true,
    rate_percent: true,
    is_price_inclusive: true,
    is_default: true,
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

const allSelected = computed(() =>
    taxes.value.length > 0 && selectedTaxes.value.length === taxes.value.length,
);

const toggleSelectAll = (checked: boolean) => {
    selectedTaxes.value = checked ? taxes.value.map((t) => t.id) : [];
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedTaxes.value.push(id);
    } else {
        selectedTaxes.value = selectedTaxes.value.filter((tid) => tid !== id);
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
        case "active": return "Active Taxes";
        case "inactive": return "Inactive Taxes";
        case "all": return "All Taxes";
        default: return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadTaxes(1);
};

const loadTaxes = (page = 1) => {
    selectedTaxes.value = [];
    taxStore.fetchTaxes(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => loadTaxes());

const handleSearch = (value: string) => {
    search.value = value;
    loadTaxes(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        taxStore.fetchTaxes(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadTaxes(1);
    }
};

const handlePageChange = (page: number) => loadTaxes(page);

const navigateToCreate = () => router.push("/admin/taxes/create");
const navigateToEdit = (id: number) => router.push(`/admin/taxes/${id}/edit`);

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedTaxes.value.length > 0) {
        filters.ids = selectedTaxes.value;
    }
    return filters;
});

const handleBatchDelete = async () => {
    if (selectedTaxes.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Taxes",
        `Are you sure you want to delete ${selectedTaxes.value.length} tax(es)?`,
        async () => {
            try {
                await taxStore.deleteTaxes(selectedTaxes.value);
                loadTaxes(meta.value.current_page);
                selectedTaxes.value = [];
            } catch (err: any) {
                const msg = err?.response?.data?.message || "Some taxes could not be deleted.";
                alert(msg);
            }
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedTaxes.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Are you sure you want to toggle active for ${selectedTaxes.value.length} tax(es)?`,
        async () => {
            await Promise.all(
                selectedTaxes.value.map((id) => taxStore.toggleActive(id)),
            );
            await loadTaxes(meta.value.current_page);
            selectedTaxes.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Taxes' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Taxes"
                :items-count="taxes.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedTaxes"
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
                            <DropdownMenuItem @click="setFilter('active')">Active Taxes</DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">Inactive Taxes</DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">All Taxes</DropdownMenuItem>
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
                            <TableHead v-if="columnVisibility.tax_type">Type</TableHead>
                            <TableHead v-if="columnVisibility.rate_percent">Rate %</TableHead>
                            <TableHead v-if="columnVisibility.is_price_inclusive">Inclusive</TableHead>
                            <TableHead v-if="columnVisibility.is_default">Default</TableHead>
                            <TableHead v-if="columnVisibility.is_active">Active</TableHead>
                            <TableHead v-if="columnVisibility.created">Created</TableHead>
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
                        <TableRow v-else-if="taxes.length === 0">
                            <TableCell :colspan="tableColspan" class="text-center py-8 text-muted-foreground">
                                No taxes found.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="t in taxes"
                            :key="t.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(t.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedTaxes.includes(t.id)"
                                    @update:checked="(checked: boolean) => toggleSelectRow(t.id, checked)"
                                />
                            </TableCell>
                            <TableCell v-if="columnVisibility.name" class="font-medium">
                                <div>{{ t.name }}</div>
                                <div v-if="t.description" class="text-xs text-muted-foreground mt-0.5">
                                    {{ t.description }}
                                </div>
                            </TableCell>
                            <TableCell v-if="columnVisibility.tax_type">
                                <span class="px-2 py-1 rounded text-xs bg-blue-500 text-white font-medium">
                                    {{ t.tax_type }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.rate_percent">
                                {{ t.rate_percent }}%
                            </TableCell>
                            <TableCell v-if="columnVisibility.is_price_inclusive">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium text-white',
                                        t.is_price_inclusive
                                            ? 'bg-purple-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ t.is_price_inclusive ? "Inclusive" : "Exclusive" }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.is_default">
                                <span
                                    v-if="t.is_default"
                                    class="px-2 py-1 rounded text-xs bg-amber-500 text-white font-medium"
                                >
                                    Default
                                </span>
                                <span v-else class="text-muted-foreground text-xs">—</span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.is_active">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium text-white',
                                        t.is_active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ t.is_active ? "Active" : "Inactive" }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.created">
                                {{ formatCreated(t.created_at) }}
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
            resource="tax"
            :filters="exportFilters"
            :default-export-columns="[
                'name',
                'tax_type',
                'rate_percent',
                'is_price_inclusive',
                'is_active',
            ]"
            export-title="Exportar Impuestos"
        />
    </DashboardLayout>
</template>
