<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import {
    useReservationStore,
    type Reservation,
    type ReservationFormOptions,
} from "@tenant/stores/reservation";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import ReservationForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import {
    ArrowLeft,
    Save,
    Settings2,
    Trash2,
    Check,
    Receipt,
    Trophy,
    UserX,
    XCircle,
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
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { UnderlineInput } from "@/components/ui/underline-input";
import {
    statusMeta,
    availableTransitions,
    formatDateTime,
    formatPrice,
} from "./status";

const route = useRoute();
const router = useRouter();
const store = useReservationStore();

const mode = computed(() => (route.params.id ? "edit" : "create"));
const reservationId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const reservationIdStr = computed(() => String(route.params.id || ""));

const { reservations } = storeToRefs(store);
const {
    currentIndex: idx,
    prevRecord,
    nextRecord,
    navigatePrev,
    navigateNext,
} = useRecordNavigator(
    reservations,
    reservationIdStr,
    "/admin/reservations",
    () => store.fetchReservations(1, "total"),
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentReservation = ref<Partial<Reservation>>({});
const formOptions = ref<ReservationFormOptions>({
    courts: [],
    statuses: [],
});
const reservationForm = ref<InstanceType<typeof ReservationForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

// Mini-modal de cancelación con razón opcional
const cancelDialogOpen = ref(false);
const cancelReason = ref("");

const canManage = computed(
    () => mode.value === "edit" && !!reservationId.value,
);
const transitions = computed(() => {
    const status = currentReservation.value?.status;
    return status ? availableTransitions(status) : [];
});

watch(
    () => route.params.id,
    async () => {
        isLoading.value = true;
        try {
            const opts = await store.fetchFormOptions();
            formOptions.value = opts;

            if (mode.value === "edit" && reservationId.value) {
                const data = await store.fetchReservation(reservationId.value);
                if (data) currentReservation.value = data;
            } else {
                currentReservation.value = {};
            }
        } catch (error) {
            console.error("Error loading reservation view:", error);
            router.push("/admin/reservations");
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
        if (mode.value === "edit" && reservationId.value) {
            const updated = await store.updateReservation(
                reservationId.value,
                formData,
            );
            currentReservation.value = updated;
            toast.success("Reserva actualizada", {
                description: "Los cambios fueron guardados.",
            });
            activityLogRef.value?.load();
        } else {
            const created = await store.createReservation(formData);
            toast.success("Reserva creada", {
                description: `Código: ${created.code}`,
            });
            router.push(`/admin/reservations/${created.id}/edit`);
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
                description:
                    err?.response?.data?.message ||
                    "Revisa los campos del formulario.",
            });
        } else {
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
    router.push("/admin/reservations");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Editar reserva" : "Nueva reserva",
);

const handleSave = () => {
    reservationForm.value?.submit();
};

// --- Transiciones ---

const runTransition = async (
    action: "confirm" | "markPaid" | "markPlayed" | "markNoShow",
) => {
    if (!reservationId.value) return;
    isLoading.value = true;
    try {
        const updated = await store[action](reservationId.value);
        currentReservation.value = updated;
        toast.success(
            action === "confirm"
                ? "Reserva confirmada"
                : action === "markPaid"
                  ? "Reserva pagada"
                  : action === "markPlayed"
                    ? "Marcada como jugada"
                    : "Marcada como no-show",
            action === "markPaid" && updated.sale
                ? {
                      description: `Venta generada: ${updated.sale.document_number} — ${formatPrice(updated.sale.total)}`,
                  }
                : undefined,
        );
        activityLogRef.value?.load();
    } catch (err: any) {
        toast.error(
            err?.response?.data?.message || "No se pudo cambiar el estado",
        );
    } finally {
        isLoading.value = false;
    }
};

const onTransitionClick = (
    action:
        | "confirm"
        | "markPaid"
        | "markPlayed"
        | "markNoShow"
        | "cancel",
) => {
    if (action === "cancel") {
        cancelReason.value = "";
        cancelDialogOpen.value = true;
        return;
    }
    runTransition(action);
};

const confirmCancel = async () => {
    if (!reservationId.value) return;
    cancelDialogOpen.value = false;
    isLoading.value = true;
    try {
        const updated = await store.cancel(
            reservationId.value,
            cancelReason.value,
        );
        currentReservation.value = updated;
        toast.success("Reserva cancelada");
        activityLogRef.value?.load();
    } catch (err: any) {
        toast.error(
            err?.response?.data?.message || "No se pudo cancelar la reserva",
        );
    } finally {
        isLoading.value = false;
    }
};

// --- Eliminar ---

const handleDelete = () => {
    if (!reservationId.value) return;
    const id = reservationId.value;
    confirmDialog.value?.show(
        "Eliminar reserva",
        "¿Seguro que deseas eliminar esta reserva? Esta acción no se puede deshacer.",
        async () => {
            isLoading.value = true;
            try {
                await store.deleteReservation(id);
                router.push("/admin/reservations");
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
    { label: "Reservas", href: "/admin/reservations" },
    { label: mode.value === "edit" ? "Editar reserva" : "Nueva reserva" },
]);

const transitionIcon = (
    action: "confirm" | "markPaid" | "markPlayed" | "markNoShow" | "cancel",
) =>
    action === "confirm"
        ? Check
        : action === "markPaid"
          ? Receipt
          : action === "markPlayed"
            ? Trophy
            : action === "markNoShow"
              ? UserX
              : XCircle;
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

                <DropdownMenu v-if="canManage && transitions.length > 0">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="h-9 gap-1">
                            Cambiar estado
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[220px]">
                        <DropdownMenuLabel>Transiciones</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            v-for="t in transitions"
                            :key="t.action"
                            :disabled="isLoading"
                            :class="
                                t.action === 'cancel'
                                    ? 'text-destructive'
                                    : ''
                            "
                            @click="onTransitionClick(t.action)"
                        >
                            <component
                                :is="transitionIcon(t.action)"
                                class="mr-2 h-4 w-4"
                            />
                            {{ t.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

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
                    :current-index="idx"
                    :total="reservations.length"
                    :has-prev="!!prevRecord"
                    :has-next="!!nextRecord"
                    :disabled="isLoading"
                    @prev="navigatePrev"
                    @next="navigateNext"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8 space-y-4">
                    <ReservationForm
                        ref="reservationForm"
                        :mode="mode"
                        :initial-data="currentReservation"
                        :form-options="formOptions"
                        :is-loading="isLoading"
                        :errors="errors"
                        @submit="handleSubmit"
                    />

                    <!-- Info de venta generada -->
                    <Card v-if="currentReservation?.sale">
                        <CardContent class="pt-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold flex items-center gap-2">
                                        <Receipt class="h-4 w-4 text-muted-foreground" />
                                        Venta generada
                                    </h3>
                                    <p
                                        class="text-xs text-muted-foreground mt-1"
                                    >
                                        Esta reserva ya fue cobrada y tiene
                                        venta asociada.
                                    </p>
                                </div>
                                <span class="font-mono text-sm">{{
                                    currentReservation.sale.document_number
                                }}</span>
                            </div>
                            <div
                                class="grid grid-cols-3 gap-3 mt-4 text-sm"
                            >
                                <div>
                                    <div class="text-muted-foreground text-xs">
                                        Subtotal
                                    </div>
                                    <div>
                                        {{
                                            formatPrice(
                                                currentReservation.sale
                                                    .subtotal,
                                            )
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted-foreground text-xs">
                                        IGV
                                    </div>
                                    <div>
                                        {{
                                            formatPrice(
                                                currentReservation.sale
                                                    .tax_amount,
                                            )
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted-foreground text-xs">
                                        Total
                                    </div>
                                    <div class="font-semibold">
                                        {{
                                            formatPrice(
                                                currentReservation.sale.total,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Info de hold activo -->
                    <Card
                        v-if="
                            currentReservation?.status === 'held' &&
                            currentReservation?.held_until
                        "
                    >
                        <CardContent class="pt-6">
                            <p class="text-sm">
                                <span class="font-semibold">Reserva en espera:</span>
                                el horario queda bloqueado hasta
                                <span class="font-mono">{{
                                    formatDateTime(currentReservation.held_until)
                                }}</span
                                >.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div
                    class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0"
                >
                    <ActivityLogPanel
                        ref="activityLogRef"
                        subject="reservation"
                        :subject-id="
                            reservationId ? Number(reservationId) : null
                        "
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>

    <ConfirmDialog ref="confirmDialog" />

    <!-- Modal de cancelación con razón -->
    <AlertDialog
        :open="cancelDialogOpen"
        @update:open="cancelDialogOpen = $event"
    >
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Cancelar reserva</AlertDialogTitle>
                <AlertDialogDescription>
                    La reserva quedará en estado "Cancelada" y el horario se
                    liberará. Opcionalmente puedes registrar un motivo.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="space-y-2 py-2">
                <Label htmlFor="cancel_reason">Motivo (opcional)</Label>
                <UnderlineInput
                    id="cancel_reason"
                    v-model="cancelReason"
                    placeholder="ej. El cliente no podrá asistir"
                />
            </div>
            <AlertDialogFooter>
                <AlertDialogCancel>Volver</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="confirmCancel"
                >
                    Cancelar reserva
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
