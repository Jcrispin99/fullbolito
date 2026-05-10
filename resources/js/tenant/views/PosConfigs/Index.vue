<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { usePosConfigStore } from "@tenant/stores/posConfig";
import { useCompanyFilterRefresh } from "@/composables/useCompanyFilterRefresh";
import { formatMediumDate } from "@tenant/lib/datetime";
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
    Play,
    Monitor,
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
const posConfigStore = usePosConfigStore();
const { posConfigs, meta, isLoading } = storeToRefs(posConfigStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedPosConfigs = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("grid");

type ColumnKey =
    | "name"
    | "warehouse"
    | "apply_tax"
    | "prices_include_tax"
    | "is_active"
    | "created";

const COLUMN_STORAGE_KEY = "pos_configs_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "warehouse", label: "Warehouse" },
    { key: "apply_tax", label: "Apply Tax" },
    { key: "prices_include_tax", label: "Prices Incl. Tax" },
    { key: "is_active", label: "Active" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    warehouse: true,
    apply_tax: true,
    prices_include_tax: true,
    is_active: true,
    created: true,
};

const columnVisibility = ref<Record<ColumnKey, boolean>>({
    ...defaultColumnVisibility,
});

const visibleColumnCount = computed(
    () => Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 3 + visibleColumnCount.value);

const allSelected = computed(() => {
    return (
        posConfigs.value.length > 0 &&
        selectedPosConfigs.value.length === posConfigs.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedPosConfigs.value = posConfigs.value.map((c) => c.id);
    } else {
        selectedPosConfigs.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedPosConfigs.value.push(id);
    } else {
        selectedPosConfigs.value = selectedPosConfigs.value.filter(
            (cid) => cid !== id,
        );
    }
};

const formatCreated = formatMediumDate;

const currentStatus = ref("active");

const filterLabel = computed(() => {
    switch (currentStatus.value) {
        case "active":
            return "Active POS Configs";
        case "inactive":
        case "archived":
            return "Inactive POS Configs";
        case "all":
            return "All POS Configs";
        default:
            return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadPosConfigs(1);
};

const loadPosConfigs = (page = 1) => {
    selectedPosConfigs.value = [];
    posConfigStore.fetchPosConfigs(
        page,
        perPage.value,
        search.value,
        currentStatus.value,
    );
};

onMounted(() => {
    loadPosConfigs();
});

useCompanyFilterRefresh(() => loadPosConfigs());

const handleSearch = (value: string) => {
    search.value = value;
    loadPosConfigs(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        posConfigStore.fetchPosConfigs(
            1,
            "total",
            search.value,
            currentStatus.value,
        );
    } else {
        perPage.value = Number(newPerPage);
        loadPosConfigs(1);
    }
};

const handlePageChange = (page: number) => {
    loadPosConfigs(page);
};

// Intentionally left empty since Form logic is out of scope as requested
const navigateToCreate = () => {
    router.push("/admin/pos-configs/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/pos-configs/${id}/edit`);
};

const openPosSession = (id: number) => {
    router.push({ name: "PosOpenSession", params: { configId: id } });
};

const openPosTerminal = (id: number) => {
    router.push({ name: "PosTerminal", params: { configId: id } });
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedPosConfigs.value.length > 0) {
        filters.ids = selectedPosConfigs.value;
    }
    return filters;
});

const handlePosAction = (config: {
    id: number;
    is_active: boolean;
    has_active_session: boolean;
}) => {
    if (!config.is_active) return;

    if (config.has_active_session) {
        openPosTerminal(config.id);
        return;
    }

    openPosSession(config.id);
};

const handleBatchDelete = async () => {
    if (selectedPosConfigs.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete POS Configs",
        `Are you sure you want to delete ${selectedPosConfigs.value.length} pos configs?`,
        async () => {
            await posConfigStore.deletePosConfigs(selectedPosConfigs.value);
            loadPosConfigs(meta.value.current_page);
            selectedPosConfigs.value = [];
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedPosConfigs.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Are you sure you want to toggle active for ${selectedPosConfigs.value.length} pos configs?`,
        async () => {
            await Promise.all(
                selectedPosConfigs.value.map((id) =>
                    posConfigStore.toggleActive(id),
                ),
            );
            loadPosConfigs(meta.value.current_page);
            selectedPosConfigs.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'POS Configs' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="POS Configs"
                :items-count="posConfigs.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedPosConfigs"
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
                                Active POS Configs
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Inactive POS Configs
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All POS Configs
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
                :is-empty="posConfigs.length === 0"
                empty-message="No pos configs found."
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
                                <TableHead v-if="columnVisibility.name"
                                    >Name</TableHead
                                >
                                <TableHead v-if="columnVisibility.warehouse"
                                    >Warehouse</TableHead
                                >
                                <TableHead v-if="columnVisibility.apply_tax"
                                    >Apply Tax</TableHead
                                >
                                <TableHead
                                    v-if="columnVisibility.prices_include_tax"
                                    >Prices Incl. Tax</TableHead
                                >
                                <TableHead v-if="columnVisibility.is_active"
                                    >Active</TableHead
                                >
                                <TableHead v-if="columnVisibility.created"
                                    >Created</TableHead
                                >
                                <TableHead class="w-[160px]">Caja</TableHead>
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
                            <TableRow v-else-if="posConfigs.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No pos configs found.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="c in posConfigs"
                                :key="c.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(c.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="
                                            selectedPosConfigs.includes(c.id)
                                        "
                                        @update:checked="
                                            (checked: boolean) =>
                                                toggleSelectRow(c.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.name"
                                    class="font-medium"
                                >
                                    {{ c.name }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.warehouse">
                                    {{ c.warehouse?.name || "-" }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.apply_tax">
                                    {{ c.apply_tax ? "Yes" : "No" }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.prices_include_tax"
                                >
                                    {{ c.prices_include_tax ? "Yes" : "No" }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.is_active">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium text-white',
                                            c.is_active
                                                ? 'bg-green-500'
                                                : 'bg-gray-400',
                                        ]"
                                    >
                                        {{
                                            c.is_active ? "Active" : "Inactive"
                                        }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.created">
                                    {{ formatCreated(c.created_at) }}
                                </TableCell>
                                <TableCell @click.stop>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="gap-2"
                                        :disabled="!c.is_active"
                                        @click.stop="handlePosAction(c)"
                                    >
                                        <component
                                            :is="
                                                c.has_active_session
                                                    ? Monitor
                                                    : Play
                                            "
                                            class="h-4 w-4"
                                        />
                                        {{
                                            c.has_active_session
                                                ? "Ir a caja"
                                                : "Abrir caja"
                                        }}
                                    </Button>
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="c in posConfigs"
                        :key="c.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(c.id)"
                    >
                        <div>
                            <h3
                                class="font-medium text-lg pr-4 truncate"
                                :title="c.name"
                            >
                                {{ c.name }}
                            </h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        c.is_active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ c.is_active ? "Active" : "Inactive" }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm mt-1">
                            <div
                                v-if="columnVisibility.warehouse"
                                class="col-span-2"
                            >
                                <span
                                    class="text-xs text-muted-foreground block mb-0.5"
                                    >Warehouse</span
                                >
                                <div
                                    class="truncate"
                                    :title="c.warehouse?.name || ''"
                                >
                                    {{ c.warehouse?.name || "-" }}
                                </div>
                            </div>
                            <div v-if="columnVisibility.apply_tax">
                                <span
                                    class="text-xs text-muted-foreground block mb-0.5"
                                    >Apply Tax</span
                                >
                                <div>{{ c.apply_tax ? "Yes" : "No" }}</div>
                            </div>
                            <div v-if="columnVisibility.prices_include_tax">
                                <span
                                    class="text-xs text-muted-foreground block mb-0.5"
                                    >Prices Incl. Tax</span
                                >
                                <div>
                                    {{ c.prices_include_tax ? "Yes" : "No" }}
                                </div>
                            </div>
                        </div>

                        <div class="pt-2" @click.stop>
                            <Button
                                class="w-full gap-2"
                                variant="outline"
                                :disabled="!c.is_active"
                                @click.stop="handlePosAction(c)"
                            >
                                <component
                                    :is="c.has_active_session ? Monitor : Play"
                                    class="h-4 w-4"
                                />
                                {{
                                    c.has_active_session
                                        ? "Ir a caja"
                                        : "Abrir caja"
                                }}
                            </Button>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="pos_config"
            :filters="exportFilters"
            :default-export-columns="[
                'name',
                'company.business_name',
                'warehouse.name',
                'tax.name',
                'is_active',
            ]"
            export-title="Exportar Configuraciones POS"
        />
    </DashboardLayout>
</template>
