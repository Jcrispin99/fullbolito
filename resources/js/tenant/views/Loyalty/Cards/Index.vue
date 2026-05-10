<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useLoyaltyCardStore } from "@tenant/stores/loyaltyCard";
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

const cardStore = useLoyaltyCardStore();
const { cards, meta, isLoading } = storeToRefs(cardStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedCards = ref<number[]>([]);

type ColumnKey =
    | "code"
    | "program"
    | "partner"
    | "points"
    | "is_active"
    | "is_usable"
    | "created";

const COLUMN_STORAGE_KEY = "loyalty_cards_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "code", label: "Code" },
    { key: "program", label: "Program" },
    { key: "partner", label: "Partner" },
    { key: "points", label: "Points" },
    { key: "is_active", label: "Active" },
    { key: "is_usable", label: "Usable" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    code: true,
    program: true,
    partner: true,
    points: true,
    is_active: true,
    is_usable: true,
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
    cards.value.length > 0 && selectedCards.value.length === cards.value.length,
);

const toggleSelectAll = (checked: boolean) => {
    selectedCards.value = checked ? cards.value.map((c) => c.id) : [];
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedCards.value.push(id);
    } else {
        selectedCards.value = selectedCards.value.filter((cid) => cid !== id);
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
        case "active": return "Active Cards";
        case "inactive": return "Inactive Cards";
        case "all": return "All Cards";
        default: return "Filter";
    }
});

const setFilter = (status: string) => {
    currentStatus.value = status;
    loadCards(1);
};

const loadCards = (page = 1) => {
    selectedCards.value = [];
    cardStore.fetchCards(page, perPage.value, search.value, currentStatus.value);
};

onMounted(() => loadCards());

const handleSearch = (value: string) => {
    search.value = value;
    loadCards(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        cardStore.fetchCards(1, "total", search.value, currentStatus.value);
    } else {
        perPage.value = Number(newPerPage);
        loadCards(1);
    }
};

const handlePageChange = (page: number) => loadCards(page);

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (currentStatus.value === "active") filters.is_active = true;
    if (currentStatus.value === "inactive") filters.is_active = false;
    if (selectedCards.value.length > 0) filters.ids = selectedCards.value;
    return filters;
});

const handleBatchToggleActive = () => {
    if (selectedCards.value.length === 0) return;

    confirmDialog.value?.show(
        "Toggle Active",
        `Are you sure you want to toggle active for ${selectedCards.value.length} card(s)?`,
        async () => {
            await Promise.all(
                selectedCards.value.map((id) => cardStore.toggleActive(id)),
            );
            await loadCards(meta.value.current_page);
            selectedCards.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Tarjetas' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Tarjetas"
                :items-count="cards.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="false"
                :search="search"
                :selected-items="selectedCards"
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
                                <span class="sm:hidden">Filter</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setFilter('active')">Active Cards</DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('inactive')">Inactive Cards</DropdownMenuItem>
                            <DropdownMenuItem @click="setFilter('all')">All Cards</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>

                <template #actions>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="h-9 gap-1">
                                Actions
                                <ChevronDown class="h-4 w-4 opacity-50" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[180px]">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleBatchToggleActive">
                                <Power class="mr-2 h-4 w-4 text-muted-foreground" />
                                Toggle Active
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="ieToolbar?.openExport()">
                                <Download class="mr-2 h-4 w-4 text-muted-foreground" />
                                Exportar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="ieToolbar?.supportsImport"
                                @click="ieToolbar?.openImport()"
                            >
                                <Upload class="mr-2 h-4 w-4 text-muted-foreground" />
                                Importar
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
                            <TableHead v-if="columnVisibility.code">Code</TableHead>
                            <TableHead v-if="columnVisibility.program">Program</TableHead>
                            <TableHead v-if="columnVisibility.partner">Partner</TableHead>
                            <TableHead v-if="columnVisibility.points">Points</TableHead>
                            <TableHead v-if="columnVisibility.is_active">Active</TableHead>
                            <TableHead v-if="columnVisibility.is_usable">Usable</TableHead>
                            <TableHead v-if="columnVisibility.created">Created</TableHead>
                            <TableColumnSettingsHead
                                v-model="columnVisibility"
                                :columns="columnOptions"
                                :storage-key="COLUMN_STORAGE_KEY"
                            />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="isLoading">
                            <TableCell :colspan="tableColspan" class="text-center py-8">
                                Loading...
                            </TableCell>
                        </TableRow>
                        <TableRow v-else-if="cards.length === 0">
                            <TableCell :colspan="tableColspan" class="text-center py-8 text-muted-foreground">
                                No cards found.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="c in cards"
                            :key="c.id"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedCards.includes(c.id)"
                                    @update:checked="(checked: boolean) => toggleSelectRow(c.id, checked)"
                                />
                            </TableCell>
                            <TableCell v-if="columnVisibility.code" class="font-mono font-medium">
                                {{ c.code }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.program">
                                <div class="flex items-center gap-2">
                                    <span>{{ c.program?.name || "-" }}</span>
                                    <span
                                        v-if="c.program?.program_type"
                                        class="px-2 py-1 rounded text-[10px] font-medium uppercase tracking-wider text-white bg-blue-500"
                                    >
                                        {{ c.program.program_type }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell v-if="columnVisibility.partner">
                                {{ c.partner?.name || "Anonymous" }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.points">
                                {{ c.points }}
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
                            <TableCell v-if="columnVisibility.is_usable">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium text-white',
                                        c.is_usable
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ c.is_usable ? "Yes" : "No" }}
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
            resource="loyalty_card"
            :filters="exportFilters"
            :default-export-columns="[
                'code',
                'program.name',
                'partner.name',
                'partner.document_number',
                'points',
                'expiration_date',
                'is_active',
            ]"
            export-title="Exportar Tarjetas de Lealtad"
        />
    </DashboardLayout>
</template>
