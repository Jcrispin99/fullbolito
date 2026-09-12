<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { usePaymentMethodStore } from "@tenant/stores/paymentMethod";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import PaymentMethodForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { toast } from "vue-sonner";
import { ArrowLeft, Save, Archive, Settings2, Trash2 } from "lucide-vue-next";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const paymentMethodStore = usePaymentMethodStore();

const { currentPaymentMethod, isLoading, paymentMethods } = storeToRefs(paymentMethodStore);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const isEditing = computed(() => route.name === "PaymentMethodsEdit");
const paymentMethodId = computed(() => route.params.id as string);

const { currentIndex: pmIndex, prevRecord: prevPm, nextRecord: nextPm, navigatePrev: navPrevPm, navigateNext: navNextPm } =
    useRecordNavigator(paymentMethods, paymentMethodId, "/admin/payment-methods", () => paymentMethodStore.fetchPaymentMethods(1, "total"));

const formRef = ref<InstanceType<typeof PaymentMethodForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const errors = ref<Record<string, string>>({});

const canManagePaymentMethod = computed(() => isEditing.value && !!paymentMethodId.value);
const archiveLabel = computed(() =>
    currentPaymentMethod.value?.is_active === false
        ? t('common.actions.activate')
        : t('common.actions.deactivate'),
);
const isArchived = computed(
    () => isEditing.value && currentPaymentMethod.value?.is_active === false,
);

watch(
    () => route.params.id,
    async () => {
        // Always reset so create form never shows stale data from a previous edit
        currentPaymentMethod.value = null;
        isLoading.value = true;
        try {
            if (isEditing.value && paymentMethodId.value) {
                const data = await paymentMethodStore.fetchPaymentMethod(paymentMethodId.value);
                if (data) {
                    currentPaymentMethod.value = data;
                }
            }
        } catch (error) {
            console.error("Error fetching paymentMethod details:", error);
            router.push("/admin/payment-methods");
        } finally {
            isLoading.value = false;
        }
    },
    { immediate: true },
);

const handleSave = () => {
    formRef.value?.submit();
};
const handleSubmit = async (payload: any) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (isEditing.value) {
            await paymentMethodStore.updatePaymentMethod(paymentMethodId.value, payload);
            toast.success(t('paymentMethods.page.updatedToastTitle'), {
                description: t('paymentMethods.page.updatedToastDesc'),
            });
            activityLogRef.value?.load();
        } else {
            const newCat = await paymentMethodStore.createPaymentMethod(payload);
            toast.success(t('paymentMethods.page.createdToastTitle'), {
                description: t('paymentMethods.page.createdToastDesc'),
            });
            router.push(`/admin/payment-methods/${newCat.id}/edit`);
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
            toast.error(t('common.validationErrorTitle'), {
                description: t('common.validationErrorDesc'),
            });
        } else {
            console.error("Error saving paymentMethod:", err);
            toast.error(t('paymentMethods.page.savingErrorToastTitle'), {
                description:
                    err?.response?.data?.message ||
                    t('common.unexpectedError'),
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/payment-methods");
};

const handleArchive = async () => {
    if (!paymentMethodId.value) return;
    const id = paymentMethodId.value;

    confirmDialog.value?.show(
        t('paymentMethods.page.archiveConfirmTitle', { action: archiveLabel.value }),
        t('paymentMethods.page.archiveConfirmMessage', { action: archiveLabel.value.toLowerCase() }),
        async () => {
            isLoading.value = true;
            try {
                const updated = await paymentMethodStore.toggleActive(id);
                currentPaymentMethod.value = updated as any;
            } catch (error: any) {
                console.error("Error toggling paymentMethod status:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = async () => {
    if (!paymentMethodId.value) return;
    const id = paymentMethodId.value;

    confirmDialog.value?.show(
        t('paymentMethods.page.deleteConfirmTitle'),
        t('paymentMethods.page.deleteConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                await paymentMethodStore.deletePaymentMethod(id);
                router.push("/admin/payment-methods");
            } catch (error: any) {
                console.error("Failed to delete paymentMethod:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const pageTitle = computed(() =>
    isEditing.value ? t('paymentMethods.page.editTitle') : t('paymentMethods.page.createTitle'),
);

const breadcrumbs = computed(() => [
    { label: t('paymentMethods.page.breadcrumbList'), href: "/admin/payment-methods" },
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
                <Button
                    size="sm"
                    class="h-9"
                    :disabled="isLoading"
                    @click="handleSave"
                >
                    <Save class="mr-2 h-4 w-4" />
                    {{
                        isLoading
                            ? t('common.saving')
                            : isEditing
                              ? t('paymentMethods.page.updateButton')
                              : t('paymentMethods.page.createTitle')
                    }}
                </Button>
                <DropdownMenu v-if="canManagePaymentMethod">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            :aria-label="t('paymentMethods.page.settingsAriaLabel')"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>{{ t('paymentMethods.page.optionsLabel') }}</DropdownMenuLabel>
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
                            {{ t('common.actions.delete') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <RecordNavigator
                    v-if="isEditing"
                    :current-index="pmIndex"
                    :total="paymentMethods.length"
                    :has-prev="!!prevPm"
                    :has-next="!!nextPm"
                    :disabled="isLoading"
                    @prev="navPrevPm"
                    @next="navNextPm"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <PaymentMethodForm
                        ref="formRef"
                        :initial-data="currentPaymentMethod || {}"
                        :is-editing="isEditing"
                        :archived="isArchived"
                        :errors="errors"
                        @submit="handleSubmit"
                    />
                </div>
                <div
                    class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0"
                >
                    <ActivityLogPanel
                        ref="activityLogRef"
                        v-if="isEditing"
                        subject="paymentMethod"
                        :subject-id="paymentMethodId"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
