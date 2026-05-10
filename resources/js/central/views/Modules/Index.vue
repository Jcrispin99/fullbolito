<script setup lang="ts">
import { onMounted, ref, computed } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useModuleStore } from "@/central/stores/module";
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
} from "lucide-vue-next";
import DashboardLayout from "@/central/layouts/DashboardLayout.vue";
import ModuleHeader from "@/central/components/ModuleHeader.vue";
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
const moduleStore = useModuleStore();
const { modules, meta, isLoading } = storeToRefs(moduleStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(20);
const search = ref("");
const selectedModules = ref<number[]>([]);

type ColumnKey = "label" | "key" | "addon_price" | "is_addon" | "status";

const COLUMN_STORAGE_KEY = "modules_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "label", label: "Nombre" },
    { key: "key", label: "Clave" },
    { key: "addon_price", label: "Precio addon" },
    { key: "is_addon", label: "Tipo" },
    { key: "status", label: "Estado" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    label: true,
    key: true,
    addon_price: true,
    is_addon: true,
    status: true,
};

const columnVisibility = ref<Record<ColumnKey, boolean>>({
    ...defaultColumnVisibility,
});

const visibleColumnCount = computed(
    () => Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 2 + visibleColumnCount.value);

const allSelected = computed(
    () =>
        modules.value.length > 0 &&
        selectedModules.value.length === modules.value.length,
);

const toggleSelectAll = (checked: boolean) => {
    selectedModules.value = checked ? modules.value.map((m: any) => m.id) : [];
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedModules.value.push(id);
    } else {
        selectedModules.value = selectedModules.value.filter((mid) => mid !== id);
    }
};

const currentStatus = ref("active");

const filterLabel = computed(() => {
    switch (currentStatus.value) {
        case "active":
            return "Módulos activos";
        case "archived":
            return "Módulos archivados";
        case "all":
            return "Todos los módulos";
        default:
            return "Filtrar";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadModules(1);
};

const loadModules = (page = 1) => {
    selectedModules.value = [];
    moduleStore.fetchModules(
        page,
        perPage.value,
        search.value,
        currentStatus.value,
    );
};

onMounted(() => loadModules());

const handleSearch = (value: string) => {
    search.value = value;
    loadModules(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        moduleStore.fetchModules(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadModules(1);
    }
};

const handlePageChange = (page: number) => loadModules(page);

const navigateToCreate = () => router.push("/modules/create");
const navigateToEdit = (id: number) => router.push(`/modules/${id}/edit`);

const handleBatchDelete = () => {
    if (selectedModules.value.length === 0) return;
    confirmDialog.value?.show(
        "Eliminar módulos",
        `¿Eliminar ${selectedModules.value.length} módulos?`,
        async () => {
            await moduleStore.deleteModules(selectedModules.value);
            loadModules(meta.value.current_page);
        },
    );
};

const handleBatchToggleStatus = () => {
    if (selectedModules.value.length === 0) return;
    confirmDialog.value?.show(
        "Cambiar estado",
        `¿Cambiar el estado de ${selectedModules.value.length} módulos?`,
        async () => {
            await Promise.all(
                selectedModules.value.map((id) => moduleStore.toggleStatus(id)),
            );
            await loadModules(meta.value.current_page);
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Módulos' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Módulos"
                :items-count="modules.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedModules"
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
                                <span class="sm:hidden">Filtrar</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setFilter('active')">Activos</DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('archived')">Archivados</DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">Todos</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>

                <template #actions>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="h-9 gap-1">
                                Acciones
                                <ChevronDown class="h-4 w-4 opacity-50" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[180px]">
                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchToggleStatus">
                                <Power class="mr-2 h-4 w-4 text-muted-foreground" />
                                Cambiar estado
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchDelete" class="text-destructive">
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
                                <Checkbox :checked="allSelected" @update:checked="toggleSelectAll" />
                            </TableHead>
                            <TableHead v-if="columnVisibility.label">Nombre</TableHead>
                            <TableHead v-if="columnVisibility.key">Clave</TableHead>
                            <TableHead v-if="columnVisibility.addon_price">Precio addon</TableHead>
                            <TableHead v-if="columnVisibility.is_addon">Tipo</TableHead>
                            <TableHead v-if="columnVisibility.status">Estado</TableHead>
                            <TableColumnSettingsHead
                                v-model="columnVisibility"
                                :columns="columnOptions"
                                :storage-key="COLUMN_STORAGE_KEY"
                            />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="isLoading">
                            <TableCell :colspan="tableColspan" class="text-center py-8">Cargando...</TableCell>
                        </TableRow>
                        <TableRow v-else-if="modules.length === 0">
                            <TableCell :colspan="tableColspan" class="text-center py-8 text-muted-foreground">
                                No se encontraron módulos.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="module in modules"
                            :key="module.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(module.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedModules.includes(module.id)"
                                    @update:checked="(checked) => toggleSelectRow(module.id, checked)"
                                />
                            </TableCell>
                            <TableCell v-if="columnVisibility.label" class="font-medium">{{ module.label }}</TableCell>
                            <TableCell v-if="columnVisibility.key">
                                <code class="text-xs bg-muted px-1.5 py-0.5 rounded">{{ module.key }}</code>
                            </TableCell>
                            <TableCell v-if="columnVisibility.addon_price">
                                <span v-if="module.addon_price > 0">${{ module.addon_price }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.is_addon">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs',
                                        module.is_addon
                                            ? 'bg-blue-100 text-blue-800'
                                            : 'bg-gray-100 text-gray-800',
                                    ]"
                                >
                                    {{ module.is_addon ? "Addon" : "Core" }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.status">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs',
                                        module.is_active
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-800',
                                    ]"
                                >
                                    {{ module.is_active ? "Activo" : "Inactivo" }}
                                </span>
                            </TableCell>
                            <TableCell />
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <ConfirmDialog ref="confirmDialog" />
    </DashboardLayout>
</template>
