<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import type { Module, Plan } from "@/types/models";
import CornerRibbon from "@/central/components/CornerRibbon.vue";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<Plan>;
    availableModules?: Module[];
    isLoading?: boolean;
    errors?: Record<string, string>;
    archived?: boolean;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const form = ref({
    name: "",
    slug: "",
    description: "",
    price: 0,
    duration_days: 30,
    includes_all_modules: false,
    module_ids: [] as number[],
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            form.value = {
                name: newData.name || "",
                slug: newData.slug || "",
                description: (newData as any).description || "",
                price: Number(newData.price ?? 0),
                duration_days: Number(newData.duration_days ?? 30),
                includes_all_modules: Boolean(newData.includes_all_modules),
                module_ids: Array.isArray(newData.module_ids)
                    ? [...newData.module_ids]
                    : [],
            };
        }
    },
    { immediate: true },
);

const toggleModule = (id: number, checked: boolean) => {
    if (checked) {
        if (!form.value.module_ids.includes(id)) {
            form.value.module_ids.push(id);
        }
    } else {
        form.value.module_ids = form.value.module_ids.filter((mid) => mid !== id);
    }
};

const moduleListLabel = computed(() =>
    form.value.includes_all_modules
        ? "Este plan incluye automáticamente todos los módulos activos."
        : `Selección manual (${form.value.module_ids.length} ${form.value.module_ids.length === 1 ? "módulo" : "módulos"})`,
);

const submit = () => {
    emit("submit", {
        ...form.value,
        // When the wildcard flag is on, the pivot list becomes irrelevant —
        // wipe it so the API doesn't store stale state we'd have to ignore.
        module_ids: form.value.includes_all_modules ? [] : form.value.module_ids,
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
                        <Label htmlFor="name">Nombre del plan</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="p. ej., Plan Básico"
                            required
                        />
                        <p v-if="errors?.name" class="text-sm text-destructive">
                            {{ errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="slug">Slug</Label>
                        <Input
                            id="slug"
                            v-model="form.slug"
                            placeholder="p. ej., basico-mensual"
                            required
                        />
                        <p v-if="errors?.slug" class="text-sm text-destructive">
                            {{ errors.slug }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label htmlFor="description">Descripción (opcional)</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Breve descripción del plan..."
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="price">Precio</Label>
                        <Input
                            id="price"
                            type="number"
                            step="0.01"
                            v-model="form.price"
                            required
                        />
                        <p v-if="errors?.price" class="text-sm text-destructive">
                            {{ errors.price }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="duration">Duración (días)</Label>
                        <Input
                            id="duration"
                            type="number"
                            v-model="form.duration_days"
                            required
                        />
                        <p
                            v-if="errors?.duration_days"
                            class="text-sm text-destructive"
                        >
                            {{ errors.duration_days }}
                        </p>
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t">
                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="includes_all_modules"
                            :checked="form.includes_all_modules"
                            @update:checked="(v: boolean) => (form.includes_all_modules = v)"
                        />
                        <div class="space-y-0.5">
                            <Label
                                htmlFor="includes_all_modules"
                                class="cursor-pointer"
                            >
                                Incluir todos los módulos activos
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                Los planes con acceso total incluyen
                                automáticamente todos los módulos activos,
                                incluso a módulos creados en el futuro.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Módulos incluidos</Label>
                        <p class="text-xs text-muted-foreground">
                            {{ moduleListLabel }}
                        </p>

                        <div
                            v-if="!form.includes_all_modules"
                            class="grid gap-2 md:grid-cols-2 lg:grid-cols-3 pt-1"
                        >
                            <label
                                v-for="m in availableModules ?? []"
                                :key="m.id"
                                class="flex items-start gap-2 rounded-md border px-3 py-2 cursor-pointer hover:bg-muted/40"
                            >
                                <Checkbox
                                    :checked="form.module_ids.includes(m.id)"
                                    @update:checked="(checked: boolean) => toggleModule(m.id, checked)"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate">
                                        {{ m.label }}
                                    </div>
                                    <code
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{ m.key }}
                                    </code>
                                </div>
                            </label>
                            <p
                                v-if="(availableModules ?? []).length === 0"
                                class="text-sm text-muted-foreground col-span-full py-2"
                            >
                                No hay módulos activos disponibles. Crea
                                módulos en la sección Módulos.
                            </p>
                        </div>

                        <p
                            v-if="errors?.module_ids"
                            class="text-sm text-destructive"
                        >
                            {{ errors.module_ids }}
                        </p>
                    </div>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
