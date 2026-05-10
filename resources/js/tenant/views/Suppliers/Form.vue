<script setup lang="ts">
import { ref, watch } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import { SearchSelect } from "@/components/ui/search-select";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: any;
    isLoading?: boolean;
    errors?: Record<string, string>;
    archived?: boolean;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

const form = ref({
    name: "",
    document_type: "",
    document_number: "",
    email: "",
    phone: "",
    address: "",
    ubigeo: "",
    payment_terms: "",
    provider_category: "",
    status: "active",
    notes: "",
    is_supplier: true,
    is_customer: false,
});

const documentTypeOptions = [
    { value: "DNI", label: "DNI" },
    { value: "RUC", label: "RUC" },
    { value: "CE", label: "CE" },
    { value: "PASSPORT", label: "Passport" },
];

watch(
    () => props.initialData,
    (newData) => {
        if (newData && Object.keys(newData).length > 0) {
            form.value = {
                name: newData.name || "",
                document_type: newData.document_type || "",
                document_number: newData.document_number || "",
                email: newData.email || "",
                phone: newData.phone || "",
                address: newData.address || "",
                ubigeo: newData.ubigeo || "",
                payment_terms: newData.payment_terms || "",
                provider_category: newData.provider_category || "",
                status: newData.status || "active",
                notes: newData.notes || "",
                is_supplier:
                    newData.is_supplier !== undefined
                        ? newData.is_supplier
                        : true,
                is_customer:
                    newData.is_customer !== undefined
                        ? newData.is_customer
                        : false,
            };
        }
    },
    { immediate: true },
);

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
                    <div class="space-y-2">
                        <Label htmlFor="name"
                            >Supplier Name
                            <span class="text-destructive">*</span></Label
                        >
                        <UnderlineInput
                            id="name"
                            v-model="form.name"
                            placeholder="e.g. Acme Corp"
                            required
                        />
                        <p v-if="errors?.name" class="text-sm text-destructive">
                            {{ errors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="provider_category">Category</Label>
                        <UnderlineInput
                            id="provider_category"
                            v-model="form.provider_category"
                            placeholder="e.g. Electronics, Services"
                        />
                        <p
                            v-if="errors?.provider_category"
                            class="text-sm text-destructive"
                        >
                            {{ errors.provider_category }}
                        </p>
                    </div>
                </div>

                <!-- Row 2 -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="document_type">Document Type</Label>
                        <SearchSelect
                            id="document_type"
                            v-model="form.document_type"
                            :options="documentTypeOptions"
                            :show-create="false"
                            placeholder="Buscar..."
                        >
                        </SearchSelect>
                        <p
                            v-if="errors?.document_type"
                            class="text-sm text-destructive"
                        >
                            {{ errors.document_type }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="document_number">Document Number</Label>
                        <UnderlineInput
                            id="document_number"
                            v-model="form.document_number"
                            placeholder="e.g. 12345678"
                        />
                        <p
                            v-if="errors?.document_number"
                            class="text-sm text-destructive"
                        >
                            {{ errors.document_number }}
                        </p>
                    </div>
                </div>

                <!-- Row 3 -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="email">Email</Label>
                        <UnderlineInput
                            id="email"
                            type="email"
                            v-model="form.email"
                            placeholder="e.g. contact@supplier.com"
                        />
                        <p
                            v-if="errors?.email"
                            class="text-sm text-destructive"
                        >
                            {{ errors.email }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="phone">Phone</Label>
                        <UnderlineInput
                            id="phone"
                            v-model="form.phone"
                            placeholder="e.g. +51 987654321"
                        />
                        <p
                            v-if="errors?.phone"
                            class="text-sm text-destructive"
                        >
                            {{ errors.phone }}
                        </p>
                    </div>
                </div>

                <!-- Row 4 -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="address">Address</Label>
                        <UnderlineInput
                            id="address"
                            v-model="form.address"
                            placeholder="e.g. Av. Build 123"
                        />
                        <p
                            v-if="errors?.address"
                            class="text-sm text-destructive"
                        >
                            {{ errors.address }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="ubigeo">Ubigeo</Label>
                        <UnderlineInput
                            id="ubigeo"
                            v-model="form.ubigeo"
                            placeholder="e.g. 150101"
                        />
                        <p
                            v-if="errors?.ubigeo"
                            class="text-sm text-destructive"
                        >
                            {{ errors.ubigeo }}
                        </p>
                    </div>
                </div>

                <!-- Row 5 -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="payment_terms">Payment Terms</Label>
                        <UnderlineInput
                            id="payment_terms"
                            v-model="form.payment_terms"
                            placeholder="e.g. Net 30, COD"
                        />
                        <p
                            v-if="errors?.payment_terms"
                            class="text-sm text-destructive"
                        >
                            {{ errors.payment_terms }}
                        </p>
                    </div>
                </div>

                <!-- Row 6 -->
                <div class="space-y-2">
                    <Label htmlFor="notes">Notes</Label>
                    <UnderlineTextarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        placeholder="Additional notes..."
                    />
                    <p v-if="errors?.notes" class="text-sm text-destructive">
                        {{ errors.notes }}
                    </p>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
