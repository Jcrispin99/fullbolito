<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useUserStore } from "@tenant/stores/user";
import { storeToRefs } from "pinia";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import UserForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import type { User } from "@/types/models";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import { ArrowLeft, Save, Settings2, Trash2 } from "lucide-vue-next";
import { toast } from "vue-sonner";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
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
const userStore = useUserStore();

const mode = computed(() => (route.params.id ? "edit" : "create"));
const userId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const userIdStr = computed(() => String(route.params.id || ""));

const { users } = storeToRefs(userStore);
const {
    currentIndex: userIdx,
    prevRecord: prevUser,
    nextRecord: nextUser,
    navigatePrev: navPrevUser,
    navigateNext: navNextUser,
} = useRecordNavigator(users, userIdStr, "/admin/users", () =>
    userStore.fetchUsers(1, "total"),
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentUser = ref<Partial<User>>({});
const formOptions = ref<any>({ roles: [], companies: [] });
const userForm = ref<InstanceType<typeof UserForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManageUser = computed(() => mode.value === "edit" && !!userId.value);

watch(
    () => route.params.id,
    async () => {
        isLoading.value = true;
        try {
            const opts = await userStore.fetchFormOptions();
            if (opts) formOptions.value = opts;

            if (mode.value === "edit" && userId.value) {
                const data = await userStore.fetchUser(userId.value);
                if (data) currentUser.value = data;
            } else {
                currentUser.value = {};
            }
        } catch (error) {
            console.error("Error fetching user:", error);
            router.push("/admin/users");
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
        if (mode.value === "edit" && userId.value) {
            await userStore.updateUser(userId.value, formData);
            toast.success("Usuario actualizado", {
                description: "El usuario fue actualizado correctamente.",
            });
            activityLogRef.value?.load();
        } else {
            const created = await userStore.createUser(formData);
            toast.success("Usuario creado", {
                description: "El usuario fue creado correctamente.",
            });
            router.push(`/admin/users/${created.id}/edit`);
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
            console.error("Error saving user:", err);
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
    router.push("/admin/users");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Editar Usuario" : "Nuevo Usuario",
);

const handleSave = () => {
    userForm.value?.submit();
};

const handleDelete = () => {
    if (!userId.value) return;
    const id = userId.value;
    confirmDialog.value?.show(
        "Eliminar usuario",
        "¿Seguro que deseas eliminar este usuario? Esta acción no se puede deshacer.",
        async () => {
            isLoading.value = true;
            try {
                await userStore.deleteUser(id);
                router.push("/admin/users");
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
    { label: "Usuarios", href: "/admin/users" },
    { label: mode.value === "edit" ? "Editar Usuario" : "Nuevo Usuario" },
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
                <DropdownMenu v-if="canManageUser">
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
                            @click="handleDelete"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <RecordNavigator
                    v-if="mode === 'edit'"
                    :current-index="userIdx"
                    :total="users.length"
                    :has-prev="!!prevUser"
                    :has-next="!!nextUser"
                    :disabled="isLoading"
                    @prev="navPrevUser"
                    @next="navNextUser"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <UserForm
                        ref="userForm"
                        :mode="mode"
                        :initial-data="currentUser"
                        :form-options="formOptions"
                        :is-loading="isLoading"
                        :errors="errors"
                        @submit="handleSubmit"
                    />
                </div>

                <div
                    class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0"
                >
                    <ActivityLogPanel
                        ref="activityLogRef"
                        subject="user"
                        :subject-id="userId ? Number(userId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
