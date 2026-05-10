<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useBillingCredentialStore } from "@tenant/stores/billingCredential";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import ActivityLogPanel from "@tenant/components/ActivityLogPanel.vue";
import BillingCredentialForm from "./Form.vue";
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
const billingStore = useBillingCredentialStore();

const { currentBillingCredential, isLoading, billingCredentials } = storeToRefs(billingStore);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const isEditing = computed(() => route.name === "BillingCredentialsEdit");
const credentialId = computed(() => route.params.id as string);

const {
    currentIndex: bcIndex,
    prevRecord: prevBc,
    nextRecord: nextBc,
    navigatePrev: navPrev,
    navigateNext: navNext,
} = useRecordNavigator(
    billingCredentials,
    credentialId,
    "/admin/billing-credentials",
    () => billingStore.fetchBillingCredentials(1, "total"),
);

const formRef = ref<InstanceType<typeof BillingCredentialForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const errors = ref<Record<string, string>>({});

const canManage = computed(() => isEditing.value && !!credentialId.value);
const archiveLabel = computed(() =>
    currentBillingCredential.value?.is_active === false ? "Activar" : "Desactivar",
);
const isArchived = computed(
    () => isEditing.value && currentBillingCredential.value?.is_active === false,
);

watch(
    () => route.params.id,
    async () => {
        currentBillingCredential.value = null;
        isLoading.value = true;
        try {
            if (isEditing.value && credentialId.value) {
                const data = await billingStore.fetchBillingCredential(credentialId.value);
                if (data) {
                    currentBillingCredential.value = data;
                }
            }
        } catch (error) {
            console.error("Error fetching billing credential:", error);
            router.push("/admin/billing-credentials");
        } finally {
            isLoading.value = false;
        }
    },
    { immediate: true },
);

const handleSave = () => {
    formRef.value?.submit();
};

const handleSubmit = async (payload: Record<string, any>) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (isEditing.value) {
            await billingStore.updateBillingCredential(credentialId.value, payload);
            toast.success("Credenciales actualizadas", {
                description: "Las credenciales se actualizaron correctamente.",
            });
            // Refrescar para reflejar el cert_filename actualizado si cambió
            const fresh = await billingStore.fetchBillingCredential(credentialId.value);
            if (fresh) currentBillingCredential.value = fresh;
            activityLogRef.value?.load();
        } else {
            const created = await billingStore.createBillingCredential(payload);
            toast.success("Credenciales creadas", {
                description: "Las credenciales se crearon correctamente.",
            });
            router.push(`/admin/billing-credentials/${created.id}/edit`);
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
            toast.error("Error de validación", {
                description: "Revisa los campos del formulario.",
            });
        } else {
            console.error("Error guardando credenciales:", err);
            toast.error("Error al guardar", {
                description:
                    err?.response?.data?.message ||
                    "Ocurrió un error inesperado.",
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/billing-credentials");
};

const handleArchive = async () => {
    if (!credentialId.value) return;
    const id = credentialId.value;

    confirmDialog.value?.show(
        `${archiveLabel.value} credenciales`,
        `¿Estás seguro de ${archiveLabel.value.toLowerCase()} estas credenciales?`,
        async () => {
            isLoading.value = true;
            try {
                const updated = await billingStore.toggleActive(id);
                currentBillingCredential.value = updated as any;
            } catch (error: any) {
                console.error("Error cambiando estado:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = async () => {
    if (!credentialId.value) return;
    const id = credentialId.value;

    confirmDialog.value?.show(
        "Eliminar credenciales",
        "¿Estás seguro de eliminar estas credenciales? Esta acción no se puede deshacer.",
        async () => {
            isLoading.value = true;
            try {
                await billingStore.deleteBillingCredential(id);
                router.push("/admin/billing-credentials");
            } catch (error: any) {
                console.error("Error al eliminar:", error);
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const pageTitle = computed(() =>
    isEditing.value ? "Editar Credenciales" : "Nuevas Credenciales",
);

const breadcrumbs = computed(() => [
    { label: "Facturación Electrónica", href: "/admin/billing-credentials" },
    { label: isEditing.value ? "Editar" : "Nueva" },
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
                    aria-label="Volver"
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
                            ? "Guardando..."
                            : isEditing
                              ? "Actualizar"
                              : "Crear"
                    }}
                </Button>
                <DropdownMenu v-if="canManage">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            aria-label="Opciones"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>Opciones</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="handleArchive">
                            <Archive class="mr-2 h-4 w-4 text-muted-foreground" />
                            {{ archiveLabel }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-destructive"
                            @click="handleDelete"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <RecordNavigator
                    v-if="isEditing"
                    :current-index="bcIndex"
                    :total="billingCredentials.length"
                    :has-prev="!!prevBc"
                    :has-next="!!nextBc"
                    :disabled="isLoading"
                    @prev="navPrev"
                    @next="navNext"
                />
            </template>
        </PageHeader>

        <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <BillingCredentialForm
                        ref="formRef"
                        :initial-data="currentBillingCredential || {}"
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
                        subject="billingCredential"
                        :subject-id="credentialId"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
