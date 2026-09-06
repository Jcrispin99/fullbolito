<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import {
    useCourtScheduleStore,
    type CourtScheduleFormOptions,
} from "@tenant/stores/courtSchedule";
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
import { Trash2, ChevronDown, Power, Filter } from "lucide-vue-next";
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
const scheduleStore = useCourtScheduleStore();
const { schedules, meta, isLoading } = storeToRefs(scheduleStore);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const perPage = ref<number | string>(50);
const selectedIds = ref<number[]>([]);
const currentStatus = ref("active");
const currentCourtId = ref<number | "">("");
const currentDayOfWeek = ref<number | "">("");

const formOptions = ref<CourtScheduleFormOptions>({
    courts: [],
    days_of_week: [],
});

type ColumnKey =
    | "court"
    | "sport"
    | "day"
    | "time"
    | "price"
    | "duration"
    | "is_active"
    | "created";

const COLUMN_STORAGE_KEY = "court_schedules_table_columns";

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: "court", label: "Cancha" },
    { key: "sport", label: "Deporte" },
    { key: "day", label: "Día" },
    { key: "time", label: "Horario" },
    { key: "price", label: "Precio" },
    { key: "duration", label: "Duración (min)" },
    { key: "is_active", label: "Estado" },
    { key: "created", label: "Creado" },
];

const defaultColumnVisibility: Record<ColumnKey, boolean> = {
    court: true,
    sport: false,
    day: true,
    time: true,
    price: true,
    duration: true,
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

const allSelected = computed(() => {
    return (
        schedules.value.length > 0 &&
        selectedIds.value.length === schedules.value.length
    );
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedIds.value = schedules.value.map((s) => s.id);
    } else {
        selectedIds.value = [];
    }
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter((sid) => sid !== id);
    }
};

const formatCreated = (iso: string | null | undefined) => {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", { dateStyle: "medium" });
};

const formatPrice = (value: number | string | null | undefined) => {
    if (value == null) return "-";
    const num = typeof value === "string" ? parseFloat(value) : value;
    if (Number.isNaN(num)) return "-";
    return `S/ ${num.toFixed(2)}`;
};

const dayLabel = (value: number | null | undefined) => {
    if (value == null) return "-";
    const d = formOptions.value.days_of_week.find((d) => d.value === value);
    return d ? d.label : `#${value}`;
};

const statusFilterLabel = computed(() => {
    switch (currentStatus.value) {
        case "active":
            return "Activos";
        case "inactive":
        case "archived":
            return "Inactivos";
        case "all":
            return "Todos";
        default:
            return "Filtro";
    }
});

const courtFilterLabel = computed(() => {
    if (!currentCourtId.value) return "Todas las canchas";
    const court = formOptions.value.courts.find(
        (c) => c.id === currentCourtId.value,
    );
    return court ? court.name : "Cancha";
});

const dayFilterLabel = computed(() => {
    if (currentDayOfWeek.value === "") return "Todos los días";
    return dayLabel(currentDayOfWeek.value as number);
});

const setStatusFilter = (status: string) => {
    currentStatus.value = status;
    loadSchedules(1);
};

const setCourtFilter = (id: number | "") => {
    currentCourtId.value = id;
    loadSchedules(1);
};

const setDayFilter = (day: number | "") => {
    currentDayOfWeek.value = day;
    loadSchedules(1);
};

const loadSchedules = (page = 1) => {
    selectedIds.value = [];
    scheduleStore.fetchSchedules(
        page,
        perPage.value,
        currentCourtId.value,
        currentDayOfWeek.value,
        currentStatus.value,
    );
};

onMounted(async () => {
    loadSchedules();
    formOptions.value = await scheduleStore.fetchFormOptions();
});

useCompanyFilterRefresh(() => loadSchedules());

const handlePerPageChange = (newPerPage: number | string) => {
    if (newPerPage === "total") {
        perPage.value = "total";
        scheduleStore.fetchSchedules(
            1,
            "total",
            currentCourtId.value,
            currentDayOfWeek.value,
            currentStatus.value,
        );
    } else {
        perPage.value = Number(newPerPage);
        loadSchedules(1);
    }
};

const handlePageChange = (page: number) => {
    loadSchedules(page);
};

const navigateToCreate = () => {
    router.push("/admin/court-schedules/create");
};

const navigateToEdit = (id: number) => {
    router.push(`/admin/court-schedules/${id}/edit`);
};

const handleBatchDelete = () => {
    if (selectedIds.value.length === 0) return;
    confirmDialog.value?.show(
        "Eliminar horarios",
        `¿Seguro que deseas eliminar ${selectedIds.value.length} horario(s)?`,
        async () => {
            await scheduleStore.deleteSchedules(selectedIds.value);
            loadSchedules(meta.value.current_page);
            selectedIds.value = [];
        },
    );
};

const handleBatchToggleActive = () => {
    if (selectedIds.value.length === 0) return;
    confirmDialog.value?.show(
        "Cambiar estado",
        `¿Cambiar estado activo de ${selectedIds.value.length} horario(s)?`,
        async () => {
            await Promise.all(
                selectedIds.value.map((id) => scheduleStore.toggleActive(id)),
            );
            await loadSchedules(meta.value.current_page);
            selectedIds.value = [];
        },
    );
};
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Horarios' }]">
        <div class="space-y-6">
            <ModuleHeader
                title="Horarios"
                :items-count="schedules.length"
                :total-items="meta.total"
                :per-page="meta.per_page"
                :current-page="meta.current_page"
                :loading="isLoading"
                :can-create="true"
                :selected-items="selectedIds"
                :can-search="false"
                @create="navigateToCreate"
                @update:per-page="handlePerPageChange"
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
                                    statusFilterLabel
                                }}</span>
                                <span class="sm:hidden">Estado</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                @click="setStatusFilter('active')"
                            >
                                Activos
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setStatusFilter('inactive')"
                            >
                                Inactivos
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="setStatusFilter('all')">
                                Todos
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu v-if="formOptions.courts.length > 0">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                class="h-9 gap-1 whitespace-nowrap"
                            >
                                <span class="hidden sm:inline">{{
                                    courtFilterLabel
                                }}</span>
                                <span class="sm:hidden">Cancha</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            class="max-h-72 overflow-auto"
                        >
                            <DropdownMenuItem @click="setCourtFilter('')">
                                Todas las canchas
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="c in formOptions.courts"
                                :key="c.id"
                                @click="setCourtFilter(c.id)"
                            >
                                {{ c.name }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                class="h-9 gap-1 whitespace-nowrap"
                            >
                                <span class="hidden sm:inline">{{
                                    dayFilterLabel
                                }}</span>
                                <span class="sm:hidden">Día</span>
                                <ChevronDown class="h-4 w-4 opacity-50 ml-1" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="setDayFilter('')">
                                Todos los días
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-for="d in formOptions.days_of_week"
                                :key="d.value"
                                @click="setDayFilter(d.value)"
                            >
                                {{ d.label }}
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
                                Cambiar estado
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
                            <TableHead v-if="columnVisibility.court"
                                >Cancha</TableHead
                            >
                            <TableHead v-if="columnVisibility.sport"
                                >Deporte</TableHead
                            >
                            <TableHead v-if="columnVisibility.day"
                                >Día</TableHead
                            >
                            <TableHead v-if="columnVisibility.time"
                                >Horario</TableHead
                            >
                            <TableHead v-if="columnVisibility.price"
                                >Precio</TableHead
                            >
                            <TableHead v-if="columnVisibility.duration"
                                >Duración (min)</TableHead
                            >
                            <TableHead v-if="columnVisibility.is_active"
                                >Estado</TableHead
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
                        <TableRow v-else-if="schedules.length === 0">
                            <TableCell
                                :colspan="tableColspan"
                                class="text-center py-8 text-muted-foreground"
                                >No hay horarios.</TableCell
                            >
                        </TableRow>
                        <TableRow
                            v-for="s in schedules"
                            :key="s.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="navigateToEdit(s.id)"
                        >
                            <TableCell @click.stop>
                                <Checkbox
                                    :checked="selectedIds.includes(s.id)"
                                    @update:checked="
                                        (checked: boolean) =>
                                            toggleSelectRow(s.id, checked)
                                    "
                                />
                            </TableCell>
                            <TableCell
                                v-if="columnVisibility.court"
                                class="font-medium"
                                >{{ s.court?.name || "-" }}</TableCell
                            >
                            <TableCell v-if="columnVisibility.sport">{{
                                s.court?.sport || "-"
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.day">{{
                                dayLabel(s.day_of_week)
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.time"
                                >{{ s.start_time }} — {{ s.end_time }}</TableCell
                            >
                            <TableCell v-if="columnVisibility.price">{{
                                formatPrice(s.price)
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.duration">{{
                                s.slot_duration_minutes ?? "—"
                            }}</TableCell>
                            <TableCell v-if="columnVisibility.is_active">
                                <span
                                    :class="[
                                        'px-2 py-1 rounded text-xs font-medium text-white',
                                        s.is_active
                                            ? 'bg-green-500'
                                            : 'bg-gray-400',
                                    ]"
                                >
                                    {{ s.is_active ? "Activo" : "Inactivo" }}
                                </span>
                            </TableCell>
                            <TableCell v-if="columnVisibility.created">{{
                                formatCreated(s.created_at)
                            }}</TableCell>
                            <TableCell />
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <ConfirmDialog ref="confirmDialog" />
    </DashboardLayout>
</template>
