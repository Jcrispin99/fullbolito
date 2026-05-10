<script setup lang="ts">
import { onMounted, ref, computed } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { usePlanStore } from "@/central/stores/plan";
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
    Edit,
    Archive,
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
const planStore = usePlanStore();
const { plans, meta, isLoading } = storeToRefs(planStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref(20);
const search = ref("");
const selectedPlans = ref<number[]>([]);

type ColumnKey = "name" | "slug" | "price" | "duration" | "status";

const COLUMN_STORAGE_KEY = "plans_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "slug", label: "Slug" },
    { key: "price", label: "Price" },
    { key: "duration", label: "Duration" },
    { key: "status", label: "Status" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    slug: true,
    price: true,
    duration: true,
    status: true,
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
        plans.value.length > 0 &&
        selectedPlans.value.length === plans.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedPlans.value = plans.value.map((p: any) => p.id);
    } else {
        selectedPlans.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedPlans.value.push(id);
    } else {
        selectedPlans.value = selectedPlans.value.filter((pid) => pid !== id);
    }
};

const currentStatus = ref("active");

const filterLabel = computed(() => {
    switch (currentStatus.value) {
        case "active":
            return "Active Plans";
        case "archived":
            return "Archived Plans";
        case "all":
            return "All Plans";
        default:
            return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadPlans(1);
};

// Clear selection when fetching new data
const loadPlans = (page = 1) => {
    selectedPlans.value = [];
    planStore.fetchPlans(
        page,
        Number(perPage.value),
        search.value,
        currentStatus.value,
    );
};

onMounted(() => {
    loadPlans();
});

const handleSearch = (value: string) => {
    search.value = value;
    loadPlans(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total" as any;
        planStore.fetchPlans(
            1,
            "total" as any,
            search.value,
            currentStatus.value,
        );
    } else {
        perPage.value = Number(newPerPage);
        loadPlans(1);
    }
};

const handlePageChange = (page: number) => {
    loadPlans(page);
};

const navigateToCreate = () => {
    router.push("/plans/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/plans/${id}/edit`);
};

const handleToggleStatus = async (id: number) => {
    await planStore.toggleStatus(id);
};

const handleDelete = async (id: number) => {
    confirmDialog.value?.show(
        "Delete Plan",
        "Are you sure you want to delete this plan?",
        async () => {
            await planStore.deletePlan(id);
            loadPlans(meta.value.current_page);
        },
    );
};

const handleBatchDelete = async () => {
    if (selectedPlans.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Plans",
        `Are you sure you want to delete ${selectedPlans.value.length} plans?`,
        async () => {
            await planStore.deletePlans(selectedPlans.value);
            loadPlans(meta.value.current_page);
            selectedPlans.value = [];
        },
    );
};

const handleBatchToggleStatus = () => {
    if (selectedPlans.value.length === 0) {
        return;
    }

    confirmDialog.value?.show(
        "Toggle Status",
        `Are you sure you want to toggle the status for ${selectedPlans.value.length} plans?`,
        async () => {
            // Use Promise.all to toggle all selected plans
            // Note: In a real app you might want a batch endpoint for this
            try {
                await Promise.all(
                    selectedPlans.value.map((id) => planStore.toggleStatus(id)),
                );
                // Reload plans to reflect changes
                await loadPlans(meta.value.current_page);
                // Clear selection
                selectedPlans.value = [];
            } catch (e) {
                console.error(e);
                const message =
                    (e as any)?.response?.data?.message ||
                    planStore.error ||
                    "Error toggling status";
                alert(message);
            }
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Plans' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Plans"
                :items-count="plans.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedPlans"
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
                                Active Plans
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('archived')">
                                Archived Plans
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All Plans
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
                        <DropdownMenuContent align="end" class="w-[160px]">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchToggleStatus">
                                <Power
                                    class="mr-2 h-4 w-4 text-muted-foreground"
                                />
                                Toggle Status
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
                            <TableHead v-if="columnVisibility.slug"
                                >Slug</TableHead
                            >
                            <TableHead v-if="columnVisibility.price"
                                >Price</TableHead
                            >
                            <TableHead v-if="columnVisibility.duration"
                                >Duration</TableHead
                            >
                            <TableHead v-if="columnVisibility.status"
                                >Status</TableHead
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
                        <TableRow v-else-if="plans.length === 0">
                            <TableCell
                                :colspan="tableColspan"
                                class="text-center py-8 text-muted-foreground"
                                >No plans found.</TableCell
                            >
                        </TableRow>
                        <TableRow
                            v-for="plan in plans"
                            :key="plan.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(plan.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedPlans.includes(plan.id)"
                                    @update:checked="
                                        (checked) =>
                                            toggleSelectRow(plan.id, checked)
                                    "
                                />
                            </TableCell>
                            <TableCell
                                v-if="columnVisibility.name"
                                class="font-medium"
                                >{{ plan.name }}</TableCell
                            >
                            <TableCell v-if="columnVisibility.slug">{{
                                plan.slug
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.price"
                                >${{ plan.price }}</TableCell
                            >
                            <TableCell v-if="columnVisibility.duration"
                                >{{ plan.duration_days }} days</TableCell
                            >
                            <TableCell v-if="columnVisibility.status">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs',
                                        plan.is_active
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-800',
                                    ]"
                                >
                                    {{ plan.is_active ? "Active" : "Inactive" }}
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
