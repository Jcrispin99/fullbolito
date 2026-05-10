<script setup lang="ts">
import { ref, watch, computed, onMounted } from "vue";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineSelect } from "@/components/ui/underline-select";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import { Plus, Trash2 } from "lucide-vue-next";
import type { LoyaltyProgram, LoyaltyRule, LoyaltyReward } from "@tenant/stores/loyaltyProgram";
import { useProductProductStore } from "@tenant/stores/productProduct";
import { useProductTemplateStore } from "@tenant/stores/productTemplate";
import { useCategoryStore } from "@tenant/stores/category";

const props = defineProps<{
    initialData?: Partial<LoyaltyProgram>;
    isEditing?: boolean;
    archived?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", payload: any): void;
}>();

interface RuleForm {
    reward_point_amount: number | string;
    reward_point_mode: string;
    minimum_amount: number | string;
    minimum_qty: number | string;
    code: string;
    product_variant_ids: string[];
    product_template_ids: string[];
    category_ids: string[];
}

interface RewardForm {
    reward_type: string;
    required_points: number | string;
    description: string;
    discount: number | string;
    discount_mode: string;
    discount_applicability: string;
    discount_max_amount: number | string;
    reward_product_id: number | string;
    reward_product_qty: number | string;
    discount_product_ids: string[];
    discount_category_ids: string[];
}

interface SelectOption {
    id: number;
    name: string;
}

const formData = ref({
    name: "",
    program_type: "",
    is_pos: false,
    is_web: false,
    is_sales: false,
    trigger: "auto",
    description: "",
    starts_at: "",
    ends_at: "",
    max_uses: "" as number | string,
    max_uses_per_customer: "" as number | string,
});

const rules = ref<RuleForm[]>([]);
const rewards = ref<RewardForm[]>([]);

const showCode = computed(() => formData.value.trigger === "with_code");
const productProductStore = useProductProductStore();
const productTemplateStore = useProductTemplateStore();
const categoryStore = useCategoryStore();
const variantOptions = ref<SelectOption[]>([]);
const templateOptions = ref<SelectOption[]>([]);
const categoryOptions = ref<SelectOption[]>([]);

const createEmptyRule = (): RuleForm => ({
    reward_point_amount: "",
    reward_point_mode: "order",
    minimum_amount: "",
    minimum_qty: "",
    code: "",
    product_variant_ids: [],
    product_template_ids: [],
    category_ids: [],
});

const createEmptyReward = (): RewardForm => ({
    reward_type: "discount",
    required_points: "",
    description: "",
    discount: "",
    discount_mode: "percent",
    discount_applicability: "order",
    discount_max_amount: "",
    reward_product_id: "",
    reward_product_qty: 1,
    discount_product_ids: [],
    discount_category_ids: [],
});

const mapIdsToStrings = (items?: number[]) =>
    Array.isArray(items) ? items.map((item) => String(item)) : [];

const loadSelectionOptions = async () => {
    const [variants, _, __] = await Promise.all([
        productProductStore.searchProductProducts("", 200),
        productTemplateStore.fetchProducts(1, "total", "", "active"),
        categoryStore.fetchCategories(1, "total", "", "active"),
    ]);

    variantOptions.value = Array.isArray(variants)
        ? variants.map((item: any) => ({
              id: item.id,
              name: item.display_name || item.name || `Producto ${item.id}`,
          }))
        : [];

    templateOptions.value = productTemplateStore.products.map((item) => ({
        id: item.id,
        name: item.name,
    }));

    categoryOptions.value = categoryStore.categories.map((item) => ({
        id: item.id,
        name: item.full_name || item.name,
    }));
};

const addRule = () => {
    rules.value.push(createEmptyRule());
};

const removeRule = (index: number) => {
    rules.value.splice(index, 1);
};

const addReward = () => {
    rewards.value.push(createEmptyReward());
};

const removeReward = (index: number) => {
    rewards.value.splice(index, 1);
};

const formatDateForInput = (iso: string | null | undefined): string => {
    if (!iso) return "";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "";
    const pad = (n: number) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            formData.value = {
                name: newData.name ?? "",
                program_type: newData.program_type ?? "",
                is_pos: newData.is_pos ?? false,
                is_web: newData.is_web ?? false,
                is_sales: newData.is_sales ?? false,
                trigger: newData.trigger ?? "auto",
                description: newData.description ?? "",
                starts_at: formatDateForInput(newData.starts_at),
                ends_at: formatDateForInput(newData.ends_at),
                max_uses: newData.max_uses ?? "",
                max_uses_per_customer: newData.max_uses_per_customer ?? "",
            };

            if (newData.rules && newData.rules.length > 0) {
                rules.value = newData.rules.map((r: LoyaltyRule) => ({
                    reward_point_amount: r.reward_point_amount ?? "",
                    reward_point_mode: r.reward_point_mode ?? "order",
                    minimum_amount: r.minimum_amount ?? "",
                    minimum_qty: r.minimum_qty ?? "",
                    code: r.code ?? "",
                    product_variant_ids: mapIdsToStrings(r.product_variant_ids),
                    product_template_ids: mapIdsToStrings(r.product_template_ids),
                    category_ids: mapIdsToStrings(r.category_ids),
                }));
            } else {
                rules.value = [];
            }

            if (newData.rewards && newData.rewards.length > 0) {
                rewards.value = newData.rewards.map((rw: LoyaltyReward) => ({
                    reward_type: rw.reward_type ?? "discount",
                    required_points: rw.required_points ?? "",
                    description: rw.description ?? "",
                    discount: rw.discount ?? "",
                    discount_mode: rw.discount_mode ?? "percent",
                    discount_applicability: rw.discount_applicability ?? "order",
                    discount_max_amount: rw.discount_max_amount ?? "",
                    reward_product_id: rw.reward_product_id ?? "",
                    reward_product_qty: rw.reward_product_qty ?? 1,
                    discount_product_ids: mapIdsToStrings(rw.discount_product_ids),
                    discount_category_ids: mapIdsToStrings(rw.discount_category_ids),
                }));
            } else {
                rewards.value = [];
            }
        }
    },
    { immediate: true },
);

onMounted(async () => {
    await loadSelectionOptions();
});

const handleSubmit = () => {
    const payload: Record<string, any> = {
        name: formData.value.name,
        program_type: formData.value.program_type,
        is_pos: formData.value.is_pos,
        is_web: formData.value.is_web,
        is_sales: formData.value.is_sales,
        trigger: formData.value.trigger,
        description: formData.value.description || null,
        starts_at: formData.value.starts_at || null,
        ends_at: formData.value.ends_at || null,
        max_uses: formData.value.max_uses === "" ? null : Number(formData.value.max_uses),
        max_uses_per_customer:
            formData.value.max_uses_per_customer === ""
                ? null
                : Number(formData.value.max_uses_per_customer),
        rules: rules.value.map((r) => ({
            reward_point_amount: r.reward_point_amount === "" ? 0 : Number(r.reward_point_amount),
            reward_point_mode: r.reward_point_mode,
            minimum_amount: r.minimum_amount === "" ? 0 : Number(r.minimum_amount),
            minimum_qty: r.minimum_qty === "" ? 0 : Number(r.minimum_qty),
            code: r.code || null,
            product_variant_ids: r.product_variant_ids.map((id) => Number(id)),
            product_template_ids: r.product_template_ids.map((id) => Number(id)),
            category_ids: r.category_ids.map((id) => Number(id)),
        })),
        rewards: rewards.value.map((rw) => ({
            reward_type: rw.reward_type,
            required_points: rw.required_points === "" ? 0 : Number(rw.required_points),
            description: rw.description || null,
            discount: rw.reward_type === "discount" && rw.discount !== "" ? Number(rw.discount) : null,
            discount_mode: rw.reward_type === "discount" ? rw.discount_mode : null,
            discount_applicability: rw.reward_type === "discount" ? rw.discount_applicability : null,
            discount_max_amount:
                rw.reward_type === "discount" && rw.discount_max_amount !== ""
                    ? Number(rw.discount_max_amount)
                    : null,
            reward_product_id:
                rw.reward_type === "product" && rw.reward_product_id !== ""
                    ? Number(rw.reward_product_id)
                    : null,
            reward_product_qty:
                rw.reward_type === "product"
                    ? Number(rw.reward_product_qty)
                    : 1,
            discount_product_ids:
                rw.reward_type === "discount" && rw.discount_applicability === "specific"
                    ? rw.discount_product_ids.map((id) => Number(id))
                    : [],
            discount_category_ids:
                rw.reward_type === "discount" && rw.discount_applicability === "specific"
                    ? rw.discount_category_ids.map((id) => Number(id))
                    : [],
        })),
    };
    emit("submit", payload);
};

defineExpose({ submit: handleSubmit });
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="grid gap-6">
            <!-- Card 1 - General Info -->
            <Card class="relative overflow-hidden">
                <CornerRibbon v-if="archived" label="Inactive" tone="danger" />

                <CardContent class="pt-6">
                    <div class="grid gap-6">
                        <!-- Name -->
                        <div class="grid gap-3">
                            <Label for="name"
                                >Name
                                <span class="text-destructive">*</span></Label
                            >
                            <UnderlineInput
                                id="name"
                                v-model="formData.name"
                                type="text"
                                placeholder="e.g. Summer Promotion"
                                required
                            />
                            <p
                                v-if="errors?.name"
                                class="text-sm text-destructive"
                            >
                                {{ errors.name }}
                            </p>
                        </div>

                        <!-- Program Type -->
                        <div class="grid gap-3">
                            <Label for="program_type"
                                >Program Type
                                <span class="text-destructive">*</span></Label
                            >
                            <UnderlineSelect
                                id="program_type"
                                v-model="formData.program_type"
                                required
                            >
                                <option value="">-- Select type --</option>
                                <option value="promotion">Promotion</option>
                                <option value="coupon">Coupon</option>
                                <option value="loyalty">Loyalty</option>
                                <option value="buy_x_get_y">Buy X Get Y</option>
                                <option value="promo_code">Promo Code</option>
                            </UnderlineSelect>
                            <p
                                v-if="errors?.program_type"
                                class="text-sm text-destructive"
                            >
                                {{ errors.program_type }}
                            </p>
                        </div>

                        <!-- Modules (channels) -->
                        <div class="grid gap-3">
                            <Label>Aplicar en</Label>
                            <div class="flex flex-wrap items-center gap-6">
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <Checkbox
                                        :checked="formData.is_pos"
                                        @update:checked="(v: boolean) => (formData.is_pos = v)"
                                    />
                                    POS
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <Checkbox
                                        :checked="formData.is_sales"
                                        @update:checked="(v: boolean) => (formData.is_sales = v)"
                                    />
                                    Sales
                                </label>
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <Checkbox
                                        :checked="formData.is_web"
                                        @update:checked="(v: boolean) => (formData.is_web = v)"
                                    />
                                    Web
                                </label>
                            </div>
                            <p
                                v-if="errors?.is_pos || errors?.is_web || errors?.is_sales"
                                class="text-sm text-destructive"
                            >
                                {{ errors.is_pos || errors.is_web || errors.is_sales }}
                            </p>
                        </div>

                        <!-- Trigger -->
                        <div class="grid gap-3">
                            <Label for="trigger">Trigger</Label>
                            <UnderlineSelect
                                id="trigger"
                                v-model="formData.trigger"
                            >
                                <option value="auto">Auto</option>
                                <option value="with_code">With Code</option>
                            </UnderlineSelect>
                            <p
                                v-if="errors?.trigger"
                                class="text-sm text-destructive"
                            >
                                {{ errors.trigger }}
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="grid gap-3">
                            <Label for="description">Description</Label>
                            <UnderlineTextarea
                                id="description"
                                v-model="formData.description"
                                placeholder="Additional details about this program"
                                rows="3"
                            />
                            <p
                                v-if="errors?.description"
                                class="text-sm text-destructive"
                            >
                                {{ errors.description }}
                            </p>
                        </div>

                        <!-- Dates -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="grid gap-3">
                                <Label for="starts_at">Starts At</Label>
                                <UnderlineInput
                                    id="starts_at"
                                    v-model="formData.starts_at"
                                    type="datetime-local"
                                />
                                <p
                                    v-if="errors?.starts_at"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.starts_at }}
                                </p>
                            </div>
                            <div class="grid gap-3">
                                <Label for="ends_at">Ends At</Label>
                                <UnderlineInput
                                    id="ends_at"
                                    v-model="formData.ends_at"
                                    type="datetime-local"
                                />
                                <p
                                    v-if="errors?.ends_at"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.ends_at }}
                                </p>
                            </div>
                        </div>

                        <!-- Max Uses -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="grid gap-3">
                                <Label for="max_uses">Max Uses</Label>
                                <UnderlineInput
                                    id="max_uses"
                                    v-model="formData.max_uses"
                                    type="number"
                                    min="0"
                                    placeholder="Unlimited"
                                />
                                <p
                                    v-if="errors?.max_uses"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.max_uses }}
                                </p>
                            </div>
                            <div class="grid gap-3">
                                <Label for="max_uses_per_customer"
                                    >Max Uses Per Customer</Label
                                >
                                <UnderlineInput
                                    id="max_uses_per_customer"
                                    v-model="formData.max_uses_per_customer"
                                    type="number"
                                    min="0"
                                    placeholder="Unlimited"
                                />
                                <p
                                    v-if="errors?.max_uses_per_customer"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.max_uses_per_customer }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Card 2 - Rules -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium">Rules</h3>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addRule"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            Add Rule
                        </Button>
                    </div>

                    <div v-if="rules.length === 0" class="text-sm text-muted-foreground text-center py-6">
                        No rules added yet. Click "Add Rule" to get started.
                    </div>

                    <div class="grid gap-4">
                        <div
                            v-for="(rule, rIdx) in rules"
                            :key="rIdx"
                            class="rounded-md border p-4 space-y-4"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-muted-foreground">Rule {{ rIdx + 1 }}</span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive"
                                    @click="removeRule(rIdx)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-3">
                                    <Label :for="`rule_${rIdx}_reward_point_amount`"
                                        >Reward Point Amount</Label
                                    >
                                    <UnderlineInput
                                        :id="`rule_${rIdx}_reward_point_amount`"
                                        v-model="rule.reward_point_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                    />
                                    <p
                                        v-if="errors?.[`rules.${rIdx}.reward_point_amount`]"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors[`rules.${rIdx}.reward_point_amount`] }}
                                    </p>
                                </div>
                                <div class="grid gap-3">
                                    <Label :for="`rule_${rIdx}_reward_point_mode`"
                                        >Reward Point Mode</Label
                                    >
                                    <UnderlineSelect
                                        :id="`rule_${rIdx}_reward_point_mode`"
                                        v-model="rule.reward_point_mode"
                                    >
                                        <option value="order">Per Order</option>
                                        <option value="money">Per Money Spent</option>
                                        <option value="unit">Per Unit</option>
                                    </UnderlineSelect>
                                    <p
                                        v-if="errors?.[`rules.${rIdx}.reward_point_mode`]"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors[`rules.${rIdx}.reward_point_mode`] }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-3">
                                    <Label :for="`rule_${rIdx}_minimum_amount`"
                                        >Minimum Amount</Label
                                    >
                                    <UnderlineInput
                                        :id="`rule_${rIdx}_minimum_amount`"
                                        v-model="rule.minimum_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                    />
                                    <p
                                        v-if="errors?.[`rules.${rIdx}.minimum_amount`]"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors[`rules.${rIdx}.minimum_amount`] }}
                                    </p>
                                </div>
                                <div class="grid gap-3">
                                    <Label :for="`rule_${rIdx}_minimum_qty`"
                                        >Minimum Qty</Label
                                    >
                                    <UnderlineInput
                                        :id="`rule_${rIdx}_minimum_qty`"
                                        v-model="rule.minimum_qty"
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                    />
                                    <p
                                        v-if="errors?.[`rules.${rIdx}.minimum_qty`]"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors[`rules.${rIdx}.minimum_qty`] }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="showCode" class="grid gap-3">
                                <Label :for="`rule_${rIdx}_code`">Code</Label>
                                <UnderlineInput
                                    :id="`rule_${rIdx}_code`"
                                    v-model="rule.code"
                                    type="text"
                                    placeholder="e.g. SUMMER2026"
                                />
                                <p
                                    v-if="errors?.[`rules.${rIdx}.code`]"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors[`rules.${rIdx}.code`] }}
                                </p>
                            </div>

                            <div class="grid gap-3">
                                <Label>Product Variants</Label>
                                <select
                                    v-model="rule.product_variant_ids"
                                    multiple
                                    class="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option
                                        v-for="option in variantOptions"
                                        :key="option.id"
                                        :value="String(option.id)"
                                    >
                                        {{ option.name }}
                                    </option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    Si dejas este campo vacio, la regla no se restringe por variante.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-3">
                                    <Label>Product Templates</Label>
                                    <select
                                        v-model="rule.product_template_ids"
                                        multiple
                                        class="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option
                                            v-for="option in templateOptions"
                                            :key="option.id"
                                            :value="String(option.id)"
                                        >
                                            {{ option.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-3">
                                    <Label>Categories</Label>
                                    <select
                                        v-model="rule.category_ids"
                                        multiple
                                        class="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option
                                            v-for="option in categoryOptions"
                                            :key="option.id"
                                            :value="String(option.id)"
                                        >
                                            {{ option.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Card 3 - Rewards -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium">Rewards</h3>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addReward"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            Add Reward
                        </Button>
                    </div>

                    <div v-if="rewards.length === 0" class="text-sm text-muted-foreground text-center py-6">
                        No rewards added yet. Click "Add Reward" to get started.
                    </div>

                    <div class="grid gap-4">
                        <div
                            v-for="(reward, rwIdx) in rewards"
                            :key="rwIdx"
                            class="rounded-md border p-4 space-y-4"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-muted-foreground">Reward {{ rwIdx + 1 }}</span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive"
                                    @click="removeReward(rwIdx)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-3">
                                    <Label :for="`reward_${rwIdx}_reward_type`"
                                        >Reward Type</Label
                                    >
                                    <UnderlineSelect
                                        :id="`reward_${rwIdx}_reward_type`"
                                        v-model="reward.reward_type"
                                    >
                                        <option value="discount">Discount</option>
                                        <option value="product">Product</option>
                                    </UnderlineSelect>
                                    <p
                                        v-if="errors?.[`rewards.${rwIdx}.reward_type`]"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors[`rewards.${rwIdx}.reward_type`] }}
                                    </p>
                                </div>
                                <div class="grid gap-3">
                                    <Label :for="`reward_${rwIdx}_required_points`"
                                        >Required Points</Label
                                    >
                                    <UnderlineInput
                                        :id="`reward_${rwIdx}_required_points`"
                                        v-model="reward.required_points"
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                    />
                                    <p
                                        v-if="errors?.[`rewards.${rwIdx}.required_points`]"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors[`rewards.${rwIdx}.required_points`] }}
                                    </p>
                                </div>
                            </div>

                            <!-- Discount fields -->
                            <template v-if="reward.reward_type === 'discount'">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-3">
                                        <Label :for="`reward_${rwIdx}_discount`"
                                            >Discount</Label
                                        >
                                        <UnderlineInput
                                            :id="`reward_${rwIdx}_discount`"
                                            v-model="reward.discount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                        />
                                        <p
                                            v-if="errors?.[`rewards.${rwIdx}.discount`]"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors[`rewards.${rwIdx}.discount`] }}
                                        </p>
                                    </div>
                                    <div class="grid gap-3">
                                        <Label :for="`reward_${rwIdx}_discount_mode`"
                                            >Discount Mode</Label
                                        >
                                        <UnderlineSelect
                                            :id="`reward_${rwIdx}_discount_mode`"
                                            v-model="reward.discount_mode"
                                        >
                                            <option value="percent">Percent</option>
                                            <option value="fixed_amount">Fixed Amount</option>
                                            <option value="per_point">Per Point</option>
                                        </UnderlineSelect>
                                        <p
                                            v-if="errors?.[`rewards.${rwIdx}.discount_mode`]"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors[`rewards.${rwIdx}.discount_mode`] }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-3">
                                        <Label :for="`reward_${rwIdx}_discount_applicability`"
                                            >Discount Applicability</Label
                                        >
                                        <UnderlineSelect
                                            :id="`reward_${rwIdx}_discount_applicability`"
                                            v-model="reward.discount_applicability"
                                        >
                                            <option value="order">Whole Order</option>
                                            <option value="cheapest">Cheapest Product</option>
                                            <option value="specific">Specific Products</option>
                                        </UnderlineSelect>
                                        <p
                                            v-if="errors?.[`rewards.${rwIdx}.discount_applicability`]"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors[`rewards.${rwIdx}.discount_applicability`] }}
                                        </p>
                                    </div>
                                    <div class="grid gap-3">
                                        <Label :for="`reward_${rwIdx}_discount_max_amount`"
                                            >Discount Max Amount</Label
                                        >
                                        <UnderlineInput
                                            :id="`reward_${rwIdx}_discount_max_amount`"
                                            v-model="reward.discount_max_amount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="No limit"
                                        />
                                        <p
                                            v-if="errors?.[`rewards.${rwIdx}.discount_max_amount`]"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors[`rewards.${rwIdx}.discount_max_amount`] }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="reward.discount_applicability === 'specific'"
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                >
                                    <div class="grid gap-3">
                                        <Label>Specific Products</Label>
                                        <select
                                            v-model="reward.discount_product_ids"
                                            multiple
                                            class="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        >
                                            <option
                                                v-for="option in variantOptions"
                                                :key="option.id"
                                                :value="String(option.id)"
                                            >
                                                {{ option.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid gap-3">
                                        <Label>Specific Categories</Label>
                                        <select
                                            v-model="reward.discount_category_ids"
                                            multiple
                                            class="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        >
                                            <option
                                                v-for="option in categoryOptions"
                                                :key="option.id"
                                                :value="String(option.id)"
                                            >
                                                {{ option.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </template>

                            <!-- Product fields -->
                            <template v-if="reward.reward_type === 'product'">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-3">
                                        <Label :for="`reward_${rwIdx}_reward_product_id`"
                                            >Reward Product</Label
                                        >
                                        <UnderlineSelect
                                            :id="`reward_${rwIdx}_reward_product_id`"
                                            v-model="reward.reward_product_id"
                                        >
                                            <option value="">-- Select product --</option>
                                            <option
                                                v-for="option in variantOptions"
                                                :key="option.id"
                                                :value="String(option.id)"
                                            >
                                                {{ option.name }}
                                            </option>
                                        </UnderlineSelect>
                                        <p
                                            v-if="errors?.[`rewards.${rwIdx}.reward_product_id`]"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors[`rewards.${rwIdx}.reward_product_id`] }}
                                        </p>
                                    </div>
                                    <div class="grid gap-3">
                                        <Label :for="`reward_${rwIdx}_reward_product_qty`"
                                            >Reward Product Qty</Label
                                        >
                                        <UnderlineInput
                                            :id="`reward_${rwIdx}_reward_product_qty`"
                                            v-model="reward.reward_product_qty"
                                            type="number"
                                            min="1"
                                            placeholder="1"
                                        />
                                        <p
                                            v-if="errors?.[`rewards.${rwIdx}.reward_product_qty`]"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors[`rewards.${rwIdx}.reward_product_qty`] }}
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <!-- Description -->
                            <div class="grid gap-3">
                                <Label :for="`reward_${rwIdx}_description`"
                                    >Description</Label
                                >
                                <UnderlineInput
                                    :id="`reward_${rwIdx}_description`"
                                    v-model="reward.description"
                                    type="text"
                                    placeholder="e.g. 10% off your next order"
                                />
                                <p
                                    v-if="errors?.[`rewards.${rwIdx}.description`]"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors[`rewards.${rwIdx}.description`] }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Hidden submit trigger -->
            <button type="submit" class="hidden"></button>
        </div>
    </form>
</template>
