<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { usePurchaseStore } from "@tenant/stores/purchase";
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
const purchaseStore = usePurchaseStore();
const { purchases, meta, isLoading } = storeToRefs(purchaseStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | "total">(15);
const search = ref("");
const selectedPurchases = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");

type ColumnKey = "serie_correlative" | "partner" | "date" | "status" | "payment_status" | "total";

const COLUMN_STORAGE_KEY = "purchases_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "serie_correlative", label: "Series / Correlative" },
    { key: "partner", label: "Supplier" },
    { key: "date", label: "Date" },
    { key: "status", label: "Status" },
    { key: "payment_status", label: "Payment Status" },
    { key: "total", label: "Total" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    serie_correlative: true,
    partner: true,
    date: true,
    status: true,
    payment_status: true,
    total: true,
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
        purchases.value.length > 0 &&
        selectedPurchases.value.length === purchases.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedPurchases.value = purchases.value.map((p) => p.id);
    } else {
        selectedPurchases.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedPurchases.value.push(id);
    } else {
        selectedPurchases.value = selectedPurchases.value.filter(
            (pid) => pid !== id,
        );
    }
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const formatCurrency = (amount: string | number) => {
    const num = Number(amount);
    if (Number.isNaN(num)) return "-";
    return new Intl.NumberFormat("es-PE", { style: "currency", currency: "PEN" }).format(num);
};

const currentStatus = ref("all");

const filterLabel = computed(() => {
    switch (currentStatus.value) {
        case "draft":
            return "Draft Purchases";
        case "posted":
            return "Posted Purchases";
        case "all":
            return "All Purchases";
        default:
            return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadPurchases(1);
};

const loadPurchases = (page = 1) => {
    selectedPurchases.value = [];
    purchaseStore.fetchPurchases(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadPurchases();
});

useCompanyFilterRefresh(() => loadPurchases());

const handleSearch = (value: string) => {
    search.value = value;
    loadPurchases(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        purchaseStore.fetchPurchases(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadPurchases(1);
    }
};

const handlePageChange = (page: number) => {
    loadPurchases(page);
};

const navigateToCreate = () => {
    router.push("/admin/purchases/create");
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);
const ieLinesToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value && currentStatus.value !== "all") {
        filters.status = currentStatus.value;
    }
    if (selectedPurchases.value.length > 0)
        filters.ids = selectedPurchases.value;
    return filters;
});

const navigateToEdit = (id: number) => {
    router.push(`/admin/purchases/${id}/edit`);
};

const handleDelete = async (id: number) => {
    confirmDialog.value?.show(
        "Delete Purchase",
        "Are you sure you want to delete this purchase?",
        async () => {
            await purchaseStore.deletePurchase(id);
            loadPurchases(meta.value.current_page);
        },
    );
};

const handleBatchDelete = async () => {
    if (selectedPurchases.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Purchases",
        `Are you sure you want to delete ${selectedPurchases.value.length} purchases?`,
        async () => {
            await purchaseStore.deletePurchases(selectedPurchases.value);
            loadPurchases(meta.value.current_page);
            selectedPurchases.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Purchases' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Purchases"
                :items-count="purchases.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedPurchases"
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
                            <DropdownMenuItem @click="setFilter('draft')">
                                Draft Purchases
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('posted')">
                                Posted Purchases
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All Purchases
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
                            <DropdownMenuItem @click="ieToolbar?.openExport()">
                                <Download class="mr-2 h-4 w-4 text-muted-foreground" />
                                Exportar
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="ieLinesToolbar?.openExport()">
                                <Download class="mr-2 h-4 w-4 text-muted-foreground" />
                                Exportar líneas
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="ieToolbar?.supportsImport"
                                @click="ieToolbar?.openImport()"
                            >
                                <Upload class="mr-2 h-4 w-4 text-muted-foreground" />
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
                :is-empty="purchases.length === 0"
                empty-message="No purchases found."
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
                                <TableHead v-if="columnVisibility.serie_correlative"
                                    >ID / Doc
                                </TableHead>
                                <TableHead v-if="columnVisibility.partner"
                                    >Supplier</TableHead>
                                <TableHead v-if="columnVisibility.date"
                                    >Date</TableHead>
                                <TableHead v-if="columnVisibility.status"
                                    >Status</TableHead>
                                <TableHead v-if="columnVisibility.payment_status"
                                    >Payment</TableHead>
                                <TableHead class="text-right" v-if="columnVisibility.total"
                                    >Total</TableHead>
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
                            <TableRow v-else-if="purchases.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No purchases found.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="p in purchases"
                                :key="p.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(p.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedPurchases.includes(p.id)"
                                        @update:checked="
                                            (checked: boolean) =>
                                                toggleSelectRow(p.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.serie_correlative"
                                    class="font-medium whitespace-nowrap"
                                >
                                    {{ p.serie ? p.serie + '-' + p.correlative : p.correlative }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.partner" class="truncate max-w-[200px]">
                                    {{ p.partner?.trade_name || p.observation || "Unknown" }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.date" class="whitespace-nowrap">
                                    {{ formatCreated(p.date) }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.status">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium uppercase text-[10px] tracking-wider text-white',
                                            p.status === 'posted'
                                                ? 'bg-blue-500'
                                                : p.status === 'draft'
                                                ? 'bg-gray-400'
                                                : 'bg-red-500',
                                        ]"
                                    >
                                        {{ p.status }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.payment_status">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium uppercase text-[10px] tracking-wider text-white',
                                            p.payment_status === 'paid'
                                                ? 'bg-emerald-500'
                                                : p.payment_status === 'partial'
                                                ? 'bg-amber-500'
                                                : 'bg-red-500',
                                        ]"
                                    >
                                        {{ p.payment_status }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right font-medium" v-if="columnVisibility.total">
                                    {{ formatCurrency(p.total) }}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="p in purchases"
                        :key="p.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(p.id)"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-medium text-base">
                                    {{ p.serie ? p.serie + '-' + p.correlative : p.correlative }}
                                </h3>
                                <div class="text-sm text-muted-foreground truncate max-w-[200px]" :title="p.partner?.trade_name || p.observation || 'Unknown'">
                                    {{ p.partner?.trade_name || p.observation || 'Unknown' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-medium text-lg block">{{ formatCurrency(p.total) }}</span>
                                <span class="text-xs text-muted-foreground">{{ formatCreated(p.date) }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-2">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        p.status === 'posted'
                                            ? 'bg-blue-500'
                                            : p.status === 'draft'
                                            ? 'bg-gray-400'
                                            : 'bg-red-500',
                                    ]"
                                >
                                    {{ p.status }}
                                </span>
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        p.payment_status === 'paid'
                                            ? 'bg-emerald-500'
                                            : p.payment_status === 'partial'
                                            ? 'bg-amber-500'
                                            : 'bg-red-500',
                                    ]"
                                >
                                    {{ p.payment_status }}
                                </span>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="purchase"
            :filters="exportFilters"
            :default-export-columns="[
                'date',
                'serie',
                'correlative',
                'vendor_bill_number',
                'partner.name',
                'partner.document_number',
                'total',
                'status',
                'payment_status',
            ]"
            export-title="Exportar Compras"
        />

        <ImportExportToolbar
            ref="ieLinesToolbar"
            resource="purchase_line"
            :filters="exportFilters"
            :default-export-columns="[
                'productable.date',
                'productable.serie',
                'productable.correlative',
                'productable.vendor_bill_number',
                'productable.partner.name',
                'productProduct.sku',
                'productProduct.display_name',
                'uom.name',
                'quantity',
                'price',
                'tax_rate',
                'tax_amount',
                'total',
            ]"
            export-title="Exportar líneas de compra"
        />
    </DashboardLayout>
</template>
