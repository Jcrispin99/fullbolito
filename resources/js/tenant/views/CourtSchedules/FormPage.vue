<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import {
    useCourtScheduleStore,
    type CourtSchedule,
    type CourtScheduleFormOptions,
} from "@tenant/stores/courtSchedule";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import CourtScheduleForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import { ArrowLeft, Save, Archive, Settings2, Trash2 } from "lucide-vue-next";
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
const scheduleStore = useCourtScheduleStore();

const mode = computed(() => (route.params.id ? "edit" : "create"));
const scheduleId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const scheduleIdStr = computed(() => String(route.params.id || ""));

const { schedules } = storeToRefs(scheduleStore);
const {
    currentIndex: scheduleIdx,
    prevRecord: prevSchedule,
    nextRecord: nextSchedule,
    navigatePrev: navPrev,
    navigateNext: navNext,
} = useRecordNavigator(
    schedules,
    scheduleIdStr,
    "/admin/court-schedules",
    () => scheduleStore.fetchSchedules(1, "total"),
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentSchedule = ref<Partial<CourtSchedule>>({});
const formOptions = ref<CourtScheduleFormOptions>({
    courts: [],
    days_of_week: [],
});
const scheduleForm = ref<InstanceType<typeof CourtScheduleForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManage = computed(() => mode.value === "edit" && !!scheduleId.value);
const archiveLabel = computed(() =>
    (currentSchedule.value as any)?.is_active === false ? "Activar" : "Desactivar",
);
const isArchived = computed(
    () =>
        mode.value === "edit" &&
        (currentSchedule.value as any)?.is_active === false,
);

watch(
    () => route.params.id,
    async () => {
        isLoading.value = true;
        try {
            const opts = await scheduleStore.fetchFormOptions();
            formOptions.value = opts;

            if (mode.value === "edit" && scheduleId.value) {
                const data = await scheduleStore.fetchSchedule(scheduleId.value);
                if (data) currentSchedule.value = data;
            } else {
                currentSchedule.value = {};
            }
        } catch (error) {
            console.error("Error loading schedule view:", error);
            router.push("/admin/court-schedules");
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
        if (mode.value === "edit" && scheduleId.value) {
            await scheduleStore.updateSchedule(scheduleId.value, formData);
            toast.success("Horario actualizado", {
                description: "El horario fue actualizado correctamente.",
            });
            activityLogRef.value?.load();
        } else {
            const created = await scheduleStore.createSchedule(formData);
            const count = created.length;
            if (count === 1) {
                toast.success("Horario creado", {
                    description: "El horario fue creado correctamente.",
                });
                router.push(`/admin/court-schedules/${created[0].id}/edit`);
            } else {
                toast.success(`Se crearon ${count} horarios`, {
                    description: "Un horario por cada día seleccionado.",
                });
                router.push("/admin/court-schedules");
            }
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
            console.error("Error saving schedule:", err);
            toast.error("Error al guardar", {
                description:
                    err?.response?.data?.message ||
                    "Ocurrió un error inesperado.",
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/court-schedules");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Editar horario" : "Nuevo horario",
);

const handleSave = () => {
    scheduleForm.value?.submit();
};

const handleArchive = () => {
    if (!scheduleId.value) return;
    const id = scheduleId.value;
    confirmDialog.value?.show(
        `${archiveLabel.value} horario`,
        `¿Seguro que deseas ${archiveLabel.value.toLowerCase()} este horario?`,
        async () => {
            isLoading.value = true;
            try {
                const updated = await scheduleStore.toggleActive(id);
                currentSchedule.value = updated;
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!scheduleId.value) return;
    const id = scheduleId.value;
    confirmDialog.value?.show(
        "Eliminar horario",
        "¿Seguro que deseas eliminar este horario? Esta acción no se puede deshacer.",
        async () => {
            isLoading.value = true;
            try {
                await scheduleStore.deleteSchedule(id);
                router.push("/admin/court-schedules");
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
    { label: "Horarios", href: "/admin/court-schedules" },
    { label: mode.value === "edit" ? "Editar horario" : "Nuevo horario" },
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
                        <DropdownMenuLabel>Opciones</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="handleArchive">
                            <Archive
                                class="mr-2 h-4 w-4 text-muted-foreground"
                            />
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

                <RecordNavigator
                    v-if="mode === 'edit'"
                    :current-index="scheduleIdx"
                    :total="schedules.length"
                    :has-prev="!!prevSchedule"
                    :has-next="!!nextSchedule"
                    :disabled="isLoading"
                    @prev="navPrev"
                    @next="navNext"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <CourtScheduleForm
                        ref="scheduleForm"
                        :mode="mode"
                        :initial-data="currentSchedule"
                        :form-options="formOptions"
                        :is-loading="isLoading"
                        :errors="errors"
                        :archived="isArchived"
                        @submit="handleSubmit"
                    />
                </div>

                <div
                    class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0"
                >
                    <ActivityLogPanel
                        ref="activityLogRef"
                        subject="courtSchedule"
                        :subject-id="scheduleId ? Number(scheduleId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
