<script setup lang="ts">
import { ref, watch } from "vue";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import type { Module } from "@/types/models";
import CornerRibbon from "@/central/components/CornerRibbon.vue";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<Module>;
    isLoading?: boolean;
    errors?: Record<string, string>;
    archived?: boolean;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const form = ref({
    key: "",
    label: "",
    description: "",
    icon: "",
    addon_price: 0,
    sort_order: 0,
    is_active: true,
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            form.value = {
                key: newData.key || "",
                label: newData.label || "",
                description: newData.description || "",
                icon: newData.icon || "",
                addon_price: Number(newData.addon_price ?? 0),
                sort_order: Number(newData.sort_order ?? 0),
                is_active: newData.is_active ?? true,
            };
        }
    },
    { immediate: true },
);

const submit = () => {
    emit("submit", {
        ...form.value,
        // Send empty strings as null so the API validates as nullable.
        description: form.value.description || null,
        icon: form.value.icon || null,
    });
};

defineExpose({ submit });
</script>

<template>
    <Card class="w-full relative overflow-hidden">
        <CornerRibbon v-if="archived" label="Archivado" tone="danger" />
        <CardContent class="pt-6">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="label">Nombre del módulo</Label>
                        <Input
                            id="label"
                            v-model="form.label"
                            placeholder="p. ej., Ventas"
                            required
                        />
                        <p v-if="errors?.label" class="text-sm text-destructive">
                            {{ errors.label }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="key">Clave</Label>
                        <Input
                            id="key"
                            v-model="form.key"
                            placeholder="p. ej., ventas"
                            :disabled="mode === 'edit'"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Identificador interno (snake_case). No se puede cambiar después.
                        </p>
                        <p v-if="errors?.key" class="text-sm text-destructive">
                            {{ errors.key }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label htmlFor="description">Descripción</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Breve descripción del módulo..."
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="icon">Icono (lucide)</Label>
                        <Input
                            id="icon"
                            v-model="form.icon"
                            placeholder="p. ej., shopping-cart"
                        />
                        <p class="text-xs text-muted-foreground">
                            Nombre del icono lucide-vue-next.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label htmlFor="sort_order">Orden</Label>
                        <Input
                            id="sort_order"
                            type="number"
                            v-model="form.sort_order"
                        />
                    </div>
                </div>

                <div class="max-w-sm">
                    <div class="space-y-2">
                        <Label htmlFor="addon_price">Precio adicional</Label>
                        <Input
                            id="addon_price"
                            type="number"
                            step="0.01"
                            v-model="form.addon_price"
                        />
                        <p class="text-xs text-muted-foreground">
                            Si es mayor que 0, el módulo se puede contratar como complemento.
                        </p>
                        <p v-if="errors?.addon_price" class="text-sm text-destructive">
                            {{ errors.addon_price }}
                        </p>
                    </div>

                </div>

                <div class="flex items-center gap-3 pt-2">
                    <Checkbox
                        id="is_active"
                        :checked="form.is_active"
                        @update:checked="(v: boolean) => (form.is_active = v)"
                    />
                    <Label htmlFor="is_active" class="cursor-pointer">
                        Módulo activo
                    </Label>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
