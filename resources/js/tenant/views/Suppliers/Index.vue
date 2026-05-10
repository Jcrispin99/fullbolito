<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useSupplierStore } from "@tenant/stores/supplier";
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
    Trash2,
    ChevronDown,
    Power,
    Filter,
    Download,
    Upload,
} from "lucide-vue-next";
import ExportDialog from "@tenant/components/ImportExport/ExportDialog.vue";
import ImportDialog from "@tenant/components/ImportExport/ImportDialog.vue";
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
const supplierStore = useSupplierStore();
const { suppliers, meta, isLoading } = storeToRefs(supplierStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedSuppliers = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");

type ColumnKey = "name" | "document" | "contact" | "status" | "created";

const COLUMN_STORAGE_KEY = "suppliers_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "document", label: "Document" },
    { key: "contact", label: "Contact" },
    { key: "status", label: "Status" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    document: true,
    contact: true,
    status: true,
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
        suppliers.value.length > 0 &&
        selectedSuppliers.value.length === suppliers.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedSuppliers.value = suppliers.value.map((s) => s.id);
    } else {
        selectedSuppliers.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedSuppliers.value.push(id);
    } else {
        selectedSuppliers.value = selectedSuppliers.value.filter(
            (sid) => sid !== id,
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
            return "Active Suppliers";
        case "inactive":
        case "archived":
            return "Inactive Suppliers";
        case "all":
            return "All Suppliers";
        default:
            return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadSuppliers(1);
};

const loadSuppliers = (page = 1) => {
    selectedSuppliers.value = [];
    supplierStore.fetchSuppliers(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadSuppliers();
});

useCompanyFilterRefresh(() => loadSuppliers());

const handleSearch = (value: string) => {
    search.value = value;
    loadSuppliers(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        supplierStore.fetchSuppliers(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadSuppliers(1);
    }
};

const handlePageChange = (page: number) => {
    loadSuppliers(page);
};

const navigateToCreate = () => {
    router.push("/admin/suppliers/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/suppliers/${id}/edit`);
};

const handleBatchDelete = async () => {
    if (selectedSuppliers.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Suppliers",
        `Are you sure you want to delete ${selectedSuppliers.value.length} suppliers?`,
        async () => {
            await supplierStore.deleteSuppliers(selectedSuppliers.value);
            loadSuppliers(meta.value?.current_page || 1);
            selectedSuppliers.value = [];
        },
    );
};

const exportDialogOpen = ref(false);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {
        is_supplier: true,
    };
    if (search.value) filters.search = search.value;
    if (currentStatus.value) filters.status = currentStatus.value;
    if (selectedSuppliers.value.length > 0) {
        filters.ids = selectedSuppliers.value;
    }
    return filters;
});

const openExportDialog = () => {
    exportDialogOpen.value = true;
};

const importDialogOpen = ref(false);

const openImportDialog = () => {
    importDialogOpen.value = true;
};

const handleImported = () => {
    loadSuppliers(meta.value?.current_page || 1);
};

const handleBatchToggleActive = () => {
    if (selectedSuppliers.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Are you sure you want to toggle active for ${selectedSuppliers.value.length} suppliers?`,
        async () => {
            await Promise.all(
                selectedSuppliers.value.map((id) =>
                    supplierStore.toggleStatus(id),
                ),
            );
            await loadSuppliers(meta.value?.current_page || 1);
            selectedSuppliers.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Suppliers' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Suppliers"
                :items-count="suppliers.length"
                :total-items="meta?.total || 0"
                :per-page="meta?.per_page || 15"
                :current-page="meta?.current_page || 1"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedSuppliers"
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
                                <span class="sm:hidden">Filter</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setFilter('active')">
                                Active Suppliers
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Inactive Suppliers
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All Suppliers
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
                            <DropdownMenuItem @click="openExportDialog">
                                <Download
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                Exportar
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="openImportDialog">
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

            <DataView
                :view-mode="viewMode"
                :is-loading="isLoading"
                :is-empty="suppliers.length === 0"
                empty-message="No suppliers found."
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
                                <TableHead v-if="columnVisibility.name">Name</TableHead>
                                <TableHead v-if="columnVisibility.document">Document</TableHead>
                                <TableHead v-if="columnVisibility.contact">Contact</TableHead>
                                <TableHead v-if="columnVisibility.status">Status</TableHead>
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
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8"
                                    >Loading...</TableCell
                                >
                            </TableRow>
                            <TableRow v-else-if="suppliers.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No suppliers found.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="s in suppliers"
                                :key="s.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(s.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedSuppliers.includes(s.id)"
                                        @update:checked="
                                            (checked: boolean) =>
                                                toggleSelectRow(s.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.name"
                                    class="font-medium"
                                >
                                    {{ s.name }}
                                    <div v-if="s.provider_category" class="text-xs text-muted-foreground font-normal mt-0.5">
                                        {{ s.provider_category }}
                                    </div>
                                </TableCell>
                                <TableCell v-if="columnVisibility.document">
                                    <div v-if="s.document_type || s.document_number">
                                        <span class="text-xs font-medium">{{ s.document_type || 'N/A' }}</span>
                                        <div class="text-sm font-mono text-muted-foreground">{{ s.document_number || '-' }}</div>
                                    </div>
                                    <span v-else>-</span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.contact">
                                    <div class="text-sm">{{ s.email || '-' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ s.phone || '-' }}</div>
                                </TableCell>
                                <TableCell v-if="columnVisibility.status">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs',
                                            s.status === 'active'
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-800',
                                        ]"
                                    >
                                        {{ s.status === 'active' ? 'Active' : 'Inactive' }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.created">
                                    {{ formatCreated(s.created_at) }}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="s in suppliers"
                        :key="s.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(s.id)"
                    >
                        <div>
                            <h3 class="font-medium text-lg pr-4 truncate" :title="s.name">{{ s.name }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider',
                                        s.status === 'active'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-800',
                                    ]"
                                >
                                    {{ s.status === 'active' ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="s.provider_category" class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-[10px] font-medium uppercase tracking-wider">
                                    {{ s.provider_category }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm mt-1">
                            <div v-if="columnVisibility.document">
                                <span class="text-xs text-muted-foreground block mb-0.5">Document</span>
                                <div class="font-medium" v-if="s.document_type || s.document_number">
                                    <span class="text-xs">{{ s.document_type || 'N/A' }}</span>
                                    <div class="font-mono text-xs">{{ s.document_number || '-' }}</div>
                                </div>
                                <span v-else class="text-muted-foreground">-</span>
                            </div>
                            <div v-if="columnVisibility.contact">
                                <span class="text-xs text-muted-foreground block mb-0.5">Contact</span>
                                <div class="truncate" :title="s.email || ''">{{ s.email || '-' }}</div>
                                <div class="text-muted-foreground">{{ s.phone || '-' }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ExportDialog
            v-model:open="exportDialogOpen"
            resource="partner"
            :filters="exportFilters"
            :default-columns="[
                'name',
                'document_type',
                'document_number',
                'email',
                'phone',
                'tax_id',
                'payment_terms',
                'status',
            ]"
            title="Exportar Proveedores"
        />

        <ImportDialog
            v-model:open="importDialogOpen"
            resource="partner"
            title="Importar Proveedores"
            @imported="handleImported"
        />
    </DashboardLayout>
</template>
