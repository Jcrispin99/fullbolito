<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useI18n } from "vue-i18n";
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

const { t } = useI18n();
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
        toast.error(t('purchases.page.errorLoadingFormData'));
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
            toast.success(t('purchases.page.updatedToastTitle'), {
                description: t('purchases.page.updatedToastDesc'),
            });
            activityLogRef.value?.load();
        } else {
            const created = await purchaseStore.createPurchase(formData);
            toast.success(t('purchases.page.createdToastTitle'), {
                description: t('purchases.page.createdToastDesc'),
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
            toast.error(t('common.validationErrorTitle'), {
                description: t('common.validationErrorDesc'),
            });
        } else {
            console.error("Error saving purchase:", err);
            toast.error(t('purchases.page.savingErrorToastTitle'), {
                description: e?.message || t('common.unexpectedError'),
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
        t('purchases.page.deleteConfirmTitle'),
        t('purchases.page.deleteConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                await purchaseStore.deletePurchase(id);
                router.push("/admin/purchases");
            } catch (err: any) {
                toast.error(t('purchases.page.errorToastTitle'), {
                    description: err.message || t('purchases.page.deleteErrorFallback'),
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
        t('purchases.page.postConfirmTitle'),
        t('purchases.page.postConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                const data = await purchaseStore.postPurchase(id);
                toast.success(t('purchases.page.postSuccessToast'));
                if (data) currentPurchase.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error(t('purchases.page.errorToastTitle'), {
                    description: err?.message || t('purchases.page.postErrorFallback'),
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
        t('purchases.page.cancelConfirmTitle'),
        t('purchases.page.cancelConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                const data = await purchaseStore.cancelPurchase(id);
                toast.success(t('purchases.page.cancelSuccessToast'));
                if (data) currentPurchase.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error(t('purchases.page.errorToastTitle'), {
                    description: err?.message || t('purchases.page.cancelErrorFallback'),
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
        t('purchases.page.payConfirmTitle'),
        t('purchases.page.payConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                const data = await purchaseStore.payPurchase(id);
                toast.success(t('purchases.page.paySuccessToast'));
                if (data) currentPurchase.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error(t('purchases.page.errorToastTitle'), {
                    description: err?.message || t('purchases.page.payErrorFallback'),
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
        t('purchases.page.draftConfirmTitle'),
        t('purchases.page.draftConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                const data = await purchaseStore.draftPurchase(id);
                toast.success(t('purchases.page.draftSuccessToast'));
                if (data) currentPurchase.value = data;
                activityLogRef.value?.load();
            } catch (err: any) {
                toast.error(t('purchases.page.errorToastTitle'), {
                    description: err?.message || t('purchases.page.draftErrorFallback'),
                });
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const purchaseDisplayName = computed(() => {
    if (mode.value === "create") return t('purchases.form.newName');

    const serie = currentPurchase.value?.serie
        ? `${currentPurchase.value.serie}-`
        : "";
    const correlative = currentPurchase.value?.correlative || "";
    return `${serie}${correlative}`;
});

const pageTitle = computed(() =>
    mode.value === "edit" ? t('purchases.page.editTitle') : t('purchases.page.createTitle'),
);

const statusLabel = computed(() => {
    const status = currentPurchase.value?.status;
    if (status === 'draft') return t('purchases.page.statusDraft');
    if (status === 'posted') return t('purchases.page.statusPosted');
    if (status === 'cancelled') return t('purchases.page.statusCancelled');
    return status;
});

const breadcrumbs = computed(() => [
    { label: t('purchases.page.breadcrumbList'), href: "/admin/purchases" },
    { label: pageTitle.value },
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
                    :aria-label="t('common.actions.back')"
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
                        {{ statusLabel }}
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
                                ? t('common.saving')
                                : mode === "edit"
                                  ? t('purchases.page.updateButton')
                                  : t('purchases.page.createTitle')
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
                                {{ t('purchases.page.actionsLabel') }}
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[220px]">
                            <DropdownMenuLabel>{{ t('purchases.page.optionsLabel') }}</DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <!-- DRAFT -->
                            <DropdownMenuItem
                                v-if="currentPurchase?.status === 'draft'"
                                @click="handlePost"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                {{ t('purchases.page.actionPublish') }}
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
                                {{ t('purchases.page.actionMarkPaid') }}
                            </DropdownMenuItem>

                            <!-- CANCELLED -->
                            <DropdownMenuItem
                                v-if="currentPurchase?.status === 'cancelled'"
                                @click="handleDraftPurchase"
                            >
                                <RotateCcw class="mr-2 h-4 w-4" />
                                {{ t('purchases.page.actionRestoreDraft') }}
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
                                {{ t('purchases.page.actionLots') }}
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
                                    {{ t('purchases.page.actionCancelPurchase') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canManagePurchase"
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
