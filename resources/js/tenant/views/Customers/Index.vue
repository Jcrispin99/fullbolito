<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useCustomerStore } from "@tenant/stores/customer";
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
const customerStore = useCustomerStore();
const { customers, meta, isLoading } = storeToRefs(customerStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedCustomers = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");

type ColumnKey = "name" | "document" | "contact" | "status" | "created";

const COLUMN_STORAGE_KEY = "customers_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Nombre" },
    { key: "document", label: "Documento" },
    { key: "contact", label: "Contacto" },
    { key: "status", label: "Estado" },
    { key: "created", label: "Creado" },
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
        customers.value.length > 0 &&
        selectedCustomers.value.length === customers.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedCustomers.value = customers.value.map((s) => s.id);
    } else {
        selectedCustomers.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedCustomers.value.push(id);
    } else {
        selectedCustomers.value = selectedCustomers.value.filter(
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
            return "Clientes activos";
        case "inactive":
        case "archived":
            return "Clientes inactivos";
        case "all":
            return "Todos los clientes";
        default:
            return "Filtrar";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadCustomers(1);
};

const loadCustomers = (page = 1) => {
    selectedCustomers.value = [];
    customerStore.fetchCustomers(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadCustomers();
});

useCompanyFilterRefresh(() => loadCustomers());

const handleSearch = (value: string) => {
    search.value = value;
    loadCustomers(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        customerStore.fetchCustomers(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadCustomers(1);
    }
};

const handlePageChange = (page: number) => {
    loadCustomers(page);
};

const navigateToCreate = () => {
    router.push("/admin/customers/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/customers/${id}/edit`);
};

const handleBatchDelete = async () => {
    if (selectedCustomers.value.length === 0) return;

    confirmDialog.value?.show(
        "Eliminar clientes",
        `¿Confirmas que deseas eliminar ${selectedCustomers.value.length} clientes?`,
        async () => {
            await customerStore.deleteCustomers(selectedCustomers.value);
            loadCustomers(meta.value?.current_page || 1);
            selectedCustomers.value = [];
        },
    );
};

const exportDialogOpen = ref(false);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {
        is_customer: true,
    };
    if (search.value) filters.search = search.value;
    if (currentStatus.value) filters.status = currentStatus.value;
    if (selectedCustomers.value.length > 0) {
        filters.ids = selectedCustomers.value;
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
    loadCustomers(meta.value?.current_page || 1);
};

const handleBatchToggleActive = () => {
    if (selectedCustomers.value.length === 0) return;

    confirmDialog.value?.show(
        "Cambiar estado",
        `¿Confirmas que deseas cambiar el estado de ${selectedCustomers.value.length} clientes?`,
        async () => {
            await Promise.all(
                selectedCustomers.value.map((id) =>
                    customerStore.toggleStatus(id),
                ),
            );
            await loadCustomers(meta.value?.current_page || 1);
            selectedCustomers.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Clientes' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Clientes"
                :items-count="customers.length"
                :total-items="meta?.total || 0"
                :per-page="meta?.per_page || 15"
                :current-page="meta?.current_page || 1"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedCustomers"
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
                                Clientes activos
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Clientes inactivos
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                Todos los clientes
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
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </ModuleHeader>

            <DataView
                :view-mode="viewMode"
                :is-loading="isLoading"
                :is-empty="customers.length === 0"
                empty-message="No se encontraron clientes."
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
                                <TableHead v-if="columnVisibility.name">Nombre</TableHead>
                                <TableHead v-if="columnVisibility.document">Documento</TableHead>
                                <TableHead v-if="columnVisibility.contact">Contacto</TableHead>
                                <TableHead v-if="columnVisibility.status">Estado</TableHead>
                                <TableHead v-if="columnVisibility.created">Creado</TableHead>
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
                            <TableRow v-else-if="customers.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No se encontraron clientes.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="s in customers"
                                :key="s.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(s.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedCustomers.includes(s.id)"
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
                                            'px-2 py-1 rounded text-xs font-medium text-white',
                                            s.status === 'active'
                                                ? 'bg-green-500'
                                                : 'bg-gray-400',
                                        ]"
                                    >
                                        {{ s.status === 'active' ? 'Activo' : 'Inactivo' }}
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
                        v-for="s in customers"
                        :key="s.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(s.id)"
                    >
                        <div>
                            <h3 class="font-medium text-lg pr-8 truncate" :title="s.name">{{ s.name }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        s.status === 'active'
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ s.status === 'active' ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 text-sm mt-1">
                            <div v-if="columnVisibility.document">
                                <span class="text-xs text-muted-foreground block mb-0.5">Documento</span>
                                <div v-if="s.document_type || s.document_number">
                                    <span class="text-xs font-medium">{{ s.document_type || 'N/A' }}</span>
                                    <div class="font-mono text-muted-foreground truncate">{{ s.document_number || '-' }}</div>
                                </div>
                                <span v-else class="text-muted-foreground">-</span>
                            </div>
                            <div v-if="columnVisibility.contact">
                                <span class="text-xs text-muted-foreground block mb-0.5">Contacto</span>
                                <div class="truncate" :title="s.email || ''">{{ s.email || '-' }}</div>
                                <div class="text-xs text-muted-foreground truncate">{{ s.phone || '-' }}</div>
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
                'status',
            ]"
            title="Exportar Clientes"
        />

        <ImportDialog
            v-model:open="importDialogOpen"
            resource="partner"
            title="Importar Clientes"
            @imported="handleImported"
        />
    </DashboardLayout>
</template>
