<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { UnderlineInput } from "@/components/ui/underline-input";
import { SearchSelect } from "@/components/ui/search-select";

import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type { PaymentMethod } from "@tenant/stores/paymentMethod";

const props = defineProps<{
    initialData?: Partial<PaymentMethod>;
    isEditing?: boolean;
    archived?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", payload: any): void;
}>();

const formData = ref({
    name: "",
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            formData.value = {
                name: newData.name || "",
            };
        }
    },
    { immediate: true },
);

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
                    <div class="grid gap-6 pt-2">
                        <div class="grid grid-cols-1 gap-4">
                            <div class="space-y-2">
                                <Label for="name">
                                    Name
                                    <span class="text-destructive">*</span>
                                </Label>
                                <UnderlineInput
                                    id="name"
                                    v-model="formData.name"
                                    type="text"
                                    placeholder="e.g. Credit Card"
                                    required
                                />
                                <p
                                    v-if="errors?.name"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.name }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Required submit button for standard forms -->
            <button type="submit" class="hidden" ref="submitBtn"></button>
        </div>
    </form>
</template>
