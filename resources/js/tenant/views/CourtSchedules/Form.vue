<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineSelect } from "@/components/ui/underline-select";
import { SearchSelect } from "@/components/ui/search-select";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type {
    CourtSchedule,
    CourtScheduleFormOptions,
} from "@tenant/stores/courtSchedule";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<CourtSchedule>;
    formOptions?: CourtScheduleFormOptions;
    isLoading?: boolean;
    errors?: Record<string, string>;
    archived?: boolean;
    lockCourt?: boolean;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

// Días laborales por default en create (1=Lun ... 5=Vie)
const DEFAULT_DAYS = [1, 2, 3, 4, 5];

const form = ref({
    court_id: undefined as number | undefined,
    day_of_week: undefined as number | undefined, // solo en edit
    days_of_week: [...DEFAULT_DAYS] as number[], // solo en create
    start_time: "",
    end_time: "",
    price: null as number | null,
    slot_duration_minutes: null as number | null,
});

watch(
    () => props.initialData,
    (data) => {
        if (!data) return;
        const priceNum =
            typeof data.price === "string"
                ? parseFloat(data.price)
                : (data.price ?? null);
        form.value = {
            court_id: data.court_id || undefined,
            day_of_week:
                typeof data.day_of_week === "number"
                    ? data.day_of_week
                    : undefined,
            days_of_week: props.mode === "create" ? [...DEFAULT_DAYS] : [],
            start_time: data.start_time || "",
            end_time: data.end_time || "",
            price:
                priceNum != null && !Number.isNaN(priceNum) ? priceNum : null,
            slot_duration_minutes: data.slot_duration_minutes ?? null,
        };
    },
    { immediate: true },
);

// Cuando se elige cancha en create y aún no hay duración propia,
// prellena con la default de la cancha.
watch(
    () => form.value.court_id,
    (id) => {
        if (props.mode !== "create") return;
        if (form.value.slot_duration_minutes != null) return;
        const court = props.formOptions?.courts.find((c) => c.id === id);
        if (court?.slot_duration_minutes) {
            form.value.slot_duration_minutes = court.slot_duration_minutes;
        }
    },
);

const courtOptions = computed(() =>
    (props.formOptions?.courts || []).map((c) => ({
        value: c.id,
        label: c.name,
        description: c.sport || null,
    })),
);

const dayShortLabel = (label: string) => label.slice(0, 3);

const toggleDay = (value: number) => {
    const idx = form.value.days_of_week.indexOf(value);
    if (idx === -1) {
        form.value.days_of_week.push(value);
        form.value.days_of_week.sort((a, b) => a - b);
    } else {
        form.value.days_of_week.splice(idx, 1);
    }
};

const selectPreset = (preset: "weekdays" | "weekend" | "all") => {
    if (preset === "weekdays") form.value.days_of_week = [1, 2, 3, 4, 5];
    else if (preset === "weekend") form.value.days_of_week = [0, 6];
    else form.value.days_of_week = [0, 1, 2, 3, 4, 5, 6];
};

const timeRangeError = computed(() => {
    if (!form.value.start_time || !form.value.end_time) return null;
    if (form.value.end_time <= form.value.start_time) {
        return "La hora de fin debe ser mayor a la hora de inicio.";
    }
    return null;
});

const submit = () => {
    const base = {
        court_id: form.value.court_id,
        start_time: form.value.start_time,
        end_time: form.value.end_time,
        price: form.value.price,
        slot_duration_minutes: form.value.slot_duration_minutes,
    };
    const payload: any =
        props.mode === "create"
            ? { ...base, day_of_week: form.value.days_of_week }
            : { ...base, day_of_week: form.value.day_of_week };
    emit("submit", payload);
};

defineExpose({ submit });
</script>

<template>
    <Card class="w-full relative overflow-hidden">
        <CornerRibbon v-if="archived" label="Inactivo" tone="danger" />
        <CardContent class="pt-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Cancha y día(s) -->
                <div
                    :class="
                        lockCourt && mode === 'create'
                            ? ''
                            : 'grid gap-4 md:grid-cols-2'
                    "
                >
                    <div v-if="!lockCourt" class="space-y-2">
                        <Label htmlFor="court_id">Cancha</Label>
                        <SearchSelect
                            id="court_id"
                            v-model="form.court_id"
                            :options="courtOptions"
                            placeholder="Buscar cancha..."
                        />
                        <p
                            v-if="errors?.court_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.court_id }}
                        </p>
                    </div>

                    <div v-if="mode === 'edit'" class="space-y-2">
                        <Label htmlFor="day_of_week">Día de la semana</Label>
                        <UnderlineSelect
                            id="day_of_week"
                            v-model="form.day_of_week"
                        >
                            <option :value="undefined" disabled>
                                Selecciona...
                            </option>
                            <option
                                v-for="d in formOptions?.days_of_week || []"
                                :key="d.value"
                                :value="d.value"
                            >
                                {{ d.label }}
                            </option>
                        </UnderlineSelect>
                        <p
                            v-if="errors?.day_of_week"
                            class="text-sm text-destructive"
                        >
                            {{ errors.day_of_week }}
                        </p>
                    </div>
                </div>

                <!-- Multiselect de días (solo create) -->
                <div v-if="mode === 'create'" class="space-y-3 pt-4 border-t">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <Label>Días de la semana</Label>
                        <div class="flex gap-1">
                            <button
                                type="button"
                                class="text-xs px-2 py-1 rounded border border-input hover:bg-muted"
                                @click="selectPreset('weekdays')"
                            >
                                L-V
                            </button>
                            <button
                                type="button"
                                class="text-xs px-2 py-1 rounded border border-input hover:bg-muted"
                                @click="selectPreset('weekend')"
                            >
                                S-D
                            </button>
                            <button
                                type="button"
                                class="text-xs px-2 py-1 rounded border border-input hover:bg-muted"
                                @click="selectPreset('all')"
                            >
                                Todos
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="d in formOptions?.days_of_week || []"
                            :key="d.value"
                            type="button"
                            :class="[
                                'px-3 py-1.5 rounded-md text-sm border transition-colors',
                                form.days_of_week.includes(d.value)
                                    ? 'bg-primary text-primary-foreground border-primary'
                                    : 'border-input hover:bg-muted',
                            ]"
                            @click="toggleDay(d.value)"
                        >
                            {{ dayShortLabel(d.label) }}
                        </button>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Se creará un horario idéntico (mismas horas y precio)
                        por cada día seleccionado.
                    </p>
                    <p
                        v-if="errors?.day_of_week"
                        class="text-sm text-destructive"
                    >
                        {{ errors.day_of_week }}
                    </p>
                </div>

                <!-- Rango horario -->
                <div class="grid gap-4 md:grid-cols-2 pt-4 border-t">
                    <div class="space-y-2">
                        <Label htmlFor="start_time">Hora de inicio</Label>
                        <UnderlineInput
                            id="start_time"
                            v-model="form.start_time"
                            type="time"
                            required
                        />
                        <p
                            v-if="errors?.start_time"
                            class="text-sm text-destructive"
                        >
                            {{ errors.start_time }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="end_time">Hora de fin</Label>
                        <UnderlineInput
                            id="end_time"
                            v-model="form.end_time"
                            type="time"
                            required
                        />
                        <p
                            v-if="errors?.end_time || timeRangeError"
                            class="text-sm text-destructive"
                        >
                            {{ errors?.end_time || timeRangeError }}
                        </p>
                    </div>
                </div>

                <!-- Precio y duración -->
                <div class="grid gap-4 md:grid-cols-2 pt-4 border-t">
                    <div class="space-y-2">
                        <Label htmlFor="price">Precio del turno (S/)</Label>
                        <UnderlineInput
                            id="price"
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="ej. 100.00"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Sobrescribe el precio base de la cancha para esta
                            franja.
                        </p>
                        <p
                            v-if="errors?.price"
                            class="text-sm text-destructive"
                        >
                            {{ errors.price }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="slot_duration_minutes"
                            >Duración slot (min)</Label
                        >
                        <UnderlineInput
                            id="slot_duration_minutes"
                            v-model.number="form.slot_duration_minutes"
                            type="number"
                            min="5"
                            max="1440"
                            placeholder="Heredado de la cancha"
                        />
                        <p class="text-xs text-muted-foreground">
                            Opcional. Si lo dejas vacío, usa el de la cancha.
                        </p>
                        <p
                            v-if="errors?.slot_duration_minutes"
                            class="text-sm text-destructive"
                        >
                            {{ errors.slot_duration_minutes }}
                        </p>
                    </div>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
