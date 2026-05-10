<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { usePlanStore } from "@/central/stores/plan";
import { useModuleStore } from "@/central/stores/module";
import DashboardLayout from "@/central/layouts/DashboardLayout.vue";
import PlanForm from "./Form.vue";
import type { Module, Plan } from "@/types/models";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import { ArrowLeft, Archive, Save, Settings2, Trash2 } from "lucide-vue-next";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@/central/components/ActivityLogPanel.vue";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const route = useRoute();
const router = useRouter();
const planStore = usePlanStore();
const moduleStore = useModuleStore();
const availableModules = ref<Module[]>([]);

// Determine mode based on route params
const mode = computed(() => (route.params.id ? "edit" : "create"));
const planId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentPlan = ref<Partial<Plan>>({});
const planForm = ref<InstanceType<typeof PlanForm> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManagePlan = computed(() => mode.value === "edit" && !!planId.value);
const archiveLabel = computed(() =>
    (currentPlan.value as any)?.is_active === false ? "Unarchive" : "Archive",
);
const isArchived = computed(
    () =>
        mode.value === "edit" &&
        (currentPlan.value as any)?.is_active === false,
);

// Fetch plan data if in edit mode
onMounted(async () => {
    // Load module catalog for the multi-select. `total` skips pagination so
    // small admin lists fit in one request.
    moduleStore
        .fetchModules(1, "total" as any, "", "active")
        .then(() => {
            availableModules.value = moduleStore.modules as unknown as Module[];
        })
        .catch((e) => console.error("Error fetching modules:", e));

    if (mode.value === "edit" && planId.value) {
        isLoading.value = true;
        try {
            const plan = await planStore.fetchPlan(planId.value);
            if (plan) {
                currentPlan.value = plan;
            }
        } catch (error) {
            console.error("Error fetching plan:", error);
            router.push("/plans");
        } finally {
            isLoading.value = false;
        }
    }
});

// Handle form submission
const handleSubmit = async (formData: any) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (mode.value === "edit" && planId.value) {
            await planStore.updatePlan(planId.value, formData);
        } else {
            await planStore.createPlan(formData);
        }
        router.push("/plans");
    } catch (err: any) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors;
        } else {
            console.error("Error saving plan:", err);
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/plans");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Edit Plan" : "Create Plan",
);

const handleSave = () => {
    planForm.value?.submit();
};

const handleArchive = () => {
    if (!planId.value) return;
    const id = planId.value;
    confirmDialog.value?.show(
        `${archiveLabel.value} plan`,
        `Are you sure you want to ${archiveLabel.value.toLowerCase()} this plan?`,
        async () => {
            isLoading.value = true;
            try {
                const updated = await planStore.toggleStatus(id);
                currentPlan.value = updated;
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!planId.value) return;
    const id = planId.value;
    confirmDialog.value?.show(
        "Delete plan",
        "Are you sure you want to delete this plan? This action cannot be undone.",
        async () => {
            isLoading.value = true;
            try {
                await planStore.deletePlan(id);
                router.push("/plans");
            } finally {
                isLoading.value = false;
            }
        },
    );
};

// Breadcrumbs
const breadcrumbs = computed(() => [
    { label: "Plans", href: "/plans" },
    { label: mode.value === "edit" ? "Edit Plan" : "Create Plan" },
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
                              ? "Update Plan"
                              : "Create Plan"
                    }}
                </Button>
                <DropdownMenu v-if="canManagePlan">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            aria-label="Plan settings"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>Plan</DropdownMenuLabel>
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
            </template>
        </PageHeader>

        <div class="pt-0">
            <div class="grid gap-4 lg:grid-cols-12">
                <div class="lg:col-span-9">
                    <PlanForm
                        ref="planForm"
                        :mode="mode"
                        :initial-data="currentPlan"
                        :available-modules="availableModules"
                        :is-loading="isLoading"
                        :errors="errors"
                        :archived="isArchived"
                        @submit="handleSubmit"
                    />
                </div>

                <div class="lg:col-span-3">
                    <ActivityLogPanel
                        subject="plan"
                        :subject-id="planId ? Number(planId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
