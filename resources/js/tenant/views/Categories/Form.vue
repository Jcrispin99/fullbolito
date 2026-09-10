<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { UnderlineInput } from "@/components/ui/underline-input";
import { SearchSelect } from "@/components/ui/search-select";

import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type { Category } from "@tenant/stores/category";

const props = defineProps<{
    initialData?: Partial<Category>;
    parentOptions?: Category[];
    isEditing?: boolean;
    archived?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", payload: any): void;
}>();

const formData = ref({
    name: "",
    description: "",
    parent_id: "" as number | string,
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            formData.value = {
                name: newData.name || "",
                description: newData.description || "",
                parent_id: newData.parent_id || "",
            };
        }
    },
    { immediate: true },
);

const parentCategoryOptions = computed(() => {
    const opts = props.parentOptions || [];
    const currentId = (props.initialData as any)?.id;
    return opts
        .filter((c) => (currentId ? String(c.id) !== String(currentId) : true))
        .map((c) => ({
            value: c.id,
            label: c.name,
        }));
});

const handleSubmit = () => {
    const payload = { ...formData.value } as Record<string, any>;
    payload.parent_id = payload.parent_id === "" ? null : payload.parent_id;
    emit("submit", payload);
};

defineExpose({ submit: handleSubmit });
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="grid gap-6">
            <Card class="relative overflow-hidden">
                <CornerRibbon v-if="archived" label="Inactiva" tone="danger" />

                <CardContent>
                    <div class="grid gap-6 pt-2">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name">
                                    Nombre
                                    <span class="text-destructive">*</span>
                                </Label>
                                <UnderlineInput
                                    id="name"
                                    v-model="formData.name"
                                    type="text"
                                    placeholder="Ej. Bebidas"
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
                                <Label for="parent_id">Categoría principal</Label>
                                <SearchSelect
                                    id="parent_id"
                                    v-model="formData.parent_id"
                                    :options="parentCategoryOptions"
                                    placeholder="Buscar..."
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Descripción</Label>
                            <Textarea
                                id="description"
                                v-model="formData.description"
                                placeholder="Detalles de esta categoría"
                                rows="3"
                            />
                            <p
                                v-if="errors?.description"
                                class="text-sm text-destructive"
                            >
                                {{ errors.description }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Required submit button for standard forms -->
            <button type="submit" class="hidden" ref="submitBtn"></button>
        </div>
    </form>
</template>
