<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useAttributeStore } from "@tenant/stores/attribute";
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
const attributeStore = useAttributeStore();
const { attributes, meta, isLoading } = storeToRefs(attributeStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | "total">(15);
const search = ref("");
const selectedAttributes = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");

type ColumnKey = "name" | "is_active" | "created";

const COLUMN_STORAGE_KEY = "attributes_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "is_active", label: "Active" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
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
        attributes.value.length > 0 &&
        selectedAttributes.value.length === attributes.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedAttributes.value = attributes.value.map((a) => a.id);
    } else {
        selectedAttributes.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedAttributes.value.push(id);
    } else {
        selectedAttributes.value = selectedAttributes.value.filter(
            (aid) => aid !== id,
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
            return "Active Attributes";
        case "inactive":
        case "archived":
            return "Inactive Attributes";
        case "all":
            return "All Attributes";
        default:
            return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadAttributes(1);
};

const loadAttributes = async (page = 1) => {
    selectedAttributes.value = [];
    await attributeStore.fetchAttributes(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadAttributes();
});

const handleSearch = (value: string) => {
    search.value = value;
    loadAttributes(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        attributeStore.fetchAttributes(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadAttributes(1);
    }
};

const handlePageChange = (page: number) => {
    loadAttributes(page);
};

const navigateToCreate = () => {
    router.push("/admin/attributes/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/attributes/${id}/edit`);
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedAttributes.value.length > 0)
        filters.ids = selectedAttributes.value;
    return filters;
});

const handleBatchDelete = async () => {
    if (selectedAttributes.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Attributes",
        `Are you sure you want to delete ${selectedAttributes.value.length} attributes?`,
        async () => {
            await attributeStore.deleteAttributes(selectedAttributes.value);
            loadAttributes(meta.value.current_page);
            selectedAttributes.value = [];
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedAttributes.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Are you sure you want to toggle active for ${selectedAttributes.value.length} attributes?`,
        async () => {
            await Promise.all(
                selectedAttributes.value.map((id) =>
                    attributeStore.toggleActive(id),
                ),
            );
            await loadAttributes(meta.value.current_page);
            selectedAttributes.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Attributes' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Attributes"
                :items-count="attributes.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedAttributes"
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
                                Active Attributes
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Inactive Attributes
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All Attributes
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
                :is-empty="attributes.length === 0"
                empty-message="No attributes found."
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
                            <TableRow v-else-if="attributes.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No attributes found.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="a in attributes"
                                :key="a.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(a.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedAttributes.includes(a.id)"
                                        @update:checked="
                                            (checked: boolean) =>
                                                toggleSelectRow(a.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.name"
                                    class="font-medium"
                                >
                                    {{ a.name }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.is_active">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium text-white',
                                            a.is_active
                                                ? 'bg-green-500'
                                                : 'bg-gray-400',
                                        ]"
                                    >
                                        {{ a.is_active ? "Active" : "Inactive" }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.created">
                                    {{ formatCreated(a.created_at) }}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="a in attributes"
                        :key="a.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col justify-between gap-4 transition-all"
                        @click="navigateToEdit(a.id)"
                    >
                        <h3 class="font-medium text-lg leading-tight">{{ a.name }}</h3>
                        
                        <div class="flex items-center">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                    a.is_active
                                        ? 'bg-green-500'
                                        : 'bg-gray-400',
                                ]"
                            >
                                {{ a.is_active ? "Active" : "Inactive" }}
                            </span>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="attribute"
            :filters="exportFilters"
            :default-export-columns="[
                'name',
                'attribute_values_count',
                'attributeValues.*.value',
                'is_active',
            ]"
            export-title="Exportar Atributos"
        />
    </DashboardLayout>
</template>
