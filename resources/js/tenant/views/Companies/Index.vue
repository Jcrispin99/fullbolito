<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useCompanyStore } from "@tenant/stores/company";
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
const companyStore = useCompanyStore();
const { companies, meta, isLoading } = storeToRefs(companyStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | "total">(15);
const search = ref("");
const selectedCompanies = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");

type ColumnKey = "business" | "ruc" | "email" | "phone" | "branch" | "active" | "created";

const COLUMN_STORAGE_KEY = "companies_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "business", label: "Business" },
    { key: "ruc", label: "RUC" },
    { key: "email", label: "Email" },
    { key: "phone", label: "Phone" },
    { key: "branch", label: "Branch Code" },
    { key: "active", label: "Active" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    business: true,
    ruc: true,
    email: true,
    phone: true,
    branch: true,
    active: true,
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
        companies.value.length > 0 &&
        selectedCompanies.value.length === companies.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedCompanies.value = companies.value.map((c: any) => c.id);
    } else {
        selectedCompanies.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedCompanies.value.push(id);
    } else {
        selectedCompanies.value = selectedCompanies.value.filter(
            (cid) => cid !== id,
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
            return "Active Companies";
        case "inactive":
        case "archived":
            return "Inactive Companies";
        case "all":
            return "All Companies";
        default:
            return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadCompanies(1);
};

const loadCompanies = (page = 1) => {
    selectedCompanies.value = [];
    companyStore.fetchCompanies(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadCompanies();
});

const handleSearch = (value: string) => {
    search.value = value;
    loadCompanies(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        companyStore.fetchCompanies(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadCompanies(1);
    }
};

const handlePageChange = (page: number) => {
    loadCompanies(page);
};

const navigateToCreate = () => {
    router.push("/admin/companies/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/companies/${id}/edit`);
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedCompanies.value.length > 0) {
        filters.ids = selectedCompanies.value;
    }
    return filters;
});

const handleToggleActive = async (id: number) => {
    await companyStore.toggleActive(id);
};

const handleDelete = async (id: number) => {
    confirmDialog.value?.show(
        "Delete Company",
        "Are you sure you want to delete this company?",
        async () => {
            await companyStore.deleteCompany(id);
            loadCompanies(meta.value.current_page);
        },
    );
};

const handleBatchDelete = async () => {
    if (selectedCompanies.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Companies",
        `Are you sure you want to delete ${selectedCompanies.value.length} companies?`,
        async () => {
            await companyStore.deleteCompanies(selectedCompanies.value);
            loadCompanies(meta.value.current_page);
            selectedCompanies.value = [];
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedCompanies.value.length === 0) {
        return;
    }

    confirmDialog.value?.show(
        "Toggle Active",
        `Are you sure you want to toggle active for ${selectedCompanies.value.length} companies?`,
        async () => {
            await Promise.all(
                selectedCompanies.value.map((id) =>
                    companyStore.toggleActive(id),
                ),
            );
            await loadCompanies(meta.value.current_page);
            selectedCompanies.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Companies' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Companies"
                :items-count="companies.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedCompanies"
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
                                Active Companies
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Inactive Companies
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All Companies
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

            <DataView
                :view-mode="viewMode"
                :is-loading="isLoading"
                :is-empty="companies.length === 0"
                empty-message="No companies found."
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
                                <TableHead v-if="columnVisibility.business"
                                    >Business</TableHead
                                >
                                <TableHead v-if="columnVisibility.ruc"
                                    >RUC</TableHead
                                >
                                <TableHead v-if="columnVisibility.email"
                                    >Email</TableHead
                                >
                                <TableHead v-if="columnVisibility.phone"
                                    >Phone</TableHead
                                >
                                <TableHead v-if="columnVisibility.branch"
                                    >Branch Code</TableHead
                                >
                                <TableHead v-if="columnVisibility.active"
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
                            <TableRow v-else-if="companies.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No companies found.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="c in companies"
                                :key="c.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(c.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedCompanies.includes(c.id)"
                                        @update:checked="
                                            (checked) =>
                                                toggleSelectRow(c.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.business"
                                    class="font-medium"
                                >
                                    {{ c.business_name }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.ruc">{{
                                    c.ruc
                                }}</TableCell>
                                <TableCell v-if="columnVisibility.email">{{
                                    c.email || "-"
                                }}</TableCell>
                                <TableCell v-if="columnVisibility.phone">{{
                                    c.phone || "-"
                                }}</TableCell>
                                <TableCell v-if="columnVisibility.branch">{{
                                    c.branch_code || "-"
                                }}</TableCell>
                                <TableCell v-if="columnVisibility.active">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium text-white',
                                            c.active
                                                ? 'bg-green-500'
                                                : 'bg-gray-400',
                                        ]"
                                    >
                                        {{ c.active ? "Active" : "Inactive" }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.created">
                                    {{ formatCreated(c.created_at) }}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="c in companies"
                        :key="c.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(c.id)"
                    >
                        <div>
                            <h3 class="font-medium text-lg pr-4 truncate" :title="c.business_name">{{ c.business_name }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        c.active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ c.active ? "Active" : "Inactive" }}
                                </span>
                                <span v-if="columnVisibility.ruc" class="text-xs font-mono text-muted-foreground">{{ c.ruc }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm mt-1">
                            <div v-if="columnVisibility.email">
                                <span class="text-xs text-muted-foreground block mb-0.5">Email</span>
                                <div class="truncate" :title="c.email || ''">{{ c.email || '-' }}</div>
                            </div>
                            <div v-if="columnVisibility.phone">
                                <span class="text-xs text-muted-foreground block mb-0.5">Phone</span>
                                <div>{{ c.phone || '-' }}</div>
                            </div>
                            <div v-if="columnVisibility.branch" class="col-span-2">
                                <span class="text-xs text-muted-foreground block mb-0.5">Branch Code</span>
                                <div>{{ c.branch_code || '-' }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="company"
            :filters="exportFilters"
            :default-export-columns="[
                'business_name',
                'trade_name',
                'ruc',
                'address',
                'phone',
                'email',
                'is_main',
                'is_active',
            ]"
            export-title="Exportar Empresas"
        />
    </DashboardLayout>
</template>
