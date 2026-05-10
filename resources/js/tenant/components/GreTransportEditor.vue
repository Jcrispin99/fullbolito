<script setup lang="ts">
import { computed } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineSelect } from "@/components/ui/underline-select";
import { Save } from "lucide-vue-next";

/**
 * Estructura del bloque GRE que entiende este editor — espejo de los
 * campos `gre_*` que valida `TransferController::updateGre`.
 *
 * El editor es deliberadamente agnóstico al motivo (en fase 1 sólo
 * soportamos `04` desde transferencias, pero el mismo formulario se
 * reusará para `01`/`02` en Sales/Purchases más adelante).
 */
export interface GreTransportData {
    gre_motive_code: string;
    gre_modality: "private" | "public" | string;
    gre_transfer_start_date: string | null;
    gre_gross_weight: number | null;
    gre_packages: number | null;
    gre_vehicle_plate: string | null;
    gre_driver_doc_type: "1" | "4" | "7" | string | null;
    gre_driver_doc_number: string | null;
    gre_driver_license: string | null;
    gre_driver_name: string | null;
}

const props = defineProps<{
    modelValue: GreTransportData;
    /** Bloquea todo el formulario (p.ej. cuando `gre_status` es `accepted` o `ticket_pending`). */
    disabled?: boolean;
    /** Indica que la operación de guardado está en curso. */
    isSaving?: boolean;
    /** Errores por campo provenientes del backend (`gre_*`). */
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "update:modelValue", value: GreTransportData): void;
    (e: "save"): void;
}>();

/** Helper: emite un cambio inmutable preservando el resto de campos. */
const setField = <K extends keyof GreTransportData>(
    key: K,
    value: GreTransportData[K],
) => {
    emit("update:modelValue", { ...props.modelValue, [key]: value });
};

const isComplete = computed(() => {
    const v = props.modelValue;
    return (
        !!v.gre_modality &&
        !!v.gre_transfer_start_date &&
        v.gre_gross_weight !== null &&
        v.gre_gross_weight !== undefined &&
        Number(v.gre_gross_weight) > 0 &&
        !!v.gre_vehicle_plate &&
        !!v.gre_driver_doc_type &&
        !!v.gre_driver_doc_number &&
        !!v.gre_driver_license &&
        !!v.gre_driver_name
    );
});

defineExpose({ isComplete });
</script>

<template>
    <Card class="w-full">
        <CardContent class="pt-6 space-y-6">
            <!-- Datos del traslado -->
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-foreground">
                    Datos del traslado
                </h3>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="gre_modality">
                            Modalidad de transporte
                            <span class="text-destructive">*</span>
                        </Label>
                        <UnderlineSelect
                            id="gre_modality"
                            :model-value="modelValue.gre_modality"
                            :disabled="disabled"
                            @update:model-value="
                                (v) => setField('gre_modality', v as string)
                            "
                        >
                            <option value="">— Seleccionar —</option>
                            <option value="private">Privado</option>
                            <option value="public" disabled>
                                Público (próximamente)
                            </option>
                        </UnderlineSelect>
                        <p
                            v-if="errors?.gre_modality"
                            class="text-sm text-destructive"
                        >
                            {{ errors.gre_modality }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="gre_transfer_start_date">
                            Fecha de inicio del traslado
                            <span class="text-destructive">*</span>
                        </Label>
                        <UnderlineInput
                            id="gre_transfer_start_date"
                            type="date"
                            :model-value="
                                modelValue.gre_transfer_start_date ?? ''
                            "
                            :disabled="disabled"
                            @update:model-value="
                                (v) =>
                                    setField(
                                        'gre_transfer_start_date',
                                        (v as string) || null,
                                    )
                            "
                        />
                        <p
                            v-if="errors?.gre_transfer_start_date"
                            class="text-sm text-destructive"
                        >
                            {{ errors.gre_transfer_start_date }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="gre_gross_weight">
                            Peso bruto total (kg)
                            <span class="text-destructive">*</span>
                        </Label>
                        <UnderlineInput
                            id="gre_gross_weight"
                            type="number"
                            step="0.001"
                            min="0"
                            :model-value="modelValue.gre_gross_weight ?? ''"
                            :disabled="disabled"
                            placeholder="0.000"
                            @update:model-value="
                                (v) =>
                                    setField(
                                        'gre_gross_weight',
                                        v === '' || v === null
                                            ? null
                                            : Number(v),
                                    )
                            "
                        />
                        <p
                            v-if="errors?.gre_gross_weight"
                            class="text-sm text-destructive"
                        >
                            {{ errors.gre_gross_weight }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="gre_packages">Cantidad de bultos</Label>
                        <UnderlineInput
                            id="gre_packages"
                            type="number"
                            step="1"
                            min="0"
                            :model-value="modelValue.gre_packages ?? ''"
                            :disabled="disabled"
                            placeholder="0"
                            @update:model-value="
                                (v) =>
                                    setField(
                                        'gre_packages',
                                        v === '' || v === null
                                            ? null
                                            : Number(v),
                                    )
                            "
                        />
                        <p
                            v-if="errors?.gre_packages"
                            class="text-sm text-destructive"
                        >
                            {{ errors.gre_packages }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Vehículo -->
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-foreground">Vehículo</h3>
                <div class="space-y-2">
                    <Label htmlFor="gre_vehicle_plate">
                        Placa
                        <span class="text-destructive">*</span>
                    </Label>
                    <UnderlineInput
                        id="gre_vehicle_plate"
                        :model-value="modelValue.gre_vehicle_plate ?? ''"
                        :disabled="disabled"
                        placeholder="ABC-123"
                        class="uppercase"
                        @update:model-value="
                            (v) =>
                                setField(
                                    'gre_vehicle_plate',
                                    ((v as string) || '').toUpperCase() || null,
                                )
                        "
                    />
                    <p
                        v-if="errors?.gre_vehicle_plate"
                        class="text-sm text-destructive"
                    >
                        {{ errors.gre_vehicle_plate }}
                    </p>
                </div>
            </div>

            <!-- Conductor -->
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-foreground">Conductor</h3>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="gre_driver_doc_type">
                            Tipo de documento
                            <span class="text-destructive">*</span>
                        </Label>
                        <UnderlineSelect
                            id="gre_driver_doc_type"
                            :model-value="
                                modelValue.gre_driver_doc_type ?? ''
                            "
                            :disabled="disabled"
                            @update:model-value="
                                (v) =>
                                    setField(
                                        'gre_driver_doc_type',
                                        (v as string) || null,
                                    )
                            "
                        >
                            <option value="">— Seleccionar —</option>
                            <option value="1">DNI</option>
                            <option value="4">Carnet de Extranjería</option>
                            <option value="7">Pasaporte</option>
                        </UnderlineSelect>
                        <p
                            v-if="errors?.gre_driver_doc_type"
                            class="text-sm text-destructive"
                        >
                            {{ errors.gre_driver_doc_type }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="gre_driver_doc_number">
                            Número de documento
                            <span class="text-destructive">*</span>
                        </Label>
                        <UnderlineInput
                            id="gre_driver_doc_number"
                            :model-value="
                                modelValue.gre_driver_doc_number ?? ''
                            "
                            :disabled="disabled"
                            placeholder="12345678"
                            @update:model-value="
                                (v) =>
                                    setField(
                                        'gre_driver_doc_number',
                                        (v as string) || null,
                                    )
                            "
                        />
                        <p
                            v-if="errors?.gre_driver_doc_number"
                            class="text-sm text-destructive"
                        >
                            {{ errors.gre_driver_doc_number }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="gre_driver_license">
                            Licencia de conducir
                            <span class="text-destructive">*</span>
                        </Label>
                        <UnderlineInput
                            id="gre_driver_license"
                            :model-value="modelValue.gre_driver_license ?? ''"
                            :disabled="disabled"
                            placeholder="Q12345678"
                            class="uppercase"
                            @update:model-value="
                                (v) =>
                                    setField(
                                        'gre_driver_license',
                                        ((v as string) || '').toUpperCase() ||
                                            null,
                                    )
                            "
                        />
                        <p
                            v-if="errors?.gre_driver_license"
                            class="text-sm text-destructive"
                        >
                            {{ errors.gre_driver_license }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="gre_driver_name">
                            Nombres y apellidos
                            <span class="text-destructive">*</span>
                        </Label>
                        <UnderlineInput
                            id="gre_driver_name"
                            :model-value="modelValue.gre_driver_name ?? ''"
                            :disabled="disabled"
                            placeholder="Juan Pérez García"
                            @update:model-value="
                                (v) =>
                                    setField(
                                        'gre_driver_name',
                                        (v as string) || null,
                                    )
                            "
                        />
                        <p
                            v-if="errors?.gre_driver_name"
                            class="text-sm text-destructive"
                        >
                            {{ errors.gre_driver_name }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="!disabled"
                class="flex items-center justify-between gap-3 pt-2 border-t"
            >
                <p class="text-xs text-muted-foreground">
                    <span v-if="!isComplete">
                        Completa todos los campos para habilitar el envío a SUNAT.
                    </span>
                    <span v-else class="text-emerald-700">
                        Datos completos — listos para enviar.
                    </span>
                </p>
                <Button
                    type="button"
                    size="sm"
                    :disabled="isSaving"
                    @click="emit('save')"
                >
                    <Save class="size-4 mr-1.5" />
                    {{ isSaving ? "Guardando..." : "Guardar transporte" }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
