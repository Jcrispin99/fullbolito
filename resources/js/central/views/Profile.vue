<script setup lang="ts">
import { computed, onMounted } from "vue";
import DashboardLayout from "@/central/layouts/DashboardLayout.vue";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useAuthStore } from "@/central/stores/auth";

const authStore = useAuthStore();

onMounted(async () => {
    if (authStore.isAuthenticated && !authStore.user) {
        await authStore.fetchUser();
    }
});

const user = computed(() => authStore.user);
</script>

<template>
    <DashboardLayout :breadcrumbs="[{ label: 'Perfil' }]">
        <div class="max-w-2xl space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>Perfil</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="!user" class="text-sm text-muted-foreground">
                        Cargando...
                    </div>
                    <div v-else class="grid gap-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-muted-foreground">Nombre</span>
                            <span class="font-medium">{{ user.name }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-muted-foreground">Email</span>
                            <span class="font-medium">{{ user.email }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-muted-foreground">Rol</span>
                            <span class="font-medium">{{ (user.roles ?? []).join(", ") || "user" }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>
