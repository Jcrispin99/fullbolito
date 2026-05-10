<script setup lang="ts">
import { computed } from "vue";
import { useRoute, RouterLink } from "vue-router";

const route = useRoute();
const isSuccess = computed(() => route.name === "BillingSuccess");
const tenantId = computed(() => (route.query.tenant as string | undefined) ?? "");
</script>

<template>
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-md rounded-lg border bg-card p-8 text-center shadow">
            <template v-if="isSuccess">
                <h1 class="text-2xl font-semibold text-green-600">¡Pago confirmado!</h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    Tu suscripción se está activando. En unos segundos podrás
                    acceder a tu panel.
                </p>
            </template>
            <template v-else>
                <h1 class="text-2xl font-semibold text-amber-600">Pago cancelado</h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    No se procesó ningún cargo. Podés intentarlo de nuevo cuando
                    quieras.
                </p>
            </template>

            <p v-if="tenantId" class="mt-4 text-xs text-muted-foreground">
                Cuenta: <code>{{ tenantId }}</code>
            </p>

            <div class="mt-6 flex justify-center gap-3">
                <RouterLink
                    to="/login"
                    class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
                >
                    Iniciar sesión
                </RouterLink>
                <RouterLink
                    to="/register"
                    class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
                >
                    Volver al registro
                </RouterLink>
            </div>
        </div>
    </div>
</template>
