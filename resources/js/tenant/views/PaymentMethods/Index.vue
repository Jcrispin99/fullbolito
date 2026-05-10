<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { usePaymentMethodStore } from "@tenant/stores/paymentMethod";
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
const paymentMethodStore = usePaymentMethodStore();
const { paymentMethods, meta, isLoading } = storeToRefs(paymentMethodStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedPaymentMethods = ref<number[]>([]);

type ColumnKey = "name" | "is_active" | "created";

const COLUMN_STORAGE_KEY = "payment_methods_table_columns";

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
        paymentMethods.value.length > 0 &&
        selectedPaymentMethods.value.length === paymentMethods.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedPaymentMethods.value = paymentMethods.value.map((c) => c.id);
    } else {
        selectedPaymentMethods.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedPaymentMethods.value.push(id);
    } else {
        selectedPaymentMethods.value = selectedPaymentMethods.value.filter(
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
            return "Active PaymentMethods";
        case "inactive":
        case "archived":
            return "Inactive PaymentMethods";
        case "all":
            return "All PaymentMethods";
        default:
            return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadPaymentMethods(1);
};

const loadPaymentMethods = (page = 1) => {
    selectedPaymentMethods.value = [];
    paymentMethodStore.fetchPaymentMethods(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadPaymentMethods();
});

const handleSearch = (value: string) => {
    search.value = value;
    loadPaymentMethods(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        paymentMethodStore.fetchPaymentMethods(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadPaymentMethods(1);
    }
};

const handlePageChange = (page: number) => {
    loadPaymentMethods(page);
};

const navigateToCreate = () => {
    router.push("/admin/payment-methods/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/payment-methods/${id}/edit`);
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedPaymentMethods.value.length > 0) {
        filters.ids = selectedPaymentMethods.value;
    }
    return filters;
});

const handleBatchDelete = async () => {
    if (selectedPaymentMethods.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete PaymentMethods",
        `Are you sure you want to delete ${selectedPaymentMethods.value.length} paymentMethods?`,
        async () => {
            await paymentMethodStore.deletePaymentMethods(selectedPaymentMethods.value);
            loadPaymentMethods(meta.value.current_page);
            selectedPaymentMethods.value = [];
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedPaymentMethods.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Are you sure you want to toggle active for ${selectedPaymentMethods.value.length} paymentMethods?`,
        async () => {
            await Promise.all(
                selectedPaymentMethods.value.map((id) =>
                    paymentMethodStore.toggleActive(id),
                ),
            );
            await loadPaymentMethods(meta.value.current_page);
            selectedPaymentMethods.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Payment Methods' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="PaymentMethods"
                :items-count="paymentMethods.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedPaymentMethods"
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
                                Active PaymentMethods
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Inactive PaymentMethods
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All PaymentMethods
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
                        <TableRow v-else-if="paymentMethods.length === 0">
                            <TableCell
                                :colspan="tableColspan"
                                class="text-center py-8 text-muted-foreground"
                                >No paymentMethods found.</TableCell
                            >
                        </TableRow>
                        <TableRow
                            v-for="c in paymentMethods"
                            :key="c.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(c.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedPaymentMethods.includes(c.id)"
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
                            <TableCell v-if="columnVisibility.is_active">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium text-white',
                                        c.is_active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ c.is_active ? "Active" : "Inactive" }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.created">
                                {{ formatCreated(c.created_at) }}
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
            resource="payment_method"
            :filters="exportFilters"
            :default-export-columns="['name', 'is_active']"
            export-title="Exportar Métodos de Pago"
        />
    </DashboardLayout>
</template>
