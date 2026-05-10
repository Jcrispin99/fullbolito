<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useLoyaltyTransactionStore } from "@tenant/stores/loyaltyTransaction";
import { useLoyaltyProgramStore } from "@tenant/stores/loyaltyProgram";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import ModuleHeader from "@/central/components/ModuleHeader.vue";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { UnderlineSelect } from "@/components/ui/underline-select";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Download, Upload, ChevronDown } from "lucide-vue-next";
import TableColumnSettingsHead from "@/components/TableColumnSettingsHead.vue";
import ImportExportToolbar from "@tenant/components/ImportExport/ImportExportToolbar.vue";

const transactionStore = useLoyaltyTransactionStore();
const programStore = useLoyaltyProgramStore();
const { transactions, meta, isLoading } = storeToRefs(transactionStore);
const { programs } = storeToRefs(programStore);

const perPage = ref<number | string>(15);
const search = ref("");
const selectedProgramId = ref<number | "">("");
const selectedType = ref("");

type ColumnKey =
    | "partner"
    | "card"
    | "type"
    | "points"
    | "balance"
    | "description"
    | "created";

const COLUMN_STORAGE_KEY = "loyalty_transactions_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "partner", label: "Partner" },
    { key: "card", label: "Card" },
    { key: "type", label: "Type" },
    { key: "points", label: "Points" },
    { key: "balance", label: "Balance" },
    { key: "description", label: "Description" },
    { key: "created", label: "Created" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    partner: true,
    card: true,
    type: true,
    points: true,
    balance: true,
    description: true,
    created: true,
};

const columnVisibility = ref<Record<ColumnKey, boolean>>({
    ...defaultColumnVisibility,
});

const visibleColumnCount = computed(() =>
    Object.values(columnVisibility.value).filter(Boolean).length,
);
const tableColspan = computed(() => 1 + visibleColumnCount.value);

const typeOptions = [
    { value: "", label: "Todos los tipos" },
    { value: "earn", label: "Earn" },
    { value: "redeem", label: "Redeem" },
    { value: "expire", label: "Expire" },
    { value: "adjust", label: "Adjust" },
];

const typeBadgeClass = (type: string) => {
    switch (type) {
        case "earn":
            return "bg-green-500";
        case "redeem":
            return "bg-red-500";
        case "expire":
            return "bg-orange-500";
        case "adjust":
            return "bg-blue-500";
        default:
            return "bg-gray-400";
    }
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium", timeStyle: "short" });
};

const loadTransactions = (page = 1) => {
    transactionStore.fetchTransactions(
        page,
        perPage.value,
        selectedProgramId.value,
        "",
        selectedType.value,
    );
};

const ieToolbar = ref<InstanceType<typeof ImportExportToolbar> | null>(null);

const exportFilters = computed(() => {
    const filters: Record<string, unknown> = {};
    if (search.value) filters.search = search.value;
    if (selectedType.value) filters.type = selectedType.value;
    if (selectedProgramId.value !== "")
        filters.loyalty_program_id = selectedProgramId.value;
    return filters;
});

onMounted(async () => {
    await programStore.fetchPrograms(1, "total", "", "all");
    if (programs.value.length > 0) {
        selectedProgramId.value = programs.value[0]!.id;
    }
    loadTransactions();
});

watch(selectedProgramId, () => {
    loadTransactions(1);
});

watch(selectedType, () => {
    loadTransactions(1);
});

const handleSearch = (value: string) => {
    search.value = value;
    loadTransactions(1);
};

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
    } else {
        perPage.value = Number(newPerPage);
    }
    loadTransactions(1);
};

const handlePageChange = (page: number) => {
    loadTransactions(page);
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Transacciones' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Transacciones"
                :items-count="transactions.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="false"
                :search="search"
                :selected-items="[]"
                @update:per-page="handlePerPageChange"
                @update:search="handleSearch"
                @page-change="handlePageChange"
            >
                <template #filters>
                    <UnderlineSelect v-model="selectedProgramId">
                        <option value="">Todos los programas</option>
                        <option
                            v-for="p in programs"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }}
                        </option>
                    </UnderlineSelect>

                    <UnderlineSelect v-model="selectedType">
                        <option
                            v-for="opt in typeOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </UnderlineSelect>
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
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </ModuleHeader>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead v-if="columnVisibility.partner">Partner</TableHead>
                            <TableHead v-if="columnVisibility.card">Card</TableHead>
                            <TableHead v-if="columnVisibility.type">Type</TableHead>
                            <TableHead v-if="columnVisibility.points" class="text-right">Points</TableHead>
                            <TableHead v-if="columnVisibility.balance" class="text-right">Balance</TableHead>
                            <TableHead v-if="columnVisibility.description">Description</TableHead>
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
                        <TableRow v-else-if="transactions.length === 0">
                            <TableCell :colspan="tableColspan" class="text-center py-8 text-muted-foreground">
                                No se encontraron transacciones.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="t in transactions"
                            :key="t.id"
                        >
                            <TableCell v-if="columnVisibility.partner" class="font-medium">
                                {{ t.partner?.name || t.partner?.display_name || "-" }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.card" class="font-mono">
                                {{ t.card?.code || "-" }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.type">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-[10px] font-medium uppercase tracking-wider text-white',
                                        typeBadgeClass(t.type),
                                    ]"
                                >
                                    {{ t.type }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.points" class="text-right font-medium">
                                <span
                                    :class="[
                                        t.points >= 0
                                            ? 'text-green-600'
                                            : 'text-red-600',
                                    ]"
                                >
                                    {{ t.points >= 0 ? "+" : "" }}{{ t.points }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.balance" class="text-right">
                                {{ t.balance }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.description" class="max-w-[250px] truncate">
                                {{ t.description || "-" }}
                            </TableCell>
                            <TableCell v-if="columnVisibility.created" class="whitespace-nowrap">
                                {{ formatCreated(t.created_at) }}
                            </TableCell>
                            <TableCell />
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <ImportExportToolbar
            ref="ieToolbar"
            resource="loyalty_transaction"
            :filters="exportFilters"
            :default-export-columns="[
                'created_at',
                'type',
                'points',
                'balance',
                'partner.name',
                'card.code',
                'program.name',
                'description',
            ]"
            export-title="Exportar Transacciones de Lealtad"
        />
    </DashboardLayout>
</template>
