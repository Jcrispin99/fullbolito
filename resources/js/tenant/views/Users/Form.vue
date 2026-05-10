<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { UnderlineInput } from "@/components/ui/underline-input";
import { MultiSelect } from "@/components/ui/multi-select";
import type { User } from "@/types/models";

interface RoleOption {
    id: number;
    name: string;
    is_system: boolean;
}

interface CompanyOption {
    id: number;
    business_name: string;
}

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<User>;
    formOptions?: { roles?: RoleOption[]; companies?: CompanyOption[] };
    isLoading?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const form = ref({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    roles: [] as string[],
    company_ids: [] as number[],
});

watch(
    () => props.initialData,
    (data) => {
        if (data) {
            form.value.name = data.name || "";
            form.value.email = data.email || "";
            form.value.roles = [...(data.roles || [])];
            form.value.company_ids = (data.companies || []).map((c) => c.id);
            form.value.password = "";
            form.value.password_confirmation = "";
        }
    },
    { immediate: true },
);

const roleOptions = computed(() =>
    (props.formOptions?.roles || []).map((r) => ({
        value: r.name,
        label: r.name,
        description: r.is_system ? "rol del sistema" : null,
    })),
);

const companyOptions = computed(() =>
    (props.formOptions?.companies || []).map((c) => ({
        value: c.id,
        label: c.business_name,
    })),
);

const submit = () => {
    const payload: any = {
        name: form.value.name,
        email: form.value.email,
        roles: form.value.roles,
        company_ids: form.value.company_ids,
    };

    if (form.value.password) {
        payload.password = form.value.password;
        payload.password_confirmation = form.value.password_confirmation;
    }

    emit("submit", payload);
};

defineExpose({ submit });
</script>

<template>
    <Card class="w-full relative overflow-hidden">
        <CardContent class="pt-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Datos básicos -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="name">Nombre</Label>
                        <UnderlineInput
                            id="name"
                            v-model="form.name"
                            placeholder="ej. Juan Pérez"
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
                        <Label htmlFor="email">Email</Label>
                        <UnderlineInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="usuario@empresa.com"
                            required
                        />
                        <p
                            v-if="errors?.email"
                            class="text-sm text-destructive"
                        >
                            {{ errors.email }}
                        </p>
                    </div>
                </div>

                <!-- Password -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="password">
                            {{
                                mode === "create"
                                    ? "Contraseña"
                                    : "Nueva contraseña (opcional)"
                            }}
                        </Label>
                        <UnderlineInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            placeholder="Mínimo 8 caracteres"
                            :required="mode === 'create'"
                        />
                        <p
                            v-if="errors?.password"
                            class="text-sm text-destructive"
                        >
                            {{ errors.password }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="password_confirmation">
                            Confirmar contraseña
                        </Label>
                        <UnderlineInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            placeholder="Repetir contraseña"
                            :required="
                                mode === 'create' || !!form.password
                            "
                        />
                    </div>
                </div>

                <!-- Roles asignados -->
                <div class="space-y-2 pt-4 border-t">
                    <Label>Roles asignados</Label>
                    <MultiSelect
                        v-model="form.roles"
                        :options="roleOptions"
                        placeholder="Selecciona uno o varios roles..."
                        search-placeholder="Buscar rol..."
                        empty-message="No hay roles disponibles."
                    />
                    <p
                        v-if="errors?.roles"
                        class="text-sm text-destructive"
                    >
                        {{ errors.roles }}
                    </p>
                </div>

                <!-- Empresas con acceso -->
                <div class="space-y-2 pt-4 border-t">
                    <Label>Empresas con acceso</Label>
                    <MultiSelect
                        v-model="form.company_ids"
                        :options="companyOptions"
                        placeholder="Selecciona empresas..."
                        search-placeholder="Buscar empresa..."
                        empty-message="No hay empresas activas."
                    />
                    <p class="text-xs text-muted-foreground">
                        Si no seleccionas ninguna, el usuario no verá datos
                        filtrados por empresa.
                    </p>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
