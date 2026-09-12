<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useAttributeStore } from "@tenant/stores/attribute";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import AttributeForm from "./Form.vue";
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
const attributeStore = useAttributeStore();

const { currentAttribute, isLoading, attributes } = storeToRefs(attributeStore);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const isEditing = computed(() => route.name === "AttributesEdit");
const attributeId = computed(() => route.params.id as string);

const formRef = ref<InstanceType<typeof AttributeForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const errors = ref<Record<string, string>>({});

const canManageAttribute = computed(() => isEditing.value && !!attributeId.value);
const archiveLabel = computed(() =>
    currentAttribute.value?.is_active === false
        ? t('common.actions.activate')
        : t('common.actions.deactivate'),
);
const isArchived = computed(
    () => isEditing.value && currentAttribute.value?.is_active === false,
);

// ── Prev / Next navigation ────────────────────────────────────────────────────
const { currentIndex, prevRecord, nextRecord, navigatePrev, navigateNext } =
    useRecordNavigator(
        attributes,
        attributeId,
        "/admin/attributes",
        () => attributeStore.fetchAttributes(1, "total"),
    );
// ─────────────────────────────────────────────────────────────────────────────

watch(
    () => route.params.id,
    async (newId) => {
        isLoading.value = true;
        try {
            if (isEditing.value && newId) {
                await attributeStore.fetchAttribute(newId as string);
            } else {
                currentAttribute.value = null;
            }
        } catch (error) {
            console.error("Error fetching attribute details:", error);
            router.push("/admin/attributes");
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
            await attributeStore.updateAttribute(attributeId.value, payload);
            toast.success(t('attributes.page.updatedToastTitle'), {
                description: t('attributes.page.updatedToastDesc'),
            });
            activityLogRef.value?.load();
        } else {
            const newAttr = await attributeStore.createAttribute(payload);
            toast.success(t('attributes.page.createdToastTitle'), {
                description: t('attributes.page.createdToastDesc'),
            });
            router.push(`/admin/attributes/${newAttr.id}/edit`);
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
            console.error("Error saving attribute:", err);
            toast.error(t('attributes.page.savingErrorToastTitle'), {
                description: err?.response?.data?.message || t('common.unexpectedError'),
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/attributes");
};

const handleArchive = async () => {
    if (!attributeId.value) return;
    const id = Number(attributeId.value);

    confirmDialog.value?.show(
        t('attributes.page.archiveConfirmTitle', { action: archiveLabel.value }),
        t('attributes.page.archiveConfirmMessage', { action: archiveLabel.value.toLowerCase() }),
        async () => {
            isLoading.value = true;
            try {
                await attributeStore.toggleActive(id);
            } catch (error: any) {
                console.error("Error toggling attribute status:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = async () => {
    if (!attributeId.value) return;
    const id = Number(attributeId.value);

    confirmDialog.value?.show(
        t('attributes.page.deleteConfirmTitle'),
        t('attributes.page.deleteConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                await attributeStore.deleteAttribute(id);
                router.push("/admin/attributes");
            } catch (error: any) {
                console.error("Failed to delete attribute:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const pageTitle = computed(() =>
    isEditing.value ? t('attributes.page.editTitle') : t('attributes.page.createTitle'),
);

const breadcrumbs = computed(() => [
    { label: t('attributes.page.breadcrumbList'), href: "/admin/attributes" },
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
                              ? t('attributes.page.updateButton')
                              : t('attributes.page.createTitle')
                    }}
                </Button>

                <DropdownMenu v-if="canManageAttribute">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            :aria-label="t('attributes.page.settingsAriaLabel')"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>{{ t('attributes.page.optionsLabel') }}</DropdownMenuLabel>
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

                <!-- Prev / Next: always last, only while editing -->
                <RecordNavigator
                    v-if="isEditing"
                    :current-index="currentIndex"
                    :total="attributes.length"
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
                <div class="xl:col-span-8">
                    <AttributeForm
                        ref="formRef"
                        :initial-data="currentAttribute || {}"
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
                        subject="attribute"
                        :subject-id="attributeId"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
