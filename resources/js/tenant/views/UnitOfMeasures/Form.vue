<script setup lang="ts">
import { ref, watch, onMounted } from "vue";
import {
    Card,
    CardContent,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineSelect } from "@/components/ui/underline-select";
import { useUnitOfMeasureStore, type UnitOfMeasure } from "@tenant/stores/unitOfMeasure";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";

const props = defineProps<{
    initialData?: Partial<UnitOfMeasure> & { attribute_values?: any[] };
    isEditing?: boolean;
    archived?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", payload: any): void;
}>();

const store = useUnitOfMeasureStore();

// Available units for "Base Unit" selector (units in the same family)
const availableBaseUnits = ref<UnitOfMeasure[]>([]);

const formData = ref({
    name: "",
    symbol: "",
    family: "",
    base_unit_id: "" as number | string,
    factor: "" as number | string,
});

// Watch to hydrate form when initialData arrives (editing mode)
watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            formData.value.name = newData.name ?? "";
            formData.value.symbol = newData.symbol ?? "";
            formData.value.family = newData.family ?? "";
            formData.value.base_unit_id = newData.base_unit_id ?? "";
            formData.value.factor = newData.factor ?? "";
        }
    },
    { immediate: true },
);

// Load base unit options from the form-options endpoint
onMounted(async () => {
    try {
        const { data } = await import("@tenant/lib/api").then(
            (m) => m.apiClient.get<any>("/v1/unit-of-measures/form-options"),
        );
        availableBaseUnits.value = data.data?.unit_of_measures ?? [];
    } catch {
        availableBaseUnits.value = [];
    }
});

const isBaseUnit = () => !formData.value.base_unit_id;

const handleSubmit = () => {
    const payload: Record<string, any> = {
        name: formData.value.name,
        symbol: formData.value.symbol || null,
        family: formData.value.family,
    };

    if (formData.value.base_unit_id) {
        payload.base_unit_id = Number(formData.value.base_unit_id);
        payload.factor = formData.value.factor ? Number(formData.value.factor) : 1;
    } else {
        payload.base_unit_id = null;
        payload.factor = 1; // Base units always have factor 1
    }

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
                    <div class="grid gap-6">
                        <!-- Name & Symbol on the same row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="name">
                                    Name <span class="text-destructive">*</span>
                                </Label>
                                <UnderlineInput
                                    id="name"
                                    v-model="formData.name"
                                    placeholder="e.g. Kilogram"
                                    required
                                />
                                <p v-if="errors?.name" class="text-sm text-destructive">{{ errors.name }}</p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="symbol">Symbol</Label>
                                <UnderlineInput
                                    id="symbol"
                                    v-model="formData.symbol"
                                    placeholder="e.g. kg"
                                />
                                <p v-if="errors?.symbol" class="text-sm text-destructive">{{ errors.symbol }}</p>
                            </div>
                        </div>

                        <!-- Family -->
                        <div class="grid gap-2">
                            <Label for="family">
                                Family <span class="text-destructive">*</span>
                            </Label>
                            <UnderlineInput
                                id="family"
                                v-model="formData.family"
                                placeholder="e.g. Weight, Volume, Length"
                                required
                            />
                            <p class="text-xs text-muted-foreground">
                                Group related units (e.g., kg, g, mg all belong to "Weight").
                            </p>
                            <p v-if="errors?.family" class="text-sm text-destructive">{{ errors.family }}</p>
                        </div>

                        <!-- Base Unit & Factor -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="base_unit_id">Base Unit</Label>
                                <UnderlineSelect
                                    id="base_unit_id"
                                    v-model="formData.base_unit_id"
                                >
                                    <option value="">— This is a base unit —</option>
                                    <option
                                        v-for="u in availableBaseUnits"
                                        :key="u.id"
                                        :value="u.id"
                                        :disabled="isEditing && u.id === initialData?.id"
                                    >
                                        {{ u.name }} ({{ u.symbol ?? u.family }})
                                    </option>
                                </UnderlineSelect>
                                <p class="text-xs text-muted-foreground">
                                    Leave empty if this unit is the base of its family.
                                </p>
                                <p v-if="errors?.base_unit_id" class="text-sm text-destructive">{{ errors.base_unit_id }}</p>
                            </div>

                            <div class="grid gap-2" :class="{ 'opacity-40 pointer-events-none': isBaseUnit() }">
                                <Label for="factor">Conversion Factor</Label>
                                <UnderlineInput
                                    id="factor"
                                    v-model="formData.factor"
                                    type="number"
                                    step="any"
                                    min="0.00000001"
                                    placeholder="e.g. 1000 (g per kg)"
                                    :disabled="isBaseUnit()"
                                />
                                <p class="text-xs text-muted-foreground">
                                    How many of this unit = 1 base unit. Auto-set to 1 for base units.
                                </p>
                                <p v-if="errors?.factor" class="text-sm text-destructive">{{ errors.factor }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </form>
</template>
