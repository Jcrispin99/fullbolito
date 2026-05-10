<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Trash2, Boxes, ArrowDownToLine, ArrowUpFromLine } from "lucide-vue-next";
import FolderTabs from "@/components/ui/tabs/FolderTabs.vue";
import { SearchSelect } from "@/components/ui/search-select";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import CreateDialog from "@/components/CreateDialog.vue";
import WarehouseForm from "@tenant/views/Warehouses/Form.vue";
import ProductForm from "@tenant/views/Products/Form.vue";
import TransferLotModal from "@tenant/components/TransferLotModal.vue";
import { useCreateDialog } from "@/composables/useCreateDialog";
import { useProductProductStore } from "@tenant/stores/productProduct";

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

const productStore = useProductProductStore();

const form = ref({
    type: "entry" as "entry" | "exit",
    warehouse_id: undefined as number | undefined,
    company_id: undefined as number | undefined,
    reason: "",
    observation: "",
    products: [] as any[],
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData && Object.keys(newData).length > 0) {
            form.value = {
                type: (newData.type as "entry" | "exit") || "entry",
                warehouse_id: newData.warehouse_id || undefined,
                company_id: newData.company_id || undefined,
                reason: newData.reason || "",
                observation: newData.observation || "",
                products: (newData.lines || []).map((p: any) => ({
                    product_product_id: p.product_product_id,
                    product_template_id: p.product?.product_template_id,
                    product_name:
                        p.product?.display_name ||
                        p.product?.name ||
                        p.product_name ||
                        "",
                    quantity: Number(p.quantity) || 0,
                    price: Number(p.price) || 0,
                    is_tracked_by_lot: !!(
                        p.product?.is_tracked_by_lot ?? p.is_tracked_by_lot
                    ),
                    lot_id: p.lot_id ?? undefined,
                    lot_number:
                        p.lot_number ?? p.lot?.lot_number ?? undefined,
                    lot_expires_at:
                        p.lot_expires_at ?? p.lot?.expires_at ?? undefined,
                })),
            };
        } else if (props.mode === "create") {
            form.value.products = [];
        }
    },
    { immediate: true },
);

const warehouses = computed(() => props.formOptions?.warehouses || []);
const companies = computed(() => props.formOptions?.companies || []);

const isDraft = computed(
    () => props.mode === "create" || props.initialData?.status === "draft",
);

const isEntry = computed(() => form.value.type === "entry");
const isExit = computed(() => form.value.type === "exit");

const movementName = computed(() => {
    if (props.displayName !== undefined) return props.displayName;
    if (props.mode === "create") return "New";
    const serie = props.initialData?.serie ? `${props.initialData.serie}-` : "";
    const correlative = props.initialData?.correlative || "";
    return `${serie}${correlative}`;
});

// ─── Warehouse create dialog ─────────────────────────────────────────────────
const warehouseDialog = useCreateDialog({
    endpoint: "/v1/warehouses",
    formOptionsEndpoint: "/v1/warehouses/form-options",
    label: "Warehouse",
});
const warehouseFormRef = ref<InstanceType<typeof WarehouseForm> | null>(null);
const localWarehouses = ref<{ id: number; name: string }[]>([]);

const warehouseOptions = computed(() =>
    [...warehouses.value, ...localWarehouses.value].map((w: any) => ({
        value: w.id,
        label: w.name || `#${w.id}`,
        description: w.company?.name ?? null,
    })),
);

function openWarehouseCreate(name: string) {
    warehouseDialog.open(name);
}

function openWarehouseEdit(id: number) {
    warehouseDialog.edit(id);
}

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

// ─── Lines ──────────────────────────────────────────────────────────────────
type SearchKey = number | "ghost";
const searchResultsByKey = ref<Record<string, any[]>>({});
const isSearchingByKey = ref<Record<string, boolean>>({});
const searchTimeoutByKey = new Map<string, any>();

const keyToString = (key: SearchKey) => String(key);

const searchProducts = (key: SearchKey, query: string) => {
    const q = query.trim();
    const k = keyToString(key);

    const prev = searchTimeoutByKey.get(k);
    if (prev) clearTimeout(prev);

    if (!q) {
        searchResultsByKey.value[k] = [];
        isSearchingByKey.value[k] = false;
        return;
    }

    searchTimeoutByKey.set(
        k,
        setTimeout(async () => {
            isSearchingByKey.value[k] = true;
            try {
                searchResultsByKey.value[k] =
                    await productStore.searchProductProducts(q);
            } catch {
                searchResultsByKey.value[k] = [];
            } finally {
                isSearchingByKey.value[k] = false;
            }
        }, 300),
    );
};

const getProductOptions = (key: SearchKey, current?: any) => {
    const k = keyToString(key);
    const results = searchResultsByKey.value[k] ?? [];
    const map = new Map<
        number,
        { value: number; label: string; description?: string | null }
    >();

    for (const p of results) {
        map.set(Number(p.id), {
            value: Number(p.id),
            label: p.display_name,
            description: p.sku ? `SKU: ${p.sku}` : null,
        });
    }

    const currentId =
        current?.product_product_id !== undefined &&
        current?.product_product_id !== null
            ? Number(current.product_product_id)
            : undefined;
    if (currentId !== undefined && !Number.isNaN(currentId)) {
        map.set(currentId, {
            value: currentId,
            label:
                current?.product_name ||
                map.get(currentId)?.label ||
                `Producto #${currentId}`,
        });
    }

    return Array.from(map.values());
};

const ghostData = ref({
    product_product_id: undefined as number | undefined,
    quantity: 1,
    price: 0,
});
const isAdding = ref(false);

const applySelectedProductToLine = (line: any, product: any) => {
    line.product_product_id = product.id;
    line.product_template_id = product.product_template_id;
    line.product_name = product.display_name;
    line.is_tracked_by_lot = !!product.is_tracked_by_lot;
    if (!line.is_tracked_by_lot) {
        line.lot_id = undefined;
        line.lot_number = undefined;
        line.lot_expires_at = undefined;
    }
};

const handleLineProductSelect = (
    index: number,
    value: string | number | boolean | null | undefined,
) => {
    const line = form.value.products[index];
    const id =
        value === null || value === undefined ? undefined : Number(value);
    line.product_product_id = id;

    if (id === undefined || Number.isNaN(id)) {
        line.product_name = "";
        return;
    }

    const results = searchResultsByKey.value[keyToString(index)] ?? [];
    const found = results.find((p: any) => Number(p.id) === id);
    if (found) applySelectedProductToLine(line, found);
};

const handleGhostProductSelect = (
    value: string | number | boolean | null | undefined,
) => {
    const id =
        value === null || value === undefined ? undefined : Number(value);
    ghostData.value.product_product_id = id;
    if (id === undefined || Number.isNaN(id)) return;

    const results = searchResultsByKey.value[keyToString("ghost")] ?? [];
    const found = results.find((p: any) => Number(p.id) === id);
    if (!found) return;

    form.value.products.push({
        product_product_id: found.id,
        product_template_id: found.product_template_id,
        product_name: found.display_name,
        quantity: ghostData.value.quantity || 1,
        price: ghostData.value.price || 0,
        is_tracked_by_lot: !!found.is_tracked_by_lot,
        lot_id: undefined,
        lot_number: undefined,
        lot_expires_at: undefined,
    });
    ghostData.value = { product_product_id: undefined, quantity: 1, price: 0 };
    searchResultsByKey.value[keyToString("ghost")] = [];
    isAdding.value = false;
};

const cancelAdding = () => {
    isAdding.value = false;
    ghostData.value = { product_product_id: undefined, quantity: 1, price: 0 };
    searchResultsByKey.value[keyToString("ghost")] = [];
};

const startAdding = () => {
    isAdding.value = true;
};

const removeLine = (index: number) => {
    form.value.products.splice(index, 1);
};

// ─── Lot modal ───────────────────────────────────────────────────────────────
const lotModalOpen = ref(false);
const lotModalLineIndex = ref<number | null>(null);

const activeLotLine = computed(() => {
    if (lotModalLineIndex.value === null) return null;
    return form.value.products[lotModalLineIndex.value] ?? null;
});

const openLotModal = (index: number) => {
    const line = form.value.products[index];
    if (!line?.is_tracked_by_lot) return;
    lotModalLineIndex.value = index;
    lotModalOpen.value = true;
};

const closeLotModal = () => {
    lotModalOpen.value = false;
    lotModalLineIndex.value = null;
};

const onLotSelected = (selection: {
    lot_id: number | null;
    lot_number?: string | null;
    expires_at?: string | null;
}) => {
    const idx = lotModalLineIndex.value;
    if (idx === null) {
        closeLotModal();
        return;
    }
    const line = form.value.products[idx];
    line.lot_id = selection.lot_id ?? undefined;
    line.lot_number = selection.lot_number ?? undefined;
    line.lot_expires_at = selection.expires_at ?? undefined;
    closeLotModal();
};

// ─── Product create/edit dialog ──────────────────────────────────────────────
const productDialog = useCreateDialog({
    endpoint: "/v1/product-templates",
    formOptionsEndpoint: "/v1/product-templates/form-options",
    label: "Product",
});
const productFormRef = ref<InstanceType<typeof ProductForm> | null>(null);
const createContext = ref<SearchKey>("ghost");

function openProductCreate(key: SearchKey, name: string) {
    createContext.value = key;
    productDialog.open(name);
}

function openProductEdit(key: SearchKey, id: number) {
    createContext.value = key;
    productDialog.edit(id);
}

function openProductEditForLine(index: number) {
    const line = form.value.products[index];
    if (!line) return;
    const templateId = Number(line.product_template_id);
    if (!templateId || Number.isNaN(templateId)) {
        const results = searchResultsByKey.value[keyToString(index)] ?? [];
        const found = results.find(
            (p: any) => Number(p.id) === Number(line.product_product_id),
        );
        const fallbackId = found ? Number(found.product_template_id) : NaN;
        if (!fallbackId || Number.isNaN(fallbackId)) return;
        openProductEdit(index, fallbackId);
        return;
    }
    openProductEdit(index, templateId);
}

async function onProductSubmit(payload: any) {
    const wasEditing = productDialog.isEditing.value;
    const template = await productDialog.handleSubmit(payload);
    if (!template) return;

    if (wasEditing) {
        const variantsById = new Map<number, any>();
        for (const v of template.variants ?? []) {
            variantsById.set(Number(v.id), v);
        }
        for (const line of form.value.products) {
            const vid = Number(line.product_product_id);
            if (!vid) continue;
            const variant = variantsById.get(vid);
            if (!variant) continue;
            line.product_name = variant.display_name || template.name;
            line.is_tracked_by_lot = !!template.tracked_by_lot;
            if (!line.is_tracked_by_lot) {
                line.lot_id = undefined;
                line.lot_number = undefined;
                line.lot_expires_at = undefined;
            }
        }
        searchResultsByKey.value = {};
        return;
    }

    const variant =
        template.variants?.find((v: any) => v.is_principal) ||
        template.variants?.[0];
    if (!variant) return;

    const productData = {
        product_product_id: variant.id,
        product_template_id: template.id,
        product_name: variant.display_name || template.name,
        quantity: 1,
        price: 0,
        is_tracked_by_lot: !!template.tracked_by_lot,
        lot_id: undefined,
        lot_number: undefined,
        lot_expires_at: undefined,
    };

    if (createContext.value === "ghost") {
        form.value.products.push(productData);
        isAdding.value = false;
        ghostData.value = {
            product_product_id: undefined,
            quantity: 1,
            price: 0,
        };
    } else {
        const idx = Number(createContext.value);
        if (idx >= 0 && idx < form.value.products.length) {
            Object.assign(form.value.products[idx], productData);
        }
    }
}

// ─── Tabs ────────────────────────────────────────────────────────────────────
const activeTab = ref("products");
const formTabs = [
    { value: "products", label: "Productos" },
    { value: "other_info", label: "Otra información" },
];

const tableSearchInputClass =
    "!h-8 !px-2 !py-1 !pr-6 text-sm border-b border-input hover:border-input focus:!border-primary";

const tableNumberInputClass =
    "h-8 w-full bg-transparent border-0 border-b border-input focus:border-primary focus:ring-0 outline-none px-2 py-1 text-sm text-foreground transition-all rounded-none";

const setType = (t: "entry" | "exit") => {
    if (!isDraft.value) return;
    form.value.type = t;
    // Resetear lots si pasamos a entry (para evitar carga inválida)
    if (t === "entry") {
        for (const p of form.value.products) {
            p.lot_id = undefined;
            p.lot_number = undefined;
            p.lot_expires_at = undefined;
        }
    }
};

const submit = () => {
    emit("submit", {
        type: form.value.type,
        warehouse_id: form.value.warehouse_id,
        company_id: form.value.company_id,
        reason: form.value.reason,
        observation: form.value.observation,
        products: form.value.products.map((line: any) => ({
            product_product_id: line.product_product_id,
            quantity: Number(line.quantity) || 0,
            price: Number(line.price) || 0,
            lot_id: line.lot_id ?? null,
        })),
    });
};

defineExpose({ submit });
</script>

<template>
    <div class="space-y-2">
        <Card class="w-full relative overflow-hidden">
            <CardContent class="pt-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="space-y-2">
                        <Label htmlFor="movement_name">Name</Label>
                        <UnderlineInput
                            id="movement_name"
                            :model-value="movementName"
                            disabled
                            readonly
                            class="h-12 text-2xl font-semibold"
                        />
                    </div>

                    <!-- Type selector (only on create) -->
                    <div v-if="mode === 'create'" class="space-y-2">
                        <Label>
                            Tipo de movimiento
                            <span class="text-destructive">*</span>
                        </Label>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-12 flex-1 justify-start gap-2"
                                :class="
                                    isEntry
                                        ? 'border-emerald-500 bg-emerald-50 text-emerald-900'
                                        : ''
                                "
                                :disabled="!isDraft"
                                @click="setType('entry')"
                            >
                                <ArrowDownToLine
                                    class="h-5 w-5"
                                    :class="
                                        isEntry
                                            ? 'text-emerald-600'
                                            : 'text-muted-foreground'
                                    "
                                />
                                <div class="flex flex-col items-start">
                                    <span class="font-medium">Entrada</span>
                                    <span
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Suma stock al almacén
                                    </span>
                                </div>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-12 flex-1 justify-start gap-2"
                                :class="
                                    isExit
                                        ? 'border-red-500 bg-red-50 text-red-900'
                                        : ''
                                "
                                :disabled="!isDraft"
                                @click="setType('exit')"
                            >
                                <ArrowUpFromLine
                                    class="h-5 w-5"
                                    :class="
                                        isExit
                                            ? 'text-red-600'
                                            : 'text-muted-foreground'
                                    "
                                />
                                <div class="flex flex-col items-start">
                                    <span class="font-medium">Salida</span>
                                    <span
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Resta stock del almacén
                                    </span>
                                </div>
                            </Button>
                        </div>
                        <p
                            v-if="errors?.type"
                            class="text-sm text-destructive"
                        >
                            {{ errors.type }}
                        </p>
                    </div>

                    <!-- Row: warehouse + reason -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label htmlFor="warehouse_id">
                                Almacén
                                <span class="text-destructive">*</span>
                            </Label>
                            <SearchSelect
                                id="warehouse_id"
                                v-model="form.warehouse_id"
                                required
                                :disabled="!isDraft"
                                :options="warehouseOptions"
                                placeholder="Buscar almacén..."
                                :show-create="isDraft"
                                :show-edit="true"
                                @create="openWarehouseCreate($event)"
                                @edit="(id) => openWarehouseEdit(id)"
                            />
                            <p
                                v-if="errors?.warehouse_id"
                                class="text-sm text-destructive"
                            >
                                {{ errors.warehouse_id }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label htmlFor="reason">
                                Motivo
                            </Label>
                            <UnderlineInput
                                id="reason"
                                v-model="form.reason"
                                :disabled="!isDraft"
                                placeholder="Ej: ajuste, merma, devolución…"
                            />
                            <p
                                v-if="errors?.reason"
                                class="text-sm text-destructive"
                            >
                                {{ errors.reason }}
                            </p>
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>

        <FolderTabs v-model="activeTab" :tabs="formTabs">
            <!-- Products Tab -->
            <div v-show="activeTab === 'products'" class="p-0">
                <div class="pt-2">
                    <p
                        v-if="errors?.products"
                        class="text-sm text-destructive mb-2 px-6"
                    >
                        {{ errors.products }}
                    </p>
                    <div class="overflow-x-auto min-h-[260px] px-2">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr
                                    class="border-b text-left text-sm text-muted-foreground uppercase tracking-wider"
                                >
                                    <th
                                        class="pt-2 pb-3 px-2 font-medium min-w-[280px] w-[45%]"
                                    >
                                        Producto
                                    </th>
                                    <th
                                        class="pt-2 pb-3 px-2 font-medium w-32 text-right"
                                    >
                                        Cantidad
                                    </th>
                                    <th
                                        class="pt-2 pb-3 px-2 font-medium w-32 text-right"
                                    >
                                        Costo unit.
                                    </th>
                                    <th class="pt-2 pb-3 px-2 font-medium w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(product, index) in form.products"
                                    :key="index"
                                    class="group transition-colors hover:bg-muted/30"
                                >
                                    <!-- Product -->
                                    <td class="py-3 px-2 relative align-top">
                                        <div class="relative">
                                            <div
                                                class="absolute left-0 top-0 bottom-0 w-[2px] bg-primary rounded-l-sm"
                                            />
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 min-w-0">
                                                    <SearchSelect
                                                        :model-value="
                                                            product.product_product_id
                                                        "
                                                        :options="
                                                            getProductOptions(
                                                                index,
                                                                product,
                                                            )
                                                        "
                                                        :disabled="!isDraft"
                                                        :show-create="isDraft"
                                                        :show-edit="
                                                            isDraft &&
                                                            !!product.product_product_id
                                                        "
                                                        clear-on-empty
                                                        placeholder="Buscar producto..."
                                                        :input-class="
                                                            tableSearchInputClass
                                                        "
                                                        @search="
                                                            (q: string) =>
                                                                searchProducts(
                                                                    index,
                                                                    q,
                                                                )
                                                        "
                                                        @update:modelValue="
                                                            (val) =>
                                                                handleLineProductSelect(
                                                                    index,
                                                                    val,
                                                                )
                                                        "
                                                        @create="
                                                            openProductCreate(
                                                                index,
                                                                $event,
                                                            )
                                                        "
                                                        @edit="
                                                            () =>
                                                                openProductEditForLine(
                                                                    index,
                                                                )
                                                        "
                                                    />
                                                </div>
                                                <span
                                                    v-if="
                                                        product.is_tracked_by_lot
                                                    "
                                                    class="shrink-0 inline-flex items-center justify-center h-7 w-7 rounded border border-muted-foreground/30 text-muted-foreground"
                                                    title="Producto con trazabilidad por lote"
                                                >
                                                    <Boxes class="h-3.5 w-3.5" />
                                                </span>
                                            </div>

                                            <!-- Lot selection (for lot-tracked) -->
                                            <div
                                                v-if="product.is_tracked_by_lot"
                                                class="mt-1 pl-3 flex items-center gap-2 flex-wrap"
                                            >
                                                <span
                                                    class="text-[10px] uppercase tracking-wider text-muted-foreground shrink-0"
                                                >
                                                    Lote
                                                </span>
                                                <template v-if="product.lot_id">
                                                    <span
                                                        class="inline-flex items-center gap-1 px-2 h-6 rounded border border-input bg-background text-[11px] font-medium"
                                                    >
                                                        <span>{{
                                                            product.lot_number ??
                                                            `Lote #${product.lot_id}`
                                                        }}</span>
                                                        <span
                                                            v-if="
                                                                product.lot_expires_at
                                                            "
                                                            class="text-muted-foreground"
                                                        >
                                                            vence
                                                            {{ product.lot_expires_at }}
                                                        </span>
                                                    </span>
                                                </template>
                                                <template v-else>
                                                    <span
                                                        v-if="isExit"
                                                        class="text-[11px] text-muted-foreground italic"
                                                    >
                                                        Auto (FEFO)
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="text-[11px] text-amber-700 italic"
                                                    >
                                                        Requiere lote (crear desde compra)
                                                    </span>
                                                </template>
                                                <Button
                                                    v-if="isDraft && isExit"
                                                    variant="ghost"
                                                    size="sm"
                                                    type="button"
                                                    class="h-6 px-2 text-[11px] text-primary hover:text-primary"
                                                    :title="
                                                        form.warehouse_id
                                                            ? 'Seleccionar lote del almacén'
                                                            : 'Selecciona primero el almacén'
                                                    "
                                                    :disabled="
                                                        !form.warehouse_id
                                                    "
                                                    @click="openLotModal(index)"
                                                >
                                                    <Boxes
                                                        class="h-3 w-3 mr-1"
                                                    />
                                                    Elegir lote
                                                </Button>
                                            </div>
                                        </div>
                                        <p
                                            v-if="
                                                errors?.[
                                                    `products.${index}.product_product_id`
                                                ]
                                            "
                                            class="text-[10px] text-destructive mt-1 pl-3"
                                        >
                                            {{
                                                errors[
                                                    `products.${index}.product_product_id`
                                                ]
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                errors?.[
                                                    `products.${index}.lot_id`
                                                ]
                                            "
                                            class="text-[10px] text-destructive mt-1 pl-3"
                                        >
                                            {{
                                                errors[
                                                    `products.${index}.lot_id`
                                                ]
                                            }}
                                        </p>
                                    </td>

                                    <!-- Quantity -->
                                    <td class="py-3 px-2 align-top">
                                        <input
                                            v-model.number="product.quantity"
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            :disabled="!isDraft"
                                            :class="[
                                                tableNumberInputClass,
                                                'text-right',
                                            ]"
                                        />
                                        <p
                                            v-if="
                                                errors?.[
                                                    `products.${index}.quantity`
                                                ]
                                            "
                                            class="text-[10px] text-destructive mt-1"
                                        >
                                            {{
                                                errors[
                                                    `products.${index}.quantity`
                                                ]
                                            }}
                                        </p>
                                    </td>

                                    <!-- Price (cost) -->
                                    <td class="py-3 px-2 align-top">
                                        <input
                                            v-model.number="product.price"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            :disabled="!isDraft"
                                            :class="[
                                                tableNumberInputClass,
                                                'text-right',
                                            ]"
                                        />
                                        <p
                                            v-if="
                                                errors?.[
                                                    `products.${index}.price`
                                                ]
                                            "
                                            class="text-[10px] text-destructive mt-1"
                                        >
                                            {{
                                                errors[
                                                    `products.${index}.price`
                                                ]
                                            }}
                                        </p>
                                    </td>

                                    <!-- Remove -->
                                    <td class="py-3 px-2 text-right align-top">
                                        <Button
                                            v-if="isDraft"
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            class="h-8 w-8 text-muted-foreground hover:text-destructive opacity-0 group-hover:opacity-100 transition-opacity"
                                            @click="removeLine(index)"
                                            aria-label="Remove line"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>

                                <!-- Add product trigger -->
                                <tr
                                    v-if="isDraft && !isAdding"
                                    class="cursor-text"
                                    @click="startAdding"
                                >
                                    <td colspan="4" class="py-3 px-2">
                                        <span
                                            class="text-primary hover:underline text-sm font-medium"
                                        >
                                            Agregar un producto
                                        </span>
                                    </td>
                                </tr>

                                <!-- Ghost row -->
                                <tr
                                    v-if="isDraft && isAdding"
                                    class="transition-colors bg-muted/20"
                                >
                                    <td class="py-3 px-2 relative align-top">
                                        <div class="relative">
                                            <div
                                                class="absolute left-0 top-0 bottom-0 w-[2px] bg-primary/40 rounded-l-sm"
                                            />
                                            <SearchSelect
                                                :model-value="
                                                    ghostData.product_product_id
                                                "
                                                :options="
                                                    getProductOptions('ghost')
                                                "
                                                :show-create="true"
                                                clear-on-empty
                                                placeholder="Busca un producto..."
                                                :input-class="
                                                    tableSearchInputClass +
                                                    ' !border-primary placeholder:text-muted-foreground/50'
                                                "
                                                @search="
                                                    (q: string) =>
                                                        searchProducts(
                                                            'ghost',
                                                            q,
                                                        )
                                                "
                                                @update:modelValue="
                                                    handleGhostProductSelect
                                                "
                                                @create="
                                                    openProductCreate(
                                                        'ghost',
                                                        $event,
                                                    )
                                                "
                                            />
                                        </div>
                                    </td>
                                    <td class="py-3 px-2 align-top">
                                        <input
                                            v-model.number="ghostData.quantity"
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            :class="[
                                                tableNumberInputClass,
                                                'text-right opacity-50 focus:opacity-100',
                                            ]"
                                        />
                                    </td>
                                    <td class="py-3 px-2 align-top">
                                        <input
                                            v-model.number="ghostData.price"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            :class="[
                                                tableNumberInputClass,
                                                'text-right opacity-50 focus:opacity-100',
                                            ]"
                                        />
                                    </td>
                                    <td class="py-3 px-2 text-right align-top">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            class="h-8 w-8 text-muted-foreground hover:text-destructive transition-opacity"
                                            @click="cancelAdding"
                                            aria-label="Cancel"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Notes/observation -->
                        <div class="pt-5 px-2">
                            <div class="max-w-2xl space-y-1">
                                <Label
                                    htmlFor="observation"
                                    class="text-xs text-muted-foreground"
                                >
                                    Observaciones
                                </Label>
                                <UnderlineTextarea
                                    id="observation"
                                    v-model="form.observation"
                                    rows="3"
                                    placeholder="Detalles adicionales..."
                                    :disabled="!isDraft"
                                />
                                <p
                                    v-if="errors?.observation"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.observation }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Other info tab -->
            <div
                v-show="activeTab === 'other_info'"
                class="p-6 space-y-6 md:min-h-[400px]"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="company_id">
                            Compañía
                            <span class="text-destructive">*</span>
                        </Label>
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
                        <p class="text-xs text-muted-foreground">
                            Compañía que emite el movimiento (para la
                            numeración).
                        </p>
                    </div>
                </div>
            </div>
        </FolderTabs>

        <!-- Warehouse create/edit dialog -->
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
                :initial-data="
                    warehouseDialog.isEditing.value
                        ? warehouseDialog.initialData.value
                        : { name: warehouseDialog.initialName.value }
                "
                :form-options="warehouseDialog.formOptions.value"
                :errors="warehouseDialog.errors.value"
                @submit="onWarehouseSubmit"
            />
        </CreateDialog>

        <!-- Product create/edit dialog -->
        <CreateDialog
            :open="productDialog.isOpen.value"
            :loading="productDialog.isLoading.value"
            :title="productDialog.title.value"
            @close="productDialog.close()"
            @save="productFormRef?.submit()"
        >
            <ProductForm
                ref="productFormRef"
                :is-editing="productDialog.isEditing.value"
                :initial-data="
                    productDialog.isEditing.value
                        ? productDialog.initialData.value
                        : { name: productDialog.initialName.value }
                "
                :form-options="productDialog.formOptions.value"
                :errors="productDialog.errors.value"
                @submit="onProductSubmit"
            />
        </CreateDialog>

        <!-- Lot modal (only used for exits) -->
        <TransferLotModal
            :open="lotModalOpen"
            :product-product-id="
                activeLotLine?.product_product_id ?? null
            "
            :product-name="activeLotLine?.product_name ?? ''"
            :warehouse-id="form.warehouse_id ?? null"
            :current-lot-id="activeLotLine?.lot_id ?? null"
            @close="closeLotModal"
            @select="onLotSelected"
        />
    </div>
</template>
