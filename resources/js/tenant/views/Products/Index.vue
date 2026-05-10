<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useProductTemplateStore } from "@tenant/stores/productTemplate";
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
    Package,
    Wrench,
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
const store = useProductTemplateStore();
const { products, meta, isLoading } = storeToRefs(store);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedIds = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("grid");

type ColumnKey = "name" | "category" | "price" | "sku" | "type" | "is_active" | "created";

const COLUMN_STORAGE_KEY = "products_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "category", label: "Category" },
    { key: "price", label: "Price" },
    { key: "sku", label: "SKU" },
    { key: "type", label: "Type" },
    { key: "is_active", label: "Active" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    category: true,
    price: true,
    sku: true,
    type: true,
    is_active: true,
    created: false,
};

const columnVisibility = ref<Record<ColumnKey, boolean>>({ ...defaultColumnVisibility });

const visibleColumnCount = computed(() =>
    Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 2 + visibleColumnCount.value);

const allSelected = computed(() =>
    products.value.length > 0 && selectedIds.value.length === products.value.length,
);

const toggleSelectAll = (checked: boolean) => {
    selectedIds.value = checked ? products.value.map((p) => p.id) : [];
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter((pid) => pid !== id);
    }
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const formatPrice = (price: string | number | null) => {
    if (price === null || price === undefined || price === "") return "-";
    return Number(price).toLocaleString("es", { style: "currency", currency: "PEN" });
};

const currentStatus = ref("active");

const filterLabel = computed(() => {
    switch (currentStatus.value) {
        case "active": return "Active Products";
        case "inactive": return "Inactive Products";
        case "all": return "All Products";
        default: return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadProducts(1);
};

const loadProducts = (page = 1) => {
    selectedIds.value = [];
    store.fetchProducts(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => loadProducts());

const handleSearch = (value: string) => {
    search.value = value;
    loadProducts(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        store.fetchProducts(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadProducts(1);
    }
};

const handlePageChange = (page: number) => loadProducts(page);

const navigateToCreate = () => router.push("/admin/products/create");
const navigateToEdit = (id: number) => router.push(`/admin/products/${id}/edit`);

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedIds.value.length > 0) filters.ids = selectedIds.value;
    return filters;
});

const variantToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(
    null,
);

const variantExportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedIds.value.length > 0) {
        filters.product_template_ids = selectedIds.value;
    }
    return filters;
});

const handleBatchDelete = async () => {
    if (selectedIds.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Products",
        `Are you sure you want to delete ${selectedIds.value.length} product(s)? This will also delete all their variants.`,
        async () => {
            try {
                await store.deleteProducts(selectedIds.value);
                loadProducts(meta.value.current_page);
                selectedIds.value = [];
            } catch (err: any) {
                const msg = err?.response?.data?.message || "Some products could not be deleted.";
                alert(msg);
            }
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedIds.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Toggle active status for ${selectedIds.value.length} product(s)?`,
        async () => {
            await Promise.all(
                selectedIds.value.map((id) => store.toggleActive(id)),
            );
            await loadProducts(meta.value.current_page);
            selectedIds.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Products' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Products"
                :items-count="products.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedIds"
                v-model:view-mode="viewMode"
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
                            <DropdownMenuItem @click="setFilter('active')">Active Products</DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">Inactive Products</DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">All Products</DropdownMenuItem>
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
                                Exportar productos
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="variantToolbar?.openExport()">
                                <Download class="mr-2 h-4 w-4 text-muted-foreground" />
                                Exportar variantes
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

            <DataView
                :view-mode="viewMode"
                :is-loading="isLoading"
                :is-empty="products.length === 0"
                empty-message="No products found."
            >
                <template #table>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[50px]">
                                    <Checkbox :checked="allSelected" @update:checked="toggleSelectAll" />
                                </TableHead>
                                <TableHead v-if="columnVisibility.name">Name</TableHead>
                                <TableHead v-if="columnVisibility.category">Category</TableHead>
                                <TableHead v-if="columnVisibility.price">Price</TableHead>
                                <TableHead v-if="columnVisibility.sku">SKU</TableHead>
                                <TableHead v-if="columnVisibility.type">Type</TableHead>
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
                            <TableRow v-else-if="products.length === 0">
                                <TableCell :colspan="tableColspan" class="text-center py-8 text-muted-foreground">
                                    No products found.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="p in products"
                                :key="p.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(p.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedIds.includes(p.id)"
                                        @update:checked="(checked: boolean) => toggleSelectRow(p.id, checked)"
                                    />
                                </TableCell>
                                <TableCell v-if="columnVisibility.name">
                                    <div class="flex items-center gap-3">
                                        <div
                                            v-if="p.image"
                                            class="h-9 w-9 rounded-md overflow-hidden border bg-muted shrink-0"
                                        >
                                            <img :src="p.image" :alt="p.name" class="h-full w-full object-cover" />
                                        </div>
                                        <div
                                            v-else
                                            class="h-9 w-9 rounded-md border bg-muted flex items-center justify-center shrink-0"
                                        >
                                            <Package class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <div class="font-medium">{{ p.name }}</div>
                                            <div v-if="p.description" class="text-xs text-muted-foreground truncate max-w-[200px]">
                                                {{ p.description }}
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell v-if="columnVisibility.category">
                                    {{ p.category?.name ?? "-" }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.price">
                                    {{ formatPrice(p.price) }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.sku">
                                    <span class="font-mono text-xs">{{ p.sku ?? "-" }}</span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.type">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium text-white',
                                            p.is_service
                                                ? 'bg-blue-500'
                                                : 'bg-amber-500',
                                        ]"
                                    >
                                        <Wrench v-if="p.is_service" class="h-3 w-3" />
                                        <Package v-else class="h-3 w-3" />
                                        {{ p.is_service ? "Service" : "Product" }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.is_active">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium text-white',
                                            p.is_active
                                                ? 'bg-green-500'
                                                : 'bg-gray-400',
                                        ]"
                                    >
                                        {{ p.is_active ? "Active" : "Inactive" }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.created">
                                    {{ formatCreated(p.created_at) }}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="p in products"
                        :key="p.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(p.id)"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                v-if="p.image"
                                class="h-12 w-12 rounded-md overflow-hidden border bg-muted shrink-0"
                            >
                                <img :src="p.image" :alt="p.name" class="h-full w-full object-cover" />
                            </div>
                            <div
                                v-else
                                class="h-12 w-12 rounded-md border bg-muted flex items-center justify-center shrink-0"
                            >
                                <Package class="h-5 w-5 text-muted-foreground" />
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <h3 class="font-medium text-base truncate" :title="p.name">{{ p.name }}</h3>
                                <div v-if="p.description" class="text-xs text-muted-foreground line-clamp-2 mt-0.5" :title="p.description">
                                    {{ p.description }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-1">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                    p.is_active
                                        ? 'bg-green-500'
                                        : 'bg-gray-400',
                                ]"
                            >
                                {{ p.is_active ? "Active" : "Inactive" }}
                            </span>
                            <span
                                v-if="columnVisibility.type"
                                :class="[
                                    'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                    p.is_service
                                        ? 'bg-blue-500'
                                        : 'bg-amber-500',
                                ]"
                            >
                                <Wrench v-if="p.is_service" class="h-3 w-3" />
                                <Package v-else class="h-3 w-3" />
                                {{ p.is_service ? "Service" : "Product" }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm mt-1">
                            <div v-if="columnVisibility.price">
                                <span class="text-xs text-muted-foreground block mb-0.5">Price</span>
                                <div class="font-medium">{{ formatPrice(p.price) }}</div>
                            </div>
                            <div v-if="columnVisibility.sku">
                                <span class="text-xs text-muted-foreground block mb-0.5">SKU</span>
                                <div class="font-mono text-xs truncate" :title="p.sku ?? ''">{{ p.sku ?? '-' }}</div>
                            </div>
                            <div v-if="columnVisibility.category" class="col-span-2">
                                <span class="text-xs text-muted-foreground block mb-0.5">Category</span>
                                <div class="truncate" :title="p.category?.name ?? ''">{{ p.category?.name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="product"
            :filters="exportFilters"
            :default-export-columns="[
                'name',
                'sku',
                'barcode',
                'category.full_name',
                'price',
                'uom.symbol',
                'is_pos_visible',
                'tracks_inventory',
                'is_active',
            ]"
            export-title="Exportar Productos"
        />

        <ImportExportToolbar
            ref="variantToolbar"
            resource="product_variant"
            :filters="variantExportFilters"
            :default-export-columns="[
                'sku',
                'barcode',
                'product.name',
                'product.category.full_name',
                'price',
                'cost_price',
                'attributeValues.*.value',
            ]"
            export-title="Exportar Variantes"
        />
    </DashboardLayout>
</template>
