<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useSaleStore } from "@tenant/stores/sale";
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
const saleStore = useSaleStore();
const { sales, meta, isLoading } = storeToRefs(saleStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | "total">(15);
const search = ref("");
const selectedSales = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");
const currentStatus = ref("all");
const currentPaymentStatus = ref("all");

type ColumnKey =
    | "serie_correlative"
    | "partner"
    | "date"
    | "status"
    | "payment_status"
    | "total";

const COLUMN_STORAGE_KEY = "sales_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "serie_correlative", label: "Series / Correlative" },
    { key: "partner", label: "Customer" },
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

const visibleColumnCount = computed(
    () => Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 2 + visibleColumnCount.value);

const allSelected = computed(() => {
    return (
        sales.value.length > 0 &&
        selectedSales.value.length === sales.value.length
    );
});
const selectedSaleId = computed<number | null>(() =>
    selectedSales.value.length === 1 ? (selectedSales.value[0] ?? null) : null,
);

const statusFilterLabel = computed(() => {
    switch (currentStatus.value) {
        case "draft":
            return `Draft (${meta.value.draft_total ?? 0})`;
        case "posted":
            return `Posted (${meta.value.posted_total ?? 0})`;
        case "cancelled":
            return `Cancelled (${meta.value.cancelled_total ?? 0})`;
        case "all":
            return "All Status";
        default:
            return "Status";
    }
});

const paymentFilterLabel = computed(() => {
    switch (currentPaymentStatus.value) {
        case "paid":
            return "Paid";
        case "partial":
            return "Partial";
        case "unpaid":
            return "Unpaid";
        case "all":
            return "All Payment";
        default:
            return "Payment";
    }
});

const loadSales = (page = 1) => {
    selectedSales.value = [];
    saleStore.fetchSales(
        page,
        perPage.value,
        search.value,
        currentStatus.value,
        currentPaymentStatus.value,
    );
};

onMounted(() => {
    loadSales();
});

useCompanyFilterRefresh(() => loadSales());

const formatCreated = formatMediumDate;

const formatCurrency = (amount: string | number) => {
    const num = Number(amount);
    if (Number.isNaN(num)) return "-";
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(num);
};

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedSales.value = sales.value.map((s) => s.id);
    } else {
        selectedSales.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedSales.value.push(id);
    } else {
        selectedSales.value = selectedSales.value.filter((sid) => sid !== id);
    }
};

const handleSearch = (value: string) => {
    search.value = value;
    loadSales(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        saleStore.fetchSales(
            1,
            "total",
            search.value,
            currentStatus.value,
            currentPaymentStatus.value,
        );
        return;
    }

    perPage.value = Number(newPerPage);
    loadSales(1);
};

const handlePageChange = (page: number) => {
    loadSales(page);
};

const setStatusFilter = (status: string) => {
    currentStatus.value = status;
    loadSales(1);
};

const setPaymentFilter = (paymentStatus: string) => {
    currentPaymentStatus.value = paymentStatus;
    loadSales(1);
};

const navigateToCreate = () => {
    router.push("/admin/sales/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/sales/${id}/edit`);
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);
const ieLinesToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value && currentStatus.value !== "all") {
        filters.status = currentStatus.value;
    }
    if (
        currentPaymentStatus.value &&
        currentPaymentStatus.value !== "all"
    ) {
        filters.payment_status = currentPaymentStatus.value;
    }
    if (selectedSales.value.length > 0) filters.ids = selectedSales.value;
    return filters;
});

const handleDelete = async (id: number) => {
    confirmDialog.value?.show(
        "Delete Sale",
        "Are you sure you want to delete this sale?",
        async () => {
            await saleStore.deleteSale(id);
            loadSales(meta.value.current_page);
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Sales' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Sales"
                :items-count="sales.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedSales"
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
                                    statusFilterLabel
                                }}</span>
                                <span class="sm:hidden">Status</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setStatusFilter('draft')">
                                Draft
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setStatusFilter('posted')"
                            >
                                Posted
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setStatusFilter('cancelled')"
                            >
                                Cancelled
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setStatusFilter('all')">
                                All
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

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
                                    paymentFilterLabel
                                }}</span>
                                <span class="sm:hidden">Payment</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                @click="setPaymentFilter('unpaid')"
                            >
                                Unpaid
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setPaymentFilter('partial')"
                            >
                                Partial
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setPaymentFilter('paid')">
                                Paid
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setPaymentFilter('all')">
                                All
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
                                :disabled="selectedSales.length !== 1"
                                class="text-destructive"
                                @click="
                                    selectedSaleId !== null
                                        ? handleDelete(selectedSaleId)
                                        : null
                                "
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
                :is-empty="sales.length === 0"
                empty-message="No sales found."
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
                                <TableHead
                                    v-if="columnVisibility.serie_correlative"
                                    >ID / Doc</TableHead
                                >
                                <TableHead v-if="columnVisibility.partner"
                                    >Customer</TableHead
                                >
                                <TableHead v-if="columnVisibility.date"
                                    >Date</TableHead
                                >
                                <TableHead v-if="columnVisibility.status"
                                    >Status</TableHead
                                >
                                <TableHead
                                    v-if="columnVisibility.payment_status"
                                    >Payment</TableHead
                                >
                                <TableHead
                                    v-if="columnVisibility.total"
                                    class="text-right"
                                    >Total</TableHead
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
                                >
                                    Loading...
                                </TableCell>
                            </TableRow>
                            <TableRow v-else-if="sales.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                >
                                    No sales found.
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="s in sales"
                                :key="s.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(s.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedSales.includes(s.id)"
                                        @update:checked="
                                            (checked: boolean) =>
                                                toggleSelectRow(s.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.serie_correlative"
                                    class="font-medium whitespace-nowrap"
                                >
                                    {{
                                        s.serie
                                            ? s.serie + "-" + s.correlative
                                            : s.correlative
                                    }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.partner"
                                    class="truncate max-w-[200px]"
                                >
                                    {{
                                        s.partner?.name ||
                                        s.partner?.display_name ||
                                        s.notes ||
                                        "Unknown"
                                    }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.date"
                                    class="whitespace-nowrap"
                                >
                                    {{ formatCreated(s.date || s.created_at) }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.status">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium uppercase text-[10px] tracking-wider text-white',
                                            s.status === 'posted'
                                                ? 'bg-blue-500'
                                                : s.status === 'draft'
                                                  ? 'bg-gray-400'
                                                  : 'bg-red-500',
                                        ]"
                                    >
                                        {{ s.status }}
                                    </span>
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.payment_status"
                                >
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium uppercase text-[10px] tracking-wider text-white',
                                            s.payment_status === 'paid'
                                                ? 'bg-emerald-500'
                                                : s.payment_status === 'partial'
                                                  ? 'bg-amber-500'
                                                  : 'bg-red-500',
                                        ]"
                                    >
                                        {{ s.payment_status }}
                                    </span>
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.total"
                                    class="text-right font-medium"
                                >
                                    {{ formatCurrency(s.total) }}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="s in sales"
                        :key="s.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(s.id)"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-medium text-base">
                                    {{
                                        s.serie
                                            ? s.serie + "-" + s.correlative
                                            : s.correlative
                                    }}
                                </h3>
                                <div
                                    class="text-sm text-muted-foreground truncate max-w-[200px]"
                                    :title="
                                        s.partner?.name ||
                                        s.partner?.display_name ||
                                        s.notes ||
                                        'Unknown'
                                    "
                                >
                                    {{
                                        s.partner?.name ||
                                        s.partner?.display_name ||
                                        s.notes ||
                                        "Unknown"
                                    }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-medium text-lg block">{{
                                    formatCurrency(s.total)
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    formatCreated(s.date || s.created_at)
                                }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-2">
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                    s.status === 'posted'
                                        ? 'bg-blue-500'
                                        : s.status === 'draft'
                                          ? 'bg-gray-400'
                                          : 'bg-red-500',
                                ]"
                            >
                                {{ s.status }}
                            </span>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                    s.payment_status === 'paid'
                                        ? 'bg-emerald-500'
                                        : s.payment_status === 'partial'
                                          ? 'bg-amber-500'
                                          : 'bg-red-500',
                                ]"
                            >
                                {{ s.payment_status }}
                            </span>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="sale"
            :filters="exportFilters"
            :default-export-columns="[
                'date',
                'document_number',
                'partner.name',
                'partner.document_number',
                'subtotal',
                'tax_amount',
                'total',
                'status',
                'payment_status',
            ]"
            export-title="Exportar Ventas"
        />

        <ImportExportToolbar
            ref="ieLinesToolbar"
            resource="sale_line"
            :filters="exportFilters"
            :default-export-columns="[
                'productable.date',
                'productable.serie',
                'productable.correlative',
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
            export-title="Exportar líneas de venta"
        />
    </DashboardLayout>
</template>
