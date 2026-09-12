<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useLoyaltyProgramStore } from "@tenant/stores/loyaltyProgram";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import ProgramForm from "./Form.vue";
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
const programStore = useLoyaltyProgramStore();

const { currentProgram, isLoading, programs } = storeToRefs(programStore);

const isEditing = computed(() => route.name === "LoyaltyProgramsEdit");
const programId = computed(() => route.params.id as string);

const {
    currentIndex: programIndex,
    prevRecord: prevProgram,
    nextRecord: nextProgram,
    navigatePrev: navPrevProgram,
    navigateNext: navNextProgram,
} = useRecordNavigator(
    programs,
    programId,
    "/admin/loyalty/programs",
    () => programStore.fetchPrograms(1, "total"),
);

const canManageProgram = computed(() => isEditing.value && !!programId.value);

const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);
const formRef = ref<InstanceType<typeof ProgramForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const errors = ref<Record<string, string>>({});

const archiveLabel = computed(() =>
    currentProgram.value?.is_active === false ? "Activate" : "Deactivate",
);
const isArchived = computed(
    () => isEditing.value && currentProgram.value?.is_active === false,
);

watch(
    () => route.params.id,
    async (newId) => {
        currentProgram.value = null;
        isLoading.value = true;
        try {
            if (isEditing.value && newId) {
                await programStore.fetchProgram(newId as string);
            }
        } catch {
            router.push("/admin/loyalty/programs");
        } finally {
            isLoading.value = false;
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
            await programStore.updateProgram(programId.value, payload);
            toast.success("Program updated", {
                description: "The program was successfully updated.",
            });
            activityLogRef.value?.load();
        } else {
            const newProgram = await programStore.createProgram(payload);
            toast.success("Program created", {
                description: "The program was successfully created.",
            });
            router.push(`/admin/loyalty/programs/${(newProgram as any).id}/edit`);
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
            console.error("Error saving program:", err);
            toast.error("Error saving program", {
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
    router.push("/admin/loyalty/programs");
};

const handleArchive = async () => {
    if (!programId.value) return;

    confirmDialog.value?.show(
        `${archiveLabel.value} program`,
        `Are you sure you want to ${archiveLabel.value.toLowerCase()} this program?`,
        async () => {
            isLoading.value = true;
            try {
                const updated = await programStore.toggleActive(programId.value);
                currentProgram.value = updated as any;
            } catch (error: any) {
                console.error("Error toggling program status:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = async () => {
    if (!programId.value) return;

    confirmDialog.value?.show(
        "Delete program",
        "Are you sure you want to delete this program? This action cannot be undone.",
        async () => {
            isLoading.value = true;
            try {
                await programStore.deleteProgram(programId.value);
                router.push("/admin/loyalty/programs");
            } catch (err: any) {
                const msg =
                    err?.response?.data?.message || "Could not delete this program.";
                alert(msg);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const pageTitle = computed(() =>
    isEditing.value ? "Edit Program" : "Create Program",
);

const breadcrumbs = computed(() => [
    { label: "Programas", href: "/admin/loyalty/programs" },
    { label: isEditing.value ? "Edit Program" : "Create Program" },
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
                              ? "Update Program"
                              : "Create Program"
                    }}
                </Button>
                <DropdownMenu v-if="canManageProgram">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            aria-label="Program settings"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>Program Options</DropdownMenuLabel>
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
                    v-if="isEditing"
                    :current-index="programIndex"
                    :total="programs.length"
                    :has-prev="!!prevProgram"
                    :has-next="!!nextProgram"
                    :disabled="isLoading"
                    @prev="navPrevProgram"
                    @next="navNextProgram"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-9">
                    <ProgramForm
                        ref="formRef"
                        :initial-data="currentProgram || {}"
                        :is-editing="isEditing"
                        :archived="isArchived"
                        :errors="errors"
                        @submit="handleSubmit"
                    />
                </div>
                <div
                    class="xl:col-span-3 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0"
                >
                    <ActivityLogPanel
                        ref="activityLogRef"
                        v-if="isEditing"
                        subject="loyalty_program"
                        :subject-id="programId"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
