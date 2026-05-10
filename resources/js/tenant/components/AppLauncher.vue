<script setup lang="ts">
import { useRouter } from "vue-router";
import { useActiveAppStore } from "@tenant/stores/activeApp";
import { useNav } from "@tenant/composables/useNav";

// Optional Close button icon
import { X } from "lucide-vue-next";

defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const router = useRouter();
const activeAppStore = useActiveAppStore();
const { visibleApps } = useNav();

const navigateToApp = (appTitle: string, route: string) => {
    activeAppStore.setApp(appTitle);
    emit('close');
    router.push(route);
};
</script>

<template>
    <div 
        v-if="isOpen"
        class="fixed inset-0 z-50 flex flex-col items-center justify-center font-sans overflow-y-auto"
        @click.self="emit('close')"
    >
        <div class="fixed inset-0 bg-background/95 backdrop-blur-sm pointer-events-none"></div>

        <!-- Optional Close Button in Top Right (visible if they just want out) -->
        <button 
            class="absolute top-6 right-6 z-10 text-muted-foreground hover:text-foreground transition-colors focus:outline-none"
            @click="emit('close')"
        >
            <X class="w-8 h-8" />
        </button>

        <!-- Main Apps Grid -->
        <div class="relative z-10 w-full max-w-[900px] p-6 py-12 mt-12 md:mt-0">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-x-6 gap-y-12 justify-items-center">
                <div
                    v-for="app in visibleApps"
                    :key="app.title"
                    class="flex flex-col items-center group cursor-pointer w-[120px]"
                    @click="navigateToApp(app.title, app.defaultRoute)"
                >
                    <!-- App Icon Container -->
                    <div 
                        class="w-[96px] h-[96px] bg-card rounded-[24px] flex items-center justify-center border-[1.5px] shadow-sm transition-all duration-200 group-hover:-translate-y-1 group-hover:shadow-md mb-3"
                        :class="app.colorClass"
                    >
                        <component :is="app.icon" class="w-12 h-12 transition-transform group-hover:scale-110" />
                    </div>
                    
                    <!-- App Name -->
                    <span class="text-[14px] font-medium text-foreground text-center leading-tight">
                        {{ app.title }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Basic fade in animation if desired */
div[v-if] {
    animation: fadeIn 0.15s ease-out forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
</style>
