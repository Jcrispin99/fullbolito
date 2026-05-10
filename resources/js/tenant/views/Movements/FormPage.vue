<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useMovementStore } from "@tenant/stores/movement";
import { useCompanyFilterRefresh } from "@/composables/useCompanyFilterRefresh";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import MovementForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import {
    ArrowLeft,
    Save,
    Trash2,
    ChevronDown,
    Upload,
    CheckCircle2,
    XCircle,
    Undo2,
} from "lucide-vue-next";
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
const movementStore = useMovementStore();
const { movements } = storeToRefs(movementStore);

const mode = computed(() => (route.params.id ? "edit" : "create"));
const movementId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const movementIdStr = computed(() => String(route.params.id || ""));

const {
    currentIndex: movementIdx,
    prevRecord: prevMovement,
    nextRecord: nextMovement,
    navigatePrev: navPrevMovement,
    navigateNext: navNextMovement,
} = useRecordNavigator(movements, movementIdStr, "/admin/movements", () =>
    movementStore.fetchMovements(1, "total"),
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentMovement = ref<any>({});
const formOptions = ref<any>({
    warehouses: [],
    companies: [],
    users: [],
});
const movementForm = ref<InstanceType<typeof MovementForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const isStandalone = computed(() => !currentMovement.value?.transfer_id);

const canManageMovement = computed(
    () =>
        mode.value === "edit" &&
        !!movementId.value &&
        currentMovement.value.status === "draft" &&
        isStandalone.value,
);

const loadFormData = async () => {
    isLoading.value = true;
    try {
        const options = await movementStore.fetchFormOptions();
        if (options) {
            formOptions.value = options;
        }

        if (mode.value === "edit" && movementId.value) {
            const data = await movementStore.fetchMovement(movementId.value);
            if (data) {
                currentMovement.value = data;
            }
        } else {
            currentMovement.value = {
                status: "draft",
                type: "entry",
                lines: [],
            };
        }
    } catch (error) {
        console.error("Error fetching movement data:", error);
        toast.error("Error loading form data");
    } finally {
        isLoading.value = false;
    }
};

watch(() => route.params.id, () => loadFormData(), { immediate: true });

useCompanyFilterRefresh(() => loadFormData());

const handleSubmit = async (formData: any) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (mode.value === "edit" && movementId.value) {
            const data = await movementStore.updateMovement(
                movementId.value,
                formData,
            );
            if (data) currentMovement.value = data;
            toast.success("Movimiento actualizado", {
                description: "El movimiento se actualizó correctamente.",
            });
            activityLogRef.value?.load();
        } else {
            const created = await movementStore.createMovement(formData);
            toast.success("Movimiento creado", {
                description: "El movimiento se creó correctamente.",
            });
            router.push(`/admin/movements/${created.id}/edit`);
            return;
        }
    } catch (err: any) {
        const e = err?.response?.data || err;
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
            console.error("Error saving movement:", err);
            toast.error("Error al guardar", {
                description: e?.message || "Ocurrió un error inesperado.",
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/movements");
};

const handleSave = () => {
    movementForm.value?.submit();
};

const handleDelete = () => {
    if (!movementId.value) return;
    const id = Number(movementId.value);
    confirmDialog.value?.show(
        "Eliminar movimiento",
        "¿Estás seguro de eliminar este movimiento? Esta acción no se puede deshacer.",
        async () => {
            isLoading.value = true;
            try {
                await movementStore.deleteMovement(id);
                router.push("/admin/movements");
            } catch (err: any) {
                toast.error("Error", {
                    description: err.message || "No se pudo eliminar",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleSubmitForApproval = () => {
    if (!movementId.value) return;
    const id = Number(movementId.value);
    confirmDialog.value?.show(
        "Enviar a aprobación",
        "¿Enviar este movimiento a aprobación? Quedará pendiente hasta que un aprobador lo confirme.",
        async () => {
            isLoading.value = true;
            try {
                const data = await movementStore.submitMovement(id);
                toast.success("Movimiento enviado a aprobación");
                if (data) currentMovement.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("Error", {
                    description: err?.message || "No se pudo enviar",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handlePost = () => {
    if (!movementId.value) return;
    const id = Number(movementId.value);
    const isEntry = currentMovement.value?.type === "entry";
    const desc = isEntry
        ? "Esto registrará la entrada de stock en el almacén."
        : "Esto descontará el stock del almacén.";
    confirmDialog.value?.show(
        "Aprobar movimiento",
        `${desc} ¿Continuar?`,
        async () => {
            isLoading.value = true;
            try {
                const data = await movementStore.postMovement(id);
                toast.success("Movimiento aprobado");
                if (data) currentMovement.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("Error", {
                    description: err?.message || "No se pudo aprobar",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleReject = () => {
    if (!movementId.value) return;
    const id = Number(movementId.value);
    confirmDialog.value?.show(
        "Rechazar movimiento",
        "El movimiento volverá a borrador para corrección. ¿Continuar?",
        async () => {
            isLoading.value = true;
            try {
                const data = await movementStore.rejectMovement(id);
                toast.success("Movimiento rechazado");
                if (data) currentMovement.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("Error", {
                    description: err?.message || "No se pudo rechazar",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleCancelMovement = () => {
    if (!movementId.value) return;
    const id = Number(movementId.value);
    confirmDialog.value?.show(
        "Cancelar movimiento",
        "Se generará un contra-asiento que revierte el stock movido. ¿Continuar?",
        async () => {
            isLoading.value = true;
            try {
                const data = await movementStore.cancelMovement(id);
                toast.success("Movimiento cancelado");
                if (data) currentMovement.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("Error", {
                    description: err?.message || "No se pudo cancelar",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const movementDisplayName = computed(() => {
    if (mode.value === "create") return "New";
    const serie = currentMovement.value?.serie
        ? `${currentMovement.value.serie}-`
        : "";
    const correlative = currentMovement.value?.correlative || "";
    return `${serie}${correlative}`;
});

const statusLabel = (s: string | undefined) => {
    if (!s) return "";
    return (
        {
            draft: "Borrador",
            submitted: "Pendiente",
            posted: "Aprobado",
            rejected: "Rechazado",
            cancelled: "Cancelado",
        } as Record<string, string>
    )[s] || s;
};

const statusBadgeClass = (s: string | undefined) => {
    switch (s) {
        case "submitted":
            return "bg-amber-100 text-amber-800";
        case "posted":
            return "bg-emerald-100 text-emerald-800";
        case "rejected":
            return "bg-red-100 text-red-800";
        case "cancelled":
            return "bg-red-200 text-red-900";
        default:
            return "bg-gray-100 text-gray-800";
    }
};

const typeLabel = computed(() => {
    if (currentMovement.value?.type === "entry") return "Entrada";
    if (currentMovement.value?.type === "exit") return "Salida";
    return "";
});

const pageTitle = computed(() =>
    mode.value === "edit"
        ? `Editar ${typeLabel.value || "Movimiento"}`
        : "Crear Movimiento",
);

const breadcrumbs = computed(() => [
    { label: "Movimientos", href: "/admin/movements" },
    {
        label:
            mode.value === "edit"
                ? `${typeLabel.value || "Movimiento"} ${movementDisplayName.value}`
                : "Crear Movimiento",
    },
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
                    aria-label="Back"
                    @click="handleCancel"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </template>

            <template #trailing>
                <div class="flex items-center gap-2">
                    <span
                        v-if="
                            mode === 'edit' &&
                            currentMovement?.transfer_id
                        "
                        class="hidden sm:inline-flex px-2 py-1 mr-1 rounded text-xs font-medium uppercase text-[10px] tracking-wider bg-blue-100 text-blue-800"
                        :title="
                            'Movimiento perteneciente a Transferencia #' +
                            currentMovement.transfer_id
                        "
                    >
                        Transferencia
                    </span>
                    <span
                        v-if="mode === 'edit' && currentMovement.status"
                        class="hidden sm:inline-flex px-2 py-1 mr-2 rounded text-xs font-medium uppercase text-[10px] tracking-wider"
                        :class="statusBadgeClass(currentMovement.status)"
                    >
                        {{ statusLabel(currentMovement.status) }}
                    </span>

                    <Button
                        v-if="
                            mode === 'create' ||
                            (currentMovement?.status === 'draft' &&
                                isStandalone)
                        "
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
                    <DropdownMenu v-if="mode === 'edit' && isStandalone">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-9 gap-1.5"
                                :disabled="isLoading"
                            >
                                Acciones
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[220px]">
                            <DropdownMenuLabel>Opciones</DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <DropdownMenuItem
                                v-if="currentMovement?.status === 'draft'"
                                @click="handleSubmitForApproval"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                Enviar a aprobación
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="currentMovement?.status === 'submitted'"
                                @click="handlePost"
                            >
                                <CheckCircle2 class="mr-2 h-4 w-4" />
                                Aprobar
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="currentMovement?.status === 'submitted'"
                                @click="handleReject"
                            >
                                <Undo2 class="mr-2 h-4 w-4" />
                                Rechazar
                            </DropdownMenuItem>

                            <template
                                v-if="
                                    canManageMovement ||
                                    currentMovement?.status === 'posted'
                                "
                            >
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-if="currentMovement?.status === 'posted'"
                                    class="text-destructive focus:text-destructive"
                                    @click="handleCancelMovement"
                                >
                                    <XCircle class="mr-2 h-4 w-4" />
                                    Cancelar
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canManageMovement"
                                    class="text-destructive focus:text-destructive"
                                    @click="handleDelete"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Eliminar
                                </DropdownMenuItem>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <RecordNavigator
                    v-if="mode === 'edit'"
                    :current-index="movementIdx"
                    :total="movements.length"
                    :has-prev="!!prevMovement"
                    :has-next="!!nextMovement"
                    :disabled="isLoading"
                    @prev="navPrevMovement"
                    @next="navNextMovement"
                />
            </template>
        </PageHeader>
        <div class="-mt-3">
            <div
                v-if="
                    mode === 'edit' && !isStandalone
                "
                class="mb-2 px-3 py-2 rounded-md border border-blue-200 bg-blue-50 text-xs text-blue-900"
            >
                Este movimiento pertenece a la
                <RouterLink
                    :to="
                        '/admin/transfers/' +
                        currentMovement.transfer_id +
                        '/edit'
                    "
                    class="font-medium underline"
                >
                    Transferencia #{{ currentMovement.transfer_id }}
                </RouterLink>
                . Para modificar o aprobar, usa el header de la transferencia.
            </div>

            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <MovementForm
                        ref="movementForm"
                        :mode="mode"
                        :initial-data="currentMovement"
                        :display-name="movementDisplayName"
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
                        v-if="mode === 'edit'"
                        subject="movement"
                        :subject-id="movementId ? Number(movementId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
