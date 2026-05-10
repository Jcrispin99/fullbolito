<script setup lang="ts">
import { ref, watch } from "vue";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type { Attribute } from "@tenant/stores/attribute";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineMultiSelect } from "@/components/ui/underline-multi-select";

const props = defineProps<{
    initialData?: Partial<Attribute>;
    isEditing?: boolean;
    archived?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", payload: any): void;
}>();

const formData = ref({
    name: "",
    values: [] as string[],
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            formData.value.name = newData.name || "";
            // Assuming API responds with attribute_values containing { value: string }
            if (newData.values) {
                formData.value.values = newData.values.map(v => v.value || v);
            } else {
                formData.value.values = [];
            }
        }
    },
    { immediate: true },
);

const handleSubmit = () => {
    emit("submit", { 
        name: formData.value.name, 
        values: formData.value.values 
    });
};

defineExpose({ submit: handleSubmit });
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="grid gap-6">
            <Card class="relative overflow-hidden">
                <CornerRibbon
                    v-if="archived"
                    label="Inactive"
                    tone="danger"
                />
                <CardContent class="pt-6">
                    <div class="grid gap-6">
                        <div class="space-y-2">
                            <Label for="name">Attribute Name <span class="text-destructive">*</span></Label>
                            <UnderlineInput
                                id="name"
                                v-model="formData.name"
                                type="text"
                                placeholder="e.g. Size, Color, Capacity"
                                required
                            />
                            <p v-if="errors?.name" class="text-sm text-destructive">
                                {{ errors.name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex flex-col gap-2">
                                <Label>Attribute Values</Label>
                                <div class="text-[0.8rem] text-muted-foreground pb-1">
                                    Type a value and press Enter or comma (,) to add it to the list.
                                </div>
                                <UnderlineMultiSelect v-model="formData.values" />
                                <p v-if="errors?.values" class="text-sm text-destructive">
                                    {{ errors.values }}
                                </p>
                            </div>
                        </div>

                    </div>
                </CardContent>
            </Card>

            <button type="submit" class="hidden" ref="submitBtn"></button>
        </div>
    </form>
</template>
