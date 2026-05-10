<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import AppHeader from "@tenant/components/AppHeader.vue";

defineProps<{
    breadcrumbs?: Array<{ label: string; href?: string }>;
}>();
</script>

<template>
    <div class="min-h-screen bg-[#F9FAFB] font-sans flex flex-col">
        <!-- Always visible Top Navigation Bar replacing Sidebar -->
        <AppHeader />
        
        <!-- Main Content Area -->
        <main class="flex-1 w-full mx-auto p-4 md:p-6 overflow-x-hidden flex flex-col">
            <!-- We could put Breadcrumbs here if desired, but Odoo typically puts them inside the module header -->
            <slot />
        </main>
    </div>
    <Toaster position="bottom-right" />
</template>
