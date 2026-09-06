<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import {
    useCourtScheduleStore,
    type CourtSchedule,
    type CourtScheduleFormOptions,
} from "@tenant/stores/courtSchedule";
import { Card, CardContent } from "@/components/ui/card";
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
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from "@/components/ui/sheet";
import { Plus, Pencil, Power, Trash2 } from "lucide-vue-next";
import { toast } from "vue-sonner";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import CourtScheduleForm from "@tenant/views/CourtSchedules/Form.vue";

const props = defineProps<{
    courtId: number;
    slotDurationMinutes?: number | null;
}>();

const scheduleStore = useCourtScheduleStore();

const localSchedules = ref<CourtSchedule[]>([]);
const isLoading = ref(false);
const formOptions = ref<CourtScheduleFormOptions>({
    courts: [],
    days_of_week: [],
});

const sheetOpen = ref(false);
const sheetMode = ref<"create" | "edit">("create");
const editingSchedule = ref<Partial<CourtSchedule> | null>(null);
const formErrors = ref<Record<string, string>>({});
const isSaving = ref(false);
const formRef = ref<InstanceType<typeof CourtScheduleForm> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const dayLabel = (value: number | null | undefined) => {
    if (value == null) return "-";
    const d = formOptions.value.days_of_week.find((d) => d.value === value);
    return d ? d.label : `#${value}`;
};

const formatPrice = (value: number | string | null | undefined) => {
    if (value == null) return "-";
    const num = typeof value === "string" ? parseFloat(value) : value;
    if (Number.isNaN(num)) return "-";
    return `S/ ${num.toFixed(2)}`;
};

// Datos iniciales para el form embebido: para create dejamos court_id fijo y
// pre-llenamos la duración por defecto de la cancha.
const sheetInitialData = computed<Partial<CourtSchedule>>(() => {
    if (sheetMode.value === "edit" && editingSchedule.value) {
        return editingSchedule.value;
    }
    return {
        court_id: props.courtId,
        slot_duration_minutes: props.slotDurationMinutes ?? null,
    };
});

const loadSchedules = async () => {
    isLoading.value = true;
    try {
        await scheduleStore.fetchSchedules(
            1,
            "total",
            props.courtId,
            "",
            "all",
        );
        // El store global mantiene la lista completa entre módulos; clonamos
        // solo lo que pertenece a esta cancha (defensa por si quedaron restos).
        localSchedules.value = scheduleStore.schedules.filter(
            (s) => s.court_id === props.courtId,
        );
    } finally {
        isLoading.value = false;
    }
};

onMounted(async () => {
    formOptions.value = await scheduleStore.fetchFormOptions();
    await loadSchedules();
});

watch(
    () => props.courtId,
    () => loadSchedules(),
);

const openCreate = () => {
    sheetMode.value = "create";
    editingSchedule.value = null;
    formErrors.value = {};
    sheetOpen.value = true;
};

const openEdit = (schedule: CourtSchedule) => {
    sheetMode.value = "edit";
    editingSchedule.value = { ...schedule };
    formErrors.value = {};
    sheetOpen.value = true;
};

const triggerSubmit = () => formRef.value?.submit();

const handleSubmit = async (payload: any) => {
    isSaving.value = true;
    formErrors.value = {};
    try {
        if (sheetMode.value === "edit" && editingSchedule.value?.id) {
            await scheduleStore.updateSchedule(
                editingSchedule.value.id,
                payload,
            );
            toast.success("Horario actualizado");
        } else {
            const created = await scheduleStore.createSchedule(payload);
            const count = created.length;
            toast.success(
                count === 1
                    ? "Horario creado"
                    : `Se crearon ${count} horarios`,
            );
        }
        sheetOpen.value = false;
        await loadSchedules();
    } catch (err: any) {
        const e = err?.response?.data;
        if (e?.errors) {
            const flat: Record<string, string> = {};
            Object.entries(e.errors).forEach(([k, v]: any) => {
                flat[k] = Array.isArray(v) ? v[0] : String(v);
            });
            formErrors.value = flat;
            toast.error("Error de validación", {
                description: "Revisa los campos del formulario.",
            });
        } else {
            toast.error("Error al guardar", {
                description:
                    err?.response?.data?.message ||
                    "Ocurrió un error inesperado.",
            });
        }
    } finally {
        isSaving.value = false;
    }
};

const handleToggle = async (schedule: CourtSchedule) => {
    try {
        await scheduleStore.toggleActive(schedule.id);
        await loadSchedules();
    } catch (err: any) {
        toast.error(err?.response?.data?.message || "Error al cambiar estado");
    }
};

const handleDelete = (schedule: CourtSchedule) => {
    confirmDialog.value?.show(
        "Eliminar horario",
        `¿Eliminar el horario de ${dayLabel(schedule.day_of_week)} ${schedule.start_time}-${schedule.end_time}?`,
        async () => {
            try {
                await scheduleStore.deleteSchedule(schedule.id);
                await loadSchedules();
                toast.success("Horario eliminado");
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
    <Card class="w-full">
        <CardContent class="pt-6 space-y-4">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div>
                    <h3 class="text-base font-semibold">Horarios</h3>
                    <p class="text-xs text-muted-foreground">
                        Franjas reservables por día. El precio aquí sobrescribe
                        el precio base de la cancha.
                    </p>
                </div>
                <Button size="sm" class="h-9" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    Agregar horario
                </Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Día</TableHead>
                            <TableHead>Horario</TableHead>
                            <TableHead>Precio</TableHead>
                            <TableHead>Duración</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="w-[140px] text-right"
                                >Acciones</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="isLoading">
                            <TableCell
                                :colspan="6"
                                class="text-center py-6 text-muted-foreground"
                                >Cargando...</TableCell
                            >
                        </TableRow>
                        <TableRow v-else-if="localSchedules.length === 0">
                            <TableCell
                                :colspan="6"
                                class="text-center py-6 text-muted-foreground"
                            >
                                Sin horarios. Agrega uno para empezar a recibir
                                reservas.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="s in localSchedules"
                            :key="s.id"
                            class="cursor-pointer hover:bg-muted/50"
                            @click="openEdit(s)"
                        >
                            <TableCell class="font-medium">{{
                                dayLabel(s.day_of_week)
                            }}</TableCell>
                            <TableCell
                                >{{ s.start_time }} — {{ s.end_time }}</TableCell
                            >
                            <TableCell>{{ formatPrice(s.price) }}</TableCell>
                            <TableCell>{{
                                s.slot_duration_minutes ?? "—"
                            }}</TableCell>
                            <TableCell>
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
                            <TableCell @click.stop>
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click="openEdit(s)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click="handleToggle(s)"
                                    >
                                        <Power class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive"
                                        @click="handleDelete(s)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </CardContent>
    </Card>

    <Sheet v-model:open="sheetOpen">
        <SheetContent
            side="right"
            class="w-full sm:max-w-xl overflow-y-auto flex flex-col"
        >
            <SheetHeader>
                <SheetTitle>
                    {{
                        sheetMode === "edit"
                            ? "Editar horario"
                            : "Nuevo horario"
                    }}
                </SheetTitle>
                <SheetDescription>
                    {{
                        sheetMode === "create"
                            ? "Crea un horario por cada día seleccionado."
                            : "Modifica la franja horaria seleccionada."
                    }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex-1">
                <CourtScheduleForm
                    ref="formRef"
                    :mode="sheetMode"
                    :initial-data="sheetInitialData"
                    :form-options="formOptions"
                    :is-loading="isSaving"
                    :errors="formErrors"
                    :lock-court="true"
                    @submit="handleSubmit"
                />
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t mt-4">
                <Button
                    variant="outline"
                    :disabled="isSaving"
                    @click="sheetOpen = false"
                >
                    Cancelar
                </Button>
                <Button :disabled="isSaving" @click="triggerSubmit">
                    {{
                        isSaving
                            ? "Guardando..."
                            : sheetMode === "edit"
                              ? "Actualizar"
                              : "Crear"
                    }}
                </Button>
            </div>
        </SheetContent>
    </Sheet>

    <ConfirmDialog ref="confirmDialog" />
</template>
