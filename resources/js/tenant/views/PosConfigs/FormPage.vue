<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { usePosConfigStore } from "@tenant/stores/posConfig";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import PosConfigForm from "./Form.vue";
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
import {
    ArrowLeft,
    Save,
    Archive,
    Settings2,
    Trash2,
    Play,
} from "lucide-vue-next";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const posConfigStore = usePosConfigStore();

const { currentPosConfig, isLoading, posConfigs } = storeToRefs(posConfigStore);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const isEditing = computed(() => route.name === "PosConfigsEdit");
const posConfigId = computed(() => route.params.id as string);

const {
    currentIndex: pcIndex,
    prevRecord: prevPc,
    nextRecord: nextPc,
    navigatePrev: navPrevPc,
    navigateNext: navNextPc,
} = useRecordNavigator(posConfigs, posConfigId, "/admin/pos-configs", () =>
    posConfigStore.fetchPosConfigs(1, "total"),
);

const formRef = ref<InstanceType<typeof PosConfigForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const formOptions = ref<any>({});
const errors = ref<Record<string, string>>({});

const canManagePosConfig = computed(
    () => isEditing.value && !!posConfigId.value,
);
const archiveLabel = computed(() =>
    currentPosConfig.value?.is_active === false
        ? t('common.actions.activate')
        : t('common.actions.deactivate'),
);
const isArchived = computed(
    () => isEditing.value && currentPosConfig.value?.is_active === false,
);

watch(
    () => route.params.id,
    async () => {
        // Always reset so create form never shows stale data from a previous edit
        currentPosConfig.value = null;
        isLoading.value = true;
        try {
            const options = await posConfigStore.fetchFormOptions();
            formOptions.value = options;

            if (isEditing.value && posConfigId.value) {
                const data = await posConfigStore.fetchPosConfig(
                    posConfigId.value,
                );
                if (data) {
                    currentPosConfig.value = data;
                }
            }
        } catch (error) {
            console.error("Error fetching posConfig details:", error);
            router.push("/admin/pos-configs");
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
            await posConfigStore.updatePosConfig(posConfigId.value, payload);
            toast.success(t('posConfigs.page.updatedToastTitle'), {
                description: t('posConfigs.page.updatedToastDesc'),
            });
            activityLogRef.value?.load();
        } else {
            const newCat = await posConfigStore.createPosConfig(payload);
            toast.success(t('posConfigs.page.createdToastTitle'), {
                description: t('posConfigs.page.createdToastDesc'),
            });
            router.push(`/admin/pos-configs/${newCat.id}/edit`);
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
            console.error("Error saving posConfig:", err);
            toast.error(t('posConfigs.page.savingErrorToastTitle'), {
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
    router.push("/admin/pos-configs");
};

const handleOpenCashier = () => {
    if (!posConfigId.value) return;

    router.push({
        name: "PosOpenSession",
        params: { configId: posConfigId.value },
    });
};

const handleArchive = async () => {
    if (!posConfigId.value) return;
    const id = posConfigId.value;

    confirmDialog.value?.show(
        t('posConfigs.page.archiveConfirmTitle', { action: archiveLabel.value }),
        t('posConfigs.page.archiveConfirmMessage', { action: archiveLabel.value.toLowerCase() }),
        async () => {
            isLoading.value = true;
            try {
                const updated = await posConfigStore.toggleActive(id);
                currentPosConfig.value = updated as any;
            } catch (error: any) {
                console.error("Error toggling posConfig status:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = async () => {
    if (!posConfigId.value) return;
    const id = posConfigId.value;

    confirmDialog.value?.show(
        t('posConfigs.page.deleteConfirmTitle'),
        t('posConfigs.page.deleteConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                await posConfigStore.deletePosConfig(id);
                router.push("/admin/pos-configs");
            } catch (error: any) {
                console.error("Failed to delete posConfig:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const pageTitle = computed(() =>
    isEditing.value ? t('posConfigs.page.editTitle') : t('posConfigs.page.createTitle'),
);

const breadcrumbs = computed(() => [
    { label: t('posConfigs.page.breadcrumbList'), href: "/admin/pos-configs" },
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
                    v-if="canManagePosConfig"
                    variant="outline"
                    size="sm"
                    class="h-9"
                    :disabled="
                        isLoading || currentPosConfig?.is_active === false
                    "
                    @click="handleOpenCashier"
                >
                    <Play class="mr-2 h-4 w-4" />
                    {{ t('posConfigs.page.openCashier') }}
                </Button>
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
                              ? t('posConfigs.page.updateButton')
                              : t('posConfigs.page.createTitle')
                    }}
                </Button>
                <DropdownMenu v-if="canManagePosConfig">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            :aria-label="t('posConfigs.page.settingsAriaLabel')"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>{{ t('posConfigs.page.optionsLabel') }}</DropdownMenuLabel>
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
                    :current-index="pcIndex"
                    :total="posConfigs.length"
                    :has-prev="!!prevPc"
                    :has-next="!!nextPc"
                    :disabled="isLoading"
                    @prev="navPrevPc"
                    @next="navNextPc"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <PosConfigForm
                        ref="formRef"
                        :initial-data="currentPosConfig || {}"
                        :form-options="formOptions"
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
                        subject="posConfig"
                        :subject-id="posConfigId"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
