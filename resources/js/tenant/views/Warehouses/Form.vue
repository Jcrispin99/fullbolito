<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type { Warehouse } from "@tenant/stores/warehouse";
import { UnderlineInput } from "@/components/ui/underline-input";
import { SearchSelect } from "@/components/ui/search-select";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<Warehouse>;
    formOptions?: { companies: any[] };
    isLoading?: boolean;
    errors?: Record<string, string>;
    archived?: boolean;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const { t } = useI18n();

const form = ref({
    name: "",
    location: "",
    company_id: undefined as number | undefined,
    is_active: true,
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            form.value = {
                name: newData.name || "",
                location: newData.location || "",
                company_id: newData.company_id || undefined,
                is_active: newData.is_active !== undefined ? newData.is_active : true,
            };
        }
    },
    { immediate: true },
);

const companyOptions = computed(() =>
    (props.formOptions?.companies || []).map((c: any) => ({
        value: c.id,
        label: c.trade_name || c.business_name || c.ruc || `#${c.id}`,
        description: c.ruc || null,
    })),
);

const submit = () => {
    emit("submit", form.value);
};

defineExpose({ submit });
</script>

<template>
    <Card class="w-full relative overflow-hidden">
        <CornerRibbon v-if="archived" :label="t('warehouses.form.inactive')" tone="danger" />
        <CardContent class="pt-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Row 1 -->
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="space-y-2 md:col-span-4">
                        <Label htmlFor="name">{{ t('warehouses.form.name') }}</Label>
                        <UnderlineInput
                            id="name"
                            v-model="form.name"
                            :placeholder="t('warehouses.form.namePlaceholder')"
                            required
                        />
                        <p v-if="errors?.name" class="text-sm text-destructive">
                            {{ errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2 md:col-span-3">
                        <Label htmlFor="location">{{ t('warehouses.form.location') }}</Label>
                        <UnderlineInput
                            id="location"
                            v-model="form.location"
                            :placeholder="t('warehouses.form.locationPlaceholder')"
                        />
                        <p v-if="errors?.location" class="text-sm text-destructive">
                            {{ errors.location }}
                        </p>
                    </div>

                    <div class="space-y-2 md:col-span-1">
                        <Label htmlFor="company_id">{{ t('warehouses.form.company') }}</Label>
                        <SearchSelect
                            id="company_id"
                            v-model="form.company_id"
                            :options="companyOptions"
                            :placeholder="t('common.search')"
                        />
                        <p v-if="errors?.company_id" class="text-sm text-destructive">
                            {{ errors.company_id }}
                        </p>
                    </div>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
