<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useTransferStore } from "@tenant/stores/transfer";
import { useCompanyFilterRefresh } from "@/composables/useCompanyFilterRefresh";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import TransferForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import {
    ArrowLeft,
    Save,
    Trash2,
    ChevronDown,
    Send,
    PackageCheck,
    XCircle,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import SunatArtifactPanel from "@tenant/components/SunatArtifactPanel.vue";
import GreTransportEditor, {
    type GreTransportData,
} from "@tenant/components/GreTransportEditor.vue";
import { useCompanyFilterStore } from "@tenant/stores/companyFilter";
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
const transferStore = useTransferStore();
const { transfers } = storeToRefs(transferStore);

const mode = computed(() => (route.params.id ? "edit" : "create"));
const transferId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const transferIdStr = computed(() => String(route.params.id || ""));

const {
    currentIndex: transferIdx,
    prevRecord: prevTransfer,
    nextRecord: nextTransfer,
    navigatePrev: navPrevTransfer,
    navigateNext: navNextTransfer,
} = useRecordNavigator(transfers, transferIdStr, "/admin/transfers", () =>
    transferStore.fetchTransfers(1, "total"),
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentTransfer = ref<any>({});
const formOptions = ref<any>({
    warehouses: [],
    companies: [],
    users: [],
});
const transferForm = ref<InstanceType<typeof TransferForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManageTransfer = computed(
    () =>
        mode.value === "edit" &&
        !!transferId.value &&
        currentTransfer.value.status === "draft",
);

// ─── Status helpers (par exit+entry) ───────────────────────────
const exitMovement = computed(() => currentTransfer.value?.exit_movement ?? null);
const entryMovement = computed(() => currentTransfer.value?.entry_movement ?? null);

const exitStatus = computed(() => exitMovement.value?.status ?? "draft");
const entryStatus = computed(() => entryMovement.value?.status ?? "draft");

// El botón Send sólo aplica cuando el exit está en draft
const canSend = computed(
    () =>
        mode.value === "edit" &&
        currentTransfer.value?.status === "draft" &&
        exitStatus.value === "draft",
);
// El botón Receive sólo aplica cuando exit posted y entry sigue en draft/submitted
const canReceive = computed(
    () =>
        mode.value === "edit" &&
        ["in_transit", "sent"].includes(currentTransfer.value?.status),
);
// Cancelar disponible mientras al menos un movement no esté cancelled
const canCancel = computed(
    () =>
        mode.value === "edit" &&
        currentTransfer.value?.status &&
        currentTransfer.value.status !== "draft" &&
        currentTransfer.value.status !== "cancelled",
);

const loadFormData = async () => {
    isLoading.value = true;
    try {
        const options = await transferStore.fetchFormOptions();
        if (options) {
            formOptions.value = options;
        }

        if (mode.value === "edit" && transferId.value) {
            const data = await transferStore.fetchTransfer(transferId.value);
            if (data) {
                currentTransfer.value = data;
            }
        } else {
            currentTransfer.value = {
                status: "draft",
                lines: [],
            };
        }
    } catch (error) {
        console.error("Error fetching transfer data:", error);
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
        if (mode.value === "edit" && transferId.value) {
            const data = await transferStore.updateTransfer(
                transferId.value,
                formData,
            );
            if (data) currentTransfer.value = data;
            toast.success("Transferencia actualizada", {
                description: "La transferencia se actualizó correctamente.",
            });
            activityLogRef.value?.load();
        } else {
            const created = await transferStore.createTransfer(formData);
            toast.success("Transferencia creada", {
                description: "La transferencia se creó correctamente.",
            });
            router.push(`/admin/transfers/${created.id}/edit`);
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
            console.error("Error saving transfer:", err);
            toast.error("Error al guardar", {
                description: e?.message || "Ocurrió un error inesperado.",
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/transfers");
};

const handleSave = () => {
    transferForm.value?.submit();
};

const handleDelete = () => {
    if (!transferId.value) return;
    const id = Number(transferId.value);
    confirmDialog.value?.show(
        "Eliminar transferencia",
        "¿Estás seguro de eliminar esta transferencia? Esta acción no se puede deshacer.",
        async () => {
            isLoading.value = true;
            try {
                await transferStore.deleteTransfer(id);
                router.push("/admin/transfers");
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

const handleSend = () => {
    if (!transferId.value) return;
    const id = Number(transferId.value);
    confirmDialog.value?.show(
        "Enviar transferencia",
        "¿Enviar esta transferencia? Se descontará el stock del almacén origen.",
        async () => {
            isLoading.value = true;
            try {
                const data = await transferStore.sendTransfer(id);
                toast.success("Transferencia enviada");
                if (data) currentTransfer.value = data;
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

const handleReceive = () => {
    if (!transferId.value) return;
    const id = Number(transferId.value);
    confirmDialog.value?.show(
        "Recibir transferencia",
        "¿Confirmar recepción? Se ingresará el stock en el almacén destino.",
        async () => {
            isLoading.value = true;
            try {
                const data = await transferStore.receiveTransfer(id);
                toast.success("Transferencia recibida");
                if (data) currentTransfer.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("Error", {
                    description: err?.message || "No se pudo recibir",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleCancelTransfer = () => {
    if (!transferId.value) return;
    const id = Number(transferId.value);
    confirmDialog.value?.show(
        "Cancelar transferencia",
        "¿Cancelar esta transferencia? Se revertirán los movimientos de stock.",
        async () => {
            isLoading.value = true;
            try {
                const data = await transferStore.cancelTransfer(id);
                toast.success("Transferencia cancelada");
                if (data) currentTransfer.value = data;
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

const transferDisplayName = computed(() => {
    if (mode.value === "create") return "New";
    const serie = currentTransfer.value?.serie
        ? `${currentTransfer.value.serie}-`
        : "";
    const correlative = currentTransfer.value?.correlative || "";
    return `${serie}${correlative}`;
});

// ─── GRE — Guía de Remisión Electrónica ────────────────────────────────
//
// El SunatArtifactPanel habla un shape genérico (`SunatDoc`); aquí mapeamos
// los campos `gre.*` del Transfer al lenguaje del componente
// (`sunat_status` ⇆ `gre.status`, etc.). El panel decide si llama
// `/sales/{id}/sunat/...` o `/transfers/{id}/gre/...` según el prop `kind`.

const companyFilter = useCompanyFilterStore();

/**
 * Gate "outbound" — la GRE-Remitente sólo se imprime/envía desde la company
 * que despacha. Si el filtro multi-company está activo y el origen del
 * transfer no está dentro, ocultamos el tab (la guía la imprime el remitente
 * real, no esta company). Si el filtro está vacío (vista global), mostramos
 * el tab y dejamos que el backend (403) sea la última palabra.
 */
const isOutboundForMe = computed(() => {
    const fromCompanyId = currentTransfer.value?.from_warehouse?.company_id;
    if (!fromCompanyId) return false;
    const selected = companyFilter.selectedIds;
    if (!selected || selected.length === 0) return true; // sin filtro → mostrar
    return selected.includes(Number(fromCompanyId));
});

const showGreTab = computed(
    () => mode.value === "edit" && isOutboundForMe.value,
);

const greStatus = computed(() => currentTransfer.value?.gre?.status ?? null);

/**
 * Adaptador: traduce `currentTransfer.gre.*` al shape genérico `SunatDoc`.
 * Reusa el mismo serie/correlative del transfer (la GRE comparte numeración).
 */
const greDoc = computed(() => ({
    id: currentTransfer.value?.id,
    serie: currentTransfer.value?.serie,
    correlative: currentTransfer.value?.correlative,
    sunat_status: greStatus.value,
    sunat_response: currentTransfer.value?.gre?.response ?? null,
    sunat_sent_at: currentTransfer.value?.gre?.sent_at ?? null,
    has_signed_xml: !!currentTransfer.value?.gre?.has_signed_xml,
    has_cdr_zip: !!currentTransfer.value?.gre?.has_cdr_zip,
}));

const greNeedsAttention = computed(
    () => greStatus.value === "error",
);

/**
 * Estado local del editor de transporte. Se sincroniza con
 * `currentTransfer.gre.*` cada vez que el FormPage recarga el transfer.
 */
const greForm = ref<GreTransportData>({
    gre_motive_code: "04",
    gre_modality: "private",
    gre_transfer_start_date: null,
    gre_gross_weight: null,
    gre_packages: null,
    gre_vehicle_plate: null,
    gre_driver_doc_type: null,
    gre_driver_doc_number: null,
    gre_driver_license: null,
    gre_driver_name: null,
});
const greErrors = ref<Record<string, string>>({});
const isSavingGre = ref(false);

/** Sincroniza `greForm` con los datos del backend al cargar/recargar. */
watch(
    () => currentTransfer.value?.gre,
    (gre) => {
        if (!gre) return;
        greForm.value = {
            gre_motive_code: gre.motive_code ?? "04",
            gre_modality: gre.modality ?? "private",
            gre_transfer_start_date: gre.transfer_start_date ?? null,
            gre_gross_weight:
                gre.gross_weight === null || gre.gross_weight === undefined
                    ? null
                    : Number(gre.gross_weight),
            gre_packages:
                gre.packages === null || gre.packages === undefined
                    ? null
                    : Number(gre.packages),
            gre_vehicle_plate: gre.vehicle_plate ?? null,
            gre_driver_doc_type: gre.driver_doc_type ?? null,
            gre_driver_doc_number: gre.driver_doc_number ?? null,
            gre_driver_license: gre.driver_license ?? null,
            gre_driver_name: gre.driver_name ?? null,
        };
    },
    { immediate: true, deep: true },
);

/**
 * El editor se bloquea cuando la guía ya está aceptada o con ticket
 * pendiente — el backend también rechazaría el PATCH, pero conviene
 * reflejarlo en UI.
 */
const greEditorDisabled = computed(() => {
    const s = greStatus.value;
    return s === "accepted" || s === "ticket_pending" || s === "processing";
});

/** Comprueba si los datos mínimos para emitir la GRE están completos. */
const isGreDataComplete = computed(() => {
    const v = greForm.value;
    return (
        !!v.gre_modality &&
        !!v.gre_transfer_start_date &&
        v.gre_gross_weight !== null &&
        Number(v.gre_gross_weight) > 0 &&
        !!v.gre_vehicle_plate &&
        !!v.gre_driver_doc_type &&
        !!v.gre_driver_doc_number &&
        !!v.gre_driver_license &&
        !!v.gre_driver_name
    );
});

const canSendGre = computed(() => {
    const exit = exitMovement.value;
    if (!exit || exit.status !== "posted") return false;
    const s = greStatus.value;
    if (s && !["pending", "error"].includes(s)) return false;
    // Sólo se habilita el envío cuando el transporte está completo.
    return isGreDataComplete.value;
});

const handleSaveGreTransport = async () => {
    if (!transferId.value) return;
    const id = Number(transferId.value);
    isSavingGre.value = true;
    greErrors.value = {};
    try {
        const data = await transferStore.updateGre(id, greForm.value);
        if (data) currentTransfer.value = data;
        toast.success("Datos de transporte guardados");
        activityLogRef.value?.load();
    } catch (err: any) {
        const e = err?.response?.data || err;
        if (e?.errors) {
            const flat: Record<string, string> = {};
            Object.entries(e.errors).forEach(([k, v]: any) => {
                flat[k] = Array.isArray(v) ? v[0] : String(v);
            });
            greErrors.value = flat;
            toast.error("Revisa los campos del transporte");
        } else {
            toast.error("No se pudo guardar", {
                description: e?.message || "Error desconocido",
            });
        }
    } finally {
        isSavingGre.value = false;
    }
};

const sideTab = ref<"logs" | "gre">("logs");

// Si la GRE entra en error tras un envío, traemos al usuario al tab GRE.
watch(greNeedsAttention, (needs) => {
    if (needs) sideTab.value = "gre";
});

const handleSendToGre = () => {
    if (!transferId.value) return;
    const id = Number(transferId.value);
    confirmDialog.value?.show(
        "Enviar GRE a SUNAT",
        "¿Enviar la guía de remisión electrónica a SUNAT? Recibirás un ticket que se consulta automáticamente.",
        async () => {
            isLoading.value = true;
            try {
                const data = await transferStore.sendGre(id);
                if (data) currentTransfer.value = data;
                toast.success("GRE enviada", {
                    description: "Ticket SUNAT en cola — se actualiza al consultar.",
                });
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("No se pudo enviar la GRE", {
                    description: err?.message || "Error desconocido",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const statusLabel = (s: string | undefined) => {
    if (!s) return "";
    return (
        {
            draft: "Borrador",
            pending_exit: "Pend. Salida",
            in_transit: "En tránsito",
            completed: "Completada",
            with_observation: "Con observación",
            cancelled: "Cancelada",
            // Aliases legacy
            sent: "En tránsito",
            received: "Completada",
        } as Record<string, string>
    )[s] || s;
};

const statusBadgeClass = (s: string | undefined) => {
    switch (s) {
        case "pending_exit":
            return "bg-amber-100 text-amber-800";
        case "in_transit":
        case "sent":
            return "bg-blue-100 text-blue-800";
        case "completed":
        case "received":
            return "bg-emerald-100 text-emerald-800";
        case "with_observation":
            return "bg-orange-100 text-orange-800";
        case "cancelled":
            return "bg-red-100 text-red-800";
        default:
            return "bg-gray-100 text-gray-800";
    }
};

// Estado por movement individual (5 estados)
const movementStatusLabel = (s: string | undefined) => {
    if (!s) return "—";
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

const movementStatusBadgeClass = (s: string | undefined) => {
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

const formatDateTime = (iso: string | null | undefined) => {
    if (!iso) return null;
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleString("es", {
        dateStyle: "medium",
        timeStyle: "short",
    });
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Editar Transferencia" : "Crear Transferencia",
);

const breadcrumbs = computed(() => [
    { label: "Transferencias", href: "/admin/transfers" },
    {
        label:
            mode.value === "edit"
                ? "Editar Transferencia"
                : "Crear Transferencia",
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
                        v-if="mode === 'edit' && currentTransfer.status"
                        class="hidden sm:inline-flex px-2 py-1 mr-2 rounded text-xs font-medium uppercase text-[10px] tracking-wider"
                        :class="statusBadgeClass(currentTransfer.status)"
                    >
                        {{ statusLabel(currentTransfer.status) }}
                    </span>

                    <Button
                        v-if="
                            mode === 'create' ||
                            currentTransfer?.status === 'draft'
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
                    <DropdownMenu v-if="mode === 'edit'">
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
                                v-if="canSend"
                                @click="handleSend"
                            >
                                <Send class="mr-2 h-4 w-4" />
                                Enviar
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="canReceive"
                                @click="handleReceive"
                            >
                                <PackageCheck class="mr-2 h-4 w-4" />
                                Recibir
                            </DropdownMenuItem>

                            <template
                                v-if="canCancel || canManageTransfer"
                            >
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-if="canCancel"
                                    class="text-destructive focus:text-destructive"
                                    @click="handleCancelTransfer"
                                >
                                    <XCircle class="mr-2 h-4 w-4" />
                                    Cancelar
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canManageTransfer"
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
                    :current-index="transferIdx"
                    :total="transfers.length"
                    :has-prev="!!prevTransfer"
                    :has-next="!!nextTransfer"
                    :disabled="isLoading"
                    @prev="navPrevTransfer"
                    @next="navNextTransfer"
                />
            </template>
        </PageHeader>
        <div class="-mt-3">
            <!-- Panel del par exit + entry (fuente de verdad del workflow) -->
            <div
                v-if="mode === 'edit' && (exitMovement || entryMovement)"
                class="mb-3 grid gap-3 md:grid-cols-2"
            >
                <!-- Salida -->
                <div
                    class="border rounded-md p-3 bg-card/40"
                    :class="{
                        'border-emerald-200':
                            exitStatus === 'posted',
                        'border-amber-200':
                            exitStatus === 'submitted',
                        'border-red-200':
                            exitStatus === 'rejected' ||
                            exitStatus === 'cancelled',
                    }"
                >
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] uppercase tracking-wider text-muted-foreground"
                            >
                                Salida (Origen)
                            </span>
                            <span
                                class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider"
                                :class="
                                    movementStatusBadgeClass(exitStatus)
                                "
                            >
                                {{ movementStatusLabel(exitStatus) }}
                            </span>
                        </div>
                        <RouterLink
                            v-if="exitMovement?.id"
                            :to="
                                '/admin/movements/' +
                                exitMovement.id +
                                '/edit'
                            "
                            class="text-[11px] text-primary hover:underline"
                        >
                            {{ exitMovement.sequence_code }}
                        </RouterLink>
                    </div>
                    <div class="text-xs text-muted-foreground space-y-0.5">
                        <div>
                            <span class="font-medium text-foreground">
                                {{ exitMovement?.warehouse?.name || "—" }}
                            </span>
                        </div>
                        <div v-if="exitMovement?.posted_at">
                            Aprobado:
                            {{ formatDateTime(exitMovement.posted_at) }}
                            <span v-if="exitMovement.posted_user">
                                · {{ exitMovement.posted_user.name }}
                            </span>
                        </div>
                        <div v-else-if="exitMovement?.submitted_at">
                            Pendiente desde:
                            {{ formatDateTime(exitMovement.submitted_at) }}
                        </div>
                    </div>
                </div>

                <!-- Entrada -->
                <div
                    class="border rounded-md p-3 bg-card/40"
                    :class="{
                        'border-emerald-200':
                            entryStatus === 'posted',
                        'border-amber-200':
                            entryStatus === 'submitted',
                        'border-red-200':
                            entryStatus === 'rejected' ||
                            entryStatus === 'cancelled',
                    }"
                >
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] uppercase tracking-wider text-muted-foreground"
                            >
                                Entrada (Destino)
                            </span>
                            <span
                                class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider"
                                :class="
                                    movementStatusBadgeClass(entryStatus)
                                "
                            >
                                {{ movementStatusLabel(entryStatus) }}
                            </span>
                        </div>
                        <RouterLink
                            v-if="entryMovement?.id"
                            :to="
                                '/admin/movements/' +
                                entryMovement.id +
                                '/edit'
                            "
                            class="text-[11px] text-primary hover:underline"
                        >
                            {{ entryMovement.sequence_code }}
                        </RouterLink>
                    </div>
                    <div class="text-xs text-muted-foreground space-y-0.5">
                        <div>
                            <span class="font-medium text-foreground">
                                {{ entryMovement?.warehouse?.name || "—" }}
                            </span>
                        </div>
                        <div v-if="entryMovement?.posted_at">
                            Recibido:
                            {{ formatDateTime(entryMovement.posted_at) }}
                            <span v-if="entryMovement.posted_user">
                                · {{ entryMovement.posted_user.name }}
                            </span>
                        </div>
                        <div
                            v-else-if="
                                exitStatus === 'posted' &&
                                entryStatus === 'draft'
                            "
                            class="text-blue-700 italic"
                        >
                            En tránsito — esperando recepción
                        </div>
                        <div v-else-if="entryStatus === 'rejected'">
                            <span class="text-orange-700 italic">
                                Recibida con observación
                            </span>
                            <div
                                v-if="entryMovement?.rejection_reason"
                                class="mt-0.5 text-foreground"
                            >
                                {{ entryMovement.rejection_reason }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <TransferForm
                        ref="transferForm"
                        :mode="mode"
                        :initial-data="currentTransfer"
                        :display-name="transferDisplayName"
                        :form-options="formOptions"
                        :is-loading="isLoading"
                        :errors="errors"
                        @submit="handleSubmit"
                    />
                </div>

                <div
                    class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0"
                >
                    <div v-if="mode === 'edit'" class="w-full">
                        <div class="flex items-center gap-2 mb-3">
                            <Button
                                :variant="sideTab === 'logs' ? 'secondary' : 'ghost'"
                                size="sm"
                                class="h-8 text-xs"
                                @click="sideTab = 'logs'"
                            >
                                Logs
                            </Button>
                            <Button
                                v-if="showGreTab"
                                :variant="sideTab === 'gre' ? 'secondary' : 'ghost'"
                                size="sm"
                                class="h-8 text-xs gap-1.5"
                                @click="sideTab = 'gre'"
                            >
                                GRE
                                <span
                                    v-if="greNeedsAttention"
                                    class="inline-flex items-center justify-center min-w-[1rem] h-4 px-1 text-[10px] font-semibold rounded bg-red-100 text-red-700"
                                >
                                    !
                                </span>
                            </Button>
                        </div>

                        <div v-show="sideTab === 'logs'">
                            <ActivityLogPanel
                                ref="activityLogRef"
                                subject="transfer"
                                :subject-id="transferId ? Number(transferId) : null"
                            />
                        </div>

                        <div
                            v-if="showGreTab"
                            v-show="sideTab === 'gre'"
                            class="space-y-4"
                        >
                            <GreTransportEditor
                                v-model="greForm"
                                :disabled="greEditorDisabled"
                                :is-saving="isSavingGre"
                                :errors="greErrors"
                                @save="handleSaveGreTransport"
                            />
                            <SunatArtifactPanel
                                :doc="greDoc"
                                kind="despatch"
                                :can-send="canSendGre"
                                :is-loading="isLoading"
                                @send="handleSendToGre"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
