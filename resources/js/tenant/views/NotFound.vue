<script setup lang="ts">
import { useRouter } from "vue-router";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";

const router = useRouter();
</script>

<template>
    <DashboardLayout>
        <div class="flex flex-col items-center justify-center flex-1 gap-4 text-center py-20">
            <p class="text-7xl font-bold text-muted-foreground/30">404</p>
            <h1 class="text-2xl font-semibold">Página no encontrada</h1>
            <p class="text-muted-foreground">La página que buscas no existe.</p>
            <button
                class="mt-2 px-4 py-2 rounded bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 transition-colors"
                @click="router.push('/')"
            >
                Ir al panel
            </button>
        </div>
    </DashboardLayout>
</template>
