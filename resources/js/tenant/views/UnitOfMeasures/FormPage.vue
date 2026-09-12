<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import { useUnitOfMeasureStore } from "@tenant/stores/unitOfMeasure";
import { storeToRefs } from "pinia";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import UnitForm from "./Form.vue";
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
const store = useUnitOfMeasureStore();

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const formRef = ref<InstanceType<typeof UnitForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const errors = ref<Record<string, string>>({});

// Local reactive copy of the fetched unit
const currentUnit = ref<any>(null);
const isLoading = ref(false);

const { units } = storeToRefs(store);

const isEditing = computed(() => route.name === "UnitOfMeasuresEdit");
const unitId = computed(() => route.params.id as string);

const { currentIndex: unitIndex, prevRecord: prevUnit, nextRecord: nextUnit, navigatePrev: navPrevUnit, navigateNext: navNextUnit } =
    useRecordNavigator(units, unitId, "/admin/unit-of-measures", () => store.fetchUnits(1, "total"));

const archiveLabel = computed(() =>
    currentUnit.value?.is_active === false
        ? t('common.actions.activate')
        : t('common.actions.deactivate'),
);
const isArchived = computed(() => isEditing.value && currentUnit.value?.is_active === false);
const canManage = computed(() => isEditing.value && !!unitId.value);

// Watch route id so navigating from /create to /:id/edit re-fetches data
watch(
    () => route.params.id,
    async (newId) => {
        if (isEditing.value && newId) {
            isLoading.value = true;
            try {
                currentUnit.value = await store.fetchUnit(newId as string);
            } catch {
                router.push("/admin/unit-of-measures");
            } finally {
                isLoading.value = false;
            }
        } else {
            currentUnit.value = null;
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
            currentUnit.value = await store.updateUnit(unitId.value, payload);
            toast.success(t('unitOfMeasures.page.updatedToastTitle'), {
                description: t('unitOfMeasures.page.updatedToastDesc'),
            });
            activityLogRef.value?.load();
        } else {
            const created = await store.createUnit(payload);
            toast.success(t('unitOfMeasures.page.createdToastTitle'), {
                description: t('unitOfMeasures.page.createdToastDesc'),
            });
            router.push(`/admin/unit-of-measures/${created.id}/edit`);
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
            console.error("Error saving unit:", err);
            toast.error(t('unitOfMeasures.page.savingErrorToastTitle'), {
                description: err?.response?.data?.message || t('common.unexpectedError'),
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleArchive = () => {
    if (!unitId.value) return;
    confirmDialog.value?.show(
        t('unitOfMeasures.page.archiveConfirmTitle', { action: archiveLabel.value }),
        t('unitOfMeasures.page.archiveConfirmMessage', { action: archiveLabel.value.toLowerCase(), name: currentUnit.value?.name }),
        async () => {
            isLoading.value = true;
            try {
                await store.toggleActive(Number(unitId.value));
                currentUnit.value = await store.fetchUnit(unitId.value);
            } catch (e) {
                console.error("Error toggling unit status:", e);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!unitId.value) return;
    confirmDialog.value?.show(
        t('unitOfMeasures.page.deleteConfirmTitle'),
        t('unitOfMeasures.page.deleteConfirmMessage', { name: currentUnit.value?.name }),
        async () => {
            isLoading.value = true;
            try {
                await store.deleteUnit(Number(unitId.value));
                router.push("/admin/unit-of-measures");
            } catch (err: any) {
                const msg = err?.response?.data?.message || t('unitOfMeasures.page.deleteErrorFallback');
                alert(msg);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleCancel = () => router.push("/admin/unit-of-measures");

const pageTitle = computed(() =>
    isEditing.value ? t('unitOfMeasures.page.editTitle') : t('unitOfMeasures.page.createTitle'),
);
const breadcrumbs = computed(() => [
    { label: t('unitOfMeasures.page.breadcrumbList'), href: "/admin/unit-of-measures" },
    { label: isEditing.value ? t('common.actions.edit') : t('common.actions.create') },
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
                <Button size="sm" class="h-9" :disabled="isLoading" @click="handleSave">
                    <Save class="mr-2 h-4 w-4" />
                    {{ isLoading ? t('common.saving') : isEditing ? t('unitOfMeasures.page.updateButton') : t('unitOfMeasures.page.createButton') }}
                </Button>

                <DropdownMenu v-if="canManage">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="icon" class="h-9 w-9" :aria-label="t('unitOfMeasures.page.settingsAriaLabel')">
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>{{ t('unitOfMeasures.page.optionsLabel') }}</DropdownMenuLabel>
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
                    :current-index="unitIndex"
                    :total="units.length"
                    :has-prev="!!prevUnit"
                    :has-next="!!nextUnit"
                    :disabled="isLoading"
                    @prev="navPrevUnit"
                    @next="navNextUnit"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <UnitForm
                        ref="formRef"
                        :initial-data="currentUnit || {}"
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
                        subject="unit_of_measure"
                        :subject-id="unitId"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
