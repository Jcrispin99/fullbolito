<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useRoleStore } from "@tenant/stores/role";
import { storeToRefs } from "pinia";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import RoleForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import type { Role } from "@/types/models";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import { ArrowLeft, Save, Settings2, Trash2 } from "lucide-vue-next";
import { toast } from "vue-sonner";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const route = useRoute();
const router = useRouter();
const roleStore = useRoleStore();

const mode = computed(() => (route.params.id ? "edit" : "create"));
const roleId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const roleIdStr = computed(() => String(route.params.id || ""));

const { roles } = storeToRefs(roleStore);
const {
    currentIndex: roleIdx,
    prevRecord: prevRole,
    nextRecord: nextRole,
    navigatePrev: navPrevRole,
    navigateNext: navNextRole,
} = useRecordNavigator(roles, roleIdStr, "/admin/roles", () =>
    roleStore.fetchRoles(1, "total"),
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentRole = ref<Partial<Role>>({});
const permissionsGrouped = ref<Record<string, string[]>>({});
const roleForm = ref<InstanceType<typeof RoleForm> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManageRole = computed(() => mode.value === "edit" && !!roleId.value);
const isSystemRole = computed(() => !!currentRole.value?.is_system);

watch(
    () => route.params.id,
    async () => {
        isLoading.value = true;
        try {
            const opts = await roleStore.fetchFormOptions();
            permissionsGrouped.value = opts?.permissions_grouped || {};

            if (mode.value === "edit" && roleId.value) {
                const data = await roleStore.fetchRole(roleId.value);
                if (data) currentRole.value = data;
            } else {
                currentRole.value = {};
            }
        } catch (error) {
            console.error("Error fetching role:", error);
            router.push("/admin/roles");
        } finally {
            isLoading.value = false;
        }
    },
    { immediate: true },
);

const handleSubmit = async (formData: any) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (mode.value === "edit" && roleId.value) {
            await roleStore.updateRole(roleId.value, formData);
            toast.success("Rol actualizado", {
                description: "El rol fue actualizado correctamente.",
            });
        } else {
            const created = await roleStore.createRole(formData);
            toast.success("Rol creado", {
                description: "El rol fue creado correctamente.",
            });
            router.push(`/admin/roles/${created.id}/edit`);
            return;
        }
    } catch (err: any) {
        const e = err?.response?.data;
        if (e?.errors) {
            const flat: Record<string, string> = {};
            Object.entries(e.errors).forEach(([k, v]: any) => {
                flat[k] = Array.isArray(v) ? v[0] : String(v);
            });
            errors.value = flat;
            toast.error("Error de validación", {
                description: "Revisa los campos del formulario.",
            });
        } else {
            console.error("Error saving role:", err);
            toast.error("Error al guardar", {
                description:
                    err?.response?.data?.message || "Ocurrió un error inesperado.",
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/roles");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Editar Rol" : "Nuevo Rol",
);

const handleSave = () => {
    roleForm.value?.submit();
};

const handleDelete = () => {
    if (!roleId.value) return;
    if (isSystemRole.value) {
        toast.error("No se puede eliminar un rol del sistema.");
        return;
    }
    const id = roleId.value;
    confirmDialog.value?.show(
        "Eliminar rol",
        "¿Seguro que deseas eliminar este rol? No se podrá si tiene usuarios asignados.",
        async () => {
            isLoading.value = true;
            try {
                await roleStore.deleteRole(id);
                router.push("/admin/roles");
            } catch (err: any) {
                toast.error(
                    err?.response?.data?.message || "Error al eliminar",
                );
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const breadcrumbs = computed(() => [
    { label: "Roles", href: "/admin/roles" },
    { label: mode.value === "edit" ? "Editar Rol" : "Nuevo Rol" },
]);
</script>

<template>
    <DashboardLayout :breadcrumbs="breadcrumbs">
        <PageHeader :title="pageTitle">
            <template #leading>
                <Button
                    variant="outline"
                    size="icon"
                    class="h-9 w-9"
                    aria-label="Volver"
                    @click="handleCancel"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </template>

            <template #trailing>
                <Button
                    size="sm"
                    class="h-9"
                    :disabled="isLoading"
                    @click="handleSave"
                >
                    <Save class="mr-2 h-4 w-4" />
                    {{
                        isLoading
                            ? "Guardando..."
                            : mode === "edit"
                              ? "Actualizar"
                              : "Crear"
                    }}
                </Button>
                <DropdownMenu v-if="canManageRole">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            aria-label="Opciones"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>Opciones</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-destructive"
                            :disabled="isSystemRole"
                            @click="handleDelete"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <RecordNavigator
                    v-if="mode === 'edit'"
                    :current-index="roleIdx"
                    :total="roles.length"
                    :has-prev="!!prevRole"
                    :has-next="!!nextRole"
                    :disabled="isLoading"
                    @prev="navPrevRole"
                    @next="navNextRole"
                />
            </template>
        </PageHeader>

        <RoleForm
            ref="roleForm"
            :mode="mode"
            :initial-data="currentRole"
            :permissions-grouped="permissionsGrouped"
            :is-loading="isLoading"
            :errors="errors"
            @submit="handleSubmit"
        />
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
