<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import { useCategoryStore } from "@tenant/stores/category";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import CategoryForm from "./Form.vue";
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

const route = useRoute();
const router = useRouter();
const categoryStore = useCategoryStore();
const { currentCategory, isLoading, categories } = storeToRefs(categoryStore);

const isEditing = computed(() => route.name === "CategoriesEdit");
const categoryId = computed(() => route.params.id as string);

const { currentIndex: categoryIndex, prevRecord: prevCat, nextRecord: nextCat, navigatePrev: navPrevCat, navigateNext: navNextCat } =
    useRecordNavigator(categories, categoryId, "/admin/categories", () => categoryStore.fetchCategories(1, "total"));

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const parentOptions = ref<any[]>([]);
const formRef = ref<InstanceType<typeof CategoryForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const errors = ref<Record<string, string>>({});

const canManageCategory = computed(() => isEditing.value && !!categoryId.value);
const archiveLabel = computed(() =>
    currentCategory.value?.is_active === false ? "Activate" : "Deactivate",
);
const isArchived = computed(
    () => isEditing.value && currentCategory.value?.is_active === false,
);

watch(
    () => route.params.id,
    async () => {
        // Always reset so create form never shows stale data from a previous edit
        currentCategory.value = null;
        isLoading.value = true;
        try {
            const options = await categoryStore.fetchFormOptions();
            parentOptions.value = options.parent_categories || [];

            if (isEditing.value && categoryId.value) {
                const data = await categoryStore.fetchCategory(categoryId.value);
                if (data) {
                    currentCategory.value = data;
                }
            }
        } catch (error) {
            console.error("Error fetching category details:", error);
            router.push("/admin/categories");
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
            await categoryStore.updateCategory(categoryId.value, payload);
            toast.success("Category updated", {
                description: "The category was successfully updated.",
            });
            activityLogRef.value?.load();
        } else {
            const newCat = await categoryStore.createCategory(payload);
            toast.success("Category created", {
                description: "The category was successfully created.",
            });
            router.push(`/admin/categories/${newCat.id}/edit`);
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
            toast.error("Validation error", {
                description: "Please check the form fields for errors.",
            });
        } else {
            console.error("Error saving category:", err);
            toast.error("Error saving category", {
                description:
                    err?.response?.data?.message ||
                    "An unexpected error occurred.",
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/categories");
};

const handleArchive = async () => {
    if (!categoryId.value) return;
    const id = categoryId.value;

    confirmDialog.value?.show(
        `${archiveLabel.value} category`,
        `Are you sure you want to ${archiveLabel.value.toLowerCase()} this category?`,
        async () => {
            isLoading.value = true;
            try {
                const updated = await categoryStore.toggleActive(id);
                currentCategory.value = updated;
            } catch (error: any) {
                console.error("Error toggling category status:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = async () => {
    if (!categoryId.value) return;
    const id = categoryId.value;

    confirmDialog.value?.show(
        "Delete category",
        "Are you sure you want to delete this category? This action cannot be undone.",
        async () => {
            isLoading.value = true;
            try {
                await categoryStore.deleteCategory(id);
                router.push("/admin/categories");
            } catch (error: any) {
                console.error("Failed to delete category:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const pageTitle = computed(() =>
    isEditing.value ? "Edit Category" : "Create Category",
);

const breadcrumbs = computed(() => [
    { label: "Categories", href: "/admin/categories" },
    { label: isEditing.value ? "Edit Category" : "Create Category" },
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
                <Button
                    size="sm"
                    class="h-9"
                    :disabled="isLoading"
                    @click="handleSave"
                >
                    <Save class="mr-2 h-4 w-4" />
                    {{
                        isLoading
                            ? "Saving..."
                            : isEditing
                              ? "Update Category"
                              : "Create Category"
                    }}
                </Button>
                <DropdownMenu v-if="canManageCategory">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            aria-label="Category settings"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>Category Options</DropdownMenuLabel>
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
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Prev / Next: always last, only while editing -->
                <RecordNavigator
                    v-if="isEditing"
                    :current-index="categoryIndex"
                    :total="categories.length"
                    :has-prev="!!prevCat"
                    :has-next="!!nextCat"
                    :disabled="isLoading"
                    @prev="navPrevCat"
                    @next="navNextCat"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <CategoryForm
                        ref="formRef"
                        :initial-data="currentCategory || {}"
                        :parent-options="parentOptions"
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
                        subject="category"
                        :subject-id="categoryId"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
