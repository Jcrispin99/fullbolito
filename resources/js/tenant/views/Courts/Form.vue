<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineSelect } from "@/components/ui/underline-select";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import { SearchSelect } from "@/components/ui/search-select";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type { Court, CourtFormOptions } from "@tenant/stores/court";
import { SPORT_OPTIONS, SURFACE_OPTIONS } from "./sports";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<Court>;
    formOptions?: CourtFormOptions;
    isLoading?: boolean;
    errors?: Record<string, string>;
    archived?: boolean;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const form = ref({
    name: "",
    code: "",
    description: "",
    sport: "",
    surface: "",
    capacity: null as number | null,
    slot_duration_minutes: 60 as number | null,
    price: null as number | null,
    company_id: undefined as number | undefined,
});

watch(
    () => props.initialData,
    (data) => {
        if (data) {
            form.value = {
                name: data.name || "",
                code: data.code || "",
                description: data.description || "",
                sport: data.sport || "",
                surface: data.surface || "",
                capacity: data.capacity ?? null,
                slot_duration_minutes: data.slot_duration_minutes ?? 60,
                price: data.price ?? null,
                company_id: data.company_id || undefined,
            };
        }
    },
    { immediate: true },
);

const companyOptions = computed(() =>
    (props.formOptions?.companies || []).map((c) => ({
        value: c.id,
        label: c.trade_name || c.business_name || `#${c.id}`,
        description: c.ruc || null,
    })),
);

const submit = () => {
    const payload: any = {
        name: form.value.name,
        code: form.value.code || null,
        description: form.value.description || null,
        sport: form.value.sport,
        surface: form.value.surface || null,
        capacity: form.value.capacity,
        slot_duration_minutes: form.value.slot_duration_minutes,
        price: form.value.price,
        company_id: form.value.company_id,
    };
    emit("submit", payload);
};

defineExpose({ submit });
</script>

<template>
    <Card class="w-full relative overflow-hidden">
        <CornerRibbon v-if="archived" label="Inactiva" tone="danger" />
        <CardContent class="pt-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Datos básicos -->
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2 md:col-span-2">
                        <Label htmlFor="name">Nombre</Label>
                        <UnderlineInput
                            id="name"
                            v-model="form.name"
                            placeholder="ej. Cancha 1"
                            required
                        />
                        <p
                            v-if="errors?.name"
                            class="text-sm text-destructive"
                        >
                            {{ errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="code">Código</Label>
                        <UnderlineInput
                            id="code"
                            v-model="form.code"
                            placeholder="ej. C-01"
                        />
                        <p
                            v-if="errors?.code"
                            class="text-sm text-destructive"
                        >
                            {{ errors.code }}
                        </p>
                    </div>
                </div>

                <!-- Deporte y características -->
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="space-y-2">
                        <Label htmlFor="sport">Deporte</Label>
                        <UnderlineSelect id="sport" v-model="form.sport">
                            <option value="" disabled>Selecciona...</option>
                            <option
                                v-for="opt in SPORT_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </UnderlineSelect>
                        <p
                            v-if="errors?.sport"
                            class="text-sm text-destructive"
                        >
                            {{ errors.sport }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="surface">Superficie</Label>
                        <UnderlineSelect id="surface" v-model="form.surface">
                            <option value="">—</option>
                            <option
                                v-for="opt in SURFACE_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </UnderlineSelect>
                        <p
                            v-if="errors?.surface"
                            class="text-sm text-destructive"
                        >
                            {{ errors.surface }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="capacity">Capacidad</Label>
                        <UnderlineInput
                            id="capacity"
                            v-model.number="form.capacity"
                            type="number"
                            min="1"
                            max="1000"
                            placeholder="ej. 14"
                        />
                        <p
                            v-if="errors?.capacity"
                            class="text-sm text-destructive"
                        >
                            {{ errors.capacity }}
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
                            placeholder="60"
                        />
                        <p
                            v-if="errors?.slot_duration_minutes"
                            class="text-sm text-destructive"
                        >
                            {{ errors.slot_duration_minutes }}
                        </p>
                    </div>
                </div>

                <!-- Sede y precio -->
                <div class="grid gap-4 md:grid-cols-2 pt-4 border-t">
                    <div class="space-y-2">
                        <Label htmlFor="company_id">Sede</Label>
                        <SearchSelect
                            id="company_id"
                            v-model="form.company_id"
                            :options="companyOptions"
                            placeholder="Buscar sede..."
                        />
                        <p
                            v-if="errors?.company_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.company_id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="price">Precio por turno (S/)</Label>
                        <UnderlineInput
                            id="price"
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="ej. 80.00"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Precio base por slot. Se podrá sobreescribir por
                            horario más adelante.
                        </p>
                        <p
                            v-if="errors?.price"
                            class="text-sm text-destructive"
                        >
                            {{ errors.price }}
                        </p>
                    </div>
                </div>

                <!-- Descripción -->
                <div class="space-y-2 pt-4 border-t">
                    <Label htmlFor="description">Descripción</Label>
                    <UnderlineTextarea
                        id="description"
                        v-model="form.description"
                        placeholder="Notas internas o descripción visible al cliente..."
                        rows="3"
                    />
                    <p
                        v-if="errors?.description"
                        class="text-sm text-destructive"
                    >
                        {{ errors.description }}
                    </p>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
