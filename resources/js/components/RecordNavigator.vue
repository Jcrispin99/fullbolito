<script setup lang="ts">
import { ChevronLeft, ChevronRight } from "lucide-vue-next";
import { Button } from "@/components/ui/button";

defineProps<{
    currentIndex: number;
    total: number;
    hasPrev: boolean;
    hasNext: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: "prev"): void;
    (e: "next"): void;
}>();
</script>

<template>
    <div class="flex items-center gap-1.5">
        <!-- Separator -->
        <div class="w-px h-6 bg-border mr-1" />

        <!-- Counter -->
        <span class="text-xs font-medium text-muted-foreground tabular-nums min-w-[28px] text-center">
            {{ currentIndex + 1 }}/{{ total }}
        </span>

        <!-- Prev button — circular -->
        <Button
            variant="outline"
            size="icon"
            class="h-7 w-7 rounded-full"
            aria-label="Previous record"
            :disabled="!hasPrev || disabled"
            @click="emit('prev')"
        >
            <ChevronLeft class="h-3.5 w-3.5" />
        </Button>

        <!-- Next button — circular -->
        <Button
            variant="outline"
            size="icon"
            class="h-7 w-7 rounded-full"
            aria-label="Next record"
            :disabled="!hasNext || disabled"
            @click="emit('next')"
        >
            <ChevronRight class="h-3.5 w-3.5" />
        </Button>
    </div>
</template>
