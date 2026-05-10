<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import FolderTabs from "@/components/ui/tabs/FolderTabs.vue";
import ProductLineItems from "@tenant/components/ProductLineItems.vue";
import { SearchSelect } from "@/components/ui/search-select";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import CreateDialog from "@/components/CreateDialog.vue";
import SupplierForm from "@tenant/views/Suppliers/Form.vue";
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

const form = ref({
    partner_id: undefined as number | undefined,
    warehouse_id: undefined as number | undefined,
    company_id: undefined as number | undefined,
    buyer_id: undefined as number | undefined,
    vendor_bill_number: "",
    vendor_bill_date: "",
    observation: "",
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
                buyer_id: newData.buyer_id || undefined,
                vendor_bill_number: newData.vendor_bill_number || "",
                vendor_bill_date: newData.vendor_bill_date
                    ? newData.vendor_bill_date.split("T")[0] // Handle ISO dates if needed
                    : "",
                observation: newData.observation || "",
                products: regroupLines(newData.lines || []),
            };
        } else if (props.mode === "create") {
            // Default 1 line
            form.value.products = [];
        }
    },
    { immediate: true },
);

/**
 * Backend stores one productable row per lot. The form no longer manages
 * lot data — that's the responsibility of the Lots page. Here we just
 * collapse rows that share (product_product_id, tax_id, uom_id, price_uom)
 * so the UI shows one line per product group, with the informational
 * `is_tracked_by_lot` flag preserved.
 */
function regroupLines(rawLines: any[]): any[] {
    const groups = new Map<string, any>();

    for (const p of rawLines) {
        const isLotted = !!(
            p.is_tracked_by_lot ??
            p.product?.is_tracked_by_lot ??
            (p.lot_id || p.lot_number_input || p.lot?.lot_number)
        );

        const key = isLotted
            ? [
                  "L",
                  p.product_product_id,
                  p.tax_id ?? "",
                  p.uom_id ?? "",
                  Number(p.price_uom ?? p.price ?? 0).toFixed(6),
              ].join("|")
            : `U|${p.product_product_id}|${p.id}`;

        const rowQty = Number(p.quantity_uom ?? p.quantity ?? 0);
        const lotAlloc = p.lot_id
            ? {
                  productable_id: p.id,
                  lot_id: p.lot_id,
                  lot_number: p.lot_number ?? p.lot?.lot_number ?? null,
                  lot_expires_at: p.lot_expires_at ?? p.lot?.expires_at ?? null,
                  quantity: Number(p.quantity ?? 0),
              }
            : null;

        if (!groups.has(key)) {
            groups.set(key, {
                product_product_id: p.product_product_id,
                product_template_id: p.product?.product_template_id,
                product_name: p.product?.name || p.product_name || "",
                quantity: Number(p.quantity ?? 0),
                price: Number(p.price ?? 0),
                tax_id: p.tax_id || undefined,
                uom_id: p.uom_id || undefined,
                quantity_uom: rowQty,
                price_uom: Number(p.price_uom ?? p.price ?? 0),
                is_tracked_by_lot: isLotted,
                lot_allocations: lotAlloc ? [lotAlloc] : [],
            });
            continue;
        }

        const group = groups.get(key);
        group.quantity = Number(group.quantity) + Number(p.quantity ?? 0);
        group.quantity_uom = Number(group.quantity_uom) + rowQty;
        if (lotAlloc) {
            (group.lot_allocations ??= []).push(lotAlloc);
        }
    }

    // Expose the single lot (lot_id / lot_number) at the row level when there's
    // exactly one allocation — this keeps the SearchSelect working as before
    // and avoids the "Lote #N" fallback when lot_number wasn't populated.
    for (const g of groups.values()) {
        if (g.lot_allocations?.length === 1) {
            g.lot_id = g.lot_allocations[0].lot_id;
            g.lot_number = g.lot_allocations[0].lot_number ?? undefined;
            g.lot_expires_at = g.lot_allocations[0].lot_expires_at ?? undefined;
        }
    }

    return Array.from(groups.values());
}

const suppliers = computed(() => props.formOptions?.suppliers || []);
const warehouses = computed(() => props.formOptions?.warehouses || []);
const companies = computed(() => props.formOptions?.companies || []);
const users = computed(() => props.formOptions?.users || []);
const taxes = computed(() => props.formOptions?.taxes || []);
const uoms = computed(() => props.formOptions?.uoms || []);

const isDraft = computed(
    () => props.mode === "create" || props.initialData?.status === "draft",
);

const purchaseName = computed(() => {
    if (props.displayName !== undefined) return props.displayName;
    if (props.mode === "create") return "New";
    const serie = props.initialData?.serie ? `${props.initialData.serie}-` : "";
    const correlative = props.initialData?.correlative || "";
    return `${serie}${correlative}`;
});

// Simple tab state
const activeTab = ref("products");
const formTabs = [
    { value: "products", label: "Productos" },
    { value: "other_info", label: "Otra información" },
];

// ─── Create Dialog: Supplier ─────────────────────────────────────────────────
const supplierDialog = useCreateDialog({
    endpoint: '/v1/suppliers',
    label: 'Supplier',
});
const supplierFormRef = ref<InstanceType<typeof SupplierForm> | null>(null);
const localSuppliers = ref<any[]>([]);

const supplierOptions = computed(() =>
    [...suppliers.value, ...localSuppliers.value].map((s: any) => ({
        value: s.id,
        label: s.trade_name || s.name || s.business_name || `#${s.id}`,
    })),
);

async function onSupplierSubmit(payload: any) {
    const record = await supplierDialog.handleSubmit(payload);
    if (record) {
        const idx = localSuppliers.value.findIndex((s) => s.id === record.id);
        if (idx !== -1) {
            localSuppliers.value[idx] = record;
        } else {
            localSuppliers.value.push(record);
        }
        form.value.partner_id = record.id;
    }
}

// ─── Create Dialog: Warehouse ────────────────────────────────────────────────
const warehouseDialog = useCreateDialog({
    endpoint: '/v1/warehouses',
    formOptionsEndpoint: '/v1/warehouses/form-options',
    label: 'Warehouse',
});
const warehouseFormRef = ref<InstanceType<typeof WarehouseForm> | null>(null);
const localWarehouses = ref<{ id: number; name: string }[]>([]);

const warehouseOptions = computed(() =>
    [...warehouses.value, ...localWarehouses.value].map((w: any) => ({
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

const companyOptions = computed(() =>
    companies.value.map((c: any) => ({
        value: c.id,
        label: c.name || `#${c.id}`,
    })),
);

const buyerOptions = computed(() =>
    users.value.map((u: any) => ({
        value: u.id,
        label: u.name || u.email || `#${u.id}`,
        description: u.email || null,
    })),
);

const submit = () => {
    const cleanedProducts = form.value.products.map((line: any) => {
        const { lot_allocations, is_tracked_by_lot, ...rest } = line;
        return rest;
    });
    emit("submit", { ...form.value, products: cleanedProducts });
};

defineExpose({ submit });
</script>

<template>
    <div class="space-y-2">
        <Card class="w-full relative overflow-hidden">
            <CardContent class="pt-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="space-y-2">
                        <Label htmlFor="purchase_name">Name</Label>
                        <UnderlineInput
                            id="purchase_name"
                            :model-value="purchaseName"
                            disabled
                            readonly
                            class="h-12 text-2xl font-semibold"
                        />
                    </div>
                    <!-- Row 1 -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label htmlFor="partner_id"
                                >Supplier
                                <span class="text-destructive">*</span></Label
                            >
                            <SearchSelect
                                id="partner_id"
                                v-model="form.partner_id"
                                :disabled="!isDraft"
                                :options="supplierOptions"
                                placeholder="Buscar proveedor..."
                                :show-create="isDraft"
                                :show-edit="true"
                                @create="supplierDialog.open($event)"
                                @edit="(id) => supplierDialog.edit(id)"
                            />
                            <p
                                v-if="errors?.partner_id"
                                class="text-sm text-destructive"
                            >
                                {{ errors.partner_id }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label htmlFor="warehouse_id"
                                >Warehouse
                                <span class="text-destructive">*</span></Label
                            >
                            <SearchSelect
                                id="warehouse_id"
                                v-model="form.warehouse_id"
                                required
                                :disabled="!isDraft"
                                :options="warehouseOptions"
                                placeholder="Buscar almacén..."
                                :show-create="isDraft"
                                :show-edit="true"
                                @create="warehouseDialog.open($event)"
                                @edit="(id) => warehouseDialog.edit(id)"
                            />
                            <p
                                v-if="errors?.warehouse_id"
                                class="text-sm text-destructive"
                            >
                                {{ errors.warehouse_id }}
                            </p>
                        </div>
                    </div>

                    <!-- Row 2 -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label htmlFor="vendor_bill_number"
                                >Vendor Bill Number</Label
                            >
                            <UnderlineInput
                                id="vendor_bill_number"
                                v-model="form.vendor_bill_number"
                                placeholder="e.g. F001-000123"
                                :disabled="!isDraft"
                            />
                            <p
                                v-if="errors?.vendor_bill_number"
                                class="text-sm text-destructive"
                            >
                                {{ errors.vendor_bill_number }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label htmlFor="vendor_bill_date"
                                >Vendor Bill Date</Label
                            >
                            <UnderlineInput
                                id="vendor_bill_date"
                                type="date"
                                v-model="form.vendor_bill_date"
                                :disabled="!isDraft"
                            />
                            <p
                                v-if="errors?.vendor_bill_date"
                                class="text-sm text-destructive"
                            >
                                {{ errors.vendor_bill_date }}
                            </p>
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>

        <FolderTabs v-model="activeTab" :tabs="formTabs">
            <!-- Products Content -->
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
                        context="purchase"
                        :purchase-id="props.initialData?.id ?? null"
                    >
                        <template #notes>
                            <div class="space-y-1">
                                <Label
                                    htmlFor="observation"
                                    class="text-xs text-muted-foreground"
                                    >Observations</Label
                                >
                                <UnderlineTextarea
                                    id="observation"
                                    v-model="form.observation"
                                    rows="3"
                                    placeholder="Additional details..."
                                    :disabled="!isDraft"
                                />
                                <p
                                    v-if="errors?.observation"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.observation }}
                                </p>
                            </div>
                        </template>
                    </ProductLineItems>
                </div>
            </div>

            <!-- Other Information Content -->
            <div
                v-show="activeTab === 'other_info'"
                class="p-6 space-y-6 md:min-h-[400px]"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="company_id"
                            >Company
                            <span class="text-destructive">*</span></Label
                        >
                        <SearchSelect
                            id="company_id"
                            v-model="form.company_id"
                            required
                            :disabled="!isDraft"
                            :options="companyOptions"
                            placeholder="Buscar compañía..."
                        />
                        <p
                            v-if="errors?.company_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.company_id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="buyer_id">Buyer</Label>
                        <SearchSelect
                            id="buyer_id"
                            v-model="form.buyer_id"
                            :disabled="!isDraft"
                            :options="buyerOptions"
                            placeholder="Buscar comprador..."
                        />
                        <p
                            v-if="errors?.buyer_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.buyer_id }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Select the user responsible for this purchase.
                        </p>
                    </div>
                </div>
            </div>
        </FolderTabs>

        <!-- Dialog: Supplier (create / edit) -->
        <CreateDialog
            :open="supplierDialog.isOpen.value"
            :loading="supplierDialog.isLoading.value"
            :title="supplierDialog.title.value"
            @close="supplierDialog.close()"
            @save="supplierFormRef?.submit()"
        >
            <SupplierForm
                ref="supplierFormRef"
                :mode="supplierDialog.mode.value"
                :initial-data="supplierDialog.isEditing.value
                    ? supplierDialog.initialData.value
                    : { name: supplierDialog.initialName.value }"
                :errors="supplierDialog.errors.value"
                @submit="onSupplierSubmit"
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
