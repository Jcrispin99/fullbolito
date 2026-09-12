<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useProductTemplateStore } from "@tenant/stores/productTemplate";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import { apiClient } from "@tenant/lib/api";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import ProductForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import type { FormOptions } from "./Form.vue";
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
import { ArrowLeft, Save, Archive, Settings2, Trash2, ArrowLeftRight } from "lucide-vue-next";

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const store = useProductTemplateStore();
const { currentProduct, isLoading, products } = storeToRefs(store);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const formRef = ref<InstanceType<typeof ProductForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const errors = ref<Record<string, string>>({});
const formOptions = ref<FormOptions>({
    categories: [] as { id: number; name: string }[],
    uoms: [] as { id: number; name: string; symbol: string | null }[],
    attributes: [] as { id: number; name: string; values: { id: number; value: string }[] }[],
    warehouses: [] as { id: number; name: string }[],
});

const isEditing = computed(() => route.name === "ProductsEdit");
const productId = computed(() => route.params.id as string);

const { currentIndex: prodIndex, prevRecord: prevProd, nextRecord: nextProd, navigatePrev: navPrevProd, navigateNext: navNextProd } =
    useRecordNavigator(products, productId, "/admin/products", () => store.fetchProducts(1, "total"));
const canManageProduct = computed(() => isEditing.value && !!productId.value);

const archiveLabel = computed(() =>
    currentProduct.value?.is_active === false
        ? t('common.actions.activate')
        : t('common.actions.deactivate'),
);
const isArchived = computed(
    () => isEditing.value && currentProduct.value?.is_active === false,
);

// Load form options: categories, UoMs, attributes, warehouses
const loadFormOptions = async () => {
    try {
        const [optRes, whRes] = await Promise.all([
            apiClient.get<any>("/v1/product-templates/form-options"),
            apiClient.get<any>("/v1/warehouses?per_page=total&status=active"),
        ]);
        formOptions.value = {
            categories: optRes.data?.data?.categories ?? [],
            uoms: optRes.data?.data?.uoms ?? [],
            attributes: optRes.data?.data?.attributes ?? [],
            warehouses: whRes.data?.data?.data ?? whRes.data?.data ?? [],
        };
    } catch (e) {
        console.error("Error fetching form options:", e);
    }
};

watch(
    () => route.params.id,
    async (newId) => {
        if (isEditing.value && newId) {
            isLoading.value = true;
            try {
                await store.fetchProduct(newId as string);
            } catch {
                router.push("/admin/products");
            } finally {
                isLoading.value = false;
            }
        } else {
            currentProduct.value = null;
        }
    },
    { immediate: true },
);

// Also load form opts on mount (always needed for both create + edit)
loadFormOptions();

const handleSave = () => formRef.value?.submit();

const handleSubmit = async (payload: any) => {
    isLoading.value = true;
    errors.value = {};
    try {
        if (isEditing.value) {
            await store.updateProduct(productId.value, payload);
            toast.success(t('products.page.updatedToastTitle'), {
                description: t('products.page.updatedToastDesc'),
            });
            activityLogRef.value?.load();
        } else {
            const newProduct = await store.createProduct(payload);
            toast.success(t('products.page.createdToastTitle'), {
                description: t('products.page.createdToastDesc'),
            });
            router.push(`/admin/products/${newProduct.id}/edit`);
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
            console.error("Error saving product:", err);
            toast.error(t('products.page.savingErrorToastTitle'), {
                description: err?.response?.data?.message || t('common.unexpectedError'),
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => router.push("/admin/products");

const handleArchive = () => {
    if (!productId.value) return;
    confirmDialog.value?.show(
        t('products.page.archiveConfirmTitle', { action: archiveLabel.value }),
        t('products.page.archiveConfirmMessage', { action: archiveLabel.value.toLowerCase() }),
        async () => {
            isLoading.value = true;
            try {
                const updated = await store.toggleActive(productId.value);
                currentProduct.value = updated;
            } catch (error) {
                console.error("Error toggling product status:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!productId.value) return;
    confirmDialog.value?.show(
        t('products.page.deleteConfirmTitle'),
        t('products.page.deleteConfirmMessage'),
        async () => {
            isLoading.value = true;
            try {
                await store.deleteProduct(productId.value);
                router.push("/admin/products");
            } catch (err: any) {
                const msg = err?.response?.data?.message || t('products.page.deleteErrorFallback');
                alert(msg);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const pageTitle = computed(() =>
    isEditing.value ? t('products.page.editTitle') : t('products.page.createTitle'),
);

const breadcrumbs = computed(() => [
    { label: t('products.page.breadcrumbList'), href: "/admin/products" },
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

            <template #center>
                <Button
                    v-if="canManageProduct"
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5"
                    type="button"
                    disabled
                >
                    <ArrowLeftRight class="h-4 w-4 text-muted-foreground" />
                    {{ t('products.page.movements') }}
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
                              ? t('products.page.updateButton')
                              : t('products.page.createTitle')
                    }}
                </Button>

                <DropdownMenu v-if="canManageProduct">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            :aria-label="t('products.page.settingsAriaLabel')"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>{{ t('products.page.optionsLabel') }}</DropdownMenuLabel>
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
                    :current-index="prodIndex"
                    :total="products.length"
                    :has-prev="!!prevProd"
                    :has-next="!!nextProd"
                    :disabled="isLoading"
                    @prev="navPrevProd"
                    @next="navNextProd"
                />
            </template>
        </PageHeader>
  <div>
        <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                <ProductForm
                    ref="formRef"
                    :initial-data="currentProduct ? { ...currentProduct, category: currentProduct.category ?? undefined } : {}"
                    :form-options="formOptions"
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
                    subject="product_template"
                    :subject-id="productId"
                />
            </div>
        </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
