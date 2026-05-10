<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useModuleStore } from "@/central/stores/module";
import DashboardLayout from "@/central/layouts/DashboardLayout.vue";
import ModuleForm from "./Form.vue";
import type { Module } from "@/types/models";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import { ArrowLeft, Archive, Save, Settings2, Trash2 } from "lucide-vue-next";
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
const moduleStore = useModuleStore();

const mode = computed(() => (route.params.id ? "edit" : "create"));
const moduleId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentModule = ref<Partial<Module>>({});
const moduleForm = ref<InstanceType<typeof ModuleForm> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManage = computed(() => mode.value === "edit" && !!moduleId.value);
const archiveLabel = computed(() =>
    (currentModule.value as any)?.is_active === false ? "Reactivar" : "Archivar",
);
const isArchived = computed(
    () =>
        mode.value === "edit" &&
        (currentModule.value as any)?.is_active === false,
);

onMounted(async () => {
    if (mode.value === "edit" && moduleId.value) {
        isLoading.value = true;
        try {
            const m = await moduleStore.fetchModule(moduleId.value);
            if (m) currentModule.value = m;
        } catch (e) {
            console.error("Error fetching module:", e);
            router.push("/modules");
        } finally {
            isLoading.value = false;
        }
    }
});

const handleSubmit = async (formData: any) => {
    isLoading.value = true;
    errors.value = {};
    try {
        if (mode.value === "edit" && moduleId.value) {
            await moduleStore.updateModule(moduleId.value, formData);
        } else {
            await moduleStore.createModule(formData);
        }
        router.push("/modules");
    } catch (err: any) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors;
        } else {
            console.error("Error saving module:", err);
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => router.push("/modules");

const pageTitle = computed(() =>
    mode.value === "edit" ? "Editar módulo" : "Crear módulo",
);

const handleSave = () => moduleForm.value?.submit();

const handleArchive = () => {
    if (!moduleId.value) return;
    const id = moduleId.value;
    confirmDialog.value?.show(
        `${archiveLabel.value} módulo`,
        `¿Seguro que quieres ${archiveLabel.value.toLowerCase()} este módulo?`,
        async () => {
            isLoading.value = true;
            try {
                const updated = await moduleStore.toggleStatus(id);
                currentModule.value = updated;
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!moduleId.value) return;
    const id = moduleId.value;
    confirmDialog.value?.show(
        "Eliminar módulo",
        "¿Seguro que quieres eliminar este módulo? Esta acción no se puede deshacer.",
        async () => {
            isLoading.value = true;
            try {
                await moduleStore.deleteModule(id);
                router.push("/modules");
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const breadcrumbs = computed(() => [
    { label: "Módulos", href: "/modules" },
    { label: mode.value === "edit" ? "Editar" : "Crear" },
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
                <DropdownMenu v-if="canManage">
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
                        <DropdownMenuLabel>Módulo</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="handleArchive">
                            <Archive class="mr-2 h-4 w-4 text-muted-foreground" />
                            {{ archiveLabel }}
                        </DropdownMenuItem>
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
            </template>
        </PageHeader>

        <div class="pt-0">
            <ModuleForm
                ref="moduleForm"
                :mode="mode"
                :initial-data="currentModule"
                :is-loading="isLoading"
                :errors="errors"
                :archived="isArchived"
                @submit="handleSubmit"
            />
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
