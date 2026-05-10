<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useLoyaltyProgramStore } from "@tenant/stores/loyaltyProgram";
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
const programStore = useLoyaltyProgramStore();
const { programs, meta, isLoading } = storeToRefs(programStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedPrograms = ref<number[]>([]);
const viewMode = ref<"table" | "grid">("table");

type ColumnKey = "name" | "program_type" | "module" | "trigger" | "is_active" | "created";

const COLUMN_STORAGE_KEY = "loyalty_programs_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "name", label: "Name" },
    { key: "program_type", label: "Type" },
    { key: "module", label: "Module" },
    { key: "trigger", label: "Trigger" },
    { key: "is_active", label: "Active" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    name: true,
    program_type: true,
    module: true,
    trigger: true,
    is_active: true,
    created: false,
};

const columnVisibility = ref<Record<ColumnKey, boolean>>({
    ...defaultColumnVisibility,
});

const visibleColumnCount = computed(() =>
    Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 2 + visibleColumnCount.value);

const allSelected = computed(() =>
    programs.value.length > 0 &&
    selectedPrograms.value.length === programs.value.length,
);

const toggleSelectAll = (checked: boolean) => {
    selectedPrograms.value = checked ? programs.value.map((p) => p.id) : [];
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedPrograms.value.push(id);
    } else {
        selectedPrograms.value = selectedPrograms.value.filter((pid) => pid !== id);
    }
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const programTypeBadge = (type: string) => {
    const map: Record<string, string> = {
        promotion: "bg-blue-500",
        coupon: "bg-green-500",
        loyalty: "bg-purple-500",
        buy_x_get_y: "bg-orange-500",
        promo_code: "bg-pink-500",
    };
    return map[type] || "bg-gray-500";
};

const moduleBadges = (p: { is_pos?: boolean; is_sales?: boolean; is_web?: boolean }) => {
    const out: { label: string; class: string }[] = [];
    if (p.is_pos) out.push({ label: "POS", class: "bg-emerald-500" });
    if (p.is_sales) out.push({ label: "Sales", class: "bg-blue-500" });
    if (p.is_web) out.push({ label: "Web", class: "bg-purple-500" });
    return out;
};

const ruleModeLabel = (mode: string) => {
    const map: Record<string, string> = {
        order: "Por orden",
        money: "Por moneda gastada",
        unit: "Por unidad pagada",
    };
    return map[mode] || mode;
};

const rewardTypeLabel = (type: string) => {
    const map: Record<string, string> = {
        discount: "Descuento",
        product: "Producto gratis",
    };
    return map[type] || type;
};

const joinNames = (items?: { name: string }[], fallbackIds?: number[]) => {
    if (items && items.length > 0) {
        const names = items.map((item) => item.name);
        return names.length > 3
            ? `${names.slice(0, 3).join(", ")} +${names.length - 3}`
            : names.join(", ");
    }

    if (fallbackIds && fallbackIds.length > 0) {
        return fallbackIds.join(", ");
    }

    return "";
};

const describeRuleScope = (rule: any) => {
    const parts: string[] = [];

    const variants = joinNames(rule.product_variants, rule.product_variant_ids);
    const templates = joinNames(rule.product_templates, rule.product_template_ids);
    const categories = joinNames(rule.categories, rule.category_ids);

    if (variants) parts.push(`Productos: ${variants}`);
    if (templates) parts.push(`Templates: ${templates}`);
    if (categories) parts.push(`Categorias: ${categories}`);

    return parts.length > 0 ? parts.join(" | ") : "Todos los productos";
};

const describeRewardScope = (reward: any) => {
    if (reward.reward_type === "product") {
        const name = reward.reward_product?.name || `Producto #${reward.reward_product_id}`;
        return name
            ? `Entrega ${reward.reward_product_qty} x ${name}`
            : "Entrega un producto";
    }

    const applicabilityMap: Record<string, string> = {
        order: "Toda la orden",
        cheapest: "Producto mas barato",
        specific: "Productos o categorias especificas",
    };

    const productNames = joinNames(reward.discount_products, reward.discount_product_ids);
    const categoryNames = joinNames(reward.discount_categories, reward.discount_category_ids);
    const scopes: string[] = [applicabilityMap[reward.discount_applicability] || "Orden"];

    if (productNames) scopes.push(`Productos: ${productNames}`);
    if (categoryNames) scopes.push(`Categorias: ${categoryNames}`);

    return scopes.join(" | ");
};

const currentStatus = ref("active");

const filterLabel = computed(() => {
    switch (currentStatus.value) {
        case "active":
            return "Active Programs";
        case "inactive":
            return "Inactive Programs";
        case "all":
            return "All Programs";
        default:
            return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadPrograms(1);
};

const loadPrograms = (page = 1) => {
    selectedPrograms.value = [];
    programStore.fetchPrograms(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => {
    loadPrograms();
});

const handleSearch = (value: string) => {
    search.value = value;
    loadPrograms(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        programStore.fetchPrograms(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadPrograms(1);
    }
};

const handlePageChange = (page: number) => {
    loadPrograms(page);
};

const navigateToCreate = () => {
    router.push("/admin/loyalty/programs/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/loyalty/programs/${id}/edit`);
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedPrograms.value.length > 0) filters.ids = selectedPrograms.value;
    return filters;
});

const handleBatchDelete = async () => {
    if (selectedPrograms.value.length === 0) return;

    confirmDialog.value?.show(
        "Delete Programs",
        `Are you sure you want to delete ${selectedPrograms.value.length} program(s)?`,
        async () => {
            try {
                await programStore.deletePrograms(selectedPrograms.value);
                loadPrograms(meta.value.current_page);
                selectedPrograms.value = [];
            } catch (err: any) {
                const msg = err?.response?.data?.message || "Some programs could not be deleted.";
                alert(msg);
            }
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedPrograms.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Are you sure you want to toggle active for ${selectedPrograms.value.length} program(s)?`,
        async () => {
            await Promise.all(
                selectedPrograms.value.map((id) => programStore.toggleActive(id)),
            );
            await loadPrograms(meta.value.current_page);
            selectedPrograms.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Programas' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Loyalty Programs"
                :items-count="programs.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :search="search"
                :selected-items="selectedPrograms"
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
                            <DropdownMenuItem @click="setFilter('active')">
                                Active Programs
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">
                                Inactive Programs
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">
                                All Programs
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

            <DataView
                :view-mode="viewMode"
                :is-loading="isLoading"
                :is-empty="programs.length === 0"
                empty-message="No programs found."
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
                                    >Name</TableHead
                                >
                                <TableHead v-if="columnVisibility.program_type"
                                    >Type</TableHead
                                >
                                <TableHead v-if="columnVisibility.module"
                                    >Module</TableHead
                                >
                                <TableHead v-if="columnVisibility.trigger"
                                    >Trigger</TableHead
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
                            <TableRow v-else-if="programs.length === 0">
                                <TableCell
                                    :colspan="tableColspan"
                                    class="text-center py-8 text-muted-foreground"
                                    >No programs found.</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="p in programs"
                                :key="p.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="navigateToEdit(p.id)"
                            >
                                <TableCell @click.stop>
                                    <Checkbox
                                        :checked="selectedPrograms.includes(p.id)"
                                        @update:checked="
                                            (checked: boolean) =>
                                                toggleSelectRow(p.id, checked)
                                        "
                                    />
                                </TableCell>
                                <TableCell
                                    v-if="columnVisibility.name"
                                    class="font-medium"
                                >
                                    <div>{{ p.name }}</div>
                                    <div
                                        v-if="p.description"
                                        class="text-xs text-muted-foreground mt-0.5"
                                    >
                                        {{ p.description }}
                                    </div>
                                    <div
                                        v-if="p.rules?.length"
                                        class="mt-2 space-y-1 text-xs text-muted-foreground"
                                    >
                                        <div
                                            v-for="rule in p.rules"
                                            :key="`rule-${rule.id}`"
                                        >
                                            Regla:
                                            {{ rule.reward_point_amount }}
                                            {{ p.point_name || "puntos" }}
                                            {{ ruleModeLabel(rule.reward_point_mode) }}.
                                            {{ describeRuleScope(rule) }}
                                        </div>
                                    </div>
                                    <div
                                        v-if="p.rewards?.length"
                                        class="mt-2 space-y-1 text-xs text-muted-foreground"
                                    >
                                        <div
                                            v-for="reward in p.rewards"
                                            :key="`reward-${reward.id}`"
                                        >
                                            Recompensa:
                                            {{ rewardTypeLabel(reward.reward_type) }}.
                                            {{ describeRewardScope(reward) }}
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell v-if="columnVisibility.program_type">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium text-white',
                                            programTypeBadge(p.program_type),
                                        ]"
                                    >
                                        {{ p.program_type }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.module">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="b in moduleBadges(p)"
                                            :key="b.label"
                                            :class="[
                                                'px-2 py-1 rounded text-xs font-medium text-white',
                                                b.class,
                                            ]"
                                        >
                                            {{ b.label }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell v-if="columnVisibility.trigger">
                                    <span class="text-sm">
                                        {{ p.trigger === 'auto' ? 'Auto' : 'With Code' }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.is_active">
                                    <span
                                        :class="[
                                            'px-2 py-1 rounded text-xs font-medium text-white',
                                            p.is_active
                                                ? 'bg-green-500'
                                                : 'bg-gray-400',
                                        ]"
                                    >
                                        {{ p.is_active ? "Active" : "Inactive" }}
                                    </span>
                                </TableCell>
                                <TableCell v-if="columnVisibility.created">
                                    {{ formatCreated(p.created_at) }}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                        </TableBody>
                    </Table>
                </template>

                <template #grid>
                    <div
                        v-for="p in programs"
                        :key="p.id"
                        class="border rounded-lg p-4 shadow-sm bg-card text-card-foreground hover:bg-muted/50 cursor-pointer flex flex-col gap-3 relative transition-all"
                        @click="navigateToEdit(p.id)"
                    >
                        <div>
                            <h3 class="font-medium text-lg pr-4 truncate" :title="p.name">{{ p.name }}</h3>
                            <div v-if="p.description" class="text-xs text-muted-foreground mt-0.5">
                                {{ p.description }}
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        programTypeBadge(p.program_type),
                                    ]"
                                >
                                    {{ p.program_type }}
                                </span>
                                <span
                                    v-for="b in moduleBadges(p)"
                                    :key="b.label"
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        b.class,
                                    ]"
                                >
                                    {{ b.label }}
                                </span>
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        p.is_active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ p.is_active ? "Active" : "Inactive" }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm mt-1">
                            <div v-if="columnVisibility.trigger">
                                <span class="text-xs text-muted-foreground block mb-0.5">Trigger</span>
                                <div>{{ p.trigger === 'auto' ? 'Auto' : 'With Code' }}</div>
                            </div>
                        </div>

                        <div
                            v-if="p.rules?.length"
                            class="rounded-md bg-muted/50 p-3 text-xs space-y-1"
                        >
                            <div class="font-medium">Reglas</div>
                            <div v-for="rule in p.rules" :key="`grid-rule-${rule.id}`">
                                {{ rule.reward_point_amount }} {{ p.point_name || "puntos" }}
                                {{ ruleModeLabel(rule.reward_point_mode) }}.
                                {{ describeRuleScope(rule) }}
                            </div>
                        </div>

                        <div
                            v-if="p.rewards?.length"
                            class="rounded-md bg-muted/50 p-3 text-xs space-y-1"
                        >
                            <div class="font-medium">Recompensas</div>
                            <div
                                v-for="reward in p.rewards"
                                :key="`grid-reward-${reward.id}`"
                            >
                                {{ rewardTypeLabel(reward.reward_type) }}.
                                {{ describeRewardScope(reward) }}
                            </div>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>

        <ConfirmDialog ref="confirmDialog" />

        <ImportExportToolbar
            ref="ieToolbar"
            resource="loyalty_program"
            :filters="exportFilters"
            :default-export-columns="[
                'name',
                'program_type',
                'point_name',
                'starts_at',
                'ends_at',
                'is_active',
            ]"
            export-title="Exportar Programas de Lealtad"
        />
    </DashboardLayout>
</template>
