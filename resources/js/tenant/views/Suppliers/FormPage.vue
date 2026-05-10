<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useSupplierStore } from "@tenant/stores/supplier";
import { storeToRefs } from "pinia";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import SupplierForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import type { Supplier } from "@tenant/stores/supplier";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import { ArrowLeft, Save, Archive, Settings2, Trash2 } from "lucide-vue-next";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import { toast } from "vue-sonner";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { apiClient } from "@tenant/lib/api";

const route = useRoute();
const router = useRouter();
const supplierStore = useSupplierStore();

// Determine mode based on route params
const mode = computed(() => (route.params.id ? "edit" : "create"));
const supplierId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const supplierIdStr = computed(() => String(route.params.id || ""));

const { suppliers } = storeToRefs(supplierStore);
const { currentIndex: supplierIdx, prevRecord: prevSupplier, nextRecord: nextSupplier, navigatePrev: navPrevSupplier, navigateNext: navNextSupplier } =
    useRecordNavigator(suppliers, supplierIdStr, "/admin/suppliers", () => supplierStore.fetchSuppliers(1, "total"));

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentSupplier = ref<Partial<Supplier>>({});
const supplierForm = ref<InstanceType<typeof SupplierForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManageSupplier = computed(() => mode.value === "edit" && !!supplierId.value);
const archiveLabel = computed(() =>
    (currentSupplier.value as any)?.status === 'inactive' ? "Activate" : "Deactivate",
);
const isArchived = computed(
    () =>
        mode.value === "edit" &&
        (currentSupplier.value as any)?.status === 'inactive',
);

// Fetch supplier data if in edit mode
watch(
    () => route.params.id,
    async () => {
        if (mode.value === "edit" && supplierId.value) {
            isLoading.value = true;
            try {
                const { data } = await apiClient.get<any>(`/v1/suppliers/${supplierId.value}`);
                if (data.data) {
                    currentSupplier.value = data.data;
                }
            } catch (error) {
                console.error("Error fetching supplier:", error);
                router.push("/admin/suppliers");
            } finally {
                isLoading.value = false;
            }
        } else {
            currentSupplier.value = {};
        }
    },
    { immediate: true },
);

// Handle form submission
const handleSubmit = async (formData: any) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (mode.value === "edit" && supplierId.value) {
            await apiClient.patch(`/v1/suppliers/${supplierId.value}`, formData);
            toast.success("Supplier updated", {
                description: "The supplier was successfully updated.",
            });
        } else {
            const { data } = await apiClient.post<any>("/v1/suppliers", formData);
            toast.success("Supplier created", {
                description: "The supplier was successfully created.",
            });
            // Optionally redirect to edit or suppliers list
            router.push(`/admin/suppliers/${data.data.id}/edit`);
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
            toast.error("Please correctly fill in the required fields");
        } else {
            console.error("Error saving supplier:", err);
            toast.error("An error occurred while saving the supplier");
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/suppliers");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Edit Supplier" : "Create Supplier",
);

const handleSave = () => {
    supplierForm.value?.submit();
};

const handleArchive = () => {
    if (!supplierId.value) return;
    const id = supplierId.value;
    confirmDialog.value?.show(
        `${archiveLabel.value} supplier`,
        `Are you sure you want to ${archiveLabel.value.toLowerCase()} this supplier?`,
        async () => {
            isLoading.value = true;
            try {
                const updated: any = await supplierStore.toggleStatus(id);
                currentSupplier.value = updated;
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!supplierId.value) return;
    const id = supplierId.value;
    confirmDialog.value?.show(
        "Delete supplier",
        "Are you sure you want to delete this supplier? This action cannot be undone.",
        async () => {
            isLoading.value = true;
            try {
                await supplierStore.deleteSupplier(id);
                router.push("/admin/suppliers");
            } finally {
                isLoading.value = false;
            }
        },
    );
};

// Breadcrumbs
const breadcrumbs = computed(() => [
    { label: "Suppliers", href: "/admin/suppliers" },
    { label: mode.value === "edit" ? "Edit Supplier" : "Create Supplier" },
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
                            : mode === "edit"
                              ? "Update Supplier"
                              : "Create Supplier"
                    }}
                </Button>
                <DropdownMenu v-if="canManageSupplier">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            aria-label="Supplier settings"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>Supplier Options</DropdownMenuLabel>
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

                <RecordNavigator
                    v-if="mode === 'edit'"
                    :current-index="supplierIdx"
                    :total="suppliers.length"
                    :has-prev="!!prevSupplier"
                    :has-next="!!nextSupplier"
                    :disabled="isLoading"
                    @prev="navPrevSupplier"
                    @next="navNextSupplier"
                />
            </template>
        </PageHeader>

          <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <SupplierForm
                        ref="supplierForm"
                        :mode="mode"
                        :initial-data="currentSupplier"
                        :is-loading="isLoading"
                        :errors="errors"
                        :archived="isArchived"
                        @submit="handleSubmit"
                    />
                </div>

                <div class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0">
                    <ActivityLogPanel
                        ref="activityLogRef"
                        subject="partner"
                        :subject-id="supplierId ? Number(supplierId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
