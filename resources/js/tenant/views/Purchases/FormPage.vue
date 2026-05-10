<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { usePurchaseStore } from "@tenant/stores/purchase";
import { useCompanyFilterRefresh } from "@/composables/useCompanyFilterRefresh";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PurchaseForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import {
    ArrowLeft,
    Save,
    Trash2,
    Boxes,
    ChevronDown,
    Upload,
    XCircle,
    CheckCircle2,
    RotateCcw,
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
const purchaseStore = usePurchaseStore();
const { purchases } = storeToRefs(purchaseStore);

// Determine mode based on route params
const mode = computed(() => (route.params.id ? "edit" : "create"));
const purchaseId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const purchaseIdStr = computed(() => String(route.params.id || ""));

const { currentIndex: purchaseIdx, prevRecord: prevPurchase, nextRecord: nextPurchase, navigatePrev: navPrevPurchase, navigateNext: navNextPurchase } =
    useRecordNavigator(purchases, purchaseIdStr, "/admin/purchases", () => purchaseStore.fetchPurchases(1, "total"));

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentPurchase = ref<any>({});
const formOptions = ref<any>({ suppliers: [], warehouses: [], taxes: [] });
const purchaseForm = ref<InstanceType<typeof PurchaseForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManagePurchase = computed(
    () =>
        mode.value === "edit" &&
        !!purchaseId.value &&
        currentPurchase.value.status === "draft",
);

const loadFormData = async () => {
    isLoading.value = true;
    try {
        const options = await purchaseStore.fetchFormOptions();
        if (options) {
            formOptions.value = options;
        }

        if (mode.value === "edit" && purchaseId.value) {
            const data = await purchaseStore.fetchPurchase(purchaseId.value);
            if (data) {
                currentPurchase.value = data;
            }
        } else {
            currentPurchase.value = {
                status: 'draft',
                items: []
            };
        }
    } catch (error) {
        console.error("Error fetching purchase data:", error);
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
        if (mode.value === "edit" && purchaseId.value) {
            await purchaseStore.updatePurchase(purchaseId.value, formData);
            toast.success("Purchase updated", {
                description: "The purchase was successfully updated.",
            });
            activityLogRef.value?.load();
        } else {
            const created = await purchaseStore.createPurchase(formData);
            toast.success("Purchase created", {
                description: "The purchase was successfully created.",
            });
            router.push(`/admin/purchases/${created.id}/edit`);
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
            toast.error("Validation error", {
                description: "Please check the form fields for errors.",
            });
        } else {
            console.error("Error saving purchase:", err);
            toast.error("Error saving purchase", {
                description: e?.message || "An unexpected error occurred.",
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/purchases");
};

const handleSave = () => {
    purchaseForm.value?.submit();
};

const handleOpenLots = () => {
    if (!purchaseId.value) return;
    router.push(`/admin/purchases/${purchaseId.value}/lots`);
};

const handleDelete = () => {
    if (!purchaseId.value) return;
    const id = Number(purchaseId.value);
    confirmDialog.value?.show(
        "Delete purchase",
        "Are you sure you want to delete this purchase? This action cannot be undone.",
        async () => {
            isLoading.value = true;
            try {
                await purchaseStore.deletePurchase(id);
                router.push("/admin/purchases");
            } catch (err: any) {
                toast.error("Error", {
                    description: err.message || "Failed to delete",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handlePost = () => {
    if (!purchaseId.value) return;
    const id = Number(purchaseId.value);
    confirmDialog.value?.show(
        "Post purchase",
        "Are you sure you want to post this purchase? This action cannot be undone.",
        async () => {
            isLoading.value = true;
            try {
                const data = await purchaseStore.postPurchase(id);
                toast.success("Purchase posted successfully");
                if (data) currentPurchase.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("Error", {
                    description: err?.message || "Failed to post",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleCancelPurchase = () => {
    if (!purchaseId.value) return;
    const id = Number(purchaseId.value);
    confirmDialog.value?.show(
        "Cancel purchase",
        "Are you sure you want to cancel this purchase? This action cannot be undone.",
        async () => {
            isLoading.value = true;
            try {
                const data = await purchaseStore.cancelPurchase(id);
                toast.success("Purchase cancelled successfully");
                if (data) currentPurchase.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("Error", {
                    description: err?.message || "Failed to cancel",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handlePayPurchase = () => {
    if (!purchaseId.value) return;
    const id = Number(purchaseId.value);
    confirmDialog.value?.show(
        "Pay purchase",
        "Are you sure you want to mark this purchase as paid?",
        async () => {
            isLoading.value = true;
            try {
                const data = await purchaseStore.payPurchase(id);
                toast.success("Purchase paid successfully");
                if (data) currentPurchase.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("Error", {
                    description: err?.message || "Failed to mark as paid",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDraftPurchase = () => {
    if (!purchaseId.value) return;
    const id = Number(purchaseId.value);
    confirmDialog.value?.show(
        "Restore to draft",
        "Are you sure you want to restore this purchase to draft?",
        async () => {
            isLoading.value = true;
            try {
                const data = await purchaseStore.draftPurchase(id);
                toast.success("Purchase restored to draft");
                if (data) currentPurchase.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error("Error", {
                    description: err?.message || "Failed to restore",
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const purchaseDisplayName = computed(() => {
    if (mode.value === "create") return "New";

    const serie = currentPurchase.value?.serie
        ? `${currentPurchase.value.serie}-`
        : "";
    const correlative = currentPurchase.value?.correlative || "";
    return `${serie}${correlative}`;
});

const pageTitle = computed(() =>
    mode.value === "edit" ? "Edit Purchase" : "Create Purchase",
);

const breadcrumbs = computed(() => [
    { label: "Purchases", href: "/admin/purchases" },
    { label: mode.value === "edit" ? "Edit Purchase" : "Create Purchase" },
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
                        v-if="mode === 'edit' && currentPurchase.status"
                        class="hidden sm:inline-flex px-2 py-1 mr-2 rounded text-xs font-medium uppercase text-[10px] tracking-wider bg-gray-100 text-gray-800"
                        :class="[
                            currentPurchase.status === 'posted'
                                ? 'bg-blue-100 text-blue-800'
                                : currentPurchase.status === 'cancelled'
                                  ? 'bg-red-100 text-red-800'
                                  : '',
                        ]"
                    >
                        {{ currentPurchase.status }}
                    </span>

                    <Button
                        v-if="
                            mode === 'create' ||
                            currentPurchase?.status === 'draft'
                        "
                        size="sm"
                        class="h-9"
                        :disabled="isLoading"
                        @click="handleSave"
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{
                            isLoading
                                ? "Saving..."
                                : mode === "edit"
                                  ? "Update Purchase"
                                  : "Create Purchase"
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
                            <DropdownMenuLabel>Purchase Options</DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <!-- DRAFT -->
                            <DropdownMenuItem
                                v-if="currentPurchase?.status === 'draft'"
                                @click="handlePost"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                Publicar
                            </DropdownMenuItem>

                            <!-- POSTED -->
                            <DropdownMenuItem
                                v-if="
                                    currentPurchase?.status === 'posted' &&
                                    currentPurchase?.payment_status !== 'paid'
                                "
                                @click="handlePayPurchase"
                            >
                                <CheckCircle2 class="mr-2 h-4 w-4" />
                                Marcar pagada
                            </DropdownMenuItem>

                            <!-- CANCELLED -->
                            <DropdownMenuItem
                                v-if="currentPurchase?.status === 'cancelled'"
                                @click="handleDraftPurchase"
                            >
                                <RotateCcw class="mr-2 h-4 w-4" />
                                Volver a borrador
                            </DropdownMenuItem>

                            <!-- LOTS (any non-empty status) -->
                            <DropdownMenuItem
                                v-if="
                                    ['draft', 'posted', 'cancelled'].includes(
                                        currentPurchase?.status,
                                    )
                                "
                                @click="handleOpenLots"
                            >
                                <Boxes class="mr-2 h-4 w-4" />
                                Lotes
                            </DropdownMenuItem>

                            <!-- DESTRUCTIVAS -->
                            <template
                                v-if="
                                    canManagePurchase ||
                                    currentPurchase?.status === 'posted'
                                "
                            >
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-if="currentPurchase?.status === 'posted'"
                                    class="text-destructive focus:text-destructive"
                                    @click="handleCancelPurchase"
                                >
                                    <XCircle class="mr-2 h-4 w-4" />
                                    Anular compra
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canManagePurchase"
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
                    :current-index="purchaseIdx"
                    :total="purchases.length"
                    :has-prev="!!prevPurchase"
                    :has-next="!!nextPurchase"
                    :disabled="isLoading"
                    @prev="navPrevPurchase"
                    @next="navNextPurchase"
                />
            </template>
        </PageHeader>
        <div class="-mt-3">
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <PurchaseForm
                        ref="purchaseForm"
                        :mode="mode"
                        :initial-data="currentPurchase"
                        :display-name="purchaseDisplayName"
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
                        subject="purchase"
                        :subject-id="purchaseId ? Number(purchaseId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
