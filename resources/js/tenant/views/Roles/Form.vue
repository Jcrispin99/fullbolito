<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { UnderlineInput } from "@/components/ui/underline-input";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ChevronDown, ChevronRight } from "lucide-vue-next";
import type { Role } from "@/types/models";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<Role>;
    permissionsGrouped?: Record<string, string[]>;
    isLoading?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const form = ref({
    name: "",
    permissions: [] as string[],
});

const collapsed = ref<Record<string, boolean>>({});
const search = ref("");

watch(
    () => props.initialData,
    (data) => {
        if (data) {
            form.value.name = data.name || "";
            form.value.permissions = [...(data.permissions || [])];
        }
    },
    { immediate: true },
);

const isSystemRole = computed(() => !!props.initialData?.is_system);

const filteredGroups = computed(() => {
    const groups = props.permissionsGrouped || {};
    const term = search.value.trim().toLowerCase();
    if (!term) return groups;

    const out: Record<string, string[]> = {};
    for (const [group, perms] of Object.entries(groups)) {
        const matched = perms.filter(
            (p) =>
                p.toLowerCase().includes(term) ||
                group.toLowerCase().includes(term),
        );
        if (matched.length) out[group] = matched;
    }
    return out;
});

const togglePermission = (name: string) => {
    const idx = form.value.permissions.indexOf(name);
    if (idx === -1) form.value.permissions.push(name);
    else form.value.permissions.splice(idx, 1);
};

const groupAllSelected = (perms: string[]) =>
    perms.every((p) => form.value.permissions.includes(p));

const groupSomeSelected = (perms: string[]) =>
    perms.some((p) => form.value.permissions.includes(p));

const toggleGroup = (perms: string[]) => {
    if (groupAllSelected(perms)) {
        form.value.permissions = form.value.permissions.filter(
            (p) => !perms.includes(p),
        );
    } else {
        const set = new Set([...form.value.permissions, ...perms]);
        form.value.permissions = [...set];
    }
};

const toggleCollapse = (group: string) => {
    collapsed.value[group] = !collapsed.value[group];
};

const selectAll = () => {
    const all = Object.values(props.permissionsGrouped || {}).flat();
    form.value.permissions = [...new Set(all)];
};

const clearAll = () => {
    form.value.permissions = [];
};

const submit = () => {
    emit("submit", {
        name: form.value.name,
        permissions: form.value.permissions,
    });
};

defineExpose({ submit });
</script>

<template>
    <Card class="w-full relative overflow-hidden">
        <CardContent class="pt-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Row: Nombre -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="name">Nombre del rol</Label>
                        <UnderlineInput
                            id="name"
                            v-model="form.name"
                            placeholder="ej. jefe_almacen"
                            :disabled="isSystemRole"
                            required
                        />
                        <p
                            v-if="isSystemRole"
                            class="text-xs text-muted-foreground"
                        >
                            Los roles del sistema no se pueden renombrar.
                        </p>
                        <p
                            v-if="errors?.name"
                            class="text-sm text-destructive"
                        >
                            {{ errors.name }}
                        </p>
                    </div>
                </div>

                <!-- Permisos -->
                <div class="space-y-3 pt-4 border-t">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <Label class="text-base">
                            Permisos
                            <span
                                class="ml-2 text-sm text-muted-foreground"
                            >
                                ({{ form.permissions.length }} seleccionados)
                            </span>
                        </Label>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="selectAll"
                            >
                                Todos
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="clearAll"
                            >
                                Ninguno
                            </Button>
                        </div>
                    </div>

                    <UnderlineInput
                        v-model="search"
                        placeholder="Buscar permiso o grupo..."
                        class="max-w-sm"
                    />

                    <div class="space-y-3">
                        <div
                            v-for="(perms, group) in filteredGroups"
                            :key="group"
                            class="rounded-md border"
                        >
                            <div
                                class="flex items-center gap-2 p-3 cursor-pointer hover:bg-muted/50"
                                @click="toggleCollapse(group as string)"
                            >
                                <component
                                    :is="
                                        collapsed[group as string]
                                            ? ChevronRight
                                            : ChevronDown
                                    "
                                    class="size-4"
                                />
                                <Checkbox
                                    :checked="groupAllSelected(perms)"
                                    :indeterminate="
                                        !groupAllSelected(perms) &&
                                        groupSomeSelected(perms)
                                    "
                                    @click.stop
                                    @update:checked="toggleGroup(perms)"
                                />
                                <span class="font-medium capitalize">
                                    {{ String(group).replace(/_/g, " ") }}
                                </span>
                                <Badge
                                    variant="secondary"
                                    class="ml-auto"
                                >
                                    {{
                                        perms.filter((p) =>
                                            form.permissions.includes(p),
                                        ).length
                                    }}
                                    / {{ perms.length }}
                                </Badge>
                            </div>

                            <div
                                v-if="!collapsed[group as string]"
                                class="border-t p-3 grid gap-2 sm:grid-cols-2 md:grid-cols-3"
                            >
                                <label
                                    v-for="p in perms"
                                    :key="p"
                                    class="flex items-center gap-2 text-sm cursor-pointer"
                                >
                                    <Checkbox
                                        :checked="
                                            form.permissions.includes(p)
                                        "
                                        @update:checked="
                                            togglePermission(p)
                                        "
                                    />
                                    <span class="font-mono text-xs">
                                        {{ p }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="errors?.permissions"
                        class="text-sm text-destructive"
                    >
                        {{ errors.permissions }}
                    </p>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
