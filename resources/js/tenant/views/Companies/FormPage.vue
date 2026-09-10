<script setup lang="ts">
import { computed, watch, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useCompanyStore } from "@tenant/stores/company";
import { storeToRefs } from "pinia";
import { useRecordNavigator } from "@/composables/useRecordNavigator";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import CompanyForm from "./Form.vue";
import RecordNavigator from "@/components/RecordNavigator.vue";
import type { Company } from "@/types/models";
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

const route = useRoute();
const router = useRouter();
const companyStore = useCompanyStore();

// Determine mode based on route params
const mode = computed(() => (route.params.id ? "edit" : "create"));
const companyId = computed(() =>
    route.params.id ? String(route.params.id) : null,
);
const companyIdStr = computed(() => String(route.params.id || ""));

const { companies } = storeToRefs(companyStore);
const { currentIndex: companyIdx, prevRecord: prevCompany, nextRecord: nextCompany, navigatePrev: navPrevCompany, navigateNext: navNextCompany } =
    useRecordNavigator(companies, companyIdStr, "/admin/companies", () => companyStore.fetchCompanies(1, "total"));

const isLoading = ref(false);
const errors = ref<Record<string, string>>({});
const currentCompany = ref<Partial<Company>>({});
const formOptions = ref<any>({ parent_companies: [] });
const companyForm = ref<InstanceType<typeof CompanyForm> | null>(null);
const activityLogRef = ref<InstanceType<typeof ActivityLogPanel> | null>(null);
const confirmDialog = ref<InstanceType<typeof ConfirmDialog> | null>(null);

const canManageCompany = computed(() => mode.value === "edit" && !!companyId.value);
const archiveLabel = computed(() =>
    (currentCompany.value as any)?.active === false ? "Activar" : "Desactivar",
);
const isArchived = computed(
    () =>
        mode.value === "edit" &&
        (currentCompany.value as any)?.active === false,
);

// Fetch company data if in edit mode
watch(
    () => route.params.id,
    async () => {
        isLoading.value = true;
        try {
            const options = await companyStore.fetchFormOptions({
                exclude_id: companyId.value ? Number(companyId.value) : undefined,
            });
            if (options) formOptions.value = options;

            if (mode.value === "edit" && companyId.value) {
                const data = await companyStore.fetchCompany(companyId.value);
                if (data) {
                    currentCompany.value = data;
                }
            } else {
                currentCompany.value = {};
            }
        } catch (error) {
            console.error("Error fetching company:", error);
            router.push("/admin/companies");
        } finally {
            isLoading.value = false;
        }
    },
    { immediate: true },
);

// Handle form submission
const handleSubmit = async (formData: any) => {
    isLoading.value = true;
    errors.value = {};

    try {
        if (mode.value === "edit" && companyId.value) {
            await companyStore.updateCompany(companyId.value, formData);
            toast.success("Empresa actualizada", {
                description: "La empresa se actualizó correctamente.",
            });
            activityLogRef.value?.load();
        } else {
            const created = await companyStore.createCompany(formData);
            toast.success("Empresa creada", {
                description: "La empresa se creó correctamente.",
            });
            router.push(`/admin/companies/${created.id}/edit`);
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
            console.error("Error saving company:", err);
            toast.error("Error al guardar la empresa", {
                description: err?.response?.data?.message || "Ocurrió un error inesperado.",
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const handleCancel = () => {
    router.push("/admin/companies");
};

const pageTitle = computed(() =>
    mode.value === "edit" ? "Editar empresa" : "Crear empresa",
);

const handleSave = () => {
    companyForm.value?.submit();
};

const handleArchive = () => {
    if (!companyId.value) return;
    const id = companyId.value;
    confirmDialog.value?.show(
        `${archiveLabel.value} empresa`,
        `¿Confirmas que deseas ${archiveLabel.value.toLowerCase()} esta empresa?`,
        async () => {
            isLoading.value = true;
            try {
                const updated = await companyStore.toggleActive(id);
                currentCompany.value = updated;
            } finally {
                isLoading.value = false;
            }
        },
    );
};

const handleDelete = () => {
    if (!companyId.value) return;
    const id = companyId.value;
    confirmDialog.value?.show(
        "Eliminar empresa",
        "¿Confirmas que deseas eliminar esta empresa? Esta acción no se puede deshacer.",
        async () => {
            isLoading.value = true;
            try {
                await companyStore.deleteCompany(id);
                router.push("/admin/companies");
            } finally {
                isLoading.value = false;
            }
        },
    );
};

// Breadcrumbs
const breadcrumbs = computed(() => [
    { label: "Empresas", href: "/admin/companies" },
    { label: mode.value === "edit" ? "Editar empresa" : "Crear empresa" },
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
                            : mode === "edit"
                              ? "Actualizar empresa"
                              : "Crear empresa"
                    }}
                </Button>
                <DropdownMenu v-if="canManageCompany">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="h-9 w-9"
                            aria-label="Opciones de la empresa"
                        >
                            <Settings2 class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-[200px]">
                        <DropdownMenuLabel>Opciones de la empresa</DropdownMenuLabel>
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
                            Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <RecordNavigator
                    v-if="mode === 'edit'"
                    :current-index="companyIdx"
                    :total="companies.length"
                    :has-prev="!!prevCompany"
                    :has-next="!!nextCompany"
                    :disabled="isLoading"
                    @prev="navPrevCompany"
                    @next="navNextCompany"
                />
            </template>
        </PageHeader>

      <div>
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-8">
                    <CompanyForm
                        ref="companyForm"
                        :mode="mode"
                        :initial-data="currentCompany"
                        :form-options="formOptions"
                        :is-loading="isLoading"
                        :errors="errors"
                        :archived="isArchived"
                        @submit="handleSubmit"
                    />
                </div>

  <div
                    class="xl:col-span-4 xl:border-l xl:border-t-0 xl:pl-6 border-t pt-6 mt-6 xl:mt-0 xl:pt-0"
                >                    <ActivityLogPanel
                        ref="activityLogRef"
                        subject="company"
                        :subject-id="companyId ? Number(companyId) : null"
                    />
                </div>
            </div>
        </div>
    </DashboardLayout>
    <ConfirmDialog ref="confirmDialog" />
</template>
