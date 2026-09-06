<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useCourtStore, type Court, type CourtFormOptions } from "@tenant/stores/court";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import CourtForm from "./Form.vue";
import SchedulesSection from "./SchedulesSection.vue";
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
const courtStore = useCourtStore();

const mode = computed(() => (route.params.id ? "edit" : "create"));
const courtId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const courtIdStr = computed(() => String(route.params.id || ""));

const { courts } = storeToRefs(courtStore);
const {
    currentIndex: courtIdx,
    prevRecord: prevCourt,
    nextRecord: nextCourt,
    navigatePrev: navPrevCourt,
    navigateNext: navNextCourt,
} = useRecordNavigator(courts, courtIdStr, "/admin/courts", () =>
    courtStore.fetchCourts(1, "total"),
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentCourt = ref<Partial<Court>>({});
const formOptions = ref<CourtFormOptions>({
    companies: [],
});
const courtForm = ref<InstanceType<typeof CourtForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManageCourt = computed(() => mode.value === "edit" && !!courtId.value);
const archiveLabel = computed(() =>
    (currentCourt.value as any)?.is_active === false ? "Activar" : "Desactivar",
);
const isArchived = computed(
    () =>
        mode.value === "edit" &&
        (currentCourt.value as any)?.is_active === false,
);

watch(
    () => route.params.id,
    async () => {
        isLoading.value = true;
        try {
            const opts = await courtStore.fetchFormOptions();
            formOptions.value = opts;

            if (mode.value === "edit" && courtId.value) {
                const data = await courtStore.fetchCourt(courtId.value);
                if (data) currentCourt.value = data;
            } else {
                currentCourt.value = {};
            }
        } catch (error) {
            console.error("Error loading court view:", error);
            router.push("/admin/courts");
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
        if (mode.value === "edit" && courtId.value) {
            await courtStore.updateCourt(courtId.value, formData);
            toast.success("Cancha actualizada", {
                description: "La cancha fue actualizada correctamente.",
            });
            activityLogRef.value?.load();
        } else {
            const created = await courtStore.createCourt(formData);
            toast.success("Cancha creada", {
                description: "La cancha fue creada correctamente.",
            });
            router.push(`/admin/courts/${created.id}/edit`);
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
            console.error("Error saving court:", err);
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
    router.push("/admin/courts");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Editar cancha" : "Nueva cancha",
);

const handleSave = () => {
    courtForm.value?.submit();
};

const handleArchive = () => {
    if (!courtId.value) return;
    const id = courtId.value;
    confirmDialog.value?.show(
        `${archiveLabel.value} cancha`,
        `¿Seguro que deseas ${archiveLabel.value.toLowerCase()} esta cancha?`,
        async () => {
            isLoading.value = true;
            try {
                const updated = await courtStore.toggleActive(id);
                currentCourt.value = updated;
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!courtId.value) return;
    const id = courtId.value;
    confirmDialog.value?.show(
        "Eliminar cancha",
        "¿Seguro que deseas eliminar esta cancha? Esta acción no se puede deshacer.",
        async () => {
            isLoading.value = true;
            try {
                await courtStore.deleteCourt(id);
                router.push("/admin/courts");
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
    { label: "Canchas", href: "/admin/courts" },
    { label: mode.value === "edit" ? "Editar cancha" : "Nueva cancha" },
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
                <DropdownMenu v-if="canManageCourt">
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
                    :current-index="courtIdx"
                    :total="courts.length"
                    :has-prev="!!prevCourt"
                    :has-next="!!nextCourt"
                    :disabled="isLoading"
                    @prev="navPrevCourt"
                    @next="navNextCourt"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8 space-y-4">
                    <CourtForm
                        ref="courtForm"
                        :mode="mode"
                        :initial-data="currentCourt"
                        :form-options="formOptions"
                        :is-loading="isLoading"
                        :errors="errors"
                        :archived="isArchived"
                        @submit="handleSubmit"
                    />

                    <SchedulesSection
                        v-if="mode === 'edit' && courtId"
                        :court-id="Number(courtId)"
                        :slot-duration-minutes="
                            currentCourt.slot_duration_minutes
                        "
                    />
                </div>

                <div
                    class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0"
                >
                    <ActivityLogPanel
                        ref="activityLogRef"
                        subject="court"
                        :subject-id="courtId ? Number(courtId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
