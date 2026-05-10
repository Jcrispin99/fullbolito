<script setup lang="ts">
import { ref, watch } from "vue";
import {
    DialogRoot,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogClose,
} from "radix-vue";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { UnderlineInput } from "@/components/ui/underline-input";
import { UnderlineTextarea } from "@/components/ui/underline-textarea";
import { SearchSelect } from "@/components/ui/search-select";
import { Save, X, Package } from "lucide-vue-next";
import type { Lot } from "@tenant/stores/lot";

const props = defineProps<{
    open: boolean;
    lot: Lot | null;
    saving?: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: "close"): void;
    (e: "save", payload: Record<string, any>): void;
}>();

const formData = ref({
    lot_number: "",
    manufactured_at: "" as string | null,
    expires_at: "" as string | null,
    notes: "" as string | null,
    status: "active" as "active" | "blocked" | "expired" | "depleted",
});

watch(
    () => props.lot,
    (l) => {
        if (l) {
            formData.value = {
                lot_number: l.lot_number ?? "",
                manufactured_at: l.manufactured_at ?? "",
                expires_at: l.expires_at ?? "",
                notes: l.notes ?? "",
                status: l.status,
            };
        }
    },
    { immediate: true },
);

const statusOptions = [
    { value: "active", label: "Activo" },
    { value: "blocked", label: "Bloqueado" },
    { value: "expired", label: "Vencido" },
    { value: "depleted", label: "Agotado" },
];

const handleSave = () => {
    const payload: Record<string, any> = {
        lot_number: formData.value.lot_number.trim(),
        manufactured_at: formData.value.manufactured_at || null,
        expires_at: formData.value.expires_at || null,
        notes: formData.value.notes?.trim() || null,
        status: formData.value.status,
    };
    emit("save", payload);
};
</script>

<template>
    <DialogRoot
        :open="open"
        @update:open="(v) => { if (!v) emit('close') }"
    >
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 grid w-full max-w-2xl -translate-x-1/2 -translate-y-1/2 border bg-background shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 sm:rounded-lg max-h-[90vh] flex flex-col"
                :trap-focus="false"
                @pointer-down-outside="(e: any) => e.preventDefault()"
                @interact-outside="(e: any) => e.preventDefault()"
                @close-auto-focus="(e: Event) => e.preventDefault()"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <div>
                        <DialogTitle class="text-lg font-semibold flex items-center gap-2">
                            <Package class="h-5 w-5" />
                            Editar lote
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground">
                            Solo se pueden editar metadatos. Cantidad y costo iniciales son inmutables.
                        </DialogDescription>
                    </div>
                    <DialogClose
                        class="rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    >
                        <X class="h-4 w-4" />
                        <span class="sr-only">Close</span>
                    </DialogClose>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    <!-- Read-only summary -->
                    <div
                        v-if="lot"
                        class="rounded-md border bg-muted/30 px-4 py-3 text-sm space-y-1"
                    >
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Producto</span>
                            <span class="font-medium">
                                {{
                                    lot.product_product?.template?.name ||
                                    lot.product_product?.sku ||
                                    `#${lot.product_product_id}`
                                }}
                            </span>
                        </div>
                        <div v-if="lot.purchase" class="flex justify-between">
                            <span class="text-muted-foreground">Compra</span>
                            <span class="font-mono text-xs">
                                {{ lot.purchase.sequence_code }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Cantidad inicial</span>
                            <span>{{ lot.initial_quantity }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Costo inicial</span>
                            <span>{{ Number(lot.initial_cost).toFixed(4) }}</span>
                        </div>
                        <div v-if="lot.total_stock !== null" class="flex justify-between">
                            <span class="text-muted-foreground">Stock total actual</span>
                            <span class="font-medium">{{ lot.total_stock }}</span>
                        </div>
                    </div>

                    <!-- Editable fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="lot_number">
                                Número de lote
                                <span class="text-destructive">*</span>
                            </Label>
                            <UnderlineInput
                                id="lot_number"
                                v-model="formData.lot_number"
                                type="text"
                                placeholder="LOT-001"
                            />
                            <p
                                v-if="errors?.lot_number"
                                class="text-xs text-destructive"
                            >
                                {{ errors.lot_number }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="status">Estado</Label>
                            <SearchSelect
                                id="status"
                                v-model="formData.status"
                                :options="statusOptions"
                                :show-create="false"
                            />
                            <p v-if="errors?.status" class="text-xs text-destructive">
                                {{ errors.status }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="manufactured_at">Fecha de fabricación</Label>
                            <UnderlineInput
                                id="manufactured_at"
                                v-model="formData.manufactured_at"
                                type="date"
                            />
                            <p
                                v-if="errors?.manufactured_at"
                                class="text-xs text-destructive"
                            >
                                {{ errors.manufactured_at }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="expires_at">Fecha de vencimiento</Label>
                            <UnderlineInput
                                id="expires_at"
                                v-model="formData.expires_at"
                                type="date"
                            />
                            <p
                                v-if="errors?.expires_at"
                                class="text-xs text-destructive"
                            >
                                {{ errors.expires_at }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="notes">Notas</Label>
                        <UnderlineTextarea
                            id="notes"
                            v-model="formData.notes"
                            rows="3"
                            placeholder="Comentarios u observaciones del lote..."
                        />
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="flex items-center justify-end gap-2 border-t px-6 py-4"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="saving"
                        @click="emit('close')"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        :disabled="saving"
                        @click="handleSave"
                    >
                        <Save class="h-4 w-4 mr-2" />
                        {{ saving ? "Guardando..." : "Guardar cambios" }}
                    </Button>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
