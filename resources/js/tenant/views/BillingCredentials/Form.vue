<script setup lang="ts">
import { ref, watch } from "vue";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { UnderlineInput } from "@/components/ui/underline-input";

import CornerRibbon from "@tenant/components/CornerRibbon.vue";
import type { BillingCredential } from "@tenant/stores/billingCredential";
import { Upload, FileText } from "lucide-vue-next";

const props = defineProps<{
    initialData?: Partial<BillingCredential>;
    isEditing?: boolean;
    archived?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "submit", payload: Record<string, any>): void;
}>();

const formData = ref({
    name: "",
    sol_user: "",
    sol_pass: "",
    client_id: "",
    client_secret: "",
    production: false,
    is_active: true,
});

const certFile = ref<File | null>(null);
const existingCertFilename = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.initialData,
    (newData) => {
        if (!newData) return;
        formData.value = {
            name: newData.name || "",
            sol_user: newData.sol_user || "",
            // Secretos nunca se prellenan: en edición el usuario los deja vacíos
            // para mantener los actuales. Esto evita exponerlos en pantalla.
            sol_pass: "",
            client_id: newData.client_id || "",
            client_secret: "",
            production: !!newData.production,
            is_active: newData.is_active ?? true,
        };
        existingCertFilename.value = newData.cert_filename || null;
        certFile.value = null;
    },
    { immediate: true },
);

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        certFile.value = file;
    }
};

const triggerFilePicker = () => {
    fileInputRef.value?.click();
};

const clearFile = () => {
    certFile.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = "";
    }
};

const handleSubmit = () => {
    const payload: Record<string, any> = {
        name: formData.value.name,
        sol_user: formData.value.sol_user,
        production: formData.value.production,
        is_active: formData.value.is_active,
    };

    // sol_pass: en create siempre (validación lo exige); en update sólo si lo escribieron
    if (formData.value.sol_pass) {
        payload.sol_pass = formData.value.sol_pass;
    }

    // client_id: enviamos siempre — backend lo trata con array_key_exists
    payload.client_id = formData.value.client_id || "";

    // client_secret: sólo si lo escribieron (en edición, vacío = mantener actual)
    if (formData.value.client_secret) {
        payload.client_secret = formData.value.client_secret;
    }

    // Certificado: requerido en create, opcional en update
    if (certFile.value) {
        payload.cert_file = certFile.value;
    }

    emit("submit", payload);
};

defineExpose({ submit: handleSubmit });
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="grid gap-6">
            <!-- Sección: Identificación -->
            <Card class="relative overflow-hidden">
                <CornerRibbon v-if="archived" label="Inactiva" tone="danger" />

                <CardContent>
                    <div class="grid gap-6 pt-2">
                        <div class="grid grid-cols-1 gap-4">
                            <div class="space-y-2">
                                <Label for="name">
                                    Nombre
                                    <span class="text-destructive">*</span>
                                </Label>
                                <UnderlineInput
                                    id="name"
                                    v-model="formData.name"
                                    type="text"
                                    placeholder="Ej: Credenciales SUNAT producción"
                                    required
                                />
                                <p
                                    v-if="errors?.name"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.name }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Sección: Credenciales SOL (clave SOL del contribuyente) -->
            <Card>
                <CardContent>
                    <div class="grid gap-6 pt-2">
                        <div>
                            <h3 class="text-sm font-semibold mb-1">
                                Credenciales SOL
                            </h3>
                            <p class="text-xs text-muted-foreground mb-4">
                                Usuario y clave SOL del contribuyente (SUNAT).
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="sol_user">
                                    Usuario SOL
                                    <span class="text-destructive">*</span>
                                </Label>
                                <UnderlineInput
                                    id="sol_user"
                                    v-model="formData.sol_user"
                                    type="text"
                                    placeholder="Ej: MODDATOS"
                                    required
                                />
                                <p
                                    v-if="errors?.sol_user"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.sol_user }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="sol_pass">
                                    Clave SOL
                                    <span v-if="!isEditing" class="text-destructive">*</span>
                                </Label>
                                <UnderlineInput
                                    id="sol_pass"
                                    v-model="formData.sol_pass"
                                    type="password"
                                    :placeholder="isEditing ? 'Dejar vacío para mantener actual' : 'Clave SOL'"
                                    autocomplete="new-password"
                                />
                                <p
                                    v-if="errors?.sol_pass"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.sol_pass }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Sección: Certificado Digital -->
            <Card>
                <CardContent>
                    <div class="grid gap-4 pt-2">
                        <div>
                            <h3 class="text-sm font-semibold mb-1">
                                Certificado Digital
                            </h3>
                            <p class="text-xs text-muted-foreground mb-2">
                                Archivo .pem, .crt, .cer o .pfx (PKCS#12). Máx. 5 MB. Se almacena de forma privada.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept=".pem,.crt,.cer,.pfx,.p12,application/x-pkcs12,application/x-x509-ca-cert,application/x-pem-file"
                                class="hidden"
                                @change="handleFileChange"
                            />

                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="triggerFilePicker"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm border rounded-md hover:bg-muted transition"
                                >
                                    <Upload class="h-4 w-4" />
                                    {{
                                        certFile
                                            ? "Cambiar archivo"
                                            : isEditing && existingCertFilename
                                              ? "Reemplazar certificado"
                                              : "Subir certificado"
                                    }}
                                </button>

                                <button
                                    v-if="certFile"
                                    type="button"
                                    @click="clearFile"
                                    class="text-xs text-muted-foreground hover:text-destructive"
                                >
                                    Quitar
                                </button>
                            </div>

                            <!-- Archivo recién seleccionado (pendiente de subir) -->
                            <div
                                v-if="certFile"
                                class="flex items-center gap-2 p-3 rounded-md bg-muted/50 text-sm"
                            >
                                <FileText class="h-4 w-4 text-muted-foreground" />
                                <span class="font-mono">{{ certFile.name }}</span>
                                <span class="text-xs text-muted-foreground ml-auto">
                                    {{ (certFile.size / 1024).toFixed(1) }} KB
                                </span>
                            </div>

                            <!-- Archivo existente (sólo en edición, sin nuevo seleccionado) -->
                            <div
                                v-else-if="isEditing && existingCertFilename"
                                class="flex items-center gap-2 p-3 rounded-md bg-muted/30 text-sm"
                            >
                                <FileText class="h-4 w-4 text-muted-foreground" />
                                <span class="font-mono text-muted-foreground">
                                    {{ existingCertFilename }}
                                </span>
                                <span class="text-xs text-muted-foreground ml-auto">
                                    Actual
                                </span>
                            </div>

                            <p
                                v-if="errors?.cert_file"
                                class="text-sm text-destructive"
                            >
                                {{ errors.cert_file }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Sección: Credenciales API (OAuth/proveedor) -->
            <Card>
                <CardContent>
                    <div class="grid gap-6 pt-2">
                        <div>
                            <h3 class="text-sm font-semibold mb-1">
                                Credenciales API (opcional)
                            </h3>
                            <p class="text-xs text-muted-foreground mb-4">
                                Sólo si tu proveedor de facturación usa OAuth.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="client_id">Client ID</Label>
                                <UnderlineInput
                                    id="client_id"
                                    v-model="formData.client_id"
                                    type="text"
                                    placeholder="OAuth Client ID"
                                />
                                <p
                                    v-if="errors?.client_id"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.client_id }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="client_secret">Client Secret</Label>
                                <UnderlineInput
                                    id="client_secret"
                                    v-model="formData.client_secret"
                                    type="password"
                                    :placeholder="isEditing ? 'Dejar vacío para mantener actual' : 'OAuth Client Secret'"
                                    autocomplete="new-password"
                                />
                                <p
                                    v-if="errors?.client_secret"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.client_secret }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Sección: Configuración de entorno -->
            <Card>
                <CardContent>
                    <div class="grid gap-4 pt-2">
                        <div>
                            <h3 class="text-sm font-semibold mb-1">
                                Configuración
                            </h3>
                        </div>

                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="production"
                                :checked="formData.production"
                                @update:checked="(v: boolean) => (formData.production = v)"
                            />
                            <div class="space-y-1">
                                <Label for="production" class="cursor-pointer">
                                    Entorno de producción
                                </Label>
                                <p class="text-xs text-muted-foreground">
                                    Activar sólo cuando hayas pasado homologación con SUNAT.
                                    Por defecto se usa el entorno beta/sandbox.
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Required submit button for standard forms -->
            <button type="submit" class="hidden"></button>
        </div>
    </form>
</template>
