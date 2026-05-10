<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useBillingCredentialStore } from "@tenant/stores/billingCredential";
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
const billingStore = useBillingCredentialStore();
const { billingCredentials, meta, isLoading } = storeToRefs(billingStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedIds = ref<number[]>([]);

type ColumnKey = "name" | "sol_user" | "production" | "is_active" | "created";

const COLUMN_STORAGE_KEY = "billing_credentials_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Nombre" },
    { key: "sol_user", label: "Usuario SOL" },
    { key: "production", label: "Entorno" },
    { key: "is_active", label: "Activo" },
    { key: "created", label: "Creado" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    sol_user: true,
    production: true,
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
        billingCredentials.value.length > 0 &&
        selectedIds.value.length === billingCredentials.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    selectedIds.value = checked
        ? billingCredentials.value.map((c) => c.id)
        : [];
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter((cid) => cid !== id);
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
            return "Activas";
        case "inactive":
            return "Inactivas";
        case "all":
            return "Todas";
        default:
            return "Filtrar";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    load(1);
};

const load = (page = 1) => {
    selectedIds.value = [];
    billingStore.fetchBillingCredentials(
        page,
        perPage.value,
        search.value,
        currentStatus.value,
    );
};

onMounted(() => {
    load();
});

const handleSearch = (value: string) => {
    search.value = value;
    load(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        billingStore.fetchBillingCredentials(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        load(1);
    }
};

const handlePageChange = (page: number) => {
    load(page);
};

const navigateToCreate = () => {
    router.push("/admin/billing-credentials/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/billing-credentials/${id}/edit`);
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedIds.value.length > 0) filters.ids = selectedIds.value;
    return filters;
});

const handleBatchToggleActive = () => {
    if (selectedIds.value.length === 0) return;

    confirmDialog.value?.show(
        "Cambiar estado",
        `¿Estás seguro de cambiar el estado de ${selectedIds.value.length} credencial(es)?`,
        async () => {
            await Promise.all(
                selectedIds.value.map((id) => billingStore.toggleActive(id)),
            );
            await load(meta.value.current_page);
            selectedIds.value = [];
        },
    );
};

const handleBatchDelete = async () => {
    if (selectedIds.value.length === 0) return;

    confirmDialog.value?.show(
        "Eliminar credenciales",
        `¿Estás seguro de eliminar ${selectedIds.value.length} credencial(es)? Esta acción no se puede deshacer.`,
        async () => {
            await Promise.all(
                selectedIds.value.map((id) =>
                    billingStore.deleteBillingCredential(id),
                ),
            );
            load(meta.value.current_page);
            selectedIds.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Facturación Electrónica' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Facturación Electrónica"
                :items-count="billingCredentials.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedIds"
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
                                Activas
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Inactivas
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                Todas
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
                        <DropdownMenuContent align="end" class="w-[200px]">
                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchToggleActive">
                                <Power
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                Activar/Desactivar
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
                            <TableHead v-if="columnVisibility.name">Nombre</TableHead>
                            <TableHead v-if="columnVisibility.sol_user">Usuario SOL</TableHead>
                            <TableHead v-if="columnVisibility.production">Entorno</TableHead>
                            <TableHead v-if="columnVisibility.is_active">Activo</TableHead>
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
                        <TableRow v-else-if="billingCredentials.length === 0">
                            <TableCell
                                :colspan="tableColspan"
                                class="text-center py-8 text-muted-foreground"
                                >Sin credenciales registradas.</TableCell
                            >
                        </TableRow>
                        <TableRow
                            v-for="c in billingCredentials"
                            :key="c.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(c.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedIds.includes(c.id)"
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
                            <TableCell v-if="columnVisibility.sol_user">
                                <code class="text-xs">{{ c.sol_user }}</code>
                            </TableCell>
                            <TableCell v-if="columnVisibility.production">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium text-white',
                                        c.production
                                            ? 'bg-blue-600'
                                            : 'bg-amber-500',
                                    ]"
                                >
                                    {{ c.production ? "Producción" : "Sandbox" }}
                                </span>
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
            </div>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="billing_credential"
            :filters="exportFilters"
            :default-export-columns="[
                'name',
                'sol_user',
                'production',
                'is_active',
            ]"
            export-title="Exportar Credenciales de Facturación"
        />
    </DashboardLayout>
</template>
