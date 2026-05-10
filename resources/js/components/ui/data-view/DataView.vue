<script setup lang="ts">
defineProps<{
    viewMode?: "table" | "grid";
    isLoading?: boolean;
    isEmpty?: boolean;
    emptyMessage?: string;
}>();
</script>

<template>
    <div>
        <!-- Table View -->
        <div v-show="viewMode === 'table'" class="rounded-md border bg-card text-card-foreground">
            <slot name="table"></slot>
        </div>

        <!-- Grid View -->
        <div v-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-if="isLoading" class="col-span-full text-center py-8 bg-card text-card-foreground rounded-lg border">
                Loading...
            </div>
            <div v-else-if="isEmpty" class="col-span-full text-center py-8 text-muted-foreground bg-card rounded-lg border">
                {{ emptyMessage || 'No records found.' }}
            </div>
            <slot v-else name="grid"></slot>
        </div>
    </div>
</template>
