<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        description?: string;
    }>(),
    {},
);
</script>

<template>
    <div
        class="flex flex-col sm:flex-row items-center justify-between gap-3 pb-4 mb-3 border-b"
    >
        <div class="flex items-center gap-4 w-full sm:w-auto">
            <slot name="leading" />
            <div class="min-w-0 flex flex-col">
                <h1 class="text-xl font-bold tracking-tight truncate">
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="text-sm text-muted-foreground truncate"
                >
                    {{ description }}
                </p>
                <div v-if="$slots.subtext" class="mt-2 flex flex-wrap items-center gap-2">
                    <slot name="subtext" />
                </div>
            </div>
        </div>

        <div
            class="flex items-center gap-2 flex-1 justify-center max-w-lg mx-auto w-full"
        >
            <slot name="center" />
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <slot name="trailing" />
        </div>
    </div>
</template>
