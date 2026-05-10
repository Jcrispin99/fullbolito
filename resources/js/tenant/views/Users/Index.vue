<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useUserStore } from "@tenant/stores/user";
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
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import TableColumnSettingsHead from "@/components/TableColumnSettingsHead.vue";

const router = useRouter();
const userStore = useUserStore();
const { users, meta, isLoading } = storeToRefs(userStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | "total">(15);
const search = ref("");
const selectedUsers = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");
const currentRole = ref<string>("");

type ColumnKey = "name" | "email" | "roles" | "companies" | "verified" | "created";

const COLUMN_STORAGE_KEY = "users_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Nombre" },
    { key: "email", label: "Email" },
    { key: "roles", label: "Roles" },
    { key: "companies", label: "Empresas" },
    { key: "verified", label: "Verificado" },
    { key: "created", label: "Creado" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    email: true,
    roles: true,
    companies: true,
    verified: false,
    created: false,
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
        users.value.length > 0 &&
        selectedUsers.value.length === users.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedUsers.value = users.value.map((u: any) => u.id);
    } else {
        selectedUsers.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedUsers.value.push(id);
    } else {
        selectedUsers.value = selectedUsers.value.filter((uid) => uid !== id);
    }
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const filterLabel = computed(() => {
    if (!currentRole.value) return "Todos los roles";
    return `Rol: ${currentRole.value}`;
});

const availableRoles = ref<string[]>([]);

const setRoleFilter = (role: string) => {
    currentRole.value = role;
    loadUsers(1);
};

const loadUsers = (page = 1) => {
    selectedUsers.value = [];
    userStore.fetchUsers(page, perPage.value, search.value, currentRole.value);
};

onMounted(async () => {
    loadUsers();
    const opts = await userStore.fetchFormOptions();
    availableRoles.value = (opts?.roles || []).map((r: any) => r.name);
});

const handleSearch = (value: string) => {
    search.value = value;
    loadUsers(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        userStore.fetchUsers(1, "total", search.value, currentRole.value);
    } else {
        perPage.value = Number(newPerPage);
        loadUsers(1);
    }
};

const handlePageChange = (page: number) => {
    loadUsers(page);
};

const navigateToCreate = () => {
    router.push("/admin/users/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/users/${id}/edit`);
};

const handleBatchDelete = () => {
    if (selectedUsers.value.length === 0) return;
    confirmDialog.value?.show(
        "Eliminar usuarios",
        `¿Seguro que deseas eliminar ${selectedUsers.value.length} usuario(s)?`,
        async () => {
            await userStore.deleteUsers(selectedUsers.value);
            loadUsers(meta.value.current_page);
            selectedUsers.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Usuarios' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Usuarios"
                :items-count="users.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedUsers"
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
                            <DropdownMenuItem @click="setRoleFilter('')">
                                Todos los roles
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="r in availableRoles"
                                :key="r"
                                @click="setRoleFilter(r)"
                            >
                                {{ r }}
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
                :is-empty="users.length === 0"
                empty-message="No hay usuarios."
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
                                <TableHead v-if="columnVisibility.email"
                                    >Email</TableHead
                                >
                                <TableHead v-if="columnVisibility.roles"
                                    >Roles</TableHead
                                >
                                <TableHead v-if="columnVisibility.companies"
                                    >Empresas</TableHead
                                >
                                <TableHead v-if="columnVisibility.verified"
                                    >Verificado</TableHead
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
                            <TableRow v-else-if="users.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No hay usuarios.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="u in users"
                                :key="u.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(u.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedUsers.includes(u.id)"
                                        @update:checked="
                                            (checked) =>
                                                toggleSelectRow(u.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.name"
                                    class="font-medium"
                                    >{{ u.name }}</TableCell
                                >
                                <TableCell v-if="columnVisibility.email">{{
                                    u.email
                                }}</TableCell>
                                <TableCell v-if="columnVisibility.roles">
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="r in u.roles || []"
                                            :key="r"
                                            variant="secondary"
                                            class="text-xs"
                                        >
                                            {{ r }}
                                        </Badge>
                                        <span
                                            v-if="(u.roles || []).length === 0"
                                            class="text-xs text-muted-foreground"
                                            >—</span
                                        >
                                    </div>
                                </TableCell>
                                <TableCell v-if="columnVisibility.companies">
                                    <span class="text-sm">
                                        {{ (u.companies || []).length }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.verified">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium text-white',
                                            u.email_verified_at
                                                ? 'bg-green-500'
                                                : 'bg-gray-400',
                                        ]"
                                    >
                                        {{
                                            u.email_verified_at ? "Sí" : "No"
                                        }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.created">
                                    {{ formatCreated(u.created_at) }}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="u in users"
                        :key="u.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(u.id)"
                    >
                        <div>
                            <h3
                                class="font-medium text-lg pr-4 truncate"
                                :title="u.name"
                            >
                                {{ u.name }}
                            </h3>
                            <p
                                class="text-sm text-muted-foreground truncate"
                                :title="u.email"
                            >
                                {{ u.email }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-1">
                            <Badge
                                v-for="r in u.roles || []"
                                :key="r"
                                variant="secondary"
                                class="text-xs"
                            >
                                {{ r }}
                            </Badge>
                            <span
                                v-if="(u.roles || []).length === 0"
                                class="text-xs text-muted-foreground"
                                >Sin roles</span
                            >
                        </div>

                        <div class="text-xs text-muted-foreground mt-auto">
                            {{ (u.companies || []).length }} empresa(s) •
                            {{
                                u.email_verified_at
                                    ? "verificado"
                                    : "no verificado"
                            }}
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />
    </DashboardLayout>
</template>
