<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useTaxStore } from "@tenant/stores/tax";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import TaxForm from "./Form.vue";
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
const taxStore = useTaxStore();

const { currentTax, isLoading, taxes } = storeToRefs(taxStore);

const isEditing = computed(() => route.name === "TaxesEdit");
const taxId = computed(() => route.params.id as string);

const { currentIndex: taxIndex, prevRecord: prevTax, nextRecord: nextTax, navigatePrev: navPrevTax, navigateNext: navNextTax } =
    useRecordNavigator(taxes, taxId, "/admin/taxes", () => taxStore.fetchTaxes(1, "total"));
const canManageTax = computed(() => isEditing.value && !!taxId.value);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const formRef = ref<InstanceType<typeof TaxForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const errors = ref<Record<string, string>>({});

const archiveLabel = computed(() =>
    currentTax.value?.is_active === false
        ? t('common.actions.activate')
        : t('common.actions.deactivate'),
);
const isArchived = computed(
    () => isEditing.value && currentTax.value?.is_active === false,
);

// Reactive fetch on route id change (handles create → edit redirect)
watch(
    () => route.params.id,
    async (newId) => {
        if (isEditing.value && newId) {
            isLoading.value = true;
            try {
                await taxStore.fetchTax(newId as string);
            } catch {
                router.push("/admin/taxes");
            } finally {
                isLoading.value = false;
            }
        } else {
            currentTax.value = null;
        }
    },
    { immediate: true },
);

const handleSave = () => formRef.value?.submit();

const handleSubmit = async (payload: any) => {
    isLoading.value = true;
    errors.value = {};
    try {
        if (isEditing.value) {
            await taxStore.updateTax(taxId.value, payload);
            toast.success(t('taxes.page.updatedToastTitle'), {
                description: t('taxes.page.updatedToastDesc'),
            });
            activityLogRef.value?.load();
        } else {
            const newTax = await taxStore.createTax(payload);
            toast.success(t('taxes.page.createdToastTitle'), {
                description: t('taxes.page.createdToastDesc'),
            });
            router.push(`/admin/taxes/${newTax.id}/edit`);
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
            console.error("Error saving tax:", err);
            toast.error(t('taxes.page.savingErrorToastTitle'), {
                description: err?.response?.data?.message || t('common.unexpectedError'),
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => router.push("/admin/taxes");

const handleArchive = () => {
    if (!taxId.value) return;
    confirmDialog.value?.show(
        t('taxes.page.archiveConfirmTitle', { action: archiveLabel.value }),
        t('taxes.page.archiveConfirmMessage', { action: archiveLabel.value.toLowerCase() }),
        async () => {
            isLoading.value = true;
            try {
                const updated = await taxStore.toggleActive(taxId.value);
                currentTax.value = updated;
            } catch (error) {
                console.error("Error toggling tax status:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!taxId.value) return;
    confirmDialog.value?.show(
        t('taxes.page.deleteConfirmTitle'),
        t('taxes.page.deleteConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                await taxStore.deleteTax(taxId.value);
                router.push("/admin/taxes");
            } catch (err: any) {
                const msg = err?.response?.data?.message || t('taxes.page.deleteErrorFallback');
                alert(msg);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const pageTitle = computed(() =>
    isEditing.value ? t('taxes.page.editTitle') : t('taxes.page.createTitle'),
);

const breadcrumbs = computed(() => [
    { label: t('taxes.page.breadcrumbList'), href: "/admin/taxes" },
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
                              ? t('taxes.page.updateButton')
                              : t('taxes.page.createTitle')
                    }}
                </Button>

                <DropdownMenu v-if="canManageTax">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            :aria-label="t('taxes.page.settingsAriaLabel')"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>{{ t('taxes.page.optionsLabel') }}</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="handleArchive">
                            <Archive class="mr-2 h-4 w-4 text-muted-foreground" />
                            {{ archiveLabel }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="text-destructive" @click="handleDelete">
                            <Trash2 class="mr-2 h-4 w-4" />
                            {{ t('common.actions.delete') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <RecordNavigator
                    v-if="isEditing"
                    :current-index="taxIndex"
                    :total="taxes.length"
                    :has-prev="!!prevTax"
                    :has-next="!!nextTax"
                    :disabled="isLoading"
                    @prev="navPrevTax"
                    @next="navNextTax"
                />
            </template>
        </PageHeader>

         <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <TaxForm
                        ref="formRef"
                        :initial-data="currentTax || {}"
                        :is-editing="isEditing"
                        :archived="isArchived"
                        :errors="errors"
                        @submit="handleSubmit"
                    />
                </div>
                <div class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0">
                    <ActivityLogPanel
                        ref="activityLogRef"
                        v-if="isEditing"
                        subject="tax"
                        :subject-id="taxId"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
