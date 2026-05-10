<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent } from "@/components/ui/card";
import type { Tenant } from "@/types/models";

const props = defineProps<{
    mode: "create" | "edit";
    initialData?: Partial<Tenant>;
    isLoading?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", data: any): void;
}>();

type TenantFormState = {
    id: string;
    user_id: string | number | undefined;
    business_name: string;
    owner_email: string;
    owner_phone: string;
    domainsText: string;
};

const form = ref<TenantFormState>({
    id: "",
    user_id: undefined,
    business_name: "",
    owner_email: "",
    owner_phone: "",
    domainsText: "",
});

const userIdModel = computed<string | number | undefined>({
    get: () => form.value.user_id ?? undefined,
    set: (value) => {
        form.value.user_id = value;
    },
});

const toDomainsText = (tenant: Partial<Tenant>) => {
    const domains = Array.isArray(tenant.domains)
        ? tenant.domains.map((d: any) => d.domain)
        : [];
    return domains.join("\n");
};

watch(
    () => props.initialData,
    (newData) => {
        if (!newData) return;

        form.value = {
            id: String(newData.id || ""),
            user_id:
                typeof (newData as any).user_id === "number"
                    ? (newData as any).user_id
                    : undefined,
            business_name: String((newData as any)?.data?.business_name || ""),
            owner_email: String((newData as any)?.data?.owner_email || ""),
            owner_phone: String((newData as any)?.data?.owner_phone || ""),
            domainsText: toDomainsText(newData),
        };
    },
    { immediate: true },
);

const parseDomains = (raw: string) => {
    return raw
        .split(/[\n,]+/g)
        .map((s) => s.trim())
        .filter(Boolean);
};

const submit = () => {
    const domains = parseDomains(form.value.domainsText);
    const rawUserId = form.value.user_id;
    const parsedUserId =
        typeof rawUserId === "number"
            ? rawUserId
            : typeof rawUserId === "string" && rawUserId.trim() !== ""
              ? Number(rawUserId)
              : null;
    const payload: any = {
        user_id: Number.isFinite(parsedUserId as number) ? parsedUserId : null,
        domains,
        data: {
            business_name: form.value.business_name,
            owner_email: form.value.owner_email,
            owner_phone: form.value.owner_phone,
        },
    };

    if (props.mode === "create") {
        payload.id = form.value.id;
    }

    emit("submit", payload);
};

defineExpose({ submit });
</script>

<template>
    <Card class="w-full relative overflow-hidden">
        <CardContent class="pt-6">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="id">Tenant ID</Label>
                        <Input
                            id="id"
                            v-model="form.id"
                            placeholder="e.g. acme"
                            :disabled="mode === 'edit' || isLoading"
                            :required="mode === 'create'"
                        />
                        <p v-if="errors?.id" class="text-sm text-destructive">
                            {{ errors.id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="user_id"
                            >Owner User ID (Optional)</Label
                        >
                        <Input
                            id="user_id"
                            v-model="userIdModel"
                            type="number"
                            placeholder="e.g. 1"
                            :disabled="isLoading"
                        />
                        <p
                            v-if="errors?.user_id"
                            class="text-sm text-destructive"
                        >
                            {{ errors.user_id }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="business_name">Business Name</Label>
                        <Input
                            id="business_name"
                            v-model="form.business_name"
                            placeholder="e.g. Acme Inc"
                            :disabled="isLoading"
                            required
                        />
                        <p
                            v-if="errors?.['data.business_name']"
                            class="text-sm text-destructive"
                        >
                            {{ errors["data.business_name"] }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="owner_email">Owner Email</Label>
                        <Input
                            id="owner_email"
                            v-model="form.owner_email"
                            type="email"
                            placeholder="e.g. owner@acme.com"
                            :disabled="isLoading"
                            required
                        />
                        <p
                            v-if="errors?.['data.owner_email']"
                            class="text-sm text-destructive"
                        >
                            {{ errors["data.owner_email"] }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label htmlFor="owner_phone"
                            >Owner Phone (Optional)</Label
                        >
                        <Input
                            id="owner_phone"
                            v-model="form.owner_phone"
                            placeholder="e.g. +1234567890"
                            :disabled="isLoading"
                        />
                        <p
                            v-if="errors?.['data.owner_phone']"
                            class="text-sm text-destructive"
                        >
                            {{ errors["data.owner_phone"] }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label htmlFor="domains">Domains</Label>
                        <Textarea
                            id="domains"
                            v-model="form.domainsText"
                            placeholder="one per line (or comma-separated)"
                            :disabled="isLoading"
                            required
                        />
                        <p
                            v-if="errors?.domains"
                            class="text-sm text-destructive"
                        >
                            {{ errors.domains }}
                        </p>
                    </div>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
