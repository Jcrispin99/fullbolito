<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";
import { Button } from "@/components/ui/button";
import { Trash2, Boxes } from "lucide-vue-next";
import { useProductProductStore } from "@tenant/stores/productProduct";
import { SearchSelect } from "@/components/ui/search-select";
import CreateDialog from "@/components/CreateDialog.vue";
import PurchaseLotModal from "@tenant/components/PurchaseLotModal.vue";
import SaleLotModal from "@tenant/components/SaleLotModal.vue";
import ProductForm from "@tenant/views/Products/Form.vue";
import { useCreateDialog } from "@/composables/useCreateDialog";

const props = defineProps<{
    modelValue: any[];
    taxes?: any[];
    uoms?: any[];
    isDraft?: boolean;
    errors?: Record<string, string>;
    warehouseId?: number | null;
    context?: "purchase" | "sale";
    purchaseId?: number | string | null;
}>();

const emit = defineEmits<{
    (e: "update:modelValue", data: any[]): void;
}>();

const { t } = useI18n();
const productStore = useProductProductStore();

// ─── Lot modals (purchase: create/edit; sale: select) ────────────────────────
const purchaseLotModalOpen = ref(false);
const saleLotModalOpen = ref(false);
const lotModalLineIndex = ref<number | null>(null);

const activeLotLine = computed(() => {
    if (lotModalLineIndex.value === null) return null;
    return lines.value[lotModalLineIndex.value] ?? null;
});

const openLotModal = (index: number) => {
    const line = lines.value[index];
    if (!line?.is_tracked_by_lot) return;
    lotModalLineIndex.value = index;
    if (props.context === "sale") {
        saleLotModalOpen.value = true;
    } else {
        purchaseLotModalOpen.value = true;
    }
};

const closeLotModal = () => {
    purchaseLotModalOpen.value = false;
    saleLotModalOpen.value = false;
    lotModalLineIndex.value = null;
};

const onPurchaseLotsSaved = async (updatedGroup: any) => {
    const idx = lotModalLineIndex.value;
    if (idx === null || !updatedGroup) {
        closeLotModal();
        return;
    }
    const line = lines.value[idx];
    const allocations = (updatedGroup.allocations || []).map((a: any) => ({
        productable_id: a.productable_id ?? null,
        lot_id: a.lot_id ?? null,
        lot_number: a.lot_number ?? null,
        lot_expires_at: a.expires_at ?? null,
        quantity: Number(a.quantity) || 0,
    }));
    line.lot_allocations = allocations;
    // Recompute the aggregate quantity on the line to reflect the new total.
    const totalQty = allocations.reduce(
        (s: number, a: any) => s + (Number(a.quantity) || 0),
        0,
    );
    if (totalQty > 0) {
        line.quantity_uom = totalQty;
        line.quantity = totalQty * (Number(line.uom_factor) || 1);
    }
    // Expose single-lot at line level for the existing read-only display logic.
    if (allocations.length === 1) {
        line.lot_id = allocations[0].lot_id ?? undefined;
        line.lot_number = allocations[0].lot_number ?? undefined;
        line.lot_expires_at = allocations[0].lot_expires_at ?? undefined;
    } else {
        line.lot_id = undefined;
        line.lot_number = undefined;
        line.lot_expires_at = undefined;
    }
    emitUpdate();
    closeLotModal();
};

const onSaleLotSelected = (selection: {
    lot_id: number | null;
    lot_number?: string | null;
    expires_at?: string | null;
}) => {
    const idx = lotModalLineIndex.value;
    if (idx === null) {
        closeLotModal();
        return;
    }
    const line = lines.value[idx];
    line.lot_id = selection.lot_id ?? undefined;
    line.lot_number = selection.lot_number ?? undefined;
    line.lot_expires_at = selection.expires_at ?? undefined;
    emitUpdate();
    closeLotModal();
};

// Local state for lines
const lines = ref<any[]>([]);
const isAdding = ref(false);
const ghostData = ref({
    product_product_id: undefined as number | undefined,
    quantity: 1,
    uom_id: undefined as number | undefined,
    uom_name: "",
    price: 0,
    tax_id: undefined as number | undefined,
});

type SearchKey = number | "ghost";
const searchResultsByKey = ref<Record<string, any[]>>({});
const isSearchingByKey = ref<Record<string, boolean>>({});
const searchTimeoutByKey = new Map<string, any>();

// Sync external modelValue → local lines
watch(
    () => props.modelValue,
    (newVal) => {
        if (!newVal) {
            lines.value = [];
            return;
        }
        lines.value = [...newVal];
    },
    { immediate: true, deep: true },
);

const emitUpdate = () => {
    emit("update:modelValue", JSON.parse(JSON.stringify(lines.value)));
};

// ─── UoM helpers ──────────────────────────────────────────────────────────────

/** Return UoM object by id */
const getUom = (id: number | undefined) =>
    id ? (props.uoms ?? []).find((u) => u.id === id) : undefined;

/** Factor of a UoM relative to base unit. 1 for a base unit or unknown. */
const uomFactor = (id: number | undefined): number => {
    const u = getUom(id);
    return u ? parseFloat(u.factor) || 1 : 1;
};

/** Default UoM id — prefer "Unidad" if present in the catalog. */
const defaultUomId = computed<number | undefined>(() => {
    const list = props.uoms ?? [];
    const unidad = list.find(
        (u: any) =>
            String(u.name ?? "").toLowerCase() === "unidad" ||
            String(u.symbol ?? "").toLowerCase() === "un",
    );
    return unidad?.id;
});

/**
 * When the user changes the UoM on an existing line, recalculate the
 * displayed price_uom proportionally.
 *
 *   new_price_uom = base_price * new_factor
 *   where base_price = old_price_uom / old_factor
 */
const onUomChange = (index: number, newUomId: number | undefined) => {
    const line = lines.value[index];
    const oldFactor = uomFactor(line._prev_uom_id ?? line.uom_id);
    const newFactor = uomFactor(newUomId);

    // Derive base price from the currently displayed uom price
    const basePrice = (line.price_uom || 0) / oldFactor;
    const newPriceUom = parseFloat((basePrice * newFactor).toFixed(6));

    // Derive base quantity
    const baseQty = (line.quantity_uom || 1) * oldFactor;
    const newQtyUom = parseFloat((baseQty / newFactor).toFixed(6));

    line.uom_id = newUomId;
    line._prev_uom_id = newUomId;
    line.price_uom = newPriceUom;
    line.quantity_uom = newQtyUom;
    emitUpdate();
};

// ─── Tax helpers ───────────────────────────────────────────────────────────────

const getTax = (id: number | undefined) =>
    id ? (props.taxes ?? []).find((t) => t.id === id) : undefined;

/**
 * Compute the net (pre-tax) subtotal and the tax amount for a single line.
 * Returns { subtotal, taxAmount, total }
 */
const lineCalc = (line: any) => {
    const qty = parseFloat(line.quantity_uom) || 0;
    const price = parseFloat(line.price_uom) || 0;
    const gross = qty * price;

    const tax = getTax(line.tax_id);
    if (!tax || tax.rate_percent === 0) {
        return { subtotal: gross, taxAmount: 0, total: gross };
    }

    const rate = tax.rate_percent / 100;

    if (tax.is_price_inclusive) {
        // Price already includes tax → back out the tax
        const subtotal = gross / (1 + rate);
        const taxAmount = gross - subtotal;
        return { subtotal, taxAmount, total: gross };
    } else {
        // Price excludes tax → add on top
        const taxAmount = gross * rate;
        return { subtotal: gross, taxAmount, total: gross + taxAmount };
    }
};

// ─── Footer totals ─────────────────────────────────────────────────────────────

const footerTotals = computed(() => {
    let subtotal = 0;
    let taxAmount = 0;
    let total = 0;
    for (const line of lines.value) {
        const calc = lineCalc(line);
        subtotal += calc.subtotal;
        taxAmount += calc.taxAmount;
        total += calc.total;
    }
    return { subtotal, taxAmount, total };
});

const hasTax = computed(() => footerTotals.value.taxAmount !== 0);

const keyToString = (key: SearchKey) => String(key);

const searchProducts = (key: SearchKey, query: string) => {
    const q = query.trim();
    const k = keyToString(key);

    const prevTimeout = searchTimeoutByKey.get(k);
    if (prevTimeout) clearTimeout(prevTimeout);

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
                t('productLineItems.productFallback', { id: currentId }),
        });
    }

    return Array.from(map.values());
};

const applySelectedProductToLine = (line: any, product: any) => {
    line.product_product_id = product.id;
    line.product_template_id = product.product_template_id;
    line.product_name = product.display_name;
    line.price = product.price || 0;
    line.price_uom = product.price || 0;
    const resolvedUom = product.uom_id ?? defaultUomId.value;
    line.uom_id = resolvedUom;
    line._prev_uom_id = resolvedUom;
    line.is_tracked_by_lot = !!product.is_tracked_by_lot;
    if (!line.is_tracked_by_lot) {
        line.lot_number = undefined;
        line.manufactured_at = undefined;
        line.expires_at = undefined;
        line.lot_id = undefined;
    }
};

const handleLineProductSelect = (
    index: number,
    value: string | number | boolean | null | undefined,
) => {
    const line = lines.value[index];
    const id =
        value === null || value === undefined ? undefined : Number(value);
    line.product_product_id = id;

    if (id === undefined || Number.isNaN(id)) {
        line.product_name = "";
        emitUpdate();
        return;
    }

    if (id !== undefined && !Number.isNaN(id)) {
        const results = searchResultsByKey.value[keyToString(index)] ?? [];
        const found = results.find((p: any) => Number(p.id) === id);
        if (found) applySelectedProductToLine(line, found);
    }

    emitUpdate();
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

    const resolvedUom =
        ghostData.value.uom_id ?? found.uom_id ?? defaultUomId.value;
    lines.value.push({
        product_product_id: found.id,
        product_template_id: found.product_template_id,
        product_name: found.display_name,
        quantity: ghostData.value.quantity || 1,
        price: found.price || 0,
        price_uom: found.price || 0,
        tax_id: ghostData.value.tax_id,
        uom_id: resolvedUom,
        quantity_uom: ghostData.value.quantity || 1,
        _prev_uom_id: resolvedUom,
        is_tracked_by_lot: !!found.is_tracked_by_lot,
        lot_id: undefined,
        lot_number: undefined,
        manufactured_at: undefined,
        expires_at: undefined,
    });
    ghostData.value = {
        product_product_id: undefined,
        quantity: 1,
        uom_id: defaultUomId.value,
        uom_name: "",
        price: 0,
        tax_id: undefined,
    };
    searchResultsByKey.value[keyToString("ghost")] = [];
    isAdding.value = false;
    emitUpdate();
};

const cancelAdding = () => {
    isAdding.value = false;
    ghostData.value = {
        product_product_id: undefined,
        quantity: 1,
        uom_id: defaultUomId.value,
        uom_name: "",
        price: 0,
        tax_id: undefined,
    };
    searchResultsByKey.value[keyToString("ghost")] = [];
};

const startAdding = () => {
    isAdding.value = true;
    // Seed default UoM in case the uoms list loaded after ghostData was initialized
    if (ghostData.value.uom_id === undefined) {
        ghostData.value.uom_id = defaultUomId.value;
    }
};

const removeLine = (index: number) => {
    lines.value.splice(index, 1);
    emitUpdate();
};

const formatCurrency = (amount: number) =>
    new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
    }).format(amount || 0);

const formatNum = (n: number, decimals = 2) => parseFloat(n.toFixed(decimals));

const tableSearchInputClass =
    "!h-8 !px-2 !py-1 !pr-6 text-sm border-b border-input hover:border-input focus:!border-primary";

const tableNumberInputClass =
    "h-8 w-full bg-transparent border-0 border-b border-input focus:border-primary focus:ring-0 outline-none px-2 py-1 text-sm text-foreground transition-all rounded-none";

const tableNativeSelectClass =
    "h-8 w-full bg-transparent border-0 border-b border-input focus:border-primary focus:ring-0 outline-none px-2 py-1 text-sm text-foreground transition-all rounded-none";

// ─── Create Dialog: Product ──────────────────────────────────────────────────
const productDialog = useCreateDialog({
    endpoint: '/v1/product-templates',
    formOptionsEndpoint: '/v1/product-templates/form-options',
    labelKey: 'product',
});
const productFormRef = ref<InstanceType<typeof ProductForm> | null>(null);

// Track which context triggered the create: 'ghost' or a line index
const createContext = ref<SearchKey>('ghost');

function openProductCreate(key: SearchKey, name: string) {
    createContext.value = key;
    productDialog.open(name);
}

function openProductEdit(key: SearchKey, id: number) {
    createContext.value = key;
    productDialog.edit(id);
}

function openProductEditForLine(index: number) {
    const line = lines.value[index];
    if (!line) return;
    const templateId = Number(line.product_template_id);
    if (!templateId || Number.isNaN(templateId)) {
        // Fallback: try to resolve from the most recent search result.
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

    // Build a map of variant.id → variant data from the (possibly updated) template
    const variantsById = new Map<number, any>();
    for (const v of template.variants ?? []) {
        variantsById.set(Number(v.id), v);
    }

    if (wasEditing) {
        // EDIT mode: refresh every line whose product_product_id matches a variant
        // of this template. Preserve per-line quantity, tax_id, and uom_id overrides.
        for (const line of lines.value) {
            const vid = Number(line.product_product_id);
            if (!vid) continue;
            const variant = variantsById.get(vid);
            if (!variant) continue;

            line.product_name = variant.display_name || template.name;
            line.price = variant.price ?? template.price ?? line.price;
            // Recompute price_uom against current UoM factor
            const factor = uomFactor(line.uom_id);
            line.price_uom = parseFloat(
                ((Number(line.price) || 0) * factor).toFixed(6),
            );
            line.is_tracked_by_lot = !!template.tracked_by_lot;
            if (!line.is_tracked_by_lot) {
                line.lot_allocations = undefined;
                line.lot_number = undefined;
                line.manufactured_at = undefined;
                line.expires_at = undefined;
            }
        }

        // Clear stale search caches so results don't show outdated names
        searchResultsByKey.value = {};

        emitUpdate();
        return;
    }

    // CREATE mode: pick principal (or first) variant, apply to context line/ghost
    const variant =
        template.variants?.find((v: any) => v.is_principal) ||
        template.variants?.[0];
    if (!variant) return;

    const productData = {
        product_product_id: variant.id,
        product_name: variant.display_name || template.name,
        quantity: 1,
        price: variant.price ?? template.price ?? 0,
        price_uom: variant.price ?? template.price ?? 0,
        tax_id: undefined,
        uom_id: template.uom_id,
        quantity_uom: 1,
        _prev_uom_id: template.uom_id,
        is_tracked_by_lot: !!template.tracked_by_lot,
    };

    if (createContext.value === 'ghost') {
        lines.value.push(productData);
        isAdding.value = false;
        ghostData.value = {
            product_product_id: undefined,
            quantity: 1,
            uom_id: undefined,
            uom_name: "",
            price: 0,
            tax_id: undefined,
        };
    } else {
        const idx = Number(createContext.value);
        if (idx >= 0 && idx < lines.value.length) {
            Object.assign(lines.value[idx], productData);
        }
    }
    emitUpdate();
}
</script>

<template>
    <div class="overflow-x-auto min-h-[260px]">
        <table class="w-full border-collapse">
            <thead>
                <tr
                    class="border-b text-left text-sm text-muted-foreground uppercase tracking-wider"
                >
                    <th
                        class="pt-2 pb-3 px-2 font-medium min-w-[250px] w-[35%]"
                    >
                        {{ t('productLineItems.colProduct') }}
                    </th>
                    <th class="pt-2 pb-3 px-2 font-medium w-24 text-right">
                        {{ t('productLineItems.colQuantity') }}
                    </th>
                    <th class="pt-2 pb-3 px-2 font-medium w-32">{{ t('productLineItems.colUom') }}</th>
                    <th class="pt-2 pb-3 px-2 font-medium w-32 text-right">
                        {{ t('productLineItems.colUnitPrice') }}
                    </th>
                    <th class="pt-2 pb-3 px-2 font-medium w-44">{{ t('productLineItems.colTax') }}</th>
                    <th class="pt-2 pb-3 px-2 font-medium w-32 text-right">
                        {{ t('productLineItems.colSubtotal') }}
                    </th>
                    <th class="pt-2 pb-3 px-2 font-medium w-10"></th>
                </tr>
            </thead>
            <tbody>
                <!-- Solid lines -->
                <template v-for="(product, index) in lines" :key="index">
                <tr
                    class="group transition-colors hover:bg-muted/30"
                >
                    <!-- Product name -->
                    <td class="py-3 px-2 relative align-top">
                        <div class="relative">
                            <div
                                class="absolute left-0 top-0 bottom-0 w-[2px] bg-primary rounded-l-sm"
                            />
                            <div class="flex items-center gap-2">
                                <div class="flex-1 min-w-0">
                                    <SearchSelect
                                        :model-value="product.product_product_id"
                                        :options="getProductOptions(index, product)"
                                        :disabled="!isDraft"
                                        :show-create="isDraft"
                                        :show-edit="
                                            isDraft && !!product.product_product_id
                                        "
                                        clear-on-empty
                                        :placeholder="t('productLineItems.searchProductPlaceholder')"
                                        :input-class="tableSearchInputClass"
                                        @search="
                                            (q: string) => searchProducts(index, q)
                                        "
                                        @update:modelValue="
                                            (val) => handleLineProductSelect(index, val)
                                        "
                                        @create="openProductCreate(index, $event)"
                                        @edit="
                                            () =>
                                                openProductEditForLine(index)
                                        "
                                    />
                                </div>
                                <span
                                    v-if="product.is_tracked_by_lot"
                                    class="shrink-0 inline-flex items-center justify-center h-7 w-7 rounded border border-muted-foreground/30 text-muted-foreground"
                                    :title="t('productLineItems.lotTrackedTitle')"
                                    :aria-label="t('productLineItems.lotTrackedTitle')"
                                >
                                    <Boxes class="h-3.5 w-3.5" />
                                </span>
                            </div>

                            <!-- Lot summary + modal trigger (when product is tracked by lot) -->
                            <div
                                v-if="product.is_tracked_by_lot"
                                class="mt-1 pl-3 flex items-center gap-2 flex-wrap"
                            >
                                <span
                                    class="text-[10px] uppercase tracking-wider text-muted-foreground shrink-0"
                                >
                                    {{ t('productLineItems.lotLabel') }}
                                </span>

                                <!-- Existing allocations as read-only badges -->
                                <template v-if="(product.lot_allocations?.length ?? 0) > 0">
                                    <span
                                        v-for="alloc in product.lot_allocations"
                                        :key="alloc.productable_id ?? alloc.lot_id ?? alloc.lot_number"
                                        class="inline-flex items-center gap-1 px-2 h-6 rounded border border-input bg-background text-[11px] font-medium"
                                    >
                                        <span>{{ alloc.lot_number ?? t('productLineItems.lotFallback', { id: alloc.lot_id ?? '?' }) }}</span>
                                        <span class="text-muted-foreground">× {{ alloc.quantity }}</span>
                                    </span>
                                </template>
                                <template v-else-if="context === 'sale' && product.lot_id">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 h-6 rounded border border-input bg-background text-[11px] font-medium"
                                    >
                                        <span>{{ product.lot_number ?? t('productLineItems.lotFallback', { id: product.lot_id }) }}</span>
                                        <span
                                            v-if="product.lot_expires_at"
                                            class="text-muted-foreground"
                                        >
                                            {{ t('productLineItems.expiresLabel') }} {{ product.lot_expires_at }}
                                        </span>
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="text-[11px] text-muted-foreground italic">
                                        {{
                                            context === "sale"
                                                ? t('productLineItems.autoFefo')
                                                : t('productLineItems.noLotsAssigned')
                                        }}
                                    </span>
                                </template>

                                <Button
                                    v-if="
                                        (context === 'sale' && isDraft) ||
                                        (context !== 'sale' && purchaseId)
                                    "
                                    variant="ghost"
                                    size="sm"
                                    type="button"
                                    class="h-6 px-2 text-[11px] text-primary hover:text-primary"
                                    :title="
                                        context === 'sale'
                                            ? t('productLineItems.selectLotTitle')
                                            : t('productLineItems.manageLotsTitle')
                                    "
                                    @click="openLotModal(index)"
                                >
                                    <Boxes class="h-3 w-3 mr-1" />
                                    {{
                                        context === "sale"
                                            ? t('productLineItems.chooseLot')
                                            : isDraft
                                              ? t('productLineItems.manageLotsTitle')
                                              : t('productLineItems.viewLots')
                                    }}
                                </Button>
                            </div>
                        </div>
                        <p
                            v-if="
                                errors?.[`products.${index}.product_product_id`]
                            "
                            class="text-[10px] text-destructive mt-1 pl-3"
                        >
                            {{ errors[`products.${index}.product_product_id`] }}
                        </p>
                        <p
                            v-if="errors?.[`products.${index}.lot_id`]"
                            class="text-[10px] text-destructive mt-1 pl-3"
                        >
                            {{ errors[`products.${index}.lot_id`] }}
                        </p>
                    </td>

                    <!-- Quantity -->
                    <td class="py-3 px-2 align-top">
                        <input
                            v-model.number="product.quantity_uom"
                            @blur="emitUpdate"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :disabled="!isDraft"
                            :class="[tableNumberInputClass, 'text-right']"
                        />
                    </td>

                    <!-- UoM (with auto-recalc) -->
                    <td class="py-3 px-2 align-top">
                        <select
                            :value="product.uom_id"
                            @change="
                                onUomChange(
                                    index,
                                    ($event.target as HTMLSelectElement).value
                                        ? Number(
                                              (
                                                  $event.target as HTMLSelectElement
                                              ).value,
                                          )
                                        : undefined,
                                )
                            "
                            :class="tableNativeSelectClass"
                            :disabled="!isDraft"
                        >
                            <option :value="undefined">{{ t('productLineItems.baseUom') }}</option>
                            <option v-for="u in uoms" :key="u.id" :value="u.id">
                                {{ u.name }}
                            </option>
                        </select>
                    </td>

                    <!-- Unit Price -->
                    <td class="py-3 px-2 align-top">
                        <input
                            v-model.number="product.price_uom"
                            @blur="emitUpdate"
                            type="number"
                            step="0.01"
                            min="0"
                            :disabled="!isDraft"
                            :class="[tableNumberInputClass, 'text-right']"
                        />
                    </td>

                    <!-- Tax -->
                    <td class="py-3 px-2 align-top">
                        <select
                            v-model="product.tax_id"
                            @change="emitUpdate"
                            :class="tableNativeSelectClass"
                            :disabled="!isDraft"
                        >
                            <option :value="undefined">{{ t('productLineItems.noTaxOption') }}</option>
                            <option
                                v-for="t in taxes"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.name }}
                            </option>
                        </select>
                    </td>

                    <!-- Subtotal (net of tax if exclusive, gross if inclusive) -->
                    <td
                        class="py-3 px-2 text-right align-top text-sm font-medium"
                    >
                        <div class="py-1">
                            {{ formatCurrency(lineCalc(product).subtotal) }}
                        </div>
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
                            :aria-label="t('productLineItems.removeLineAria')"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </td>
                </tr>

                </template>

                <!-- "Agregar un producto" trigger row -->
                <tr
                    v-if="isDraft && !isAdding"
                    class="cursor-text"
                    @click="startAdding"
                >
                    <td colspan="7" class="py-3 px-2">
                        <span
                            class="text-primary hover:underline text-sm font-medium"
                            >{{ t('productLineItems.addProduct') }}</span
                        >
                    </td>
                </tr>

                <!-- Ghost / search row -->
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
                                :model-value="ghostData.product_product_id"
                                :options="getProductOptions('ghost')"
                                :show-create="true"
                                clear-on-empty
                                :placeholder="t('productLineItems.searchProductGhostPlaceholder')"
                                :input-class="
                                    tableSearchInputClass +
                                    ' !border-primary placeholder:text-muted-foreground/50'
                                "
                                @search="
                                    (q: string) => searchProducts('ghost', q)
                                "
                                @update:modelValue="handleGhostProductSelect"
                                @create="openProductCreate('ghost', $event)"
                            />
                        </div>
                    </td>
                    <td class="py-3 px-2 align-top">
                        <input
                            v-model.number="ghostData.quantity"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :class="[
                                tableNumberInputClass,
                                'text-right opacity-50 focus:opacity-100',
                            ]"
                        />
                    </td>
                    <td class="py-3 px-2 align-top">
                        <select
                            v-model="ghostData.uom_id"
                            :class="[
                                tableNativeSelectClass,
                                'opacity-50 focus:opacity-100',
                            ]"
                        >
                            <option :value="undefined">{{ t('productLineItems.baseUom') }}</option>
                            <option v-for="u in uoms" :key="u.id" :value="u.id">
                                {{ u.name }}
                            </option>
                        </select>
                    </td>
                    <td class="py-3 px-2 align-top">
                        <input
                            v-model.number="ghostData.price"
                            type="number"
                            step="0.01"
                            min="0"
                            :class="[
                                tableNumberInputClass,
                                'text-right opacity-50 focus:opacity-100',
                            ]"
                        />
                    </td>
                    <td class="py-3 px-2 align-top">
                        <select
                            v-model="ghostData.tax_id"
                            :class="[
                                tableNativeSelectClass,
                                'opacity-50 focus:opacity-100',
                            ]"
                        >
                            <option :value="undefined">{{ t('productLineItems.noTaxOption') }}</option>
                            <option
                                v-for="t in taxes"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.name }}
                            </option>
                        </select>
                    </td>
                    <td
                        class="py-3 px-2 text-right align-top text-sm font-medium text-muted-foreground"
                    >
                        <div class="py-1">
                            {{
                                formatCurrency(
                                    (ghostData.quantity || 0) *
                                        (ghostData.price || 0),
                                )
                            }}
                        </div>
                    </td>
                    <td class="py-3 px-2 text-right align-top">
                        <Button
                            variant="ghost"
                            size="icon"
                            type="button"
                            class="h-8 w-8 text-muted-foreground hover:text-destructive transition-opacity"
                            @click="cancelAdding"
                            :aria-label="t('common.actions.cancel')"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Footer: Notes (slot) + Totals -->
        <div class="flex items-start justify-between pt-5 px-2 gap-8">
            <!-- Notes slot (left) -->
            <div class="flex-1 min-w-0">
                <slot name="notes" />
            </div>

            <!-- Totals (right) -->
            <div class="text-sm space-y-1.5 min-w-[230px] shrink-0">
                <div class="flex justify-between gap-8">
                    <span class="text-muted-foreground">{{ t('productLineItems.subtotalLabel') }}</span>
                    <span>{{ formatCurrency(footerTotals.subtotal) }}</span>
                </div>
                <div v-if="hasTax" class="flex justify-between gap-8">
                    <span class="text-muted-foreground">{{ t('productLineItems.taxLabel') }}</span>
                    <span>{{ formatCurrency(footerTotals.taxAmount) }}</span>
                </div>
                <div
                    class="flex justify-between gap-8 font-semibold text-base border-t border-border pt-2 mt-1"
                >
                    <span>{{ t('productLineItems.totalLabel') }}</span>
                    <span>{{ formatCurrency(footerTotals.total) }}</span>
                </div>
            </div>
        </div>

        <!-- Modal: Purchase lots (create/edit, scoped to one product line) -->
        <PurchaseLotModal
            v-if="context !== 'sale'"
            :open="purchaseLotModalOpen"
            :purchase-id="purchaseId ?? null"
            :product-product-id="activeLotLine?.product_product_id ?? null"
            :product-name="activeLotLine?.product_name ?? ''"
            :is-draft="!!isDraft"
            @close="closeLotModal"
            @saved="onPurchaseLotsSaved"
        />

        <!-- Modal: Sale lot picker -->
        <SaleLotModal
            v-if="context === 'sale'"
            :open="saleLotModalOpen"
            :product-product-id="activeLotLine?.product_product_id ?? null"
            :product-name="activeLotLine?.product_name ?? ''"
            :warehouse-id="warehouseId ?? null"
            :current-lot-id="activeLotLine?.lot_id ?? null"
            @close="closeLotModal"
            @select="onSaleLotSelected"
        />

        <!-- Dialog: Product (create) -->
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
    </div>
</template>
