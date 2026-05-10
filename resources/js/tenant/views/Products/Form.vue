<script setup lang="ts">
import { ref, watch, computed } from "vue";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineSelect } from "@/components/ui/underline-select";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import { SearchSelect } from "@/components/ui/search-select";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Plus, Trash2, Package } from "lucide-vue-next";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import CreateDialog from "@/components/CreateDialog.vue";
import CategoryForm from "@tenant/views/Categories/Form.vue";
import ProductLotsTab from "@tenant/components/ProductLotsTab.vue";
import { useCreateDialog } from "@/composables/useCreateDialog";
import type { ProductTemplate } from "@tenant/stores/productTemplate";

export interface AttributeOption {
    id: number;
    name: string;
    values: { id: number; value: string }[];
}

export interface WarehouseOption {
    id: number;
    name: string;
}

export interface FormOptions {
    categories: { id: number; name: string }[];
    uoms: { id: number; name: string; symbol: string | null }[];
    attributes: AttributeOption[];
    warehouses: WarehouseOption[];
}

const props = defineProps<{
    initialData?: Partial<ProductTemplate> & {
        variants?: any[];
        category?: { id: number; name: string };
        uom?: { id: number; name: string };
    };
    formOptions?: FormOptions;
    isEditing?: boolean;
    archived?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", payload: any): void;
}>();

// ─── General Info ────────────────────────────────────────────────────────────
const formData = ref({
    name: "",
    description: "",
    price: "" as number | string,
    category_id: "" as number | string,
    uom_id: "" as number | string,
    is_pos_visible: false,
    tracks_inventory: true,
    is_service: false,
    tracked_by_lot: false,
    expiration_alert_days: "" as number | string,
    expiration_block_days: "" as number | string,
    sku: "",
    barcode: "",
});

watch(
    () => props.initialData,
    (d) => {
        if (!d) return;
        formData.value = {
            name: d.name ?? "",
            description: d.description ?? "",
            price: d.price ?? "",
            category_id:
                (d as any).category?.id ?? (d as any).category_id ?? "",
            uom_id: (d as any).uom?.id ?? (d as any).uom_id ?? "",
            is_pos_visible: (d as any).is_pos_visible ?? false,
            tracks_inventory: (d as any).tracks_inventory ?? true,
            is_service: (d as any).is_service ?? false,
            tracked_by_lot: (d as any).tracked_by_lot ?? false,
            expiration_alert_days: (d as any).expiration_alert_days ?? "",
            expiration_block_days: (d as any).expiration_block_days ?? "",
            sku: (d as any).sku ?? "",
            barcode: (d as any).barcode ?? "",
        };
    },
    { immediate: true },
);

// ─── Attributes tab ───────────────────────────────────────────────────────────
interface AttributeLine {
    attribute_id: number | string;
    values: string[]; // value names selected
}

const attributeLines = ref<AttributeLine[]>([]);

// ─── Create Dialog: Category ─────────────────────────────────────────────────
const categoryDialog = useCreateDialog({
    endpoint: '/v1/categories',
    formOptionsEndpoint: '/v1/categories/form-options',
    label: 'Category',
});
const categoryFormRef = ref<InstanceType<typeof CategoryForm> | null>(null);

const localCategories = ref<{ id: number; name: string }[]>([]);

const categoryOptions = computed(() =>
    [
        ...(props.formOptions?.categories ?? []),
        ...localCategories.value,
    ].map((c) => ({
        value: c.id,
        label: c.name,
    })),
);

async function onCategorySubmit(payload: any) {
    const record = await categoryDialog.handleSubmit(payload);
    if (record) {
        const idx = localCategories.value.findIndex((c) => c.id === record.id);
        if (idx !== -1) {
            localCategories.value[idx] = { id: record.id, name: record.name };
        } else {
            localCategories.value.push({ id: record.id, name: record.name });
        }
        formData.value.category_id = record.id;
    }
}

// Populate attribute lines from existing variants
watch(
    () => props.initialData?.variants,
    (variants) => {
        if (!variants || variants.length === 0) {
            attributeLines.value = [];
            return;
        }
        // Reconstruct attribute lines from loaded variant data
        const lineMap: Record<
            number,
            { attribute_id: number; attrName: string; values: Set<string> }
        > = {};
        for (const v of variants) {
            for (const av of v.attributes ?? []) {
                if (!lineMap[av.attribute_id]) {
                    lineMap[av.attribute_id] = {
                        attribute_id: av.attribute_id,
                        attrName: av.attribute?.name ?? "",
                        values: new Set(),
                    };
                }
                lineMap[av.attribute_id]!.values.add(av.value);
            }
        }
        attributeLines.value = Object.values(lineMap).map((l) => ({
            attribute_id: l.attribute_id,
            values: Array.from(l.values),
        }));
    },
    { immediate: true },
);

const addAttributeLine = () => {
    attributeLines.value.push({ attribute_id: "", values: [] });
};

const removeAttributeLine = (idx: number) => {
    attributeLines.value.splice(idx, 1);
};

const getAttributeById = (id: number | string) =>
    props.formOptions?.attributes?.find((a) => String(a.id) === String(id));

const toggleValue = (lineIdx: number, valueName: string) => {
    const line = attributeLines.value[lineIdx];
    if (!line) return;
    const pos = line.values.indexOf(valueName);
    if (pos === -1) {
        line.values.push(valueName);
    } else {
        line.values.splice(pos, 1);
    }
};

// Generate cartesian product of attribute values → preview variant names
const generatedVariantNames = computed<string[]>(() => {
    const filled = attributeLines.value.filter(
        (l) => l.attribute_id !== "" && l.values.length > 0,
    );
    if (filled.length === 0) return [];

    const sets = filled.map((l) => l.values);

    const cartesian = (...arrays: string[][]): string[][] => {
        if (arrays.length === 0) return [[]];
        const [first, ...rest] = arrays;
        const restCart = cartesian(...rest);
        return first!.flatMap((v) => restCart.map((r) => [v, ...r]));
    };

    return cartesian(...sets).map((combo) => combo.join(" / "));
});

// ─── Variants Tab ─────────────────────────────────────────────────────────────
const selectedWarehouseId = ref<number | string>("");

const variants = computed(() => props.initialData?.variants ?? []);

const formatMoney = (val: number | string | null) => {
    if (val === null || val === undefined || val === "") return "-";
    return Number(val).toLocaleString("es", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const getVariantLabel = (variant: any): string => {
    const attrs = variant.attributes ?? [];
    if (!attrs.length) return props.initialData?.name ?? "Default";
    return attrs.map((a: any) => a.value).join(" / ");
};

const getVariantStock = (variant: any): number => {
    if (!variant.inventories || variant.inventories.length === 0) {
        return variant.stock ?? 0;
    }
    if (selectedWarehouseId.value === "") {
        // All warehouses: last balance per warehouse, sum them
        const warehouseBalances: Record<number, number> = {};
        for (const inv of variant.inventories) {
            warehouseBalances[inv.warehouse_id] = Number(
                inv.quantity_balance ?? 0,
            );
        }
        return Object.values(warehouseBalances).reduce((a, b) => a + b, 0);
    }
    // Specific warehouse: latest entry
    const filtered = [...variant.inventories].filter(
        (i: any) =>
            String(i.warehouse_id) === String(selectedWarehouseId.value),
    );
    if (!filtered.length) return 0;
    filtered.sort(
        (a: any, b: any) =>
            new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    );
    return Number(filtered[0].quantity_balance ?? 0);
};

// ─── Submit ───────────────────────────────────────────────────────────────────
const handleSubmit = () => {
    const payload: Record<string, any> = {
        name: formData.value.name,
        description: formData.value.description || null,
        price: formData.value.price === "" ? 0 : Number(formData.value.price),
        category_id:
            formData.value.category_id === ""
                ? null
                : formData.value.category_id,
        uom_id: formData.value.uom_id === "" ? null : formData.value.uom_id,
        is_pos_visible: formData.value.is_pos_visible,
        tracks_inventory: formData.value.tracks_inventory,
        is_service: formData.value.is_service,
        tracked_by_lot: formData.value.tracked_by_lot,
        expiration_alert_days:
            formData.value.expiration_alert_days === ""
                ? null
                : Number(formData.value.expiration_alert_days),
        expiration_block_days:
            formData.value.expiration_block_days === ""
                ? null
                : Number(formData.value.expiration_block_days),
    };
    if (formData.value.sku) payload.sku = formData.value.sku;
    if (formData.value.barcode) payload.barcode = formData.value.barcode;

    // Map attribute lines for backend (attributeLines + generatedVariants)
    const filled = attributeLines.value.filter(
        (l) => l.attribute_id !== "" && l.values.length > 0,
    );
    if (filled.length > 0) {
        payload.attributeLines = filled.map((l) => ({
            attribute_id: l.attribute_id,
            values: l.values,
        }));

        // Generate cartesian variants for backend sync
        const sets = filled.map((l) =>
            l.values.map((v) => ({
                attrId: l.attribute_id as number,
                value: v,
            })),
        );

        const cartesian = (
            ...arrays: { attrId: number; value: string }[][]
        ): {
            attrId: number;
            value: string;
        }[][] => {
            if (arrays.length === 0) return [[]];
            const [first, ...rest] = arrays;
            const restCart = cartesian(...rest);
            return first!.flatMap((v) => restCart.map((r) => [v, ...r]));
        };

        const combos = cartesian(...sets);
        payload.generatedVariants = combos.map((combo) => ({
            attributes: Object.fromEntries(
                combo.map((c) => [c.attrId, c.value]),
            ),
            price: Number(formData.value.price),
        }));
    }

    emit("submit", payload);
};

defineExpose({ submit: handleSubmit });
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="relative">
            <CornerRibbon v-if="archived" label="Inactive" tone="danger" />
        </div>

        <Tabs default-value="general">
            <TabsList
                :class="[
                    'grid w-full',
                    formData.tracked_by_lot ? 'grid-cols-4' : 'grid-cols-3',
                ]"
            >
                <TabsTrigger value="general">General Info</TabsTrigger>
                <TabsTrigger value="attributes">Attributes</TabsTrigger>
                <TabsTrigger value="variants" :disabled="!isEditing">
                    Variants
                    <span
                        v-if="variants.length"
                        class="ml-1.5 rounded-full bg-primary/10 px-1.5 py-0.5 text-xs font-medium"
                    >
                        {{ variants.length }}
                    </span>
                </TabsTrigger>
                <TabsTrigger
                    v-if="formData.tracked_by_lot"
                    value="lots"
                    :disabled="!isEditing"
                >
                    Lotes
                </TabsTrigger>
            </TabsList>

            <!-- ─── Tab 1: General Info ─────────────────────────────────── -->
            <TabsContent value="general">
                <Card>
                    <CardContent class="pt-6">
                        <div class="grid gap-6">
                            <!-- Name & Price -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-3 md:col-span-2">
                                    <Label for="name"
                                        >Name
                                        <span class="text-destructive"
                                            >*</span
                                        ></Label
                                    >
                                    <UnderlineInput
                                        id="name"
                                        v-model="formData.name"
                                        placeholder="e.g. T-Shirt Classic"
                                        required
                                        class="h-12 text-2xl font-semibold"
                                    />
                                    <p
                                        v-if="errors?.name"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.name }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        id="is_service"
                                        v-model:checked="formData.is_service"
                                    />
                                    <Label
                                        for="is_service"
                                        class="cursor-pointer"
                                        >Is Service</Label
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        id="tracks_inventory"
                                        v-model:checked="
                                            formData.tracks_inventory
                                        "
                                    />
                                    <Label
                                        for="tracks_inventory"
                                        class="cursor-pointer"
                                        >Track Inventory</Label
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        id="is_pos_visible"
                                        v-model:checked="
                                            formData.is_pos_visible
                                        "
                                    />
                                    <Label
                                        for="is_pos_visible"
                                        class="cursor-pointer"
                                        >POS Visible</Label
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        id="tracked_by_lot"
                                        v-model:checked="
                                            formData.tracked_by_lot
                                        "
                                    />
                                    <Label
                                        for="tracked_by_lot"
                                        class="cursor-pointer"
                                        >Rastrear por lote</Label
                                    >
                                </div>
                            </div>

                            <!-- Lot tracking options (only when enabled) -->
                            <div
                                v-if="formData.tracked_by_lot"
                                class="rounded-md border border-dashed p-4 grid grid-cols-1 md:grid-cols-2 gap-4 bg-muted/20"
                            >
                                <div class="grid gap-3">
                                    <Label for="expiration_alert_days">
                                        Días de alerta pre-vencimiento
                                    </Label>
                                    <UnderlineInput
                                        id="expiration_alert_days"
                                        v-model="formData.expiration_alert_days"
                                        type="number"
                                        min="0"
                                        placeholder="e.g. 30"
                                    />
                                    <p
                                        v-if="errors?.expiration_alert_days"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.expiration_alert_days }}
                                    </p>
                                </div>
                                <div class="grid gap-3">
                                    <Label for="expiration_block_days">
                                        Días de bloqueo pre-vencimiento
                                    </Label>
                                    <UnderlineInput
                                        id="expiration_block_days"
                                        v-model="formData.expiration_block_days"
                                        type="number"
                                        min="0"
                                        placeholder="e.g. 7"
                                    />
                                    <p
                                        v-if="errors?.expiration_block_days"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.expiration_block_days }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-3">
                                    <Label for="price"
                                        >Price
                                        <span class="text-destructive"
                                            >*</span
                                        ></Label
                                    >
                                    <UnderlineInput
                                        id="price"
                                        v-model="formData.price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        required
                                    />
                                    <p
                                        v-if="errors?.price"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.price }}
                                    </p>
                                </div>
                                <div class="grid gap-3">
                                    <Label for="category_id"
                                        >Category
                                        <span class="text-destructive"
                                            >*</span
                                        ></Label
                                    >
                                    <SearchSelect
                                        id="category_id"
                                        v-model="formData.category_id"
                                        required
                                        :options="categoryOptions"
                                        placeholder="Buscar..."
                                        :show-create="true"
                                        :show-edit="true"
                                        @create="categoryDialog.open($event)"
                                        @edit="(id) => categoryDialog.edit(id)"
                                    />
                                    <p
                                        v-if="errors?.category_id"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.category_id }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-3">
                                    <Label for="sku">SKU</Label>
                                    <UnderlineInput
                                        id="sku"
                                        v-model="formData.sku"
                                        placeholder="Optional — auto-generated if empty"
                                    />
                                    <p
                                        v-if="errors?.sku"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.sku }}
                                    </p>
                                </div>
                                <div class="grid gap-3">
                                    <Label for="barcode">Barcode</Label>
                                    <UnderlineInput
                                        id="barcode"
                                        v-model="formData.barcode"
                                        placeholder="EAN-13 — auto-generated if empty"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-3">
                                <Label for="uom_id">Unit of Measure</Label>
                                <UnderlineSelect
                                    id="uom_id"
                                    v-model="formData.uom_id"
                                >
                                    <option value="">— None —</option>
                                    <option
                                        v-for="uom in formOptions?.uoms"
                                        :key="uom.id"
                                        :value="uom.id"
                                    >
                                        {{ uom.name
                                        }}{{
                                            uom.symbol ? ` (${uom.symbol})` : ""
                                        }}
                                    </option>
                                </UnderlineSelect>
                            </div>

                            <div class="grid gap-3">
                                <Label for="description">Description</Label>
                                <UnderlineTextarea
                                    id="description"
                                    v-model="formData.description"
                                    placeholder="Details about this product"
                                    rows="3"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ─── Tab 2: Attributes ────────────────────────────────────── -->
            <TabsContent value="attributes">
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Attributes</CardTitle>
                                <CardDescription
                                    >Define attributes and values to generate
                                    product variants.</CardDescription
                                >
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addAttributeLine"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                Add Attribute
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="attributeLines.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-muted-foreground gap-3"
                        >
                            <Package class="h-10 w-10 opacity-30" />
                            <p class="text-sm">
                                No attributes added yet. Click "Add Attribute"
                                to start.
                            </p>
                        </div>

                        <div v-else class="grid gap-4">
                            <div
                                v-for="(line, idx) in attributeLines"
                                :key="idx"
                                class="rounded-md border p-4 grid gap-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 grid gap-1.5">
                                        <Label :for="`attr-${idx}`"
                                            >Attribute</Label
                                        >
                                        <UnderlineSelect
                                            :id="`attr-${idx}`"
                                            v-model="line.attribute_id"
                                            @change="line.values = []"
                                        >
                                            <option value="">
                                                — Select attribute —
                                            </option>
                                            <option
                                                v-for="attr in formOptions?.attributes"
                                                :key="attr.id"
                                                :value="attr.id"
                                                :disabled="
                                                    attributeLines.some(
                                                        (l, i) =>
                                                            i !== idx &&
                                                            String(
                                                                l.attribute_id,
                                                            ) ===
                                                                String(attr.id),
                                                    )
                                                "
                                            >
                                                {{ attr.name }}
                                            </option>
                                        </UnderlineSelect>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="mt-6 text-destructive hover:text-destructive"
                                        @click="removeAttributeLine(idx)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>

                                <!-- Values selection -->
                                <div v-if="line.attribute_id !== ''">
                                    <Label
                                        class="text-xs text-muted-foreground mb-2 block"
                                        >Values</Label
                                    >
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="val in getAttributeById(
                                                line.attribute_id,
                                            )?.values ?? []"
                                            :key="val.id"
                                            type="button"
                                            :class="[
                                                'px-3 py-1.5 rounded-full text-sm border transition-colors',
                                                line.values.includes(val.value)
                                                    ? 'bg-primary text-primary-foreground border-primary'
                                                    : 'bg-background hover:bg-muted border-input',
                                            ]"
                                            @click="toggleValue(idx, val.value)"
                                        >
                                            {{ val.value }}
                                        </button>
                                        <p
                                            v-if="
                                                !getAttributeById(
                                                    line.attribute_id,
                                                )?.values?.length
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            No values configured for this
                                            attribute.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Variant preview -->
                            <div
                                v-if="generatedVariantNames.length > 0"
                                class="rounded-md border border-dashed p-4"
                            >
                                <p
                                    class="text-xs font-medium text-muted-foreground mb-2"
                                >
                                    {{ generatedVariantNames.length }}
                                    variant(s) will be generated:
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="name in generatedVariantNames"
                                        :key="name"
                                        class="inline-flex items-center rounded-md bg-muted px-2.5 py-1 text-xs font-medium"
                                    >
                                        {{ name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ─── Tab 3: Variants (read-only) ─────────────────────────── -->
            <TabsContent value="variants">
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <CardTitle>Variants</CardTitle>
                                <CardDescription
                                    >All generated variants and their current
                                    stock.</CardDescription
                                >
                            </div>
                            <!-- Warehouse selector -->
                            <div class="shrink-0">
                                <UnderlineSelect
                                    v-model="selectedWarehouseId"
                                    class="w-auto h-9"
                                >
                                    <option value="">All Warehouses</option>
                                    <option
                                        v-for="wh in formOptions?.warehouses"
                                        :key="wh.id"
                                        :value="wh.id"
                                    >
                                        {{ wh.name }}
                                    </option>
                                </UnderlineSelect>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="!isEditing"
                            class="text-center py-12 text-muted-foreground text-sm"
                        >
                            Save the product first to see its variants.
                        </div>
                        <div
                            v-else-if="variants.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-muted-foreground gap-3"
                        >
                            <Package class="h-10 w-10 opacity-30" />
                            <p class="text-sm">
                                No variants yet. Add attributes and save to
                                generate them.
                            </p>
                        </div>
                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Variant</TableHead>
                                    <TableHead>SKU</TableHead>
                                    <TableHead>Barcode</TableHead>
                                    <TableHead class="text-right"
                                        >Cost</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Price</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Stock</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="v in variants" :key="v.id">
                                    <TableCell class="font-medium">
                                        {{ getVariantLabel(v) }}
                                        <span
                                            v-if="v.is_principal"
                                            class="ml-1.5 text-xs text-muted-foreground"
                                            >(principal)</span
                                        >
                                    </TableCell>
                                    <TableCell class="font-mono text-xs">{{
                                        v.sku ?? "-"
                                    }}</TableCell>
                                    <TableCell class="font-mono text-xs">{{
                                        v.barcode ?? "-"
                                    }}</TableCell>
                                    <TableCell class="text-right">{{
                                        formatMoney(v.cost_price)
                                    }}</TableCell>
                                    <TableCell class="text-right">{{
                                        formatMoney(v.price)
                                    }}</TableCell>
                                    <TableCell class="text-right">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-xs font-medium',
                                                getVariantStock(v) > 0
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-700',
                                            ]"
                                        >
                                            {{ getVariantStock(v) }}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ─── Tab 4: Lots (read-only, only when tracked_by_lot) ───── -->
            <TabsContent
                v-if="formData.tracked_by_lot"
                value="lots"
            >
                <div
                    v-if="!isEditing"
                    class="text-center py-12 text-muted-foreground text-sm border rounded-md"
                >
                    Save the product first to see its lots.
                </div>
                <ProductLotsTab
                    v-else
                    :product-name="formData.name"
                    :variants="variants"
                    :warehouses="formOptions?.warehouses"
                />
            </TabsContent>
        </Tabs>

        <button type="submit" class="hidden"></button>

        <!-- Dialog: Category (create / edit) -->
        <CreateDialog
            :open="categoryDialog.isOpen.value"
            :loading="categoryDialog.isLoading.value"
            :title="categoryDialog.title.value"
            @close="categoryDialog.close()"
            @save="categoryFormRef?.submit()"
        >
            <CategoryForm
                ref="categoryFormRef"
                :is-editing="categoryDialog.isEditing.value"
                :initial-data="categoryDialog.isEditing.value
                    ? categoryDialog.initialData.value
                    : { name: categoryDialog.initialName.value }"
                :parent-options="(formOptions?.categories as any)"
                :errors="categoryDialog.errors.value"
                @submit="onCategorySubmit"
            />
        </CreateDialog>
    </form>
</template>
