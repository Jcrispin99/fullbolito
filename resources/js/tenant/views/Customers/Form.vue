<script setup lang="ts">
import { ref, watch } from "vue";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import { SearchSelect } from "@/components/ui/search-select";
import { Checkbox } from "@/components/ui/checkbox";
import CornerRibbon from "@tenant/components/CornerRibbon.vue";

const props = withDefaults(
    defineProps<{
        mode: "create" | "edit";
        initialData?: any;
        isLoading?: boolean;
        errors?: Record<string, string>;
        archived?: boolean;
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

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
    birth_date: "",
    gender: "",
    status: "active",
    notes: "",
    is_supplier: false,
    is_customer: true,
});

const documentTypeOptions = [
    { value: "DNI", label: "DNI" },
    { value: "RUC", label: "RUC" },
    { value: "CE", label: "CE" },
    { value: "PASSPORT", label: "Passport" },
];

const genderOptions = [
    { value: "male", label: "Male" },
    { value: "female", label: "Female" },
    { value: "other", label: "Other" },
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
                birth_date: newData.birth_date || "",
                gender: newData.gender || "",
                status: newData.status || "active",
                notes: newData.notes || "",
                is_supplier:
                    newData.is_supplier !== undefined
                        ? newData.is_supplier
                        : false,
                is_customer:
                    newData.is_customer !== undefined
                        ? newData.is_customer
                        : true,
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
    <form @submit.prevent="submit">
        <div class="grid gap-6">
            <Card
                class="relative overflow-hidden"
                :class="{ 'border-0 shadow-none': compact }"
            >
                <CornerRibbon
                    v-if="archived && !compact"
                    label="Inactive"
                    tone="danger"
                />
                <CardContent :class="compact ? 'p-0' : 'pt-6'">
                    <div :class="compact ? 'grid gap-4' : 'grid gap-6'">
                        <!-- Row 1: name + (is_supplier when not compact) -->
                        <div
                            class="grid gap-4"
                            :class="compact ? '' : 'md:grid-cols-2'"
                        >
                            <div class="space-y-2">
                                <Label htmlFor="name"
                                    >Customer Name
                                    <span class="text-destructive">*</span></Label
                                >
                                <UnderlineInput
                                    id="name"
                                    v-model="form.name"
                                    placeholder="e.g. Acme Corp"
                                    required
                                />
                                <p
                                    v-if="errors?.name"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.name }}
                                </p>
                            </div>

                            <div
                                v-if="!compact"
                                class="space-y-2 flex flex-col justify-end pb-2"
                            >
                                <div class="flex items-center space-x-2">
                                    <Checkbox
                                        id="is_supplier"
                                        :checked="form.is_supplier"
                                        @update:checked="
                                            (v) => (form.is_supplier = v)
                                        "
                                    />
                                    <Label htmlFor="is_supplier"
                                        >Is also a Supplier?</Label
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: document_type + document_number -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label htmlFor="document_type"
                                    >Document Type</Label
                                >
                                <SearchSelect
                                    id="document_type"
                                    v-model="form.document_type"
                                    :options="documentTypeOptions"
                                    :show-create="false"
                                    placeholder="Buscar..."
                                />
                                <p
                                    v-if="errors?.document_type"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.document_type }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label htmlFor="document_number"
                                    >Document Number</Label
                                >
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

                        <!-- Row 3: email + phone -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <UnderlineInput
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    placeholder="e.g. contact@customer.com"
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

                        <!-- Secondary fields (hidden in compact mode) -->
                        <template v-if="!compact">
                            <!-- Row 4: address + ubigeo -->
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

                            <!-- Row 5: payment_terms + gender -->
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label htmlFor="payment_terms"
                                        >Payment Terms</Label
                                    >
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

                                <div class="space-y-2">
                                    <Label htmlFor="gender">Gender</Label>
                                    <SearchSelect
                                        id="gender"
                                        v-model="form.gender"
                                        :options="genderOptions"
                                        :show-create="false"
                                        placeholder="Select Gender..."
                                    />
                                    <p
                                        v-if="errors?.gender"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.gender }}
                                    </p>
                                </div>
                            </div>

                            <!-- Row 6: birth_date + notes -->
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label htmlFor="birth_date"
                                        >Birth Date</Label
                                    >
                                    <UnderlineInput
                                        id="birth_date"
                                        type="date"
                                        v-model="form.birth_date"
                                    />
                                    <p
                                        v-if="errors?.birth_date"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.birth_date }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <UnderlineTextarea
                                        id="notes"
                                        v-model="form.notes"
                                        rows="1"
                                        placeholder="Additional notes..."
                                    />
                                    <p
                                        v-if="errors?.notes"
                                        class="text-sm text-destructive"
                                    >
                                        {{ errors.notes }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </CardContent>
            </Card>
            <button type="submit" class="hidden"></button>
        </div>
    </form>
</template>
