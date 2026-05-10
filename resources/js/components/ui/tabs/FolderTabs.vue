<script setup lang="ts">
import { computed } from 'vue';

interface Tab {
    value: string;
    label: string;
}

const props = defineProps<{
    modelValue: string;
    tabs: Tab[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const activeTab = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});
</script>

<template>
    <div class="w-full">
        <!-- Tab Headers -->
        <div class="flex border-b border-border mb-0 px-2 lg:px-4">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                @click="activeTab = tab.value"
                class="px-4 py-1.5 text-xs font-medium transition-colors border-t border-l border-r rounded-t-lg -mb-px outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                :class="[
                    activeTab === tab.value 
                        ? 'bg-card border-border text-foreground z-10' 
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/50'
                ]"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Tab Content Container -->
        <div class="bg-card border-t-0 border border-border rounded-b-lg shadow-sm">
            <slot />
        </div>
    </div>
</template>
