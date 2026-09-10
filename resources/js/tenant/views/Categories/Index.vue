<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useCategoryStore } from "@tenant/stores/category";
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
const categoryStore = useCategoryStore();
const { categories, meta, isLoading } = storeToRefs(categoryStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedCategories = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");

type ColumnKey = "name" | "full_name" | "parent" | "is_active" | "created";

const COLUMN_STORAGE_KEY = "categories_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Nombre" },
    { key: "full_name", label: "Nombre completo" },
    { key: "parent", label: "Categoría principal" },
    { key: "is_active", label: "Activa" },
    { key: "created", label: "Creada" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    full_name: true,
    parent: true,
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
        categories.value.length > 0 &&
        selectedCategories.value.length === categories.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedCategories.value = categories.value.map((c) => c.id);
    } else {
        selectedCategories.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedCategories.value.push(id);
    } else {
        selectedCategories.value = selectedCategories.value.filter(
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
            return "Categorías activas";
        case "inactive":
        case "archived":
            return "Categorías inactivas";
        case "all":
            return "Todas las categorías";
        default:
            return "Filtrar";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadCategories(1);
};

const loadCategories = (page = 1) => {
    selectedCategories.value = [];
    categoryStore.fetchCategories(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadCategories();
});

const handleSearch = (value: string) => {
    search.value = value;
    loadCategories(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        categoryStore.fetchCategories(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadCategories(1);
    }
};

const handlePageChange = (page: number) => {
    loadCategories(page);
};

const navigateToCreate = () => {
    router.push("/admin/categories/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/categories/${id}/edit`);
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedCategories.value.length > 0) {
        filters.ids = selectedCategories.value;
    }
    return filters;
});

const handleBatchDelete = async () => {
    if (selectedCategories.value.length === 0) return;

    confirmDialog.value?.show(
        "Eliminar categorías",
        `¿Confirmas que deseas eliminar ${selectedCategories.value.length} categorías?`,
        async () => {
            await categoryStore.deleteCategories(selectedCategories.value);
            loadCategories(meta.value.current_page);
            selectedCategories.value = [];
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedCategories.value.length === 0) return;

    confirmDialog.value?.show(
        "Cambiar estado",
        `¿Confirmas que deseas cambiar el estado de ${selectedCategories.value.length} categorías?`,
        async () => {
            await Promise.all(
                selectedCategories.value.map((id) =>
                    categoryStore.toggleActive(id),
                ),
            );
            await loadCategories(meta.value.current_page);
            selectedCategories.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Categorías' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Categorías"
                :items-count="categories.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedCategories"
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
                                <span class="sm:hidden">Filtrar</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setFilter('active')">
                                Categorías activas
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Categorías inactivas
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                Todas las categorías
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
                        <DropdownMenuContent align="end" class="w-[180px]">
                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchToggleActive">
                                <Power
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                Cambiar estado
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
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </ModuleHeader>

            <DataView
                :view-mode="viewMode"
                :is-loading="isLoading"
                :is-empty="categories.length === 0"
                empty-message="No se encontraron categorías."
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
                                    >Nombre</TableHead
                                >
                                <TableHead v-if="columnVisibility.full_name"
                                    >Nombre completo</TableHead
                                >
                                <TableHead v-if="columnVisibility.parent"
                                    >Categoría principal</TableHead
                                >
                                <TableHead v-if="columnVisibility.is_active"
                                    >Activa</TableHead
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
                            <TableRow v-else-if="categories.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No se encontraron categorías.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="c in categories"
                                :key="c.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(c.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedCategories.includes(c.id)"
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
                                <TableCell v-if="columnVisibility.full_name">{{
                                    c.full_name || "-"
                                }}</TableCell>
                                <TableCell v-if="columnVisibility.parent">{{
                                    c.parent_id || "-"
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
                        v-for="c in categories"
                        :key="c.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(c.id)"
                    >
                        <div>
                            <h3 class="font-medium text-lg pr-4 truncate" :title="c.name">{{ c.name }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        c.is_active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ c.is_active ? "Activa" : "Inactiva" }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm mt-1">
                            <div v-if="columnVisibility.full_name" class="col-span-2">
                                <span class="text-xs text-muted-foreground block mb-0.5">Nombre completo</span>
                                <div class="truncate" :title="c.full_name || ''">{{ c.full_name || '-' }}</div>
                            </div>
                            <div v-if="columnVisibility.parent">
                                <span class="text-xs text-muted-foreground block mb-0.5">Categoría principal</span>
                                <div>{{ c.parent_id || '-' }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="category"
            :filters="exportFilters"
            :default-export-columns="[
                'name',
                'full_name',
                'description',
                'is_active',
            ]"
            export-title="Exportar Categorías"
        />
    </DashboardLayout>
</template>
