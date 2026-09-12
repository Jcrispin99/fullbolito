<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useSaleStore } from "@tenant/stores/sale";
import { useCompanyFilterRefresh } from "@/composables/useCompanyFilterRefresh";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import SaleForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import {
    ArrowLeft,
    Save,
    Trash2,
    ChevronDown,
    Undo2,
    Send,
    RefreshCw,
    CheckCircle2,
    XCircle,
    Upload,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import SunatArtifactPanel from "@tenant/components/SunatArtifactPanel.vue";
import LoyaltyPanel from "@tenant/components/LoyaltyPanel.vue";
import { Heart } from "lucide-vue-next";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const saleStore = useSaleStore();
const { sales } = storeToRefs(saleStore);

const mode = computed(() => (route.params.id ? "edit" : "create"));
const saleId = computed(() => (route.params.id ? String(route.params.id) : null));
const saleIdStr = computed(() => String(route.params.id || ""));

const {
    currentIndex: saleIdx,
    prevRecord: prevSale,
    nextRecord: nextSale,
    navigatePrev: navPrevSale,
    navigateNext: navNextSale,
} = useRecordNavigator(sales, saleIdStr, "/admin/sales", () =>
    saleStore.fetchSales(1, "total"),
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentSale = ref<any>({});
const formOptions = ref<any>({
    customers: [],
    warehouses: [],
    companies: [],
    taxes: [],
    uoms: [],
});
const saleForm = ref<InstanceType<typeof SaleForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const loyaltyOpen = ref(false);
const loyaltyEarned = ref<any[]>([]);

type ViewMode = "edit" | "refund";
const viewMode = ref<ViewMode>("edit");
const refundQuantities = ref<Record<number, number>>({});
const refundNotes = ref("");
const isRefunding = ref(false);

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(amount);

const canManageSale = computed(
    () => mode.value === "edit" && !!saleId.value && currentSale.value.status === "draft",
);

const refundLines = computed<any[]>(
    () => currentSale.value?.refunds_summary?.lines ?? [],
);

const refundEligible = computed(() => {
    const sale = currentSale.value;
    if (!sale) return false;
    if (sale.status !== "posted") return false;
    if (sale.original_sale?.id || sale.original_sale_id) return false;
    const totals = sale.refunds_summary?.totals;
    return Boolean(totals && Number(totals.available_total) > 0);
});

const refundTotal = computed(() => {
    let total = 0;
    for (const l of refundLines.value) {
        const qty = Number(refundQuantities.value[l.product_product_id] ?? 0);
        if (qty <= 0 || Number(l.available_quantity) <= 0) continue;
        total +=
            (qty / Number(l.available_quantity)) * Number(l.available_total);
    }
    return Math.round(total * 100) / 100;
});

const isLineOverflow = (line: any) => {
    const qty = Number(refundQuantities.value[line.product_product_id] ?? 0);
    return qty > Number(line.available_quantity);
};

const hasAnyOverflow = computed(() =>
    refundLines.value.some((l) => isLineOverflow(l)),
);

const canSubmitRefund = computed(
    () =>
        !hasAnyOverflow.value &&
        refundLines.value.some(
            (l) =>
                Number(refundQuantities.value[l.product_product_id] ?? 0) > 0,
        ),
);

const startRefund = () => {
    refundQuantities.value = {};
    for (const l of refundLines.value) {
        refundQuantities.value[l.product_product_id] = 0;
    }
    refundNotes.value = "";
    viewMode.value = "refund";
};

const cancelRefund = () => {
    viewMode.value = "edit";
    refundQuantities.value = {};
    refundNotes.value = "";
};

const updateRefundQty = (productId: number, value: string) => {
    let n = Number(value);
    if (!Number.isFinite(n) || n < 0) n = 0;
    refundQuantities.value[productId] = n;
};

const submitRefund = async () => {
    if (!canSubmitRefund.value || !saleId.value || isRefunding.value) return;
    isRefunding.value = true;
    try {
        const lines = refundLines.value
            .filter(
                (l) =>
                    Number(refundQuantities.value[l.product_product_id] ?? 0) >
                    0,
            )
            .map((l) => ({
                product_product_id: l.product_product_id,
                quantity: Number(refundQuantities.value[l.product_product_id]),
            }));

        await saleStore.createSaleRefund(saleId.value, {
            lines,
            notes: refundNotes.value.trim() || null,
        });

        toast.success(t('sales.page.creditNoteGeneratedToast'));
        const refreshed = await saleStore.fetchSale(saleId.value);
        if (refreshed) currentSale.value = refreshed;
        viewMode.value = "edit";
        refundQuantities.value = {};
        refundNotes.value = "";
        activityLogRef.value?.load();
    } catch (err: any) {
        const e = err?.response?.data || err;
        toast.error(
            e?.message || t('sales.page.creditNoteErrorFallback'),
        );
    } finally {
        isRefunding.value = false;
    }
};

const loadFormData = async () => {
    isLoading.value = true;
    try {
        const options = await saleStore.fetchFormOptions();
        if (options) {
            formOptions.value = options;
        }

        if (mode.value === "edit" && saleId.value) {
            const data = await saleStore.fetchSale(saleId.value);
            if (data) {
                currentSale.value = data;
            }
        } else {
            currentSale.value = {
                status: "draft",
                products: [],
            };
        }
    } catch (error) {
        console.error("Error fetching sale data:", error);
        toast.error(t('sales.page.errorLoadingFormData'));
    } finally {
        isLoading.value = false;
    }
};

watch(() => route.params.id, () => {
    viewMode.value = "edit";
    refundQuantities.value = {};
    refundNotes.value = "";
    loadFormData();
}, { immediate: true });

useCompanyFilterRefresh(() => loadFormData());

const handleSubmit = async (formData: any) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (mode.value === "edit" && saleId.value) {
            await saleStore.updateSale(saleId.value, formData);
            toast.success(t('sales.page.updatedToastTitle'), {
                description: t('sales.page.updatedToastDesc'),
            });
            activityLogRef.value?.load();
        } else {
            const created = await saleStore.createSale(formData);
            toast.success(t('sales.page.createdToastTitle'), {
                description: t('sales.page.createdToastDesc'),
            });
            router.push(`/admin/sales/${created.id}/edit`);
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
            toast.error(t('common.validationErrorTitle'), {
                description: t('common.validationErrorDesc'),
            });
        } else {
            console.error("Error saving sale:", err);
            toast.error(t('sales.page.savingErrorToastTitle'), {
                description: e?.message || t('common.unexpectedError'),
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/sales");
};

const handleSave = () => {
    saleForm.value?.submit();
};

const handleDelete = () => {
    if (!saleId.value) return;
    const id = Number(saleId.value);
    confirmDialog.value?.show(
        t('sales.page.deleteConfirmTitle'),
        t('sales.page.deleteConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                await saleStore.deleteSale(id);
                router.push("/admin/sales");
            } catch (err: any) {
                toast.error(t('sales.page.errorToastTitle'), {
                    description: err.message || t('sales.page.deleteErrorFallback'),
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handlePost = () => {
    if (!saleId.value) return;
    const id = Number(saleId.value);
    confirmDialog.value?.show(
        t('sales.page.postConfirmTitle'),
        t('sales.page.postConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                const response = await saleStore.postSale(id);
                toast.success(t('sales.page.postSuccessToast'));
                if (response) {
                    currentSale.value = response;
                    // Capturar puntos de lealtad
                    const ltx = response.loyalty_transactions || [];
                    const earned = ltx.filter((t: any) => t.type === 'earn');
                    if (earned.length > 0) {
                        loyaltyEarned.value = earned;
                    }
                }
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error(t('sales.page.errorToastTitle'), {
                    description: err?.message || t('sales.page.postErrorFallback'),
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleCancelSale = () => {
    if (!saleId.value) return;
    const id = Number(saleId.value);
    confirmDialog.value?.show(
        t('sales.page.cancelConfirmTitle'),
        t('sales.page.cancelConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                const data = await saleStore.cancelSale(id);
                toast.success(t('sales.page.cancelSuccessToast'));
                if (data) currentSale.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error(t('sales.page.errorToastTitle'), {
                    description: err?.message || t('sales.page.cancelErrorFallback'),
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handlePaySale = () => {
    if (!saleId.value) return;
    const id = Number(saleId.value);
    confirmDialog.value?.show(
        t('sales.page.payConfirmTitle'),
        t('sales.page.payConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                const data = await saleStore.paySale(id);
                toast.success(t('sales.page.paySuccessToast'));
                if (data) currentSale.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error(t('sales.page.errorToastTitle'), {
                    description: err?.message || t('sales.page.payErrorFallback'),
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleSendToSunat = () => {
    if (!saleId.value) return;
    const id = Number(saleId.value);
    confirmDialog.value?.show(
        t('sales.page.sendToSunatConfirmTitle'),
        t('sales.page.sendToSunatConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                const data = await saleStore.sendToSunat(id);
                if (data) currentSale.value = data;
                const status = data?.sunat_status;
                if (status === "accepted") {
                    toast.success(t('sales.page.sunatAcceptedToast'));
                } else if (status === "sent") {
                    toast.success(t('sales.page.sunatSentToast'));
                } else if (status === "skipped") {
                    toast.info(t('sales.page.sunatSkippedToast'));
                } else {
                    toast.warning(t('sales.page.sunatErrorToastTitle'), {
                        description:
                            data?.sunat_response?.error ||
                            t('sales.page.sunatErrorToastDescFallback'),
                    });
                }
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error(t('sales.page.resendErrorToastTitle'), {
                    description: err?.message || t('sales.page.resendErrorFallback'),
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

// Mapeo de sunat_status a presentación visual.
const sunatBadge = computed(() => {
    const status = currentSale.value?.sunat_status;
    if (!status) return null;
    const map: Record<string, { label: string; class: string }> = {
        pending: { label: t('sales.page.sunatPending'), class: "bg-gray-100 text-gray-700" },
        processing: { label: t('sales.page.sunatProcessing'), class: "bg-amber-100 text-amber-800" },
        sent: { label: t('sales.page.sunatSentBadge'), class: "bg-blue-100 text-blue-800" },
        accepted: { label: t('sales.page.sunatAcceptedBadge'), class: "bg-green-100 text-green-800" },
        error: { label: t('sales.page.sunatErrorBadge'), class: "bg-red-100 text-red-800" },
        skipped: { label: t('sales.page.sunatNA'), class: "bg-gray-100 text-gray-500" },
    };
    return map[status] || { label: t('sales.page.sunatFallback', { status }), class: "bg-gray-100 text-gray-800" };
});

const saleDisplayName = computed(() => {
    if (mode.value === "create") return t('sales.form.newName');

    const serie = currentSale.value?.serie ? `${currentSale.value.serie}-` : "";
    const correlative = currentSale.value?.correlative || "";
    return `${serie}${correlative}`;
});

const pageTitle = computed(() =>
    mode.value === "edit" ? t('sales.page.editTitle') : t('sales.page.createTitle'),
);

const statusLabel = computed(() => {
    const status = currentSale.value?.status;
    if (status === 'draft') return t('sales.page.statusDraft');
    if (status === 'posted') return t('sales.page.statusPosted');
    if (status === 'cancelled') return t('sales.page.statusCancelled');
    return status;
});

const breadcrumbs = computed(() => [
    { label: t('sales.page.breadcrumbList'), href: "/admin/sales" },
    { label: pageTitle.value },
]);

// Si hay un envío SUNAT con error/pendiente, badgear la pestaña SUNAT
// y arrancar con esa pestaña activa.
const sunatNeedsAttention = computed(() => {
    const s = currentSale.value?.sunat_status;
    return s === "error" || s === "pending" || s === "sent";
});

const sideTab = ref<string>("logs");

watch(
    () => currentSale.value?.sunat_status,
    () => {
        sideTab.value = sunatNeedsAttention.value ? "sunat" : "logs";
    },
);
</script>

<template>
    <DashboardLayout :breadcrumbs="breadcrumbs">
        <PageHeader :title="pageTitle">
            <template #leading>
                <Button
                    variant="outline"
                    size="icon"
                    class="h-9 w-9"
                    :aria-label="t('common.actions.back')"
                    @click="handleCancel"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </template>

            <template #trailing>
                <div class="flex items-center gap-2">
                    <span
                        v-if="mode === 'edit' && currentSale.status"
                        class="hidden sm:inline-flex px-2 py-1 mr-2 rounded text-xs font-medium uppercase text-[10px] tracking-wider bg-gray-100 text-gray-800"
                        :class="[
                            currentSale.status === 'posted'
                                ? 'bg-blue-100 text-blue-800'
                                : currentSale.status === 'cancelled'
                                  ? 'bg-red-100 text-red-800'
                                  : '',
                        ]"
                    >
                        {{ statusLabel }}
                    </span>

                    <span
                        v-if="mode === 'edit' && sunatBadge"
                        class="hidden sm:inline-flex px-2 py-1 mr-2 rounded text-xs font-medium uppercase text-[10px] tracking-wider"
                        :class="sunatBadge.class"
                        :title="currentSale?.sunat_response?.error || ''"
                    >
                        {{ sunatBadge.label }}
                    </span>

                    <Button
                        v-if="mode === 'create' || currentSale?.status === 'draft'"
                        size="sm"
                        class="h-9"
                        :disabled="isLoading"
                        @click="handleSave"
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{
                            isLoading
                                ? t('common.saving')
                                : mode === "edit"
                                  ? t('sales.page.updateButton')
                                  : t('sales.page.createTitle')
                        }}
                    </Button>
                    <DropdownMenu v-if="mode === 'edit' && viewMode === 'edit'">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-9 gap-1.5"
                                :disabled="isLoading"
                            >
                                {{ t('sales.page.actionsLabel') }}
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[220px]">
                            <DropdownMenuLabel>{{ t('sales.page.optionsLabel') }}</DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <!-- DRAFT -->
                            <DropdownMenuItem
                                v-if="currentSale?.status === 'draft'"
                                @click="handlePost"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                {{ t('sales.page.actionPublish') }}
                            </DropdownMenuItem>

                            <!-- POSTED -->
                            <DropdownMenuItem
                                v-if="
                                    currentSale?.status === 'posted' &&
                                    currentSale?.sunat_status &&
                                    currentSale.sunat_status !== 'accepted' &&
                                    currentSale.sunat_status !== 'skipped'
                                "
                                @click="handleSendToSunat"
                            >
                                <RefreshCw
                                    v-if="currentSale.sunat_status === 'error'"
                                    class="mr-2 h-4 w-4"
                                />
                                <Send v-else class="mr-2 h-4 w-4" />
                                {{
                                    currentSale.sunat_status === "error"
                                        ? t('sales.page.actionRetrySunat')
                                        : t('sales.page.actionSendSunat')
                                }}
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="refundEligible"
                                @click="startRefund"
                            >
                                <Undo2 class="mr-2 h-4 w-4" />
                                {{ t('sales.page.actionRefund') }}
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                v-if="
                                    currentSale?.status === 'posted' &&
                                    currentSale?.payment_status !== 'paid'
                                "
                                @click="handlePaySale"
                            >
                                <CheckCircle2 class="mr-2 h-4 w-4" />
                                {{ t('sales.page.actionMarkPaid') }}
                            </DropdownMenuItem>

                            <!-- LEALTAD (cualquier estado con partner) -->
                            <DropdownMenuItem
                                v-if="
                                    currentSale?.partner_id &&
                                    (currentSale?.status === 'draft' ||
                                        currentSale?.status === 'posted')
                                "
                                @click="loyaltyOpen = true"
                            >
                                <Heart class="mr-2 h-4 w-4 text-pink-500" />
                                {{ t('sales.page.actionLoyalty') }}
                            </DropdownMenuItem>

                            <!-- DESTRUCTIVAS -->
                            <template
                                v-if="
                                    canManageSale ||
                                    currentSale?.status === 'posted'
                                "
                            >
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-if="currentSale?.status === 'posted'"
                                    class="text-destructive focus:text-destructive"
                                    @click="handleCancelSale"
                                >
                                    <XCircle class="mr-2 h-4 w-4" />
                                    {{ t('sales.page.actionCancelSale') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canManageSale"
                                    class="text-destructive focus:text-destructive"
                                    @click="handleDelete"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    {{ t('common.actions.delete') }}
                                </DropdownMenuItem>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <RecordNavigator
                    v-if="mode === 'edit'"
                    :current-index="saleIdx"
                    :total="sales.length"
                    :has-prev="!!prevSale"
                    :has-next="!!nextSale"
                    :disabled="isLoading"
                    @prev="navPrevSale"
                    @next="navNextSale"
                />
            </template>
        </PageHeader>
        <div class="-mt-3">
            <!-- Loyalty earned badges -->
            <div
                v-if="currentSale?.loyalty_transactions?.length && viewMode === 'edit'"
                class="flex flex-wrap items-center gap-2 mb-1"
            >
                <span
                    v-for="tx in currentSale.loyalty_transactions.filter((t: any) => t.type === 'earn')"
                    :key="tx.id"
                    class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-pink-50 text-pink-700 border border-pink-200"
                >
                    <Heart class="h-3 w-3" />
                    +{{ tx.points.toLocaleString() }} {{ tx.point_name }} ({{ tx.program_name }})
                </span>
            </div>

            <div v-if="viewMode === 'edit'" class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <SaleForm
                        ref="saleForm"
                        :mode="mode"
                        :initial-data="currentSale"
                        :display-name="saleDisplayName"
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
                                {{ t('sales.page.logsTab') }}
                            </Button>
                            <Button
                                :variant="sideTab === 'sunat' ? 'secondary' : 'ghost'"
                                size="sm"
                                class="h-8 text-xs gap-1.5"
                                @click="sideTab = 'sunat'"
                            >
                                SUNAT
                                <span
                                    v-if="sunatNeedsAttention"
                                    class="inline-flex items-center justify-center min-w-[1rem] h-4 px-1 text-[10px] font-semibold rounded bg-red-100 text-red-700"
                                >
                                    !
                                </span>
                            </Button>
                        </div>

                        <div v-show="sideTab === 'logs'">
                            <ActivityLogPanel
                                ref="activityLogRef"
                                subject="sale"
                                :subject-id="saleId ? Number(saleId) : null"
                            />
                        </div>

                        <div v-show="sideTab === 'sunat'">
                            <SunatArtifactPanel
                                :doc="currentSale"
                                kind="sale"
                                :can-send="false"
                                :is-loading="isLoading"
                                @send="handleSendToSunat"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- REFUND VIEW -->
            <div v-else class="max-w-4xl">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="gap-1.5 -ml-2"
                            :disabled="isRefunding"
                            @click="cancelRefund"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            {{ t('sales.page.backToDetail') }}
                        </Button>
                    </div>
                </div>

                <h2 class="text-xl font-bold mb-1">{{ t('sales.page.refundTitle') }}</h2>
                <p class="text-sm text-muted-foreground mb-6">
                    {{ t('sales.page.refundSubjectPrefix') }}
                    <span class="font-mono">
                        {{ currentSale.serie }}-{{ currentSale.correlative }}
                    </span>
                    — {{ currentSale.partner?.name || t('sales.page.noCustomer') }}
                </p>

                <div
                    class="border border-border rounded-lg overflow-hidden mb-6"
                >
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs text-muted-foreground uppercase tracking-wide"
                        >
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">
                                    {{ t('sales.page.tableProduct') }}
                                </th>
                                <th class="text-right px-4 py-2 font-medium w-20">
                                    {{ t('sales.page.tableSold') }}
                                </th>
                                <th class="text-right px-4 py-2 font-medium w-20">
                                    {{ t('sales.page.tableReturned') }}
                                </th>
                                <th class="text-right px-4 py-2 font-medium w-20">
                                    {{ t('sales.page.tableAvailable') }}
                                </th>
                                <th class="text-right px-4 py-2 font-medium w-28">
                                    {{ t('sales.page.tableToReturn') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template
                                v-for="line in refundLines"
                                :key="line.product_product_id"
                            >
                                <tr>
                                    <td class="px-4 py-2">
                                        <p class="font-medium">
                                            {{
                                                currentSale.products?.find(
                                                    (p: any) =>
                                                        p.product_product_id ===
                                                        line.product_product_id,
                                                )?.product?.name ||
                                                t('sales.page.productFallback', { id: line.product_product_id })
                                            }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right tabular-nums"
                                    >
                                        {{
                                            Number(
                                                line.original_quantity,
                                            ).toFixed(2)
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right tabular-nums text-muted-foreground"
                                    >
                                        {{
                                            Number(
                                                line.refunded_quantity,
                                            ).toFixed(2)
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right tabular-nums font-medium"
                                    >
                                        {{
                                            Number(
                                                line.available_quantity,
                                            ).toFixed(2)
                                        }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <div
                                            class="flex flex-col items-end gap-0.5"
                                        >
                                            <input
                                                :value="
                                                    refundQuantities[
                                                        line.product_product_id
                                                    ] ?? 0
                                                "
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                :disabled="
                                                    Number(
                                                        line.available_quantity,
                                                    ) <= 0
                                                "
                                                :class="[
                                                    'w-24 h-8 px-2 bg-background border rounded text-sm text-right tabular-nums focus:outline-none focus:ring-2 disabled:opacity-50',
                                                    isLineOverflow(line)
                                                        ? 'border-destructive focus:ring-destructive text-destructive'
                                                        : 'border-input focus:ring-ring',
                                                ]"
                                                @input="
                                                    (e) =>
                                                        updateRefundQty(
                                                            line.product_product_id,
                                                            (
                                                                e.target as HTMLInputElement
                                                            ).value,
                                                        )
                                                "
                                            />
                                            <span
                                                :class="[
                                                    'text-[10px] tabular-nums',
                                                    isLineOverflow(line)
                                                        ? 'text-destructive font-medium'
                                                        : 'text-muted-foreground',
                                                ]"
                                            >
                                                {{ t('sales.page.maxLabel') }}
                                                {{
                                                    Number(
                                                        line.available_quantity,
                                                    ).toFixed(2)
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-if="line.lots && line.lots.length > 0"
                                    class="bg-muted/20"
                                >
                                    <td colspan="5" class="px-4 py-2">
                                        <div
                                            class="pl-4 border-l-2 border-border space-y-1"
                                        >
                                            <p
                                                class="text-[10px] uppercase tracking-wider text-muted-foreground font-medium"
                                            >
                                                {{ t('sales.page.actionLots') }}
                                            </p>
                                            <div
                                                v-for="lot in line.lots"
                                                :key="lot.lot_id"
                                                class="flex items-center gap-3 text-xs"
                                            >
                                                <span class="font-mono">
                                                    {{
                                                        lot.lot_number ||
                                                        `#${lot.lot_id}`
                                                    }}
                                                </span>
                                                <span
                                                    v-if="lot.expires_at"
                                                    class="text-muted-foreground"
                                                >
                                                    {{ t('sales.page.expiresLabel') }} {{ lot.expires_at }}
                                                </span>
                                                <span
                                                    class="ml-auto tabular-nums text-muted-foreground"
                                                >
                                                    {{ t('sales.page.soldLabel') }}
                                                    {{
                                                        Number(
                                                            lot.sold_quantity,
                                                        ).toFixed(2)
                                                    }}
                                                    · {{ t('sales.page.returnedLabel') }}
                                                    {{
                                                        Number(
                                                            lot.refunded_quantity,
                                                        ).toFixed(2)
                                                    }}
                                                    ·
                                                    <span
                                                        :class="
                                                            Number(
                                                                lot.available_quantity,
                                                            ) > 0
                                                                ? 'text-foreground font-medium'
                                                                : ''
                                                        "
                                                    >
                                                        {{ t('sales.page.availableLabel') }}
                                                        {{
                                                            Number(
                                                                lot.available_quantity,
                                                            ).toFixed(2)
                                                        }}
                                                    </span>
                                                </span>
                                            </div>
                                            <p
                                                class="text-[10px] text-muted-foreground italic pt-1"
                                            >
                                                {{ t('sales.page.fefoNote') }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mb-6">
                    <label
                        class="block text-xs text-muted-foreground uppercase tracking-wide mb-1"
                    >
                        {{ t('sales.page.notesOptional') }}
                    </label>
                    <textarea
                        v-model="refundNotes"
                        rows="2"
                        :placeholder="t('sales.page.refundNotesPlaceholder')"
                        class="w-full px-3 py-2 bg-background border border-input rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    ></textarea>
                </div>

                <div
                    class="flex items-center justify-between border-t border-border pt-4"
                >
                    <div>
                        <p
                            class="text-xs text-muted-foreground uppercase tracking-wide"
                        >
                            {{ t('sales.page.totalToReturn') }}
                        </p>
                        <p
                            :class="[
                                'text-2xl font-bold tabular-nums',
                                hasAnyOverflow ? 'text-destructive' : '',
                            ]"
                        >
                            {{ formatCurrency(refundTotal) }}
                        </p>
                        <p
                            v-if="hasAnyOverflow"
                            class="text-xs text-destructive mt-1"
                        >
                            {{ t('sales.page.overflowWarning') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            :disabled="isRefunding"
                            @click="cancelRefund"
                        >
                            {{ t('common.actions.cancel') }}
                        </Button>
                        <Button
                            :disabled="!canSubmitRefund || isRefunding"
                            @click="submitRefund"
                        >
                            <Undo2 class="h-4 w-4 mr-1.5" />
                            {{
                                isRefunding
                                    ? t('sales.page.generating')
                                    : t('sales.page.generateCreditNote')
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />

    <!-- Loyalty Panel Modal -->
    <LoyaltyPanel
        :open="loyaltyOpen"
        :mode="currentSale?.status === 'posted' || currentSale?.status === 'cancelled' ? 'posted' : 'draft'"
        :partner-id="currentSale?.partner_id"
        :total="currentSale?.total ? Number(currentSale.total) : 0"
        :total-qty="currentSale?.products?.reduce((sum: number, p: any) => sum + (p.quantity || 0), 0) || 0"
        :loyalty-transactions="currentSale?.loyalty_transactions"
        module="sales"
        @close="loyaltyOpen = false"
    />
</template>
