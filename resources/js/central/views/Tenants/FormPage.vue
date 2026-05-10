<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import DashboardLayout from "@/central/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import TenantForm from "./Form.vue";
import { Button } from "@/components/ui/button";
import {
    ArrowLeft,
    Save,
    Settings2,
    TicketPlus,
    Trash2,
} from "lucide-vue-next";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import ActivityLogPanel from "@/central/components/ActivityLogPanel.vue";
import RenewSubscriptionDialog from "@/central/components/RenewSubscriptionDialog.vue";
import SubscriptionHistoryDialog from "@/central/components/SubscriptionHistoryDialog.vue";
import PaymentHistoryDialog from "@/central/components/PaymentHistoryDialog.vue";
import { useTenantStore } from "@/central/stores/tenant";
import type { Tenant } from "@/types/models";

const route = useRoute();
const router = useRouter();
const tenantStore = useTenantStore();

const mode = computed(() => (route.params.id ? "edit" : "create"));
const tenantId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentTenant = ref<Partial<Tenant>>({});
const tenantForm = ref<InstanceType<typeof TenantForm> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const renewDialog = ref<InstanceType<typeof RenewSubscriptionDialog> | null>(
    null,
);
const subscriptionHistoryDialog = ref<InstanceType<
    typeof SubscriptionHistoryDialog
> | null>(null);
const paymentHistoryDialog = ref<InstanceType<
    typeof PaymentHistoryDialog
> | null>(null);

const canManageTenant = computed(
    () => mode.value === "edit" && !!tenantId.value,
);

onMounted(async () => {
    if (mode.value === "create") {
        router.replace("/tenants/create");
        return;
    }
    if (mode.value === "edit" && tenantId.value) {
        isLoading.value = true;
        try {
            const tenant = await tenantStore.fetchTenant(tenantId.value);
            if (tenant) {
                currentTenant.value = tenant;
            }
        } catch (error) {
            console.error("Error fetching tenant:", error);
            router.push("/tenants");
        } finally {
            isLoading.value = false;
        }
    }
});

const handleSubmit = async (formData: any) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (mode.value === "edit" && tenantId.value) {
            await tenantStore.updateTenant(tenantId.value, formData);
        }
        router.push("/tenants");
    } catch (err: any) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors;
        } else {
            console.error("Error saving tenant:", err);
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/tenants");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Edit Tenant" : "Create Tenant",
);

const handleSave = () => {
    tenantForm.value?.submit();
};

const handleDelete = () => {
    if (!tenantId.value) return;
    const id = tenantId.value;
    confirmDialog.value?.show(
        "Delete tenant",
        "Are you sure you want to delete this tenant? This action cannot be undone.",
        async () => {
            isLoading.value = true;
            try {
                await tenantStore.deleteTenant(id);
                router.push("/tenants");
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleRenew = () => {
    if (!tenantId.value) return;
    const id = tenantId.value;
    renewDialog.value?.show(
        {
            title: "Extend access",
            message: "Add days to this tenant subscription.",
            duration_days: 15,
            payment_reference: "",
        },
        async (payload) => {
            isLoading.value = true;
            try {
                await tenantStore.renewSubscription(id, payload);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleOpenSubscriptionHistory = () => {
    if (!tenantId.value) return;
    subscriptionHistoryDialog.value?.show(currentTenant.value);
};

const handleOpenPaymentHistory = () => {
    if (!tenantId.value) return;
    paymentHistoryDialog.value?.show(currentTenant.value);
};

const breadcrumbs = computed(() => [
    { label: "Tenants", href: "/tenants" },
    { label: mode.value === "edit" ? "Edit Tenant" : "Create Tenant" },
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

            <template #center>
                <div v-if="mode === 'edit'" class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9"
                        :disabled="isLoading || !tenantId"
                        @click="handleOpenSubscriptionHistory"
                    >
                        Suscripción
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9"
                        :disabled="isLoading || !tenantId"
                        @click="handleOpenPaymentHistory"
                    >
                        Pagos
                    </Button>
                </div>
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
                              ? "Update Tenant"
                              : "Create Tenant"
                    }}
                </Button>
                <DropdownMenu v-if="canManageTenant">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            aria-label="Tenant settings"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>Tenant</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="handleRenew">
                            <TicketPlus
                                class="mr-2 h-4 w-4 text-muted-foreground"
                            />
                            Extend access
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
            </template>
        </PageHeader>

        <div class="pt-0">
            <div class="grid gap-4 lg:grid-cols-12">
                <div class="lg:col-span-9">
                    <TenantForm
                        ref="tenantForm"
                        :mode="mode"
                        :initial-data="currentTenant"
                        :is-loading="isLoading"
                        :errors="errors"
                        @submit="handleSubmit"
                    />
                </div>
                <div class="lg:col-span-3 space-y-4">
                    <ActivityLogPanel subject="tenant" :subject-id="tenantId" />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
    <RenewSubscriptionDialog ref="renewDialog" />
    <SubscriptionHistoryDialog ref="subscriptionHistoryDialog" />
    <PaymentHistoryDialog ref="paymentHistoryDialog" />
</template>
