<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import { useWarehouseStore } from "@tenant/stores/warehouse";
import { storeToRefs } from "pinia";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import WarehouseForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import type { Warehouse } from "@tenant/stores/warehouse";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import { ArrowLeft, Save, Archive, Settings2, Trash2 } from "lucide-vue-next";
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
const warehouseStore = useWarehouseStore();

// Determine mode based on route params
const mode = computed(() => (route.params.id ? "edit" : "create"));
const warehouseId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const warehouseIdStr = computed(() => String(route.params.id || ""));

const { warehouses } = storeToRefs(warehouseStore);
const { currentIndex: warehouseIdx, prevRecord: prevWarehouse, nextRecord: nextWarehouse, navigatePrev: navPrevWarehouse, navigateNext: navNextWarehouse } =
    useRecordNavigator(warehouses, warehouseIdStr, "/admin/warehouses", () => warehouseStore.fetchWarehouses(1, "total"));

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentWarehouse = ref<Partial<Warehouse>>({});
const formOptions = ref<{ companies: any[] }>({ companies: [] });
const warehouseForm = ref<InstanceType<typeof WarehouseForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManageWarehouse = computed(() => mode.value === "edit" && !!warehouseId.value);
const archiveLabel = computed(() =>
    (currentWarehouse.value as any)?.is_active === false
        ? t('common.actions.activate')
        : t('common.actions.deactivate'),
);
const isArchived = computed(
    () =>
        mode.value === "edit" &&
        (currentWarehouse.value as any)?.is_active === false,
);

watch(
    () => route.params.id,
    async () => {
        isLoading.value = true;
        try {
            const options = await warehouseStore.fetchFormOptions();
            formOptions.value = options;

            if (mode.value === "edit" && warehouseId.value) {
                const data = await warehouseStore.fetchWarehouse(warehouseId.value);
                if (data) {
                    currentWarehouse.value = data;
                }
            } else {
                currentWarehouse.value = {};
            }
        } catch (error) {
            console.error("Error loading warehouse view:", error);
            router.push("/admin/warehouses");
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
        if (mode.value === "edit" && warehouseId.value) {
            await warehouseStore.updateWarehouse(warehouseId.value, formData);
            toast.success(t('warehouses.page.updatedToastTitle'), {
                description: t('warehouses.page.updatedToastDesc'),
            });
            activityLogRef.value?.load();
        } else {
            const created = await warehouseStore.createWarehouse(formData);
            toast.success(t('warehouses.page.createdToastTitle'), {
                description: t('warehouses.page.createdToastDesc'),
            });
            router.push(`/admin/warehouses/${created.id}/edit`);
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
            console.error("Error saving warehouse:", err);
            toast.error(t('warehouses.page.savingErrorToastTitle'), {
                description: err?.response?.data?.message || t('common.unexpectedError'),
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/warehouses");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? t('warehouses.page.editTitle') : t('warehouses.page.createTitle'),
);

const handleSave = () => {
    warehouseForm.value?.submit();
};

const handleArchive = () => {
    if (!warehouseId.value) return;
    const id = warehouseId.value;
    confirmDialog.value?.show(
        t('warehouses.page.archiveConfirmTitle', { action: archiveLabel.value }),
        t('warehouses.page.archiveConfirmMessage', { action: archiveLabel.value.toLowerCase() }),
        async () => {
            isLoading.value = true;
            try {
                const updated = await warehouseStore.toggleActive(id);
                currentWarehouse.value = updated;
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!warehouseId.value) return;
    const id = warehouseId.value;
    confirmDialog.value?.show(
        t('warehouses.page.deleteConfirmTitle'),
        t('warehouses.page.deleteConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                await warehouseStore.deleteWarehouse(id);
                router.push("/admin/warehouses");
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const breadcrumbs = computed(() => [
    { label: t('warehouses.page.breadcrumbList'), href: "/admin/warehouses" },
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
                            : mode === "edit"
                              ? t('warehouses.page.updateButton')
                              : t('warehouses.page.createTitle')
                    }}
                </Button>
                <DropdownMenu v-if="canManageWarehouse">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            :aria-label="t('warehouses.page.settingsAriaLabel')"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>{{ t('warehouses.page.optionsLabel') }}</DropdownMenuLabel>
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
                    v-if="mode === 'edit'"
                    :current-index="warehouseIdx"
                    :total="warehouses.length"
                    :has-prev="!!prevWarehouse"
                    :has-next="!!nextWarehouse"
                    :disabled="isLoading"
                    @prev="navPrevWarehouse"
                    @next="navNextWarehouse"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <WarehouseForm
                        ref="warehouseForm"
                        :mode="mode"
                        :initial-data="currentWarehouse"
                        :form-options="formOptions"
                        :is-loading="isLoading"
                        :errors="errors"
                        :archived="isArchived"
                        @submit="handleSubmit"
                    />
                </div>

                <div class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0">
                    <ActivityLogPanel
                        subject="warehouse"
                        :subject-id="warehouseId ? Number(warehouseId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
