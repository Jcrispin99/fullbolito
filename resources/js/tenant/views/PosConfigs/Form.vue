<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { UnderlineInput } from "@/components/ui/underline-input";
import { SearchSelect } from "@/components/ui/search-select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Trash2, Plus } from "lucide-vue-next";
import { RouterLink } from "vue-router";

import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type { PosConfig } from "@tenant/stores/posConfig";

const props = defineProps<{
    initialData?: Partial<PosConfig>;
    formOptions?: { warehouses?: any[], customers?: any[], taxes?: any[], journals?: any[] };
    isEditing?: boolean;
    archived?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", payload: any): void;
}>();

const formData = ref({
    company_id: null as number | null,
    name: "",
    warehouse_id: null as number | null,
    default_customer_id: null as number | null,
    tax_id: null as number | null,
    apply_tax: false,
    prices_include_tax: false,
    is_active: true,
    default_lot_strategy: "fefo_suggest_manual" as
        | "fefo_auto"
        | "fefo_suggest_manual"
        | "manual",
    allow_expired_sale_with_override: false,
    lot_scan_mode: "product_only" as "product_only" | "hybrid",
    auto_print_receipt: false,
    journals: [] as any[],
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData && Object.keys(newData).length > 0) {
            formData.value = {
                company_id: newData.company_id || null,
                name: newData.name || "",
                warehouse_id: newData.warehouse_id || null,
                default_customer_id: newData.default_customer_id || null,
                tax_id: newData.tax_id || null,
                apply_tax: newData.apply_tax || false,
                prices_include_tax: newData.prices_include_tax || false,
                // In editing, trust the data; in create, default true
                is_active: newData.is_active !== undefined ? newData.is_active : true,
                default_lot_strategy:
                    (newData as any).default_lot_strategy || "fefo_suggest_manual",
                allow_expired_sale_with_override:
                    (newData as any).allow_expired_sale_with_override || false,
                lot_scan_mode: (newData as any).lot_scan_mode || "product_only",
                auto_print_receipt:
                    (newData as any).auto_print_receipt ?? false,
                journals: Array.isArray((newData as any).journals)
                    ? (newData as any).journals.map((j: any) => ({
                          journal_id: j.id || j.journal_id,
                          document_type: j.pivot?.document_type || j.document_type || "invoice",
                          is_default: j.pivot?.is_default || j.is_default || false,
                      }))
                    : [],
            };
        }
    },
    { immediate: true },
);

const warehouseOptions = computed(() => {
    return (props.formOptions?.warehouses || []).map((w) => ({
        value: w.id,
        label: w.name,
    }));
});

const customerOptions = computed(() => {
    return (props.formOptions?.customers || []).map((c) => ({
        value: c.id,
        label: c.display_name || c.name,
    }));
});

const taxOptions = computed(() => {
    return (props.formOptions?.taxes || []).map((t) => ({
        value: t.id,
        label: t.name,
    }));
});

// Etiqueta humana del document_type_code SUNAT
const docTypeCodeLabels: Record<string, string> = {
    "01": "Factura",
    "03": "Boleta",
    "07": "Nota de Crédito",
    "08": "Nota de Débito",
    "09": "Guía de Remisión",
};

// Mapeo doc_type_code SUNAT → valor del campo pivot.document_type
const docTypeCodeToFormValue: Record<string, string> = {
    "01": "invoice",
    "03": "receipt",
    "07": "credit_note",
    "08": "debit_note",
};

const journalOptions = computed(() => {
    return (props.formOptions?.journals || []).map((j) => {
        const docLabel = j.document_type_code
            ? docTypeCodeLabels[j.document_type_code] || j.document_type_code
            : j.type;
        let label = `${j.code || j.name} — ${docLabel}`;
        if (j.affects_document_type_code) {
            const affectsLabel = docTypeCodeLabels[j.affects_document_type_code] || j.affects_document_type_code;
            label += ` (afecta ${affectsLabel})`;
        }
        return { value: j.id, label };
    });
});

const documentTypeOptions = [
    { value: "invoice", label: "Factura" },
    { value: "receipt", label: "Boleta" },
    { value: "ticket", label: "Ticket (sin SUNAT)" },
    { value: "credit_note", label: "Nota de Crédito" },
    { value: "debit_note", label: "Nota de Débito" },
];

// Cuando el cajero elige un journal, autocompletar document_type según el doc_type_code
const onJournalChange = (index: number, value: number | string | boolean | null | undefined) => {
    const id = value === null || value === undefined ? null : Number(value);
    formData.value.journals[index].journal_id = id;
    if (!id) return;

    const picked = (props.formOptions?.journals || []).find((j: any) => j.id === id);
    const code = picked?.document_type_code;
    if (code && docTypeCodeToFormValue[code]) {
        formData.value.journals[index].document_type = docTypeCodeToFormValue[code];
    }
};

const lotStrategyOptions = [
    { value: "fefo_auto", label: "FEFO automático (sin intervención)" },
    { value: "fefo_suggest_manual", label: "FEFO sugerido (cajero puede cambiar)" },
    { value: "manual", label: "Manual (cajero siempre elige)" },
];

const lotScanModeOptions = [
    { value: "product_only", label: "Solo escaneo de producto" },
    { value: "hybrid", label: "Híbrido (producto o lote)" },
];

const addJournal = () => {
    formData.value.journals.push({
        journal_id: null,
        document_type: "invoice",
        is_default: false,
    });
};

const removeJournal = (index: number) => {
    formData.value.journals.splice(index, 1);
};

const handleSubmit = () => {
    const payload = { ...formData.value } as Record<string, any>;
    emit("submit", payload);
};

defineExpose({ submit: handleSubmit });
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="grid gap-6">
            <Card class="relative overflow-hidden">
                <CornerRibbon v-if="archived" label="Inactive" tone="danger" />

                <CardContent>
                    <div class="grid gap-6 pt-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name">
                                    Config Name
                                    <span class="text-destructive">*</span>
                                </Label>
                                <UnderlineInput
                                    id="name"
                                    v-model="formData.name"
                                    type="text"
                                    placeholder="e.g. Main POS"
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
                                <Label for="warehouse_id">Warehouse</Label>
                                <SearchSelect
                                    id="warehouse_id"
                                    v-model="formData.warehouse_id"
                                    :options="warehouseOptions"
                                    placeholder="Select Warehouse..."
                                />
                                <p
                                    v-if="errors?.warehouse_id"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.warehouse_id }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-2">
                            <div class="space-y-2">
                                <Label for="default_customer_id">
                                    Default Customer
                                    <span class="text-destructive">*</span>
                                </Label>
                                <SearchSelect
                                    id="default_customer_id"
                                    v-model="formData.default_customer_id"
                                    :options="customerOptions"
                                    placeholder="Select Default Customer..."
                                />
                                <p
                                    v-if="errors?.default_customer_id"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.default_customer_id }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-2">
                            <div class="flex items-center space-x-2 pt-6">
                                <Checkbox 
                                    id="apply_tax" 
                                    :checked="formData.apply_tax" 
                                    @update:checked="(v: boolean) => formData.apply_tax = v" 
                                />
                                <Label for="apply_tax">Apply Tax</Label>
                            </div>
                            
                            <div class="flex items-center space-x-2 pt-6">
                                <Checkbox
                                    id="prices_include_tax"
                                    :checked="formData.prices_include_tax"
                                    @update:checked="(v: boolean) => formData.prices_include_tax = v"
                                />
                                <Label for="prices_include_tax">Prices Include Tax</Label>
                            </div>

                            <div class="flex items-center space-x-2 pt-6 md:col-span-2">
                                <Checkbox
                                    id="auto_print_receipt"
                                    :checked="formData.auto_print_receipt"
                                    @update:checked="(v: boolean) => formData.auto_print_receipt = v"
                                />
                                <Label for="auto_print_receipt">
                                    Imprimir comprobante automáticamente al confirmar venta
                                </Label>
                            </div>

                            <div class="md:col-span-2 mt-1 flex items-center justify-between gap-3 rounded-md border border-dashed border-muted bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                <span>
                                    El diseño del comprobante es <strong>global</strong> para todas las cajas.
                                </span>
                                <RouterLink
                                    to="/admin/receipt-template"
                                    class="font-medium text-primary hover:underline whitespace-nowrap"
                                >
                                    Editar plantilla →
                                </RouterLink>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mt-2">
                            <div class="space-y-2">
                                <Label for="tax_id">Default Tax</Label>
                                <SearchSelect
                                    id="tax_id"
                                    v-model="formData.tax_id"
                                    :options="taxOptions"
                                    placeholder="Select Default Tax..."
                                    :disabled="!formData.apply_tax"
                                />
                                <p
                                    v-if="errors?.tax_id"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.tax_id }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium">Trazabilidad por lotes</h3>
                        <p class="text-sm text-muted-foreground">
                            Define cómo se asignan lotes a las ventas en este POS.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="default_lot_strategy">
                                Estrategia de asignación
                            </Label>
                            <SearchSelect
                                id="default_lot_strategy"
                                v-model="formData.default_lot_strategy"
                                :options="lotStrategyOptions"
                                :show-create="false"
                                placeholder="Seleccionar estrategia..."
                            />
                            <p
                                v-if="errors?.default_lot_strategy"
                                class="text-sm text-destructive"
                            >
                                {{ errors.default_lot_strategy }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="lot_scan_mode">Modo de escaneo</Label>
                            <SearchSelect
                                id="lot_scan_mode"
                                v-model="formData.lot_scan_mode"
                                :options="lotScanModeOptions"
                                :show-create="false"
                                placeholder="Seleccionar modo..."
                            />
                            <p
                                v-if="errors?.lot_scan_mode"
                                class="text-sm text-destructive"
                            >
                                {{ errors.lot_scan_mode }}
                            </p>
                        </div>

                        <div class="flex items-center space-x-2 pt-2 md:col-span-2">
                            <Checkbox
                                id="allow_expired_sale_with_override"
                                :checked="formData.allow_expired_sale_with_override"
                                @update:checked="(v: boolean) => formData.allow_expired_sale_with_override = v"
                            />
                            <Label for="allow_expired_sale_with_override">
                                Permitir vender lotes vencidos (con autorización)
                            </Label>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-medium">Diarios habilitados</h3>
                            <p class="text-sm text-muted-foreground">
                                Boletas y facturas que esta caja puede emitir, además de las notas de crédito/débito asociadas.
                            </p>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addJournal">
                            <Plus class="w-4 h-4 mr-2" />
                            Agregar diario
                        </Button>
                    </div>

                    <div v-if="formData.journals.length === 0" class="text-center py-4 text-muted-foreground border border-dashed rounded-md">
                        Sin diarios. Agrega al menos uno de venta y uno de nota de crédito.
                    </div>

                    <div class="space-y-4">
                        <div v-for="(journal, index) in formData.journals" :key="index" class="flex items-start gap-4 p-4 border rounded-md relative flex-wrap md:flex-nowrap">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 w-full mr-8">
                                <div class="space-y-2">
                                    <Label>Diario <span class="text-destructive">*</span></Label>
                                    <SearchSelect
                                        :model-value="journal.journal_id ?? undefined"
                                        :options="journalOptions"
                                        placeholder="Buscar diario..."
                                        @update:modelValue="(v) => onJournalChange(index, v)"
                                    />
                                    <p v-if="errors?.[`journals.${index}.journal_id`]" class="text-sm text-destructive">
                                        Required
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label>Tipo de documento <span class="text-destructive">*</span></Label>
                                    <SearchSelect
                                        v-model="journal.document_type"
                                        :options="documentTypeOptions"
                                        :show-create="false"
                                    />
                                </div>
                                <div class="flex items-center space-x-2 md:pt-8 w-full">
                                    <Checkbox
                                        :id="`is_default_${index}`"
                                        :checked="journal.is_default"
                                        @update:checked="(v: boolean) => journal.is_default = v"
                                    />
                                    <Label :class="{'cursor-pointer': true}" :for="`is_default_${index}`">
                                        Predeterminado
                                    </Label>
                                </div>
                            </div>
                            
                            <Button type="button" variant="ghost" size="icon" class="text-destructive absolute top-4 right-4" @click="removeJournal(index)">
                                <Trash2 class="w-4 h-4" />
                            </Button>
                        </div>
                    </div>
                    <p v-if="errors?.journals" class="text-sm text-destructive mt-2">
                        {{ errors.journals }}
                    </p>
                </CardContent>
            </Card>

            <button type="submit" class="hidden" ref="submitBtn"></button>
        </div>
    </form>
</template>
