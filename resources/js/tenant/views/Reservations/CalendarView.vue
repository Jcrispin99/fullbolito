<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { apiClient } from "@tenant/lib/api";
import {
    useReservationStore,
    type Reservation,
    type ReservationFormOptions,
    type ReservationStatus,
} from "@tenant/stores/reservation";
import { Button } from "@/components/ui/button";
import { SearchSelect } from "@/components/ui/search-select";
import { Card, CardContent } from "@/components/ui/card";
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from "@/components/ui/sheet";
import { ChevronLeft, ChevronRight, Eye } from "lucide-vue-next";
import { toast } from "vue-sonner";
import ReservationForm from "./Form.vue";
import { statusMeta, formatTime } from "./status";

const props = defineProps<{
    formOptions: ReservationFormOptions;
    initialCourtId?: number;
}>();

const store = useReservationStore();

// --- Config visual ---
const START_HOUR = 6; // 6:00 AM
const END_HOUR = 23; // 23:00 (visible hasta las 22:30)
const SLOT_MINUTES = 30;
const SLOT_HEIGHT = 24; // px por slot de 30 min

const totalSlots = ((END_HOUR - START_HOUR) * 60) / SLOT_MINUTES;
const totalHours = END_HOUR - START_HOUR;

// --- State ---
const selectedCourtId = ref<number | undefined>(props.initialCourtId);
const weekStart = ref<Date>(startOfWeek(new Date()));
const showAll = ref(false);
const weekReservations = ref<Reservation[]>([]);
const isLoading = ref(false);

// Sheet de form embebido
const sheetOpen = ref(false);
const sheetMode = ref<"create" | "edit">("create");
const sheetInitial = ref<Partial<Reservation>>({});
const sheetErrors = ref<Record<string, string>>({});
const sheetSaving = ref(false);
const formRef = ref<InstanceType<typeof ReservationForm> | null>(null);

// --- Helpers de fecha (semana ISO: lunes=0) ---
function startOfWeek(d: Date): Date {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    const day = x.getDay(); // 0=dom..6=sáb
    const diff = day === 0 ? -6 : 1 - day; // lleva al lunes
    x.setDate(x.getDate() + diff);
    return x;
}

function addDays(d: Date, n: number): Date {
    const x = new Date(d);
    x.setDate(x.getDate() + n);
    return x;
}

function isoDate(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function localDateTime(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, "0");
    return `${isoDate(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const weekEnd = computed(() => addDays(weekStart.value, 6));

const weekDays = computed(() =>
    Array.from({ length: 7 }, (_, i) => addDays(weekStart.value, i)),
);

const weekRangeLabel = computed(() => {
    const start = weekStart.value;
    const end = weekEnd.value;
    const sameMonth = start.getMonth() === end.getMonth();
    const fmt = (d: Date) =>
        d.toLocaleDateString("es", { day: "numeric", month: "short" });
    const startLabel = sameMonth
        ? String(start.getDate())
        : fmt(start);
    return `${startLabel} – ${fmt(end)} ${end.getFullYear()}`;
});

const courtOptions = computed(() =>
    (props.formOptions.courts || []).map((c) => ({
        value: c.id,
        label: c.name,
        description: c.sport || null,
    })),
);

const hourLabels = computed(() =>
    Array.from({ length: totalHours }, (_, i) => `${START_HOUR + i}:00`),
);

const todayIso = isoDate(new Date());

// --- Carga de datos ---
async function loadWeek() {
    if (!selectedCourtId.value) {
        weekReservations.value = [];
        return;
    }
    isLoading.value = true;
    try {
        const qp = new URLSearchParams();
        qp.set("per_page", "total");
        qp.set("court_id", String(selectedCourtId.value));
        qp.set("date_from", `${isoDate(weekStart.value)} 00:00:00`);
        qp.set("date_to", `${isoDate(weekEnd.value)} 23:59:59`);
        const { data } = await apiClient.get<any>(
            `/v1/reservations?${qp.toString()}`,
        );
        const list = data.data?.data ?? data.data ?? [];
        weekReservations.value = Array.isArray(list) ? list : [];
    } catch (err: any) {
        toast.error(
            err?.response?.data?.message ||
                "Error cargando reservas de la semana",
        );
    } finally {
        isLoading.value = false;
    }
}

// Auto-elegir primera cancha si no hay seleccionada
watch(
    () => props.formOptions.courts,
    (courts) => {
        if (!selectedCourtId.value && courts.length > 0) {
            selectedCourtId.value = courts[0].id;
        }
    },
    { immediate: true },
);

watch([selectedCourtId, weekStart], () => loadWeek());

onMounted(() => loadWeek());

// --- Reservas visibles ---
const VISIBLE_STATUSES: ReservationStatus[] = [
    "held",
    "confirmed",
    "paid",
    "played",
];

const visibleReservations = computed(() =>
    weekReservations.value.filter((r) =>
        showAll.value ? true : VISIBLE_STATUSES.includes(r.status),
    ),
);

interface PositionedReservation {
    reservation: Reservation;
    dayIndex: number; // 0..6
    topPx: number;
    heightPx: number;
    isDimmed: boolean;
}

function dayIndexOf(d: Date): number {
    // weekStart es lunes; mapeo 0..6 desde el lunes.
    const dayDate = new Date(d);
    dayDate.setHours(0, 0, 0, 0);
    const ms = dayDate.getTime() - weekStart.value.getTime();
    return Math.floor(ms / (24 * 60 * 60 * 1000));
}

function minutesFromCalendarStart(d: Date): number {
    return (d.getHours() - START_HOUR) * 60 + d.getMinutes();
}

const positioned = computed<PositionedReservation[]>(() => {
    const items: PositionedReservation[] = [];
    for (const r of visibleReservations.value) {
        const start = new Date(r.start_at);
        const end = new Date(r.end_at);
        const day = dayIndexOf(start);
        if (day < 0 || day > 6) continue;

        const startMin = Math.max(0, minutesFromCalendarStart(start));
        const endMin = Math.min(
            totalHours * 60,
            minutesFromCalendarStart(end),
        );
        if (endMin <= startMin) continue;

        const topPx = (startMin / SLOT_MINUTES) * SLOT_HEIGHT;
        const heightPx = ((endMin - startMin) / SLOT_MINUTES) * SLOT_HEIGHT;

        const isExpiredHold =
            r.status === "held" &&
            (!r.held_until || new Date(r.held_until).getTime() < Date.now());
        const isInactive =
            r.status === "cancelled" ||
            r.status === "no_show" ||
            isExpiredHold;

        items.push({
            reservation: r,
            dayIndex: day,
            topPx,
            heightPx,
            isDimmed: isInactive,
        });
    }
    return items;
});

function blocksForDay(day: number): PositionedReservation[] {
    return positioned.value.filter((p) => p.dayIndex === day);
}

// --- Navegación de semana ---
const goPrevWeek = () => {
    weekStart.value = addDays(weekStart.value, -7);
};
const goNextWeek = () => {
    weekStart.value = addDays(weekStart.value, 7);
};
const goToday = () => {
    weekStart.value = startOfWeek(new Date());
};

// --- Click en celda vacía: crear ---
const onCellClick = (dayDate: Date, hour: number, minute: number) => {
    if (!selectedCourtId.value) {
        toast.error("Selecciona una cancha primero");
        return;
    }
    const start = new Date(dayDate);
    start.setHours(hour, minute, 0, 0);
    sheetMode.value = "create";
    sheetInitial.value = {
        court_id: selectedCourtId.value,
        start_at: start.toISOString(),
    };
    sheetErrors.value = {};
    sheetOpen.value = true;
};

// --- Click en bloque: editar ---
const onBlockClick = (r: Reservation) => {
    sheetMode.value = "edit";
    sheetInitial.value = { ...r };
    sheetErrors.value = {};
    sheetOpen.value = true;
};

const triggerSubmit = () => formRef.value?.submit();

const handleSubmit = async (payload: any) => {
    sheetSaving.value = true;
    sheetErrors.value = {};
    try {
        if (sheetMode.value === "edit" && sheetInitial.value.id) {
            await store.updateReservation(sheetInitial.value.id, payload);
            toast.success("Reserva actualizada");
        } else {
            const created = await store.createReservation(payload);
            toast.success("Reserva creada", {
                description: `Código: ${created.code}`,
            });
        }
        sheetOpen.value = false;
        await loadWeek();
    } catch (err: any) {
        const e = err?.response?.data;
        if (e?.errors) {
            const flat: Record<string, string> = {};
            Object.entries(e.errors).forEach(([k, v]: any) => {
                flat[k] = Array.isArray(v) ? v[0] : String(v);
            });
            sheetErrors.value = flat;
            toast.error("Error de validación", {
                description: e.message || "Revisa los campos.",
            });
        } else {
            toast.error("Error al guardar", {
                description:
                    err?.response?.data?.message || "Ocurrió un error.",
            });
        }
    } finally {
        sheetSaving.value = false;
    }
};

const dayHeaderLabel = (d: Date) => {
    const dow = d.toLocaleDateString("es", { weekday: "short" });
    return `${dow.charAt(0).toUpperCase()}${dow.slice(1)} ${d.getDate()}`;
};
</script>

<template>
    <Card class="w-full">
        <CardContent class="pt-6 space-y-4">
            <!-- Toolbar -->
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 flex-1 min-w-[200px]">
                    <SearchSelect
                        v-model="selectedCourtId"
                        :options="courtOptions"
                        placeholder="Selecciona una cancha..."
                        class="max-w-xs"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="goToday">
                        Hoy
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-9 w-9"
                        @click="goPrevWeek"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-9 w-9"
                        @click="goNextWeek"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <span class="text-sm font-medium ml-2">{{
                        weekRangeLabel
                    }}</span>
                </div>

                <label
                    class="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer"
                >
                    <input v-model="showAll" type="checkbox" class="h-3 w-3" />
                    <Eye class="h-3.5 w-3.5" />
                    Mostrar canceladas / no-show
                </label>
            </div>

            <p
                v-if="!selectedCourtId"
                class="text-sm text-muted-foreground text-center py-12"
            >
                Selecciona una cancha para ver su agenda semanal.
            </p>

            <!-- Calendario -->
            <div v-else class="overflow-x-auto">
                <div
                    class="grid border rounded-md bg-background min-w-[700px]"
                    :style="{
                        gridTemplateColumns: '60px repeat(7, minmax(0, 1fr))',
                    }"
                >
                    <!-- Esquina vacía -->
                    <div class="border-b border-r" />

                    <!-- Header de días -->
                    <div
                        v-for="(d, i) in weekDays"
                        :key="i"
                        :class="[
                            'border-b text-center text-xs font-semibold py-2',
                            i < 6 ? 'border-r' : '',
                            isoDate(d) === todayIso
                                ? 'bg-primary/5 text-primary'
                                : 'text-muted-foreground',
                        ]"
                    >
                        {{ dayHeaderLabel(d) }}
                    </div>

                    <!-- Columna de horas -->
                    <div class="border-r">
                        <div
                            v-for="(label, i) in hourLabels"
                            :key="i"
                            class="text-[10px] text-muted-foreground text-right pr-2"
                            :style="{ height: `${SLOT_HEIGHT * 2}px` }"
                        >
                            {{ label }}
                        </div>
                    </div>

                    <!-- Columnas de días -->
                    <div
                        v-for="(d, dayI) in weekDays"
                        :key="dayI"
                        :class="[
                            'relative',
                            dayI < 6 ? 'border-r' : '',
                            isoDate(d) === todayIso ? 'bg-primary/[0.02]' : '',
                        ]"
                        :style="{
                            height: `${totalSlots * SLOT_HEIGHT}px`,
                        }"
                    >
                        <!-- Celdas clickeables (1 por hora) -->
                        <div
                            v-for="h in totalHours"
                            :key="h"
                            class="border-b border-dashed border-muted hover:bg-muted/50 cursor-pointer"
                            :style="{ height: `${SLOT_HEIGHT * 2}px` }"
                            @click="
                                onCellClick(d, START_HOUR + (h - 1), 0)
                            "
                        />

                        <!-- Bloques de reservas -->
                        <div
                            v-for="p in blocksForDay(dayI)"
                            :key="p.reservation.id"
                            :class="[
                                'absolute left-1 right-1 rounded text-[11px] px-1.5 py-1 cursor-pointer overflow-hidden shadow-sm transition-opacity',
                                statusMeta(p.reservation.status).badgeClass,
                                p.isDimmed ? 'opacity-50' : 'hover:opacity-90',
                            ]"
                            :style="{
                                top: `${p.topPx}px`,
                                height: `${Math.max(p.heightPx, SLOT_HEIGHT)}px`,
                            }"
                            :title="`${p.reservation.code} — ${statusMeta(p.reservation.status).label}`"
                            @click.stop="onBlockClick(p.reservation)"
                        >
                            <div class="font-semibold truncate">
                                {{ formatTime(p.reservation.start_at) }} —
                                {{ formatTime(p.reservation.end_at) }}
                            </div>
                            <div class="truncate">
                                {{
                                    p.reservation.partner?.name ||
                                    p.reservation.customer_name ||
                                    p.reservation.code
                                }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="isLoading" class="text-xs text-muted-foreground text-center">
                Cargando...
            </p>
        </CardContent>
    </Card>

    <!-- Sheet con form embebido -->
    <Sheet v-model:open="sheetOpen">
        <SheetContent
            side="right"
            class="w-full sm:max-w-2xl overflow-y-auto flex flex-col"
        >
            <SheetHeader>
                <SheetTitle>
                    {{
                        sheetMode === "edit"
                            ? "Editar reserva"
                            : "Nueva reserva"
                    }}
                </SheetTitle>
                <SheetDescription>
                    {{
                        sheetMode === "create"
                            ? "Reserva un slot para la cancha seleccionada."
                            : "Modifica los datos de la reserva."
                    }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex-1">
                <ReservationForm
                    ref="formRef"
                    :mode="sheetMode"
                    :initial-data="sheetInitial"
                    :form-options="formOptions"
                    :is-loading="sheetSaving"
                    :errors="sheetErrors"
                    :lock-court="true"
                    @submit="handleSubmit"
                />
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t mt-4">
                <Button
                    variant="outline"
                    :disabled="sheetSaving"
                    @click="sheetOpen = false"
                >
                    Cancelar
                </Button>
                <Button :disabled="sheetSaving" @click="triggerSubmit">
                    {{
                        sheetSaving
                            ? "Guardando..."
                            : sheetMode === "edit"
                              ? "Actualizar"
                              : "Crear"
                    }}
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
