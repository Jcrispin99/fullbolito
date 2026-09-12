<script setup lang="ts">
import { ref, watch, computed, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineSelect } from "@/components/ui/underline-select";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { MultiSelect } from "@/components/ui/multi-select";
import { SearchSelect } from "@/components/ui/search-select";
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

const { t } = useI18n();

interface RuleForm {
    reward_point_amount: number | string;
    reward_point_mode: string;
    minimum_amount: number | string;
    minimum_qty: number | string;
    code: string;
    product_variant_ids: number[];
    product_template_ids: number[];
    category_ids: number[];
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
    discount_product_ids: number[];
    discount_category_ids: number[];
}

interface MultiSelectOption {
    value: number;
    label: string;
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
const categoryOptions = ref<MultiSelectOption[]>([]);

// Product variants/templates son catálogos potencialmente enormes (50k+), así
// que no se precargan: se buscan en el servidor a medida que el usuario
// escribe. `variantKnownLabels`/`templateKnownLabels` conservan el nombre de
// los ids ya guardados en el programa para que sus chips se vean bien aunque
// todavía no coincidan con la búsqueda actual.
const variantSearchResults = ref<MultiSelectOption[]>([]);
const templateSearchResults = ref<MultiSelectOption[]>([]);
const variantKnownLabels = ref<Map<number, string>>(new Map());
const templateKnownLabels = ref<Map<number, string>>(new Map());
const isLoadingVariants = ref(false);
const isLoadingTemplates = ref(false);

const mergeKnownOptions = (
    searchResults: MultiSelectOption[],
    knownLabels: Map<number, string>,
    selectedIds: Iterable<number>,
): MultiSelectOption[] => {
    const map = new Map<number, MultiSelectOption>();
    for (const opt of searchResults) map.set(opt.value, opt);
    for (const id of selectedIds) {
        if (!map.has(id) && knownLabels.has(id)) {
            map.set(id, { value: id, label: knownLabels.get(id)! });
        }
    }
    return [...map.values()];
};

const allSelectedVariantIds = computed(() => {
    const ids = new Set<number>();
    rules.value.forEach((r) => r.product_variant_ids.forEach((id) => ids.add(id)));
    rewards.value.forEach((rw) => rw.discount_product_ids.forEach((id) => ids.add(id)));
    return ids;
});

const allSelectedTemplateIds = computed(() => {
    const ids = new Set<number>();
    rules.value.forEach((r) => r.product_template_ids.forEach((id) => ids.add(id)));
    return ids;
});

const variantOptions = computed(() =>
    mergeKnownOptions(variantSearchResults.value, variantKnownLabels.value, allSelectedVariantIds.value),
);

const templateOptions = computed(() =>
    mergeKnownOptions(templateSearchResults.value, templateKnownLabels.value, allSelectedTemplateIds.value),
);

const onVariantSearch = async (term: string) => {
    isLoadingVariants.value = true;
    try {
        const results = await productProductStore.searchProductProducts(term, 20);
        variantSearchResults.value = Array.isArray(results)
            ? results.map((item: any) => ({
                  value: item.id,
                  label: item.display_name || item.name || `Producto ${item.id}`,
              }))
            : [];
    } finally {
        isLoadingVariants.value = false;
    }
};

const onTemplateSearch = async (term: string) => {
    isLoadingTemplates.value = true;
    try {
        const results = await productTemplateStore.searchProductTemplates(term, 20);
        templateSearchResults.value = Array.isArray(results)
            ? results.map((item: any) => ({ value: item.id, label: item.name }))
            : [];
    } finally {
        isLoadingTemplates.value = false;
    }
};

const rememberLabels = (
    knownLabels: Map<number, string>,
    items?: { id: number; name: string }[],
) => {
    (items ?? []).forEach((item) => knownLabels.set(item.id, item.name));
};

// El selector único "Reward Product" reutiliza el mismo caché de búsqueda de
// variantes (`variantOptions`/`onVariantSearch`), pero SearchSelect emite
// "search" en cada tecla sin debounce propio, así que se debounce aquí.
let rewardProductSearchTimer: ReturnType<typeof setTimeout> | undefined;
const onRewardProductSearch = (term: string) => {
    clearTimeout(rewardProductSearchTimer);
    const q = term.trim();
    if (!q) return;
    rewardProductSearchTimer = setTimeout(() => onVariantSearch(q), 300);
};

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

const toIdArray = (items?: number[]) => (Array.isArray(items) ? items : []);

const loadSelectionOptions = async () => {
    // Categorías es un catálogo acotado (a diferencia de variantes/plantillas
    // de producto, que pueden tener decenas de miles de filas): se precarga
    // completa para el selector.
    await categoryStore.fetchCategories(1, "total", "", "active");

    categoryOptions.value = categoryStore.categories.map((item) => ({
        value: item.id,
        label: item.full_name || item.name,
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
                rules.value = newData.rules.map((r: LoyaltyRule) => {
                    rememberLabels(variantKnownLabels.value, r.product_variants);
                    rememberLabels(templateKnownLabels.value, r.product_templates);
                    return {
                        reward_point_amount: r.reward_point_amount ?? "",
                        reward_point_mode: r.reward_point_mode ?? "order",
                        minimum_amount: r.minimum_amount ?? "",
                        minimum_qty: r.minimum_qty ?? "",
                        code: r.code ?? "",
                        product_variant_ids: toIdArray(r.product_variant_ids),
                        product_template_ids: toIdArray(r.product_template_ids),
                        category_ids: toIdArray(r.category_ids),
                    };
                });
            } else {
                rules.value = [];
            }

            if (newData.rewards && newData.rewards.length > 0) {
                rewards.value = newData.rewards.map((rw: LoyaltyReward) => {
                    rememberLabels(variantKnownLabels.value, rw.discount_products);
                    if (rw.reward_product) {
                        variantKnownLabels.value.set(rw.reward_product.id, rw.reward_product.name);
                    }
                    return {
                        reward_type: rw.reward_type ?? "discount",
                        required_points: rw.required_points ?? "",
                        description: rw.description ?? "",
                        discount: rw.discount ?? "",
                        discount_mode: rw.discount_mode ?? "percent",
                        discount_applicability: rw.discount_applicability ?? "order",
                        discount_max_amount: rw.discount_max_amount ?? "",
                        reward_product_id: rw.reward_product_id ?? "",
                        reward_product_qty: rw.reward_product_qty ?? 1,
                        discount_product_ids: toIdArray(rw.discount_product_ids),
                        discount_category_ids: toIdArray(rw.discount_category_ids),
                    };
                });
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
            product_variant_ids: r.product_variant_ids,
            product_template_ids: r.product_template_ids,
            category_ids: r.category_ids,
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
                    ? rw.discount_product_ids
                    : [],
            discount_category_ids:
                rw.reward_type === "discount" && rw.discount_applicability === "specific"
                    ? rw.discount_category_ids
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
                <CornerRibbon v-if="archived" :label="t('loyalty.programs.form.inactive')" tone="danger" />

                <CardContent class="pt-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Name -->
                        <div class="grid gap-3">
                            <Label for="name"
                                >{{ t('loyalty.programs.form.name') }}
                                <span class="text-destructive">*</span></Label
                            >
                            <UnderlineInput
                                id="name"
                                v-model="formData.name"
                                type="text"
                                :placeholder="t('loyalty.programs.form.namePlaceholder')"
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
                                >{{ t('loyalty.programs.form.programType') }}
                                <span class="text-destructive">*</span></Label
                            >
                            <UnderlineSelect
                                id="program_type"
                                v-model="formData.program_type"
                                required
                            >
                                <option value="">{{ t('loyalty.programs.form.selectType') }}</option>
                                <option value="promotion">{{ t('loyalty.programs.form.typePromotion') }}</option>
                                <option value="coupon">{{ t('loyalty.programs.form.typeCoupon') }}</option>
                                <option value="loyalty">{{ t('loyalty.programs.form.typeLoyalty') }}</option>
                                <option value="buy_x_get_y">{{ t('loyalty.programs.form.typeBuyXGetY') }}</option>
                                <option value="promo_code">{{ t('loyalty.programs.form.typePromoCode') }}</option>
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
                            <Label>{{ t('loyalty.programs.form.applyIn') }}</Label>
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
                                    {{ t('loyalty.programs.form.channelSales') }}
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
                            <Label for="trigger">{{ t('loyalty.programs.form.trigger') }}</Label>
                            <UnderlineSelect
                                id="trigger"
                                v-model="formData.trigger"
                            >
                                <option value="auto">{{ t('loyalty.programs.form.triggerAuto') }}</option>
                                <option value="with_code">{{ t('loyalty.programs.form.triggerWithCode') }}</option>
                            </UnderlineSelect>
                            <p
                                v-if="errors?.trigger"
                                class="text-sm text-destructive"
                            >
                                {{ errors.trigger }}
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="grid gap-3 md:col-span-2">
                            <Label for="description">{{ t('loyalty.programs.form.description') }}</Label>
                            <UnderlineTextarea
                                id="description"
                                v-model="formData.description"
                                :placeholder="t('loyalty.programs.form.descriptionPlaceholder')"
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
                        <div class="grid gap-3">
                            <Label for="starts_at">{{ t('loyalty.programs.form.startsAt') }}</Label>
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
                            <Label for="ends_at">{{ t('loyalty.programs.form.endsAt') }}</Label>
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

                        <!-- Max Uses -->
                        <div class="grid gap-3">
                            <Label for="max_uses">{{ t('loyalty.programs.form.maxUses') }}</Label>
                            <UnderlineInput
                                id="max_uses"
                                v-model="formData.max_uses"
                                type="number"
                                min="0"
                                :placeholder="t('loyalty.programs.form.unlimitedPlaceholder')"
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
                                >{{ t('loyalty.programs.form.maxUsesPerCustomer') }}</Label
                            >
                            <UnderlineInput
                                id="max_uses_per_customer"
                                v-model="formData.max_uses_per_customer"
                                type="number"
                                min="0"
                                :placeholder="t('loyalty.programs.form.unlimitedPlaceholder')"
                            />
                            <p
                                v-if="errors?.max_uses_per_customer"
                                class="text-sm text-destructive"
                            >
                                {{ errors.max_uses_per_customer }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Cards 2 & 3 - Rules & Rewards side by side -->
            <div class="grid gap-6 lg:grid-cols-2 items-start">
            <!-- Card 2 - Rules -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium">{{ t('loyalty.programs.form.rulesTitle') }}</h3>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addRule"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            {{ t('loyalty.programs.form.addRule') }}
                        </Button>
                    </div>

                    <div v-if="rules.length === 0" class="text-sm text-muted-foreground text-center py-6">
                        {{ t('loyalty.programs.form.noRulesYet') }}
                    </div>

                    <div class="grid gap-4">
                        <div
                            v-for="(rule, rIdx) in rules"
                            :key="rIdx"
                            class="rounded-md border p-4 space-y-4"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-muted-foreground">{{ t('loyalty.programs.form.ruleLabel', { n: rIdx + 1 }) }}</span>
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
                                        >{{ t('loyalty.programs.form.rewardPointAmount') }}</Label
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
                                        >{{ t('loyalty.programs.form.rewardPointMode') }}</Label
                                    >
                                    <UnderlineSelect
                                        :id="`rule_${rIdx}_reward_point_mode`"
                                        v-model="rule.reward_point_mode"
                                    >
                                        <option value="order">{{ t('loyalty.programs.form.perOrder') }}</option>
                                        <option value="money">{{ t('loyalty.programs.form.perMoneySpent') }}</option>
                                        <option value="unit">{{ t('loyalty.programs.form.perUnit') }}</option>
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
                                        >{{ t('loyalty.programs.form.minimumAmount') }}</Label
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
                                        >{{ t('loyalty.programs.form.minimumQty') }}</Label
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
                                <Label :for="`rule_${rIdx}_code`">{{ t('loyalty.programs.form.code') }}</Label>
                                <UnderlineInput
                                    :id="`rule_${rIdx}_code`"
                                    v-model="rule.code"
                                    type="text"
                                    :placeholder="t('loyalty.programs.form.codePlaceholder')"
                                />
                                <p
                                    v-if="errors?.[`rules.${rIdx}.code`]"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors[`rules.${rIdx}.code`] }}
                                </p>
                            </div>

                            <div class="grid gap-3">
                                <Label>{{ t('loyalty.programs.form.productVariants') }}</Label>
                                <MultiSelect
                                    v-model="rule.product_variant_ids"
                                    :options="variantOptions"
                                    remote
                                    :loading="isLoadingVariants"
                                    :placeholder="t('loyalty.programs.form.selectVariants')"
                                    :search-placeholder="t('loyalty.programs.form.searchProduct')"
                                    :empty-message="t('loyalty.programs.form.noResults')"
                                    @search="onVariantSearch"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ t('loyalty.programs.form.variantsHelp') }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-3">
                                    <Label>{{ t('loyalty.programs.form.productTemplates') }}</Label>
                                    <MultiSelect
                                        v-model="rule.product_template_ids"
                                        :options="templateOptions"
                                        remote
                                        :loading="isLoadingTemplates"
                                        :placeholder="t('loyalty.programs.form.selectTemplates')"
                                        :search-placeholder="t('loyalty.programs.form.searchTemplate')"
                                        :empty-message="t('loyalty.programs.form.noResults')"
                                        @search="onTemplateSearch"
                                    />
                                </div>
                                <div class="grid gap-3">
                                    <Label>{{ t('loyalty.programs.form.categories') }}</Label>
                                    <MultiSelect
                                        v-model="rule.category_ids"
                                        :options="categoryOptions"
                                        :placeholder="t('loyalty.programs.form.selectCategories')"
                                        :search-placeholder="t('loyalty.programs.form.searchCategory')"
                                        :empty-message="t('loyalty.programs.form.noCategoriesAvailable')"
                                    />
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
                        <h3 class="text-lg font-medium">{{ t('loyalty.programs.form.rewardsTitle') }}</h3>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addReward"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            {{ t('loyalty.programs.form.addReward') }}
                        </Button>
                    </div>

                    <div v-if="rewards.length === 0" class="text-sm text-muted-foreground text-center py-6">
                        {{ t('loyalty.programs.form.noRewardsYet') }}
                    </div>

                    <div class="grid gap-4">
                        <div
                            v-for="(reward, rwIdx) in rewards"
                            :key="rwIdx"
                            class="rounded-md border p-4 space-y-4"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-muted-foreground">{{ t('loyalty.programs.form.rewardLabel', { n: rwIdx + 1 }) }}</span>
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
                                        >{{ t('loyalty.programs.form.rewardType') }}</Label
                                    >
                                    <UnderlineSelect
                                        :id="`reward_${rwIdx}_reward_type`"
                                        v-model="reward.reward_type"
                                    >
                                        <option value="discount">{{ t('loyalty.programs.form.typeDiscount') }}</option>
                                        <option value="product">{{ t('loyalty.programs.form.typeProduct') }}</option>
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
                                        >{{ t('loyalty.programs.form.requiredPoints') }}</Label
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
                                            >{{ t('loyalty.programs.form.discountFieldLabel') }}</Label
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
                                            >{{ t('loyalty.programs.form.discountModeLabel') }}</Label
                                        >
                                        <UnderlineSelect
                                            :id="`reward_${rwIdx}_discount_mode`"
                                            v-model="reward.discount_mode"
                                        >
                                            <option value="percent">{{ t('loyalty.programs.form.percent') }}</option>
                                            <option value="fixed_amount">{{ t('loyalty.programs.form.fixedAmount') }}</option>
                                            <option value="per_point">{{ t('loyalty.programs.form.perPoint') }}</option>
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
                                            >{{ t('loyalty.programs.form.discountApplicability') }}</Label
                                        >
                                        <UnderlineSelect
                                            :id="`reward_${rwIdx}_discount_applicability`"
                                            v-model="reward.discount_applicability"
                                        >
                                            <option value="order">{{ t('loyalty.programs.form.wholeOrder') }}</option>
                                            <option value="cheapest">{{ t('loyalty.programs.form.cheapestProduct') }}</option>
                                            <option value="specific">{{ t('loyalty.programs.form.specificProducts') }}</option>
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
                                            >{{ t('loyalty.programs.form.discountMaxAmount') }}</Label
                                        >
                                        <UnderlineInput
                                            :id="`reward_${rwIdx}_discount_max_amount`"
                                            v-model="reward.discount_max_amount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :placeholder="t('loyalty.programs.form.noLimitPlaceholder')"
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
                                        <Label>{{ t('loyalty.programs.form.specificProducts') }}</Label>
                                        <MultiSelect
                                            v-model="reward.discount_product_ids"
                                            :options="variantOptions"
                                            remote
                                            :loading="isLoadingVariants"
                                            :placeholder="t('loyalty.programs.form.selectProducts')"
                                            :search-placeholder="t('loyalty.programs.form.searchProduct')"
                                            :empty-message="t('loyalty.programs.form.noResults')"
                                            @search="onVariantSearch"
                                        />
                                    </div>
                                    <div class="grid gap-3">
                                        <Label>{{ t('loyalty.programs.form.specificCategories') }}</Label>
                                        <MultiSelect
                                            v-model="reward.discount_category_ids"
                                            :options="categoryOptions"
                                            :placeholder="t('loyalty.programs.form.selectCategories')"
                                            :search-placeholder="t('loyalty.programs.form.searchCategory')"
                                            :empty-message="t('loyalty.programs.form.noCategoriesAvailable')"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- Product fields -->
                            <template v-if="reward.reward_type === 'product'">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-3">
                                        <Label :for="`reward_${rwIdx}_reward_product_id`"
                                            >{{ t('loyalty.programs.form.rewardProduct') }}</Label
                                        >
                                        <SearchSelect
                                            :id="`reward_${rwIdx}_reward_product_id`"
                                            v-model="reward.reward_product_id"
                                            :options="variantOptions"
                                            :placeholder="t('loyalty.programs.form.searchProduct')"
                                            @search="onRewardProductSearch"
                                        />
                                        <p
                                            v-if="errors?.[`rewards.${rwIdx}.reward_product_id`]"
                                            class="text-sm text-destructive"
                                        >
                                            {{ errors[`rewards.${rwIdx}.reward_product_id`] }}
                                        </p>
                                    </div>
                                    <div class="grid gap-3">
                                        <Label :for="`reward_${rwIdx}_reward_product_qty`"
                                            >{{ t('loyalty.programs.form.rewardProductQty') }}</Label
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
                                    >{{ t('loyalty.programs.form.description') }}</Label
                                >
                                <UnderlineInput
                                    :id="`reward_${rwIdx}_description`"
                                    v-model="reward.description"
                                    type="text"
                                    :placeholder="t('loyalty.programs.form.rewardDescriptionPlaceholder')"
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
            </div>

            <!-- Hidden submit trigger -->
            <button type="submit" class="hidden"></button>
        </div>
    </form>
</template>
