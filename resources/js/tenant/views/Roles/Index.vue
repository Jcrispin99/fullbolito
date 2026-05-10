<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useRoleStore } from "@tenant/stores/role";
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
import { Trash2, ChevronDown, Filter } from "lucide-vue-next";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import { toast } from "vue-sonner";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import TableColumnSettingsHead from "@/components/TableColumnSettingsHead.vue";
import type { Role } from "@/types/models";

const router = useRouter();
const roleStore = useRoleStore();
const { roles, meta, isLoading } = storeToRefs(roleStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | "total">(15);
const search = ref("");
const selectedRoles = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");
const currentType = ref<"all" | "system" | "custom">("all");

type ColumnKey = "name" | "type" | "permissions" | "users" | "created";

const COLUMN_STORAGE_KEY = "roles_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Nombre" },
    { key: "type", label: "Tipo" },
    { key: "permissions", label: "Permisos" },
    { key: "users", label: "Usuarios" },
    { key: "created", label: "Creado" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    type: true,
    permissions: true,
    users: true,
    created: false,
};

const columnVisibility = ref<Record<ColumnKey, boolean>>({
    ...defaultColumnVisibility,
});

const visibleColumnCount = computed(() =>
    Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 2 + visibleColumnCount.value);

const filteredRoles = computed(() => {
    if (currentType.value === "all") return roles.value;
    if (currentType.value === "system")
        return roles.value.filter((r: Role) => r.is_system);
    return roles.value.filter((r: Role) => !r.is_system);
});

const selectableRoles = computed(() =>
    filteredRoles.value.filter((r: Role) => !r.is_system),
);

const allSelected = computed(() => {
    return (
        selectableRoles.value.length > 0 &&
        selectedRoles.value.length === selectableRoles.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedRoles.value = selectableRoles.value.map((r: any) => r.id);
    } else {
        selectedRoles.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedRoles.value.push(id);
    } else {
        selectedRoles.value = selectedRoles.value.filter((rid) => rid !== id);
    }
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const filterLabel = computed(() => {
    switch (currentType.value) {
        case "system":
            return "Roles del sistema";
        case "custom":
            return "Roles personalizados";
        default:
            return "Todos los roles";
    }
});

const setFilter = (type: "all" | "system" | "custom") => {
    currentType.value = type;
    selectedRoles.value = [];
};

const loadRoles = (page = 1) => {
    selectedRoles.value = [];
    roleStore.fetchRoles(page, perPage.value, search.value);
};

onMounted(() => {
    loadRoles();
});

const handleSearch = (value: string) => {
    search.value = value;
    loadRoles(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        roleStore.fetchRoles(1, "total", search.value);
    } else {
        perPage.value = Number(newPerPage);
        loadRoles(1);
    }
};

const handlePageChange = (page: number) => {
    loadRoles(page);
};

const navigateToCreate = () => {
    router.push("/admin/roles/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/roles/${id}/edit`);
};

const handleBatchDelete = () => {
    if (selectedRoles.value.length === 0) return;
    confirmDialog.value?.show(
        "Eliminar roles",
        `¿Seguro que deseas eliminar ${selectedRoles.value.length} rol(es)? No se podrán eliminar los que tengan usuarios asignados.`,
        async () => {
            try {
                await roleStore.deleteRoles(selectedRoles.value);
                loadRoles(meta.value.current_page);
                selectedRoles.value = [];
            } catch (err: any) {
                toast.error(
                    err?.response?.data?.message || "Error al eliminar",
                );
            }
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Roles' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Roles"
                :items-count="filteredRoles.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedRoles"
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
                                <span class="sm:hidden">Filtro</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setFilter('all')">
                                Todos los roles
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('system')">
                                Roles del sistema
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('custom')">
                                Roles personalizados
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
                :is-empty="filteredRoles.length === 0"
                empty-message="No hay roles."
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
                                <TableHead v-if="columnVisibility.type"
                                    >Tipo</TableHead
                                >
                                <TableHead v-if="columnVisibility.permissions"
                                    >Permisos</TableHead
                                >
                                <TableHead v-if="columnVisibility.users"
                                    >Usuarios</TableHead
                                >
                                <TableHead v-if="columnVisibility.created"
                                    >Creado</TableHead
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
                            <TableRow v-else-if="filteredRoles.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No hay roles.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="r in filteredRoles"
                                :key="r.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(r.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedRoles.includes(r.id)"
                                        :disabled="r.is_system"
                                        @update:checked="
                                            (checked) =>
                                                toggleSelectRow(r.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.name"
                                    class="font-medium font-mono"
                                    >{{ r.name }}</TableCell
                                >
                                <TableCell v-if="columnVisibility.type">
                                    <Badge
                                        v-if="r.is_system"
                                        variant="secondary"
                                        >sistema</Badge
                                    >
                                    <Badge v-else variant="outline"
                                        >personalizado</Badge
                                    >
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.permissions"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ (r.permissions || []).length }}
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.users"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ r.users_count || 0 }}
                                </TableCell>
                                <TableCell v-if="columnVisibility.created">
                                    {{ formatCreated(r.created_at) }}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="r in filteredRoles"
                        :key="r.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(r.id)"
                    >
                        <div>
                            <h3
                                class="font-medium text-lg pr-4 truncate font-mono"
                                :title="r.name"
                            >
                                {{ r.name }}
                            </h3>
                            <div class="flex items-center gap-2 mt-1">
                                <Badge
                                    v-if="r.is_system"
                                    variant="secondary"
                                    class="text-[10px]"
                                    >sistema</Badge
                                >
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="text-[10px]"
                                    >personalizado</Badge
                                >
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm mt-1">
                            <div>
                                <span
                                    class="text-xs text-muted-foreground block mb-0.5"
                                    >Permisos</span
                                >
                                <div>{{ (r.permissions || []).length }}</div>
                            </div>
                            <div>
                                <span
                                    class="text-xs text-muted-foreground block mb-0.5"
                                    >Usuarios</span
                                >
                                <div>{{ r.users_count || 0 }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />
    </DashboardLayout>
</template>
