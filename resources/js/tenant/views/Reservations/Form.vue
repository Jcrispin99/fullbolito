<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import { SearchSelect } from "@/components/ui/search-select";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type {
    Reservation,
    ReservationFormOptions,
} from "@tenant/stores/reservation";
import { statusMeta } from "./status";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<Reservation>;
    formOptions?: ReservationFormOptions;
    isLoading?: boolean;
    errors?: Record<string, string>;
    lockCourt?: boolean;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const form = ref({
    court_id: undefined as number | undefined,
    start_at: "",
    end_at: "",
    customer_name: "",
    customer_phone: "",
    customer_email: "",
    total: null as number | null,
    notes: "",
});

// "Auto-pilot" del end_at: si el usuario no lo tocó manualmente,
// lo recalculamos a partir de start_at + duración del slot de la cancha.
const endTouched = ref(false);

const toLocalInputValue = (iso: string | null | undefined): string => {
    if (!iso) return "";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "";
    const pad = (n: number) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

watch(
    () => props.initialData,
    (data) => {
        if (!data) return;
        const totalNum =
            typeof data.total === "string"
                ? parseFloat(data.total)
                : (data.total ?? null);
        form.value = {
            court_id: data.court_id || undefined,
            start_at: toLocalInputValue(data.start_at),
            end_at: toLocalInputValue(data.end_at),
            customer_name: data.customer_name || data.partner?.name || "",
            customer_phone: data.customer_phone || "",
            customer_email: data.customer_email || "",
            total:
                totalNum != null && !Number.isNaN(totalNum) ? totalNum : null,
            notes: data.notes || "",
        };
        endTouched.value = !!data.end_at;
    },
    { immediate: true },
);

// Auto-rellenar end_at = start_at + duración del slot de la cancha
watch(
    [() => form.value.start_at, () => form.value.court_id],
    ([start, courtId]) => {
        if (endTouched.value) return;
        if (!start) return;
        const court = props.formOptions?.courts.find((c) => c.id === courtId);
        const minutes = court?.slot_duration_minutes || 60;
        const startDate = new Date(start);
        if (Number.isNaN(startDate.getTime())) return;
        const endDate = new Date(startDate.getTime() + minutes * 60 * 1000);
        form.value.end_at = toLocalInputValue(endDate.toISOString());
    },
);

const handleEndChange = () => {
    endTouched.value = true;
};

const courtOptions = computed(() =>
    (props.formOptions?.courts || []).map((c) => ({
        value: c.id,
        label: c.name,
        description: c.sport || null,
    })),
);

const timeRangeError = computed(() => {
    if (!form.value.start_at || !form.value.end_at) return null;
    if (form.value.end_at <= form.value.start_at) {
        return "La hora de fin debe ser mayor a la de inicio.";
    }
    return null;
});

const currentStatus = computed(() => props.initialData?.status);
const isReadOnly = computed(() => {
    // En estados terminales, ocultamos edición fuerte (queda solo notas).
    return (
        props.mode === "edit" &&
        currentStatus.value !== undefined &&
        ["played", "cancelled", "no_show"].includes(currentStatus.value)
    );
});

const submit = () => {
    const payload: any = {
        court_id: form.value.court_id,
        start_at: form.value.start_at,
        end_at: form.value.end_at,
        customer_name: form.value.customer_name || null,
        customer_phone: form.value.customer_phone || null,
        customer_email: form.value.customer_email || null,
        total: form.value.total,
        notes: form.value.notes || null,
    };
    emit("submit", payload);
};

defineExpose({ submit });
</script>

<template>
    <Card class="w-full relative overflow-hidden">
        <CornerRibbon
            v-if="currentStatus === 'cancelled'"
            label="Cancelada"
            tone="danger"
        />

        <CardContent class="pt-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Estado actual (info en edit) -->
                <div
                    v-if="mode === 'edit' && currentStatus"
                    class="flex items-center gap-3 pb-4 border-b"
                >
                    <span class="text-sm text-muted-foreground">Estado:</span>
                    <span
                        :class="[
                            'px-2 py-1 rounded text-xs font-medium',
                            statusMeta(currentStatus).badgeClass,
                        ]"
                    >
                        {{ statusMeta(currentStatus).label }}
                    </span>
                    <span
                        v-if="initialData?.code"
                        class="font-mono text-xs text-muted-foreground"
                    >
                        {{ initialData.code }}
                    </span>
                </div>

                <!-- Cancha + ventana de tiempo -->
                <div class="grid gap-4 md:grid-cols-3">
                    <div
                        v-if="!lockCourt"
                        class="space-y-2 md:col-span-3"
                    >
                        <Label htmlFor="court_id">Cancha</Label>
                        <SearchSelect
                            id="court_id"
                            v-model="form.court_id"
                            :options="courtOptions"
                            placeholder="Buscar cancha..."
                            :disabled="isReadOnly"
                        />
                        <p
                            v-if="errors?.court_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.court_id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="start_at">Inicio</Label>
                        <UnderlineInput
                            id="start_at"
                            v-model="form.start_at"
                            type="datetime-local"
                            :disabled="isReadOnly"
                            required
                        />
                        <p
                            v-if="errors?.start_at"
                            class="text-sm text-destructive"
                        >
                            {{ errors.start_at }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="end_at">Fin</Label>
                        <UnderlineInput
                            id="end_at"
                            v-model="form.end_at"
                            @input="handleEndChange"
                            type="datetime-local"
                            :disabled="isReadOnly"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Calculado desde la duración del slot, editable.
                        </p>
                        <p
                            v-if="errors?.end_at || timeRangeError"
                            class="text-sm text-destructive"
                        >
                            {{ errors?.end_at || timeRangeError }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="total">Total (S/)</Label>
                        <UnderlineInput
                            id="total"
                            v-model.number="form.total"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="ej. 80.00"
                            :disabled="isReadOnly"
                            required
                        />
                        <p
                            v-if="errors?.total"
                            class="text-sm text-destructive"
                        >
                            {{ errors.total }}
                        </p>
                    </div>
                </div>

                <!-- Datos del cliente -->
                <div class="pt-4 border-t space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold">Cliente</h3>
                        <p class="text-xs text-muted-foreground">
                            Datos del invitado. Si no existe un cliente
                            registrado, se creará automáticamente al guardar.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-2 md:col-span-1">
                            <Label htmlFor="customer_name">Nombre</Label>
                            <UnderlineInput
                                id="customer_name"
                                v-model="form.customer_name"
                                placeholder="ej. Juan Pérez"
                                :disabled="isReadOnly"
                            />
                            <p
                                v-if="errors?.customer_name"
                                class="text-sm text-destructive"
                            >
                                {{ errors.customer_name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label htmlFor="customer_phone">Teléfono</Label>
                            <UnderlineInput
                                id="customer_phone"
                                v-model="form.customer_phone"
                                placeholder="ej. 999 888 777"
                                :disabled="isReadOnly"
                            />
                            <p
                                v-if="errors?.customer_phone"
                                class="text-sm text-destructive"
                            >
                                {{ errors.customer_phone }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label htmlFor="customer_email">Email</Label>
                            <UnderlineInput
                                id="customer_email"
                                v-model="form.customer_email"
                                type="email"
                                placeholder="ej. cliente@correo.com"
                                :disabled="isReadOnly"
                            />
                            <p
                                v-if="errors?.customer_email"
                                class="text-sm text-destructive"
                            >
                                {{ errors.customer_email }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Notas -->
                <div class="space-y-2 pt-4 border-t">
                    <Label htmlFor="notes">Notas</Label>
                    <UnderlineTextarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Comentarios internos sobre la reserva..."
                        rows="3"
                    />
                    <p v-if="errors?.notes" class="text-sm text-destructive">
                        {{ errors.notes }}
                    </p>
                </div>

                <!-- Info de cancelación -->
                <div
                    v-if="
                        currentStatus === 'cancelled' &&
                        initialData?.cancellation_reason
                    "
                    class="pt-4 border-t"
                >
                    <Label>Motivo de cancelación</Label>
                    <p class="text-sm text-muted-foreground italic mt-1">
                        {{ initialData.cancellation_reason }}
                    </p>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
