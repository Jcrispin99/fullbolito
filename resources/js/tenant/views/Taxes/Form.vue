<script setup lang="ts">
import { ref, watch } from "vue";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineSelect } from "@/components/ui/underline-select";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type { Tax } from "@tenant/stores/tax";

const props = defineProps<{
    initialData?: Partial<Tax>;
    isEditing?: boolean;
    archived?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", payload: any): void;
}>();

const TAX_TYPES = [
    { value: "IGV", label: "IGV — Impuesto General a las Ventas" },
    { value: "ISC", label: "ISC — Impuesto Selectivo al Consumo" },
    { value: "IVAP", label: "IVAP — Impuesto a la Venta de Arroz Pilado" },
    { value: "ICBPER", label: "ICBPER — Impuesto al Consumo de Bolsas" },
    { value: "EXO", label: "EXO — Exonerado" },
    { value: "INA", label: "INA — Inafecto" },
    { value: "EXP", label: "EXP — Exportación" },
    { value: "OTHER", label: "OTHER — Otro" },
];

const formData = ref({
    name: "",
    description: "",
    invoice_label: "",
    tax_type: "",
    affectation_type_code: "",
    rate_percent: "" as number | string,
    is_price_inclusive: false,
    is_default: false,
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            formData.value = {
                name: newData.name ?? "",
                description: newData.description ?? "",
                invoice_label: newData.invoice_label ?? "",
                tax_type: newData.tax_type ?? "",
                affectation_type_code: newData.affectation_type_code ?? "",
                rate_percent: newData.rate_percent ?? "",
                is_price_inclusive: newData.is_price_inclusive ?? false,
                is_default: newData.is_default ?? false,
            };
        }
    },
    { immediate: true },
);

const handleSubmit = () => {
    const payload: Record<string, any> = {
        name: formData.value.name,
        description: formData.value.description || null,
        invoice_label: formData.value.invoice_label || null,
        tax_type: formData.value.tax_type,
        affectation_type_code: formData.value.affectation_type_code || null,
        rate_percent:
            formData.value.rate_percent === ""
                ? 0
                : Number(formData.value.rate_percent),
        is_price_inclusive: formData.value.is_price_inclusive,
        is_default: formData.value.is_default,
    };
    emit("submit", payload);
};

defineExpose({ submit: handleSubmit });
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="grid gap-6">
            <Card class="relative overflow-hidden">
                <CornerRibbon v-if="archived" label="Inactive" tone="danger" />

                <CardContent class="pt-6">
                    <div class="grid gap-6">
                        <!-- Name & Invoice Label -->
                        <div class="grid grid-cols-1 gap-4">
                            <div class="grid gap-3">
                                <Label for="name"
                                    >Name
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <UnderlineInput
                                    id="name"
                                    v-model="formData.name"
                                    type="text"
                                    placeholder="e.g. IGV 18%"
                                    required
                                />
                                <p
                                    v-if="errors?.name"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.name }}
                                </p>
                            </div>
                            <div class="grid gap-3">
                                <Label for="invoice_label">Invoice Label</Label>
                                <UnderlineInput
                                    id="invoice_label"
                                    v-model="formData.invoice_label"
                                    type="text"
                                    placeholder="e.g. IGV"
                                />
                                <p
                                    v-if="errors?.invoice_label"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.invoice_label }}
                                </p>
                            </div>
                        </div>

                        <!-- Tax Type & Affectation Code -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="grid gap-3">
                                <Label for="tax_type"
                                    >Tax Type
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <UnderlineSelect
                                    id="tax_type"
                                    v-model="formData.tax_type"
                                    required
                                >
                                    <option value="">— Select type —</option>
                                    <option
                                        v-for="opt in TAX_TYPES"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </UnderlineSelect>
                                <p
                                    v-if="errors?.tax_type"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.tax_type }}
                                </p>
                            </div>
                            <div class="grid gap-3">
                                <Label for="affectation_type_code"
                                    >Affectation Code</Label
                                >
                                <UnderlineInput
                                    id="affectation_type_code"
                                    v-model="formData.affectation_type_code"
                                    type="text"
                                    placeholder="e.g. 10"
                                    maxlength="10"
                                />
                                <p
                                    v-if="errors?.affectation_type_code"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.affectation_type_code }}
                                </p>
                            </div>
                        </div>

                        <!-- Rate % -->
                        <div class="grid gap-3">
                            <Label for="rate_percent"
                                >Rate %
                                <span class="text-destructive">*</span></Label
                            >
                            <UnderlineInput
                                id="rate_percent"
                                v-model="formData.rate_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                placeholder="e.g. 18"
                                required
                            />
                            <p
                                v-if="errors?.rate_percent"
                                class="text-sm text-destructive"
                            >
                                {{ errors.rate_percent }}
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="grid gap-3">
                            <Label for="description">Description</Label>
                            <UnderlineTextarea
                                id="description"
                                v-model="formData.description"
                                placeholder="Additional details about this tax"
                                rows="3"
                            />
                            <p
                                v-if="errors?.description"
                                class="text-sm text-destructive"
                            >
                                {{ errors.description }}
                            </p>
                        </div>

                        <!-- Flags row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                class="flex items-start gap-3 rounded-md border p-4"
                            >
                                <input
                                    id="is_price_inclusive"
                                    v-model="formData.is_price_inclusive"
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300"
                                />
                                <div>
                                    <Label
                                        for="is_price_inclusive"
                                        class="cursor-pointer"
                                    >
                                        Price Inclusive
                                    </Label>
                                    <p
                                        class="text-xs text-muted-foreground mt-0.5"
                                    >
                                        Tax is already included in the listed
                                        price.
                                    </p>
                                </div>
                            </div>
                            <div
                                class="flex items-start gap-3 rounded-md border p-4"
                            >
                                <input
                                    id="is_default"
                                    v-model="formData.is_default"
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300"
                                />
                                <div>
                                    <Label
                                        for="is_default"
                                        class="cursor-pointer"
                                    >
                                        Default Tax
                                    </Label>
                                    <p
                                        class="text-xs text-muted-foreground mt-0.5"
                                    >
                                        Use as the default tax for this type.
                                        Unchecks other defaults.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Hidden submit trigger -->
            <button type="submit" class="hidden"></button>
        </div>
    </form>
</template>
