<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useCustomerStore } from "@tenant/stores/customer";
import { storeToRefs } from "pinia";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import CustomerForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import type { Customer } from "@tenant/stores/customer";
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
const customerStore = useCustomerStore();

// Determine mode based on route params
const mode = computed(() => (route.params.id ? "edit" : "create"));
const customerId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const customerIdStr = computed(() => String(route.params.id || ""));

const { customers } = storeToRefs(customerStore);
const { currentIndex: customerIdx, prevRecord: prevCustomer, nextRecord: nextCustomer, navigatePrev: navPrevCustomer, navigateNext: navNextCustomer } =
    useRecordNavigator(customers, customerIdStr, "/admin/customers", () => customerStore.fetchCustomers(1, "total"));

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentCustomer = ref<Partial<Customer>>({});
const customerForm = ref<InstanceType<typeof CustomerForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManageCustomer = computed(() => mode.value === "edit" && !!customerId.value);
const archiveLabel = computed(() =>
    (currentCustomer.value as any)?.status === 'inactive' ? "Activate" : "Deactivate",
);
const isArchived = computed(
    () =>
        mode.value === "edit" &&
        (currentCustomer.value as any)?.status === 'inactive',
);

// Fetch customer data if in edit mode
watch(
    () => route.params.id,
    async () => {
        if (mode.value === "edit" && customerId.value) {
            isLoading.value = true;
            try {
                const { data } = await apiClient.get<any>(`/v1/customers/${customerId.value}`);
                if (data.data) {
                    currentCustomer.value = data.data;
                }
            } catch (error) {
                console.error("Error fetching customer:", error);
                router.push("/admin/customers");
            } finally {
                isLoading.value = false;
            }
        }
    },
    { immediate: true },
);

// Handle form submission
const handleSubmit = async (formData: any) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (mode.value === "edit" && customerId.value) {
            await apiClient.patch(`/v1/customers/${customerId.value}`, formData);
            toast.success("Customer updated", {
                description: "The customer was successfully updated.",
            });
        } else {
            const { data } = await apiClient.post<any>("/v1/customers", formData);
            toast.success("Customer created", {
                description: "The customer was successfully created.",
            });
            // Optionally redirect to edit or customers list
            router.push(`/admin/customers/${data.data.id}/edit`);
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
            console.error("Error saving customer:", err);
            toast.error("An error occurred while saving the customer");
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/customers");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Edit Customer" : "Create Customer",
);

const handleSave = () => {
    customerForm.value?.submit();
};

const handleArchive = () => {
    if (!customerId.value) return;
    const id = customerId.value;
    confirmDialog.value?.show(
        `${archiveLabel.value} customer`,
        `Are you sure you want to ${archiveLabel.value.toLowerCase()} this customer?`,
        async () => {
            isLoading.value = true;
            try {
                const updated: any = await customerStore.toggleStatus(id);
                currentCustomer.value = updated;
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!customerId.value) return;
    const id = customerId.value;
    confirmDialog.value?.show(
        "Delete customer",
        "Are you sure you want to delete this customer? This action cannot be undone.",
        async () => {
            isLoading.value = true;
            try {
                await customerStore.deleteCustomer(id);
                router.push("/admin/customers");
            } finally {
                isLoading.value = false;
            }
        },
    );
};

// Breadcrumbs
const breadcrumbs = computed(() => [
    { label: "Customers", href: "/admin/customers" },
    { label: mode.value === "edit" ? "Edit Customer" : "Create Customer" },
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
                              ? "Update Customer"
                              : "Create Customer"
                    }}
                </Button>
                <DropdownMenu v-if="canManageCustomer">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            aria-label="Customer settings"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>Customer Options</DropdownMenuLabel>
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
                    :current-index="customerIdx"
                    :total="customers.length"
                    :has-prev="!!prevCustomer"
                    :has-next="!!nextCustomer"
                    :disabled="isLoading"
                    @prev="navPrevCustomer"
                    @next="navNextCustomer"
                />
            </template>
        </PageHeader>

          <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <CustomerForm
                        ref="customerForm"
                        :mode="mode"
                        :initial-data="currentCustomer"
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
                        :subject-id="customerId ? Number(customerId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
