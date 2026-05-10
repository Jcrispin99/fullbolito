<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import DashboardLayout from "@/central/layouts/DashboardLayout.vue";
import ModuleHeader from "@/central/components/ModuleHeader.vue";
import { Checkbox } from "@/components/ui/checkbox";
import TableColumnSettingsHead from "@/components/TableColumnSettingsHead.vue";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { useUserStore } from "@/central/stores/user";

const router = useRouter();
const userStore = useUserStore();
const { users, meta, isLoading } = storeToRefs(userStore);

const perPage = ref<number | string>(20);
const search = ref("");
const selectedUsers = ref<number[]>([]);

type ColumnKey = "name" | "email" | "tenant" | "role" | "verified" | "created";

const COLUMN_STORAGE_KEY = "users_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "email", label: "Email" },
    { key: "tenant", label: "Tenant" },
    { key: "role", label: "Role" },
    { key: "verified", label: "Verified" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    email: true,
    tenant: true,
    role: true,
    verified: true,
    created: true,
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

const loadUsers = (page = 1) => {
    selectedUsers.value = [];
    userStore.fetchUsers(page, perPage.value, search.value);
};

onMounted(() => {
    loadUsers();
});

const handleSearch = (value: string) => {
    search.value = value;
    loadUsers(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        userStore.fetchUsers(1, "total", search.value);
    } else {
        perPage.value = Number(newPerPage);
        loadUsers(1);
    }
};

const handlePageChange = (page: number) => {
    loadUsers(page);
};

const navigateToCreate = () => {
    router.push("/users/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/users/${id}/edit`);
};

const navigateToTenantEdit = (tenantId: string) => {
    router.push(`/tenants/${tenantId}/edit`);
};

const primaryTenantId = (u: any) => {
    return u?.tenants?.[0]?.id || null;
};

const primaryTenantLabel = (u: any) => {
    const list = u?.tenants || [];
    const first = list[0];
    if (!first) return "-";
    const label = first.business_name || first.id;
    const more = list.length > 1 ? ` (+${list.length - 1})` : "";
    return `${label}${more}`;
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Users' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Users"
                :items-count="users.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedUsers"
                @create="navigateToCreate"
                @update:per-page="handlePerPageChange"
                @update:search="handleSearch"
                @page-change="handlePageChange"
            />

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
                            <TableHead v-if="columnVisibility.email"
                                >Email</TableHead
                            >
                            <TableHead v-if="columnVisibility.tenant"
                                >Tenant</TableHead
                            >
                            <TableHead v-if="columnVisibility.role"
                                >Role</TableHead
                            >
                            <TableHead v-if="columnVisibility.verified"
                                >Verified</TableHead
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
                        <TableRow v-else-if="users.length === 0">
                            <TableCell
                                :colspan="tableColspan"
                                class="text-center py-8 text-muted-foreground"
                                >No users found.</TableCell
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
                            >
                                {{ u.name }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.email">
                                {{ u.email }}
                            </TableCell>
                            <TableCell
                                v-if="columnVisibility.tenant"
                                class="cursor-pointer font-medium hover:underline"
                                @click.stop="
                                    primaryTenantId(u) &&
                                        navigateToTenantEdit(primaryTenantId(u))
                                "
                            >
                                {{ primaryTenantLabel(u) }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.role">
                                {{ (u.roles ?? []).join(", ") || "-" }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.verified">
                                {{ u.email_verified_at ? "Yes" : "No" }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.created">
                                {{ formatCreated(u.created_at) }}
                            </TableCell>
                            <TableCell />
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </DashboardLayout>
</template>
