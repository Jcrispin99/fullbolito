<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ref } from "vue";

const open = ref(false);
const title = ref("Extender acceso");
const message = ref("Agrega días a la suscripción de este negocio.");
const durationDays = ref(15);
const paymentReference = ref("");
const onConfirm = ref<
    (payload: { duration_days: number; payment_reference?: string }) => Promise<void> | void
>(() => {});
const isLoading = ref(false);
const error = ref<string | null>(null);

const show = (
    opts: {
        title?: string;
        message?: string;
        duration_days?: number;
        payment_reference?: string;
    },
    confirmFn: (payload: { duration_days: number; payment_reference?: string }) => Promise<void> | void,
) => {
    title.value = opts.title ?? "Extender acceso";
    message.value = opts.message ?? "Agrega días a la suscripción de este negocio.";
    durationDays.value = opts.duration_days ?? 15;
    paymentReference.value = opts.payment_reference ?? "";
    onConfirm.value = confirmFn;
    error.value = null;
    if (typeof window !== "undefined") {
        const active = document.activeElement as HTMLElement | null;
        active?.blur?.();
        window.requestAnimationFrame(() => {
            open.value = true;
        });
        return;
    }
    open.value = true;
};

const handleConfirm = async () => {
    const days = Number(durationDays.value);
    if (!Number.isFinite(days) || days < 1) {
        error.value = "La duración debe ser de al menos 1 día";
        return;
    }

    try {
        isLoading.value = true;
        error.value = null;
        const payload: { duration_days: number; payment_reference?: string } = {
            duration_days: Math.floor(days),
        };
        if (paymentReference.value.trim() !== "") {
            payload.payment_reference = paymentReference.value.trim();
        }
        await onConfirm.value(payload);
        open.value = false;
    } catch (e: any) {
        error.value = e?.response?.data?.message || e?.message || "No se pudo extender el acceso";
        console.error(e);
    } finally {
        isLoading.value = false;
    }
};

defineExpose({ show });
</script>

<template>
    <AlertDialog :open="open" @update:open="open = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ message }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div class="space-y-4">
                <div class="space-y-2">
                    <Label htmlFor="duration_days">Duración (días)</Label>
                    <Input
                        id="duration_days"
                        type="number"
                        min="1"
                        v-model="durationDays"
                        :disabled="isLoading"
                        required
                    />
                </div>

                <div class="space-y-2">
                    <Label htmlFor="payment_reference">Referencia (opcional)</Label>
                    <Input
                        id="payment_reference"
                        v-model="paymentReference"
                        placeholder="p. ej., extensión de cortesía"
                        :disabled="isLoading"
                    />
                </div>

                <p v-if="error" class="text-sm text-destructive">
                    {{ error }}
                </p>
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="isLoading">Cancelar</AlertDialogCancel>
                <AlertDialogAction @click.prevent="handleConfirm" :disabled="isLoading">
                    <span v-if="isLoading">Procesando...</span>
                    <span v-else>Continuar</span>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
