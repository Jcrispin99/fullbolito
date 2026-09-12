<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import FolderTabs from "@/components/ui/tabs/FolderTabs.vue";
import ProductLineItems from "@tenant/components/ProductLineItems.vue";
import { SearchSelect } from "@/components/ui/search-select";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import CreateDialog from "@/components/CreateDialog.vue";
import CustomerForm from "@tenant/views/Customers/Form.vue";
import WarehouseForm from "@tenant/views/Warehouses/Form.vue";
import { useCreateDialog } from "@/composables/useCreateDialog";
const props = defineProps<{
    mode: "create" | "edit";
    initialData?: any;
    displayName?: string;
    formOptions?: any;
    isLoading?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const { t } = useI18n();

const form = ref({
    partner_id: undefined as number | undefined,
    warehouse_id: undefined as number | undefined,
    company_id: undefined as number | undefined,
    seller_id: undefined as number | undefined,
    notes: "",
    products: [] as any[],
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData && Object.keys(newData).length > 0) {
            form.value = {
                partner_id: newData.partner_id || undefined,
                warehouse_id: newData.warehouse_id || undefined,
                company_id: newData.company_id || undefined,
                seller_id:
                    newData.seller_id ||
                    newData.user_id ||
                    newData.seller?.id ||
                    newData.user?.id ||
                    undefined,
                notes: newData.notes || "",
                products: newData.products
                    ? newData.products.map((p: any) => ({
                          product_product_id: p.product_product_id,
                          product_template_id: p.product?.product_template_id,
                          product_name:
                              p.product?.display_name ||
                              p.product?.name ||
                              p.productProduct?.display_name ||
                              "",
                          quantity: p.quantity,
                          price: p.price,
                          tax_id: p.tax_id || undefined,
                          uom_id: p.uom_id || undefined,
                          quantity_uom: p.quantity_uom ?? p.quantity,
                          price_uom: p.price_uom ?? p.price,
                          is_tracked_by_lot: !!p.product?.is_tracked_by_lot,
                          lot_id: p.lot_id ?? undefined,
                          lot_number: p.lot_number ?? p.lot?.lot_number ?? undefined,
                          lot_expires_at: p.lot_expires_at ?? p.lot?.expires_at ?? undefined,
                      }))
                    : [],
            };
        } else if (props.mode === "create") {
            form.value.products = [];
        }
    },
    { immediate: true },
);

const customers = computed(() => props.formOptions?.customers || []);
const warehouses = computed(() => props.formOptions?.warehouses || []);
const companies = computed(() => props.formOptions?.companies || []);
const users = computed(() => props.formOptions?.users || []);
const taxes = computed(() => props.formOptions?.taxes || []);
const uoms = computed(() => props.formOptions?.uoms || []);

const isDraft = computed(
    () => props.mode === "create" || props.initialData?.status === "draft",
);

const saleName = computed(() => {
    if (props.displayName !== undefined) return props.displayName;
    if (props.mode === "create") return t('sales.form.newName');
    const serie = props.initialData?.serie ? `${props.initialData.serie}-` : "";
    const correlative = props.initialData?.correlative || "";
    return `${serie}${correlative}`;
});

const customerOptions = computed(() =>
    customers.value.map((c: any) => ({
        value: c.id,
        label: c.name || c.display_name || `#${c.id}`,
        description: c.document_number || c.email || null,
    })),
);

const warehouseOptions = computed(() =>
    warehouses.value.map((w: any) => ({
        value: w.id,
        label: w.name || `#${w.id}`,
    })),
);

const companyOptions = computed(() =>
    companies.value.map((c: any) => ({
        value: c.id,
        label: c.name || `#${c.id}`,
    })),
);

const sellerOptions = computed(() =>
    users.value.map((u: any) => ({
        value: u.id,
        label: u.name || u.email || `#${u.id}`,
        description: u.email || null,
    })),
);

// ─── Create Dialog: Customer ─────────────────────────────────────────────────
const customerDialog = useCreateDialog({
    endpoint: '/v1/customers',
    labelKey: 'customer',
});
const customerFormRef = ref<InstanceType<typeof CustomerForm> | null>(null);
const localCustomers = ref<{ id: number; name: string }[]>([]);

const allCustomerOptions = computed(() =>
    [
        ...customers.value,
        ...localCustomers.value,
    ].map((c: any) => ({
        value: c.id,
        label: c.name || c.display_name || `#${c.id}`,
        description: c.document_number || c.email || null,
    })),
);

async function onCustomerSubmit(payload: any) {
    const record = await customerDialog.handleSubmit(payload);
    if (record) {
        // Update or add to local options
        const idx = localCustomers.value.findIndex((c) => c.id === record.id);
        if (idx !== -1) {
            localCustomers.value[idx] = { id: record.id, name: record.name };
        } else {
            localCustomers.value.push({ id: record.id, name: record.name });
        }
        form.value.partner_id = record.id;
    }
}

// ─── Create Dialog: Warehouse ────────────────────────────────────────────────
const warehouseDialog = useCreateDialog({
    endpoint: '/v1/warehouses',
    formOptionsEndpoint: '/v1/warehouses/form-options',
    labelKey: 'warehouse',
});
const warehouseFormRef = ref<InstanceType<typeof WarehouseForm> | null>(null);
const localWarehouses = ref<{ id: number; name: string }[]>([]);

const allWarehouseOptions = computed(() =>
    [
        ...warehouses.value,
        ...localWarehouses.value,
    ].map((w: any) => ({
        value: w.id,
        label: w.name || `#${w.id}`,
    })),
);

async function onWarehouseSubmit(payload: any) {
    const record = await warehouseDialog.handleSubmit(payload);
    if (record) {
        const idx = localWarehouses.value.findIndex((w) => w.id === record.id);
        if (idx !== -1) {
            localWarehouses.value[idx] = { id: record.id, name: record.name };
        } else {
            localWarehouses.value.push({ id: record.id, name: record.name });
        }
        form.value.warehouse_id = record.id;
    }
}

const activeTab = ref("products");
const formTabs = [
    { value: "products", label: "Productos" },
    { value: "other_info", label: "Otra información" },
];

const submit = () => {
    emit("submit", form.value);
};

defineExpose({ submit });
</script>

<template>
    <div class="space-y-2">
        <Card class="w-full relative overflow-hidden">
            <CardContent class="pt-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="space-y-2">
                        <Label htmlFor="sale_name">{{ t('sales.form.name') }}</Label>
                        <UnderlineInput
                            id="sale_name"
                            :model-value="saleName"
                            disabled
                            readonly
                            class="h-12 text-2xl font-semibold"
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label htmlFor="partner_id">{{ t('sales.form.customer') }}</Label>
                            <SearchSelect
                                id="partner_id"
                                v-model="form.partner_id"
                                :disabled="!isDraft"
                                :options="allCustomerOptions"
                                :placeholder="t('sales.form.searchCustomer')"
                                :show-create="isDraft"
                                :show-edit="true"
                                @create="customerDialog.open($event)"
                                @edit="(id) => customerDialog.edit(id)"
                            />
                            <p v-if="errors?.partner_id" class="text-sm text-destructive">
                                {{ errors.partner_id }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label htmlFor="warehouse_id">
                                {{ t('sales.form.warehouse') }}
                                <span class="text-destructive">*</span>
                            </Label>
                            <SearchSelect
                                id="warehouse_id"
                                v-model="form.warehouse_id"
                                required
                                :disabled="!isDraft"
                                :options="allWarehouseOptions"
                                :placeholder="t('sales.form.searchWarehouse')"
                                :show-create="isDraft"
                                :show-edit="true"
                                @create="warehouseDialog.open($event)"
                                @edit="(id) => warehouseDialog.edit(id)"
                            />
                            <p v-if="errors?.warehouse_id" class="text-sm text-destructive">
                                {{ errors.warehouse_id }}
                            </p>
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>

        <FolderTabs v-model="activeTab" :tabs="formTabs">
            <div v-show="activeTab === 'products'" class="p-0">
                <div class="pt-2">
                    <p
                        v-if="errors?.products"
                        class="text-sm text-destructive mb-2 px-6"
                    >
                        {{ errors.products }}
                    </p>
                    <ProductLineItems
                        v-model="form.products"
                        :taxes="taxes"
                        :uoms="uoms"
                        :is-draft="isDraft"
                        :errors="errors"
                        :warehouse-id="form.warehouse_id"
                        context="sale"
                    >
                        <template #notes>
                            <div class="space-y-1">
                                <Label
                                    htmlFor="notes"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ t('sales.form.notes') }}
                                </Label>
                                <UnderlineTextarea
                                    id="notes"
                                    v-model="form.notes"
                                    rows="3"
                                    :placeholder="t('sales.form.notesPlaceholder')"
                                    :disabled="!isDraft"
                                />
                                <p
                                    v-if="errors?.notes"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.notes }}
                                </p>
                            </div>
                        </template>
                    </ProductLineItems>
                </div>
            </div>

            <div
                v-show="activeTab === 'other_info'"
                class="p-6 space-y-6 md:min-h-[400px]"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="company_id">
                            {{ t('sales.form.company') }}
                            <span class="text-destructive">*</span>
                        </Label>
                        <SearchSelect
                            id="company_id"
                            v-model="form.company_id"
                            required
                            :disabled="!isDraft"
                            :options="companyOptions"
                            :placeholder="t('sales.form.searchCompany')"
                        />
                        <p
                            v-if="errors?.company_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.company_id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="seller_id">{{ t('sales.form.seller') }}</Label>
                        <SearchSelect
                            id="seller_id"
                            v-model="form.seller_id"
                            :disabled="!isDraft"
                            :options="sellerOptions"
                            :placeholder="t('sales.form.searchSeller')"
                        />
                        <p
                            v-if="errors?.seller_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.seller_id }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ t('sales.form.sellerHelp') }}
                        </p>
                    </div>
                </div>
            </div>
        </FolderTabs>

        <!-- Dialog: Customer (create / edit) -->
        <CreateDialog
            :open="customerDialog.isOpen.value"
            :loading="customerDialog.isLoading.value"
            :title="customerDialog.title.value"
            @close="customerDialog.close()"
            @save="customerFormRef?.submit()"
        >
            <CustomerForm
                ref="customerFormRef"
                :mode="customerDialog.mode.value"
                :initial-data="customerDialog.isEditing.value
                    ? customerDialog.initialData.value
                    : { name: customerDialog.initialName.value }"
                :errors="customerDialog.errors.value"
                @submit="onCustomerSubmit"
            />
        </CreateDialog>

        <!-- Dialog: Warehouse (create / edit) -->
        <CreateDialog
            :open="warehouseDialog.isOpen.value"
            :loading="warehouseDialog.isLoading.value"
            :title="warehouseDialog.title.value"
            @close="warehouseDialog.close()"
            @save="warehouseFormRef?.submit()"
        >
            <WarehouseForm
                ref="warehouseFormRef"
                :mode="warehouseDialog.mode.value"
                :initial-data="warehouseDialog.isEditing.value
                    ? warehouseDialog.initialData.value
                    : { name: warehouseDialog.initialName.value }"
                :form-options="warehouseDialog.formOptions.value"
                :errors="warehouseDialog.errors.value"
                @submit="onWarehouseSubmit"
            />
        </CreateDialog>
    </div>
</template>
