<script setup lang="ts">
import { useRouter } from "vue-router";
import DashboardLayout from "@tenant/layouts/DashboardLayout.vue";
import { useNav } from "@tenant/composables/useNav";

const router = useRouter();
const { visibleApps } = useNav();

const navigateToApp = (route: string) => {
    router.push(route);
};
</script>

<template>
    <DashboardLayout>
        <div class="flex-1 flex items-center justify-center p-6">
            <div class="w-full max-w-[900px]">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-x-6 gap-y-12 justify-items-center">
                    <div
                        v-for="app in visibleApps"
                        :key="app.title"
                        class="flex flex-col items-center group cursor-pointer w-[120px]"
                        @click="navigateToApp(app.defaultRoute)"
                    >
                        <div
                            class="w-[96px] h-[96px] bg-card rounded-[24px] flex items-center justify-center border-[1.5px] shadow-sm transition-all duration-200 group-hover:-translate-y-1 group-hover:shadow-md mb-3"
                            :class="app.colorClass"
                        >
                            <component :is="app.icon" class="w-12 h-12 transition-transform group-hover:scale-110" />
                        </div>

                        <span class="text-[14px] font-medium text-foreground text-center leading-tight">
                            {{ app.title }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
