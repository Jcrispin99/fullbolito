<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type { Company } from "@/types/models";
import { SearchSelect } from "@/components/ui/search-select";
import { UnderlineInput } from "@/components/ui/underline-input";
import { Checkbox } from "@/components/ui/checkbox";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<Company>;
    formOptions?: any;
    isLoading?: boolean;
    errors?: Record<string, string>;
    archived?: boolean;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const form = ref({
    business_name: "",
    trade_name: "",
    ruc: "",
    address: "",
    phone: "",
    email: "",
    ubigeo: "",
    active: true,
    parent_id: undefined as number | undefined,
    branch_code: "",
    is_main: false,
});

watch(
    () => props.initialData,
    (newData) => {
        if (newData) {
            form.value = {
                business_name: newData.business_name || "",
                trade_name: newData.trade_name || "",
                ruc: newData.ruc || "",
                address: newData.address || "",
                phone: newData.phone || "",
                email: newData.email || "",
                ubigeo: newData.ubigeo || "",
                active: newData.active !== undefined ? newData.active : true,
                parent_id: newData.parent_id || undefined,
                branch_code: newData.branch_code || "",
                is_main: newData.is_main || false,
            };
        }
    },
    { immediate: true },
);

const parentCompanyOptions = computed(() => {
    const raw = props.formOptions?.parent_companies || [];
    const currentId = (props.initialData as any)?.id;
    return raw
        .filter((c: any) => (currentId ? String(c.id) !== String(currentId) : true))
        .map((c: any) => ({
            value: c.id,
            label: c.trade_name || c.business_name || c.ruc || `#${c.id}`,
            description: c.ruc || null,
        }));
});

const submit = () => {
    emit("submit", form.value);
};

defineExpose({ submit });
</script>

<template>
    <Card class="w-full relative overflow-hidden">
        <CornerRibbon v-if="archived" label="Inactive" tone="danger" />
        <CardContent class="pt-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Row 1 -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2 md:col-span-2">
                        <Label htmlFor="business_name">Business Name</Label>
                        <UnderlineInput
                            id="business_name"
                            v-model="form.business_name"
                            placeholder="e.g. Acme Corp"
                            required
                        />
                        <p v-if="errors?.business_name" class="text-sm text-destructive">
                            {{ errors.business_name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="trade_name">Trade Name</Label>
                        <UnderlineInput
                            id="trade_name"
                            v-model="form.trade_name"
                            placeholder="e.g. Acme"
                        />
                        <p v-if="errors?.trade_name" class="text-sm text-destructive">
                            {{ errors.trade_name }}
                        </p>
                    </div>
                </div>

                <!-- Row 2 -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="ruc">RUC</Label>
                        <UnderlineInput
                            id="ruc"
                            v-model="form.ruc"
                            placeholder="e.g. 20123456789"
                            required
                        />
                        <p v-if="errors?.ruc" class="text-sm text-destructive">
                            {{ errors.ruc }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="email">Email</Label>
                        <UnderlineInput
                            id="email"
                            type="email"
                            v-model="form.email"
                            placeholder="e.g. contact@acme.com"
                        />
                        <p v-if="errors?.email" class="text-sm text-destructive">
                            {{ errors.email }}
                        </p>
                    </div>
                </div>

                <!-- Row 3 -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="phone">Phone</Label>
                        <UnderlineInput
                            id="phone"
                            v-model="form.phone"
                            placeholder="e.g. +51 987654321"
                        />
                        <p v-if="errors?.phone" class="text-sm text-destructive">
                            {{ errors.phone }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="ubigeo">Ubigeo</Label>
                        <UnderlineInput
                            id="ubigeo"
                            v-model="form.ubigeo"
                            placeholder="e.g. 150101"
                        />
                        <p v-if="errors?.ubigeo" class="text-sm text-destructive">
                            {{ errors.ubigeo }}
                        </p>
                    </div>
                </div>

                <!-- Row 4 -->
                <div class="space-y-2">
                    <Label htmlFor="address">Address</Label>
                    <UnderlineInput
                        id="address"
                        v-model="form.address"
                        placeholder="e.g. Av. Build 123"
                    />
                    <p v-if="errors?.address" class="text-sm text-destructive">
                        {{ errors.address }}
                    </p>
                </div>

                <!-- Row 5 -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="branch_code">Branch Code</Label>
                        <UnderlineInput
                            id="branch_code"
                            v-model="form.branch_code"
                            placeholder="e.g. 000"
                        />
                        <p v-if="errors?.branch_code" class="text-sm text-destructive">
                            {{ errors.branch_code }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="parent_id">Parent Company</Label>
                        <SearchSelect
                            id="parent_id"
                            v-model="form.parent_id"
                            :options="parentCompanyOptions"
                            placeholder="Buscar..."
                        />
                        <p v-if="errors?.parent_id" class="text-sm text-destructive">
                            {{ errors.parent_id }}
                        </p>
                    </div>
                </div>

                <!-- Row 6 -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="is_main">Main Office</Label>
                        <div class="flex items-center gap-2 pt-2">
                            <Checkbox
                                id="is_main"
                                v-model:checked="form.is_main"
                            />
                            <Label
                                htmlFor="is_main"
                                class="text-sm text-muted-foreground"
                            >
                                Yes
                            </Label>
                        </div>
                        <p v-if="errors?.is_main" class="text-sm text-destructive">
                            {{ errors.is_main }}
                        </p>
                    </div>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
